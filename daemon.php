#!/usr/bin/php -q
<?php



// Make it possible to test in source directory
// This is for PEAR developers only
ini_set('include_path', ini_get('include_path').':..');

// Turn on error reporting and include daemon class
error_reporting(E_ALL);

require_once "System/Daemon.php";
require_once "OrderManager.php";





// Bare minimum setup
System_Daemon::setOption("appName", "mtgox");
System_Daemon::setOption("authorEmail", "lego.admin@gmail.com");
System_Daemon::setOption("appDescription", "mtgox trading bot");
System_Daemon::setOption("authorName", "diego alejos");

//System_Daemon::setOption("appDir", dirname(__FILE__));
System_Daemon::log(
	System_Daemon::LOG_INFO, 
	"Daemon not yet started so this will be written on-screen"
);

# Write a init.d script
if (($initd_location = System_Daemon::writeAutoRun()) === false) {
    System_Daemon::notice('unable to write init.d script');
} else {
    System_Daemon::info(
        'sucessfully written startup script: %s',
        $initd_location
    );
}

// Spawn Deamon!

System_Daemon::start();

System_Daemon::log(
	System_Daemon::LOG_INFO, "Daemon: '".
	System_Daemon::getOption("appName").
	"' spawned! This will be written to ".
	System_Daemon::getOption("logLocation")
);


$config = json_decode(file_get_contents("config.json"), true);
$min = $config['wait'];

try {
	# create mtGox object
	$mtgox = new MtGox( $config['mtgox']['key'],$config['mtgox']['secret'], $config['mtgox']['certFile']);
}catch( Exception $e){
	echo "poop". $e->getMessage();
	exit;
}

# Start instance of order manager
$orders = new OrderManager($mtgox);
# Start instance of ticker trends
$trends = new TickerTrends($mtgox);


while(!System_Daemon::isDying()){
	
	

	try {
	
	    $trends->updateTicker();
	    
	    $orders->update();
	    
	    $balanceBTC = $orders->getAvailableBalance("BTC");
	    $balanceUSD = $orders->getAvailableBalance("USD");
	    $orderCount = $orders->length();
	    $lastPrice = $trends->getTickerData()->last->value;
	    $suggestedAction = $trends->detectSwing();
	    $SMA = $trends->getSMA();
	    $fullEMA = $trends->getEMA();
	    $halfEMA = $trends->getEMA(2);

	    $prediction = $lastPrice; //$trends->predict($lastPrice);
	    
		// anounce ticker info		
		System_Daemon::log(
			System_Daemon::LOG_INFO, 
			"TREND: EMA: $halfEMA/$fullEMA, AVG PRICE: $SMA, PRICE: $lastPrice, ORDERS: $orderCount, BALANCE: $balanceUSD USD / $balanceBTC BTC, ACTION: $suggestedAction"
		);	    
	    
	    // Place orders if we have enough cash or bitcoins for half of our available balances
	    // smallest order size is 1.0E-2
	    
	    if($suggestedAction == "buy" && (($balanceUSD/$prediction)/2) > 1.0E-2 ){
	        
	        $buy = $mtgox->placeOrder("bid", (($balanceUSD/$prediction)/2), $prediction);
	        
	        System_Daemon::log(
		        System_Daemon::LOG_INFO, 
		        "Placed buy order $buy"
	        );	        	        
	    }
	    
	    if($suggestedAction == "sell" && $balanceBTC > 1.0E-2 ){
	        
	        $sell = $mtgox->placeOrder("ask", $balanceBTC, $prediction);	   
	       
	        System_Daemon::log(
		        System_Daemon::LOG_INFO, 
		        "Placed sell order $sell"
	        );
	            
	    }	    
	    

										
	} catch(Exception $e) {

		System_Daemon::log(
			System_Daemon::LOG_INFO, 
			$e->getMessage()
		);

	}


    // Take a nap for a specified offset 

    sleep(60*$min);

}
$tradeBot->save();
System_Daemon::stop();

?>
