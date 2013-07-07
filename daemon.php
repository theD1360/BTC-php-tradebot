#!/usr/bin/php -q
<?php


require_once "trade.php";
require_once "OrderManager.php";

// Make it possible to test in source directory
// This is for PEAR developers only
ini_set('include_path', ini_get('include_path').':..');

// Turn on error reporting and include daemon class
error_reporting(E_ALL);
require_once "System/Daemon.php";






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
	$tradeBot = new MtGox( $config['mtgox']['key'],$config['mtgox']['secret'], $config['mtgox']['certFile']);
}catch( Exception $e){
	echo $e->getMessage();
	exit;
}

# Start instance of order manager
$orders = new OrderManager();

# priming read for orders in the event that we have orders
$openOrders = $tradeBot->getOrders();
foreach($openOrders as $order){
   // var_dump($order);
    if($order['status'] == "open"){
        $newOrder = new Order();
        $newOrder->importOrder($order);
        $orders->addOrder( $newOrder );
    }
}

//var_dump($orders);

//die();

while(!System_Daemon::isDying()){
	
	

	try {
        # Get My info
        $info = $tradeBot->getInfo();
		

		# Get ticker data
		$ticker = $tradeBot->getTicker();

//        $orderLog = $tradeBot->getOrders();
		
		// anounce ticker info		
		System_Daemon::log(
			System_Daemon::LOG_INFO, 
			"ticker (ask: {$ticker['sell']['value']}, buy: {$ticker['buy']['value']}, avg: {$ticker['avg']['value']})"
		);

        
        $balanceChanged = ( $info['Wallets']['USD']['Balance']['value'] > $usdAvailable ) ? true : false ;

        // Get USD funds from wallet 
        $usdAvailable = $info['Wallets']['USD']['Balance']['value'];

        // Check tracked USD total
        $usdManagedTotal = $orders->getTotalsUSD();

        $trueAvailable = ($usdAvailable - $usdManagedTotal);
        
        if($trueAvailable > 0 && $balanceChanged){
		    // Announce Discovery of new funds		
		    System_Daemon::log(
		    	System_Daemon::LOG_INFO, 
		    	"Unmanaged funds at {$trueAvailable}USD. Currently managing {$usdManagedTotal}USD"
		    );
            $split = $trueAvailable / $ticker['avg']['value'];
            if($split > 0.5){
                $orders->addOrder( new Order( $trueAvailable ) );
		        // anounce ticker info		
		        System_Daemon::log(
			         System_Daemon::LOG_INFO, 
			         "Adding order from new funds for {$trueAvailable}USD"
	            );
                
            }
        }


//        if($orderCache != $orderLog){

                // trigger the action for each order in orders. This will automatically decide what actions to take.
            $orders->actions($tradeBot, function($trigger, $buyPrice, $sellPrice, $BTC, $USD, $status){
                 System_Daemon::log(
                     System_Daemon::LOG_INFO,
                     "Order Placed: Changed from {$trigger} To: {$status}, Amounts: {$BTC}BTC, {$USD}USD, Prices: Buy({$buyPrice}) Sell({$sellPrice})"
                 );
            }); 

            // anounce ticker info		
        	System_Daemon::log(
	        	System_Daemon::LOG_INFO, 
        		"Managing ".$orders->getCount()." orders. buy(".$orders->getCount('bid').") sell(".$orders->getCount('ask').") pending(".$orders->getCount("pending").") fee({$info['Trade_Fee']})"
        	);
            
//            $orderCache = $orderLog;
//        }
					

										
	} catch(Exception $e) {

		System_Daemon::log(
			System_Daemon::LOG_INFO, 
			$e->getMessage()
		);

	}
/*	
	System_Daemon::log(
		System_Daemon::LOG_INFO, 
		"Sleeping for $min minutes"
	);
*/		

    # Take a nap for a specified offset 

    sleep(60*$min);

}
$tradeBot->save();
System_Daemon::stop();

?>
