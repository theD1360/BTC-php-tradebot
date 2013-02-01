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

// Your normal PHP code goes here. Only the code will run in the background
// so you can close your terminal session, and the application will
// still run.
$config = json_decode(file_get_contents("config.json"), true);
$ledger = new Ledger();
$min = ( is_int( $config['wait'] ) ) ? $config['wait'] : 1;
$minWait = ( is_int( $config['throttling']['low'] ) ) ? $config['throttling']['low'] : $min;
$maxWait = ( is_int( $config['throttling']['high'] ) ) ? $config['throttling']['high'] : 30;
$tradeBalanceMinBTC = ( is_numeric( $config['trade']['balanceMinimum']['BTC'] ) ) ? $config['trade']['balanceMinimum']['BTC'] : 1;
$tradeBalanceMinUSD = ( is_numeric( $config['trade']['balanceMinimum']['USD'] ) ) ? $config['trade']['balanceMinimum']['USD'] : 1;
$randomness = false;
$firstRun = $ledger->isFirstRun();

if($firstRun)
	echo "Detected a possible first run scenario. No ledger settings loaded \n";

while(!System_Daemon::isDying()){
	
	# Turn on throttling if setting is available
	if( isset( $config['throttling'] ) ){
		$min += ( is_int( $config['throttling']['interval'] ) ) ? $config['throttling']['interval'] : 1 ;
	
		# Set a maximum amount of minutes it should sleep
		if($min>$maxWait)
			$min = $minWait;
	}
	
	try {
	
		# create mtGox object
		$mtGox = new mtGox( $config['mtgox']['key'],$config['mtgox']['secret'], $config['mtgox']['certFile']);

	}catch( Exception $e){
		echo $e->getMessage();
		exit;
	}

	try {
		# Check and log orders into the ledger
		$orders = $mtGox->getOrders();
		$syncRes = $ledger->syncOrders($orders, $firstRun);
		
		# Get my data
		$info = $mtGox->getInfo();
		
		$btc = $info['Wallets']["BTC"]["Balance"]["value"];
		$usd = $info['Wallets']["USD"]["Balance"]["value"];	
		$fee = $info['Trade_Fee'];
		
		$balanceChanged = $ledger->updateBalance($btc, $usd);
		
		# Get ticker data
		$ticker = $mtGox->getTicker();
/*
		if(($syncRes === 0 || ($min % $maxWait)==0) &&  $btc==0){
			$ledger->resetPrice();
			$firstRun=true;	
		}
*/
		if($firstRun===true){
			$firstRun = false;
			$avg = ($ticker['buy']['value']+$ticker['sell']['value'])/2;
			$startSellPrice = ($avg+($avg*($fee/100)));
			$startBuyPrice = ($avg-($avg*($fee/100)));

			$ledger->addPrice(time(), $startBuyPrice, 1, "bid");
			$ledger->addPrice(time(), $startSellPrice, 1, "ask");
			System_Daemon::log(
				System_Daemon::LOG_INFO, 
				"adjusting avgs: ask: {$ledger->avgPrice('ask')}, buy: {$ledger->avgPrice('bid')}"
			);
		}


		$btcAvailable = $ledger->btcAvailable();
		$usdAvailable = $ledger->usdAvailable();


		// anounce ticker info		
		System_Daemon::log(
			System_Daemon::LOG_INFO, 
			"ticker (ask: {$ticker['sell']['value']}, buy: {$ticker['buy']['value']})"
		);



		if($btcAvailable>=$tradeBalanceMinBTC){
			$rand = rand(1, 2);
			// Begin Drafting a trade
			$btcAvailable = $btcAvailable/$rand;
			$price = $ledger->feeAdjust("ask", $fee, $randomness);

					
			System_Daemon::log(
				System_Daemon::LOG_INFO, 
				"drafting (ask: {$price}, amount: $btcAvailable BTC)"
			);

			// if our drafted trade is profitable then push a trade
			if($ticker['buy']['value']>$price){		
				$min = $minWait;								
				$price = $ticker['buy']['value'];

				$adj = ($ledger->avgPrice('bid')/$price);

				# Log this price in the ledger
				$ledger->addPrice(time() ,$price, $btcAvailable, 'ask');
				$orders = $mtGox->placeOrder("ask", $btcAvailable, $price);
				
				$ledger->reset();			

				System_Daemon::log(
					System_Daemon::LOG_INFO, 
					"Placing order: (amount: $btcAvailable, ask: $price, adjustment: $adj)"
				);
			}
			
		}
		
		if($usdAvailable>=$tradeBalanceMinUSD){
			$rand = rand(1, 2);			
			// Begin drafting a trade		
			$price = $ledger->feeAdjust("bid", $fee, $randomness);

			$usdAvailable = ($usdAvailable/$rand);

			System_Daemon::log(
				System_Daemon::LOG_INFO, 
				"drafting (bid: {$price}, amount: $usdAvailable USD"
			);

			// If our our drafted trade is profitable push a trade
			if($ticker['sell']['value']<$price){
				$min = $minWait;		
				$price = $ticker['sell']['value'];
                      		$adj = ($price/$ledger->avgPrice('ask'));
				
				$usdAvailable = $usdAvailable/$price;
				$ledger->addPrice(time(), $price, $usdAvailable,"bid");
					
				$orders = $mtGox->placeOrder("bid", $usdAvailable, $price);
					
				$ledger->reset();
				System_Daemon::log(
					System_Daemon::LOG_INFO, 
					"Placing order: (amount: $usdAvailable, bid: $price, adjustment: $adj)"
				);
			}
		}

		unset($mtGox);
										
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
$ledger->save();
System_Daemon::stop();

?>
