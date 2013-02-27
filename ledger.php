<?php
/*
	Name       : Ledger
        Author     : Diego O. Alejos
        Description: Simple ledger and balance management for trades

*/

class Ledger {
				private $saveFile = "./.ledgerDump",
					$balances = array(),
					$lastTrade = 0,
					$fee = 0.60,
					$percentChange = 0,
					$btc = array(0),
					$usd = array(0),
					$avgs = array(),
					$price = array("ask"=>array(), "bid"=>array()),
					$orders = array("clearme"=>"firstRun"),
					$ticker = array("ask"=>array(), "bid"=>array()),
					$balanceLog = array(),
					$firstRun = true;

					function __construct(){
						$this->load();
						$this->lastTrade = (!$this->lastTrade)?time():$this->lastTrade;
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
						
						$this->percentChange = $this->avgs['bid']/$this->avgs['ask'];
					}	

					public function avgPrice($type){
						return $this->avgs[$type];
					}

					/* This should be called logTrade */
					public function addPrice($date, $price, $amount, $type){
						# Store values to get weighted avg later
						 $this->price[$type][$date] = array( 'price'=>$price, 'amount'=>$amount);
						$this->updateAvgPrices();						
						$this->lastTrade = time();
					}
					
					public function syncOrders($orders, $logTrade = false){
						#no change just skip this

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
				
					public function updateFee($fee){
						$this->fee = $fee;
					}

					public function updateTicker($buy, $sell){
						$this->ticker['bid'] = $buy;
						$this->ticker['ask'] = $sell;
					}
	
					public function updateBalance($btc, $usd){
					
						$changed = false;
						$newBalance = array("btc"=>$btc, "usd"=>$usd);
						if($this->balances != $newBalance){
							$changed = true; 
							$this->balances = $newBalance;
							if($btc==0 || $usd==0){
								array_unshift($this->balanceLog, $newBalance);
							}
						}	

						/* Adjust price to speed up trading */
						if( $btc == 0 && count($this->orders)==0 && ((time() - $this->lastTrade)>60*60*24*1 || $changed == true)){
							$this->resetPrice();
						}
						$this->save();				
						return $changed;
					}
					
					public function reset(){
						$this->orders = array("clearme"=>rand());
						$this->btc = array(0);
						$this->usd = array(0);
					}
					
					public function resetPrice(){
						
						if(empty($this->ticker['ask']) || empty($this->ticker['bid']))
							throw new Exception("Ticker must be set before reseting price");

						$this->price = array("ask"=>array(), "bid"=>array());
						$this->avgs = array();
						$this->percentChange = 0;
						$this->addPrice(time(), $this->ticker['ask'], 0.0000001, "ask");

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
	
					public function feeAdjust($type, $random = false){
						
						if(empty($type))
							throw new Exception("transaction type cannot be left blank in feeAdjust()");

						$type = strtolower("$type");
						if(!in_array($type, array("bid","ask")))
							throw new Exception("transaction type expects string 'bid' or 'ask' only in feeAdjust()");
						$fee = ($this->fee>=$this->percentChange)?$this->fee:$this->percentChange;

						// Ask for the avg price of the opposite type
						$avg = $this->avgs[($type=="bid")?"ask":"bid"];
						$max = 10000-($fee*10000);	

						// Always ask for just a tiny bit more than the fee
						$adjust = 1;

						// If random is set throw a random adjustment
						if($random)
							$adjust = rand(1,$max);

						$fee = $fee + ((double) ($adjust/10000));
					
						//$fee = $fee * 2;

						if($type == "bid")
							$price = ($avg-($avg*($fee/100)));
					
						if($type == "ask")
							$price = ($avg+($avg*($fee/100)));		

						return $price;
					}	

}

?>
