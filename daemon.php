#!/usr/bin/php -q
<?php

require __DIR__ . '/vendor/autoload.php';

use \System_Daemon; 
use \OrderManager\OrderManager;
use \OrderManager\TickerTrends;
use \MtGox\MtGox;
use \Utilities\Arr;

// get CLI arguments
$args = new Arr($argv);
$args = $args->slice(1);

// get config file options
$config_string = file_get_contents(__DIR__."/config.json");
$config = new Arr($config_string);

// merge in our CLI args into our config
$config->merge([
	"dry" => ($args->contains("--dry"))?$args->contains("--dry"):$config->dry
]);

$dry_notice = ($config->dry)?"DRY RUN!":"CAUTION::LIVE RUN!!!";


// Bare minimum setup
System_Daemon::setOption("appName", "mtgox");
System_Daemon::setOption("authorEmail", "lego.admin@gmail.com");
System_Daemon::setOption("appDescription", "mtgox trading bot");
System_Daemon::setOption("authorName", "diego alejos");

# Write a init.d script
if (($initd_location = System_Daemon::writeAutoRun()) === false) {
    System_Daemon::notice('unable to write init.d script');
} else {
    System_Daemon::info(
        'sucessfully written startup script: %s',
        $initd_location
    );
}


// tell us if we're live or not  
System_Daemon::notice($dry_notice);


// Spawn Deamon!

System_Daemon::start();

System_Daemon::log(
	System_Daemon::LOG_INFO, "Daemon: '".
	System_Daemon::getOption("appName").
	"' spawned! This will be written to ".
	System_Daemon::getOption("logLocation")
);

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
	    $buyPrice = $trends->getTickerData()->buy->value;
	    $sellPrice = $trends->getTickerData()->sell->value;

	    $suggestedAction = $trends->detectSwing();
	    $SMA = $trends->getSMA();
	    $fullEMA = $trends->getEMA();
	    $halfEMA = $trends->getShortEMA();

	    $hourlAvg = $trends->getHourlyAvg();
	    
		// anounce ticker info		
		System_Daemon::log(
			System_Daemon::LOG_INFO, 
			sprintf("EMA: %01.2f(16)/%01.2f(36), AVG: %01.2f, Hourly: %01.2f, LAST: %01.2f, BUY: %01.2f, SELL: %01.2f, ORDERS: %s, BALANCE: %01.2fUSD | %01.2fBTC, ACTION: %s, SETS: %s",
				$halfEMA,
				$fullEMA,
				$SMA,
				$hourlAvg,
				$lastPrice,
				$buyPrice,
				$sellPrice,
				$orderCount,
				$balanceUSD,
				$balanceBTC,
				$suggestedAction,
				$trends->length()
				)

			
		);	    
	    
	    // Place orders if we have enough cash or bitcoins for half of our available balances
	    // smallest order size is 1.0E-2
	    
	    if($suggestedAction == "buy" && (($balanceUSD/$buyPrice)/2) > 1.0E-2 ){
	        
	        if(!$config->dry)
	        	$buy = $mtgox->placeOrder("bid", (($balanceUSD/$buyPrice)/2), $buyPrice);
	        else
	        	$buy = "[dry run]";

	        System_Daemon::log(
		        System_Daemon::LOG_INFO, 
		        sprintf("Placed buy order %01.2f", $buy)
	        );	        	        
	    }
	    
	    if($suggestedAction == "sell" && $balanceBTC > 1.0E-2 ){
	        
	        if(!$config->dry)
	        	$sell = $mtgox->placeOrder("ask", $balanceBTC, $sellPrice);	   
	       	else
	       		$sell = "[dry run]";

	        System_Daemon::log(
		        System_Daemon::LOG_INFO, 
		        sprintf("Placed buy order %01.2f", $sell)
	        );
	            
	    }	    
	    

										
	} catch(Exception $e) {

		System_Daemon::log(
			System_Daemon::LOG_INFO, 
			$e->getMessage()
		);

	}


    // Take a nap for a specified offset 

    sleep(60*$config->wait);

}
$tradeBot->save();
System_Daemon::stop();

?>
