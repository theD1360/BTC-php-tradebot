<?php
/*
	Name       : Ledger
        Author     : Diego O. Alejos
        Description: Simple ledger and balance management for trades

*/

class Ledger {
				private $balances = array(),
					$btc = array(0),
					$usd = array(0),
					$price = array("ask"=>array(), "bid"=>array()),
					$orders = array("clearme"=>"firstRun");

					public function addUSD($amount){
						 $this->usd[] = sprintf("%.5F", $amount);
					}					
					public function addBTC($amount){
						 $this->btc[] = sprintf("%.8F", $amount);
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

					public function addPrice($date, $price, $amount, $type){
						if(count($this->price[$type])>20){
							krsort($this->price[$type]);
							array_pop($this->price[$type]);
						}
						# Store values to get weighted avg later
						 $this->price[$type][$date] = array( 'price'=>$price, 'amount'=>$amount);
					}
					
					public function syncOrders($orders){
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
						}	
						$count = count($this->orders);
						
						# If there are no orders open reset the price to avoid restarting daemon
						
						return $count;
					}
					
					public function updateBalance($btc, $usd){
					
						$changed = false;
						$newBalance = array("btc"=>$btc, "usd"=>$usd);
						if($this->balances != $newBalance){
							$changed = true; 
							$this->balances = $newBalance;
						}					
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
	
					public function feeAdjust($type, $fee=0.6){
						if(empty($type))
							throw new Exception("transaction type cannot be left blank in feeAdjust()");
						$type = strtolower("$type");
						if(!in_array($type, array("bid","ask")))
							throw new Exception("transaction type expects string 'bid' or 'ask' only in feeAdjust()");

						// Ask for the avg price of the opposite type
						$avg = $this->avgPrice(($type=="bid")?"ask":"bid");
						$max = 1000-($fee*1000);	
						$fee = $fee + ((double) (rand(1,$max)/1000));
					
						if($type == "bid")
							$price = ($avg-($avg*($fee/100)));
					
						if($type == "ask")
							$price = ($avg+($avg*($fee/100)));		

						return $price;
					}	

}

?>
