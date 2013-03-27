<?php
/*
	Name       : Ledger
        Author     : Diego O. Alejos
        Description: Simple ledger and balance management for trades

*/

class trader extends mtGox{
				private $saveFile = "./.ledgerDump",
					$balances = array(),
					$lastTrade = 0,
					$fee = 0.00,
					$percentChange = 0,
					$btc = array(0),
					$usd = array(0),
					$avgs = array(),
					$price = array("ask"=>array(), "bid"=>array()),
					$orders = array("clearme"=>"firstRun"),
					$ticker = array("ask"=>array(), "bid"=>array()),
					$balanceLog = array(),
					$lastBalanceChange = true,
					$firstRun = true;

					function __construct($key ="", $secret="", $cert = "mtgox-cert"){
						$this->load();
						$this->lastTrade = (!$this->lastTrade)?time():$this->lastTrade;
						parent::__construct($key, $secret, $cert);
					}

					public function addUSD($amount){
						 $this->usd[] = sprintf("%.5F", $amount);
						 $this->save();
					}					
					public function addBTC($amount){
						 $this->btc[] = sprintf("%.8F", $amount);
						 $this->save();
					}
				/* Determine if properties loaded if not then assume first run */
					public function isFirstRun(){
						return $this->firstRun;
					}
				/* Save this objects properties to a file */
					public function save(){
						file_put_contents($this->saveFile, json_encode((array) $this));
					}
				/* Retrieve this objects properties from save file */	
					public function load(){
						$className = get_class();
						if(file_exists($this->saveFile) && is_readable($this->saveFile)){
							
							$settings = json_decode(file_get_contents($this->saveFile), true);

							if(!empty($settings)){

								foreach($settings as $key=> $value){
									$opt = trim(substr($key, strlen($className)+1));
									if(!empty($opt)){
										echo "loading: $opt = $value \n";
										$this->{$opt} = $value;
									}
								}
								
								$this->firstRun = false;
							}
						}
					}
					public function updateAvgPrices(){
						$types = array("ask", "bid");
						$this->avgs = array();
						foreach($types as $type){

							$total = 0;
							$count = 0;
						
							foreach($this->price[$type] as $item){
								$total += $item['price'] * $item['amount'];
								$count += $item['amount'];
							}
						
							if($count)
								$p = $total/$count;
							else
								$p = $this->feeAdjust($type);	

							$this->avgs[$type] = $p;
							
						}
						
						$this->percentChange = ( 1 - ( $this->avgs['bid'] / $this->avgs['ask'] ) ) * 100;
					}	

					public function avgPrice($type){
						return $this->avgs[$type];
					}

					public function sellBTC($amount, $price){
						$this->addPrice(time(), $price, $amount, "ask");
						$this->placeOrder("ask", $amount, $price);
						$this->reset();			
					}

					public function buyBTC($amount, $price){
						$this->addPrice(time(), $price, $amount, "bid");
						$this->placeOrder("bid", $amount, $price);
						$this->reset();			
					}

					/* This should be called logTrade */
					public function addPrice($date, $price, $amount, $type){
						# Store values to get weighted avg later
						 $this->price[$type][$date] = array( 'price'=>$price, 'amount'=>$amount);
						$this->updateAvgPrices();						
						$this->lastTrade = time();
					}
					
					public function syncOrders($logTrade = false){
						#no change just skip this
						$orders = $this->getOrders();

						if($this->orders == $orders)
							return false;
							
						$this->reset();
						$this->orders = $orders;
						foreach($this->orders as $order){
							if($order['type']=='bid'){
								$this->addUSD($order['amount']['value']*$order['price']['value']);
							}else{
								$this->addBTC($order['amount']['value']);
							}
							/* Written for First Run Function */
							if($logTrade==true)
								$this->addPrice($order['date'], $order['price']['value'], $order['amount']['value'], $order['type']);
						}	
						$count = count($this->orders);
						
						$this->save();	
						return $count;
					}
				

					public function updateTicker(){
						$ticker = $this->getTicker();
						$this->ticker['bid'] = $ticker['buy']['value'];
						$this->ticker['ask'] = $ticker['sell']['value'];
						return $ticker;
					}
					public function updateInfo(){
						$info = $this->getInfo();
												
						$btc = $info['Wallets']["BTC"]["Balance"]["value"];
						$usd = $info['Wallets']["USD"]["Balance"]["value"];	
						$fee = $info['Trade_Fee'];
		
						$this->updateBalance($btc, $usd);

						$this->fee = $fee;	
						return $info;
					}	

					public function updateBalance($btc, $usd){
					
						$changed = false;
						$newBalance = array("btc"=>$btc, "usd"=>$usd);
						if($this->balances != $newBalance){
							$changed = time(); 
							$this->balances = $newBalance;
							if($btc==0 || $usd==0){
								array_unshift($this->balanceLog, $newBalance);
							}
						}	

						/* Adjust price to speed up trading */
						if( $btc == 0 && count($this->orders)==0 && ((time() - $this->lastTrade)>60*60*6 || $changed == true)){
							$this->resetPrice();
						}

						$this->lastBalanceChange = $change;
						$this->save();				
						
					}

					public function balanceChanged(){
						return $this->lastBalanceChange;
					}
					
					public function reset(){
						$this->orders = array("clearme"=>rand());
						$this->btc = array(0);
						$this->usd = array(0);
					}
					
					public function resetPrice(){
						
						while(empty($this->ticker['ask']) || empty($this->ticker['bid'])){
							$this->updateTicker();
						}
							

						$this->price = array("ask"=>array(), "bid"=>array());
						$this->percentChange = 0.0;
						$this->avgs = array();
						$avg = ($this->ticker['bid']+$this->ticker['ask'])/2;
						$this->addPrice(time(), $avg, 0.0000001, "ask");
						$this->addPrice(time(), $avg, 0.0000001, "bid");

					}

					public function btcOrderTotal(){
						return array_sum($this->btc);
					}	

					public function usdOrderTotal(){
						return array_sum($this->usd);
					}
					
					public function btcAvailable(){
						return sprintf("%.8F", ($this->balances["btc"] - $this->btcOrderTotal()));
					}					
					
					public function usdAvailable(){
						return sprintf("%.5F", ($this->balances["usd"] - $this->usdOrderTotal()));
					}
					public function getPercentChange(){
						return $this->percentChange;
					}	
					public function feeAdjust($type, $random = false){
						
						if(empty($type))
							throw new Exception("transaction type cannot be left blank in feeAdjust()");

						$type = strtolower("$type");
						if(!in_array($type, array("bid","ask")))
							throw new Exception("transaction type expects string 'bid' or 'ask' only in feeAdjust()");
						//$fee = ($this->fee>=$this->percentChange)?$this->fee:$this->percentChange;
						$fee = $this->percentChange;
						// Ask for the avg price of the opposite type
						$avg = $this->avgs[($type=="bid")?"ask":"bid"];
						$max = 10000-($fee*10000);	

						// Always ask for just a tiny bit more than the fee
						$adjust = 1;

						// If random is set throw a random adjustment
						if($random)
							$adjust = rand(1,$max);

						$fee = $fee + ((double) ($adjust/10000));
					
						if($type == "bid")
							$price = ($avg-($avg*($fee/100)));
					
						if($type == "ask")
							$price = ($avg+($avg*($fee/100)));		

						return $price;
					}	

}

?>
