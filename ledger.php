<?php
/*
	Name       : Ledger
        Author     : Diego O. Alejos
        Description: Simple ledger and balance management for trades

*/

class Ledger {
				private $saveFile = "./.ledgerDump",
					$balances = array(),
					$btc = array(0),
					$usd = array(0),
					$price = array("ask"=>array(), "bid"=>array()),
					$orders = array("clearme"=>"firstRun"),
					$balanceLog = array(),
					$firstRun = true;

					function __construct(){
						$this->load();
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
						
					public function avgPrice($type){
						#$p = array_sum($this->price[$type])/count($this->price[$type]);
						
						$total = 0;
						$count = 0;
						
						foreach($this->price[$type] as $item){
							$total += $item['price'] * $item['amount'];
							$count += $item['amount'];
						}
						
						$p = $total/$count;
						
						if(empty($p))
							throw new Exception("Avg price is empty!");
						#var_dump($this->price);
						#echo "avg price call for $type is ".$p;
						return $p;
					}

					/* This should be called logTrade */
					public function addPrice($date, $price, $amount, $type){
						if(count($this->price[$type])>20){
							krsort($this->price[$type]);
							array_pop($this->price[$type]);
						}
						# Store values to get weighted avg later
						 $this->price[$type][$date] = array( 'price'=>$price, 'amount'=>$amount);
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
						
						# If there are no orders open reset the price to avoid restarting daemon
						$this->save();	
						return $count;
					}
					
					public function updateBalance($btc, $usd){
					
						$changed = false;
						$newBalance = array("btc"=>$btc, "usd"=>$usd);
						if($this->balances != $newBalance){
							$changed = true; 
							$this->balances = $newBalance;
							if($btc==0 || $usd==0){
								array_unshift($this->balanceLog, $newBalance);
								/* Resetting price to speed up trading */
								if($btc == 0 && count($this->orders)==0)
									$this->resetPrice();
							}
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
						$this->price = array("ask"=>array(), "bid"=>array());
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
	
					public function feeAdjust($type, $fee=0.6, $random = false){
						if(empty($type))
							throw new Exception("transaction type cannot be left blank in feeAdjust()");
						$type = strtolower("$type");
						if(!in_array($type, array("bid","ask")))
							throw new Exception("transaction type expects string 'bid' or 'ask' only in feeAdjust()");

						// Ask for the avg price of the opposite type
						$avg = $this->avgPrice(($type=="bid")?"ask":"bid");
						$max = 1000-($fee*1000);	

						if($random)
							$fee = $fee + ((double) (rand(1,$max)/1000));
					
						if($type == "bid")
							$price = ($avg-($avg*($fee/100)));
					
						if($type == "ask")
							$price = ($avg+($avg*($fee/100)));		

						return $price;
					}	

}

?>
