#!/usr/bin/php -q
<?php


require_once "trade.php";
require_once "ledger.php";

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

$min = ( is_int( $config['wait'] ) ) ? $config['wait'] : 1;
$minWait = ( is_int( $config['throttling']['low'] ) ) ? $config['throttling']['low'] : $min;
$maxWait = ( is_int( $config['throttling']['high'] ) ) ? $config['throttling']['high'] : 30;
$tradeBalanceMinBTC = ( is_numeric( $config['trade']['balanceMinimum']['BTC'] ) ) ? $config['trade']['balanceMinimum']['BTC'] : 1;
$tradeBalanceMinUSD = ( is_numeric( $config['trade']['balanceMinimum']['USD'] ) ) ? $config['trade']['balanceMinimum']['USD'] : 1;
$randomness = false;

try {
	# create mtGox object
	$tradeBot = new trader( $config['mtgox']['key'],$config['mtgox']['secret'], $config['mtgox']['certFile']);
}catch( Exception $e){
	echo $e->getMessage();
	exit;
}



if($tradeBot->isFirstRun)
	System_Daemon::log(
		System_Daemon::LOG_INFO, 
		"Detected a first run scenario. No save file loaded..."
	);



while(!System_Daemon::isDying()){
	
	# Turn on throttling if setting is available
	if( isset( $config['throttling'] ) ){
		$min += ( is_int( $config['throttling']['interval'] ) ) ? $config['throttling']['interval'] : 1 ;
	
		# Set a maximum amount of minutes it should sleep
		if($min>$maxWait)
			$min = $minWait;
	}
	

	try {
		# Check and log orders into the tradeBot
		$syncRes = $tradeBot->syncOrders($firstRun);
		
		# Get my data
		$info = $tradeBot->updateInfo();
		

		# Get ticker data
		$ticker = $tradeBot->updateTicker();


		$btcAvailable = $tradeBot->btcAvailable();
		$usdAvailable = $tradeBot->usdAvailable();

		$percentChange = $tradeBot->getPercentChange();
		
		// anounce ticker info		
		System_Daemon::log(
			System_Daemon::LOG_INFO, 
			"ticker (ask: {$ticker['sell']['value']}, buy: {$ticker['buy']['value']}, current percent change: {$percentChange})"
		);



		$rand = rand(1, 10);
		// Begin Drafting a trade
		$btcAvailable = $btcAvailable/$rand;
		$price = $tradeBot->feeAdjust("ask", $randomness);

		if($btcAvailable>$tradeBalanceMinBTC){

					
			System_Daemon::log(
				System_Daemon::LOG_INFO, 
				"drafting (ask: {$price}, amount: $btcAvailable BTC)"
			);

			$bestPrice = ($ticker['sell']['value'] > $ticker['buy']['value'])?$ticker['sell']['value']:$ticker['buy']['value'];

			$shortSell = ($bestPrice/$price)*100;
			// if our drafted trade is profitable then push a trade
			//if($bestPrice>$price || ($usdAvailable <= $tradeBalanceMinUSD && $shortSell <= 95)){
			
			if($bestPrice>$price){
				$min = $minWait;								
				$price = $bestPrice;


				# Log this price in the tradeBot
				$orders = $tradeBot->sellBTC($btcAvailable, $price);
				

				System_Daemon::log(
					System_Daemon::LOG_INFO, 
					"Placing order: (amount: $btcAvailable, ask: $price)"
				);
			}
			
		}
		
		$rand = rand(1, 10);			
		// Begin drafting a trade		
		$price = $tradeBot->feeAdjust("bid", $randomness);
		$usdAvailable = ($usdAvailable/$rand);

		if($usdAvailable>$tradeBalanceMinUSD){

			System_Daemon::log(
				System_Daemon::LOG_INFO, 
				"drafting (bid: {$price}, amount: $usdAvailable USD)"
			);
			$bestPrice = ($ticker['sell']['value'] < $ticker['buy']['value'])?$ticker['sell']['value']:$ticker['buy']['value'];
			// If our our drafted trade is profitable push a trade
			if($bestPrice<$price){
				$min = $minWait;		
				$price = $bestPrice;
				
				$usdAvailable = $usdAvailable/$price;
					
				$orders = $tradeBot->buyBTC($usdAvailable, $price);
					
				System_Daemon::log(
					System_Daemon::LOG_INFO, 
					"Placing order: (amount: $usdAvailable, bid: $price)"
				);
			}
		}

										
	} catch(Exception $e) {

		System_Daemon::log(
			System_Daemon::LOG_INFO, 
			$e->getMessage()
		);

	}
	
	System_Daemon::log(
		System_Daemon::LOG_INFO, 
		"Sleeping for $min minutes"
	);
		
	# Take a nap for a specified offset 
	sleep(60*$min);
}
$tradeBot->save();
System_Daemon::stop();

?>
