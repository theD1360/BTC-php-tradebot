#!/usr/bin/php -q
<?php

require __DIR__ . '/vendor/autoload.php';

use \System_Daemon; 
use \OrderManager\TickerTrends;
use \BitcoinExchange\Factory;
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
System_Daemon::setOption("appName", "tradebot");
System_Daemon::setOption("authorEmail", "lego.admin@gmail.com");
System_Daemon::setOption("appDescription", "Bitcoin market trading bot");
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
	$instance  = new Factory( $config->driver, $config->auth->toArray());
	$client = $instance->client();

}catch( Exception $e){
	echo "poop". $e->getMessage();
	exit;
}


# Start instance of ticker trends
$trends = new TickerTrends($client);

$marketSymbol = preg_replace('/[^\da-z]/i', '', $config->driver)."USD";

$trends->fillHistoric($marketSymbol);


while(!System_Daemon::isDying()){

	try {
	
	    $trends->updateTicker();
	    
	    
	    $balanceBTC = $client->balance()->btc_available;
	    $balanceUSD = $client->balance()->usd_available;
	    $orderCount = $client->orders()->length();
	    $lastPrice = $trends->getTickerData()->last;
	    $buyPrice = $trends->getTickerData()->bid;
	    $sellPrice = $trends->getTickerData()->ask;

	    $suggestedAction = $trends->detectSwing();
	    $SMA = $trends->getSMA();
	    $fullEMA = $trends->getEMA();
	    $halfEMA = $trends->getShortEMA();

	    $hourlAvg = $trends->getHourlyAvg();
	    
		// anounce ticker info		
		System_Daemon::log(
			System_Daemon::LOG_INFO, 
			sprintf("EMA: %01.2f(16)/%01.2f(36), Avg: %01.2f(%01.2fhrly), Last: %01.2f, Buy: %01.2f, Sell: %01.2f, ORDERS: %s, Balance: %01.2fUSD | %01.2fBTC, Action: %s Transaction Avg: %01.2f",
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
				$trends->transactionAvg()
				)

			
		);	    
	    
	    // Place orders if we have enough cash or bitcoins for half of our available balances
	    // smallest order size is 1.0E-2
	    
	    if($suggestedAction == "buy" && (($balanceUSD/$buyPrice)/2) > 1.0E-2 ){
	        
	        if(!$config->dry)
	        	$buy = $client->buy((($balanceUSD/$buyPrice)/2), $buyPrice);
	        else
	        	$buy = "[dry run]";

	        System_Daemon::log(
		        System_Daemon::LOG_INFO, 
		        sprintf("Placed buy order %01.2f", $buy)
	        );	        	        
	    }
	    
	    if($suggestedAction == "sell" && $balanceBTC > 1.0E-2 ){
	        
	        if(!$config->dry)
	        	$sell = $client->sell($balanceBTC, $sellPrice);	   
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
