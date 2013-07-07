<?php
/*
	Name       : Ledger
        Author     : Diego O. Alejos
        Description: Simple ledger and balance management for trades

*/


class OrderManager {
    protected $collection = array();

    public function __construct(){}

    // Push an order into our collection of orders
    public function addOrder($order){
                $this->collection[] = $order;
    }

    public function getLastOrder(){
        return $this->collection[count($this->collection)-1];
    }

    public function getCount($status = null){
        $count = 0;

        if($status){
            foreach($this->collection as $order){
                if($order->getStatus() == $status)
                    $count++;
            }
            return $count;
        }

        return count($this->collection);
    }



    public function getTotalsUSD($collection = null){
        $collection = ($collection == null) ? $this->collection:$collection;
        $total = 0;
        foreach($collection as $order){
            $total += $order->getAmountUSD();
        }

        return $total;
    }
    
    public function getTotalsBTC($collection = null){

        $collection = ($collection == null) ? $this->collection:$collection;

        $total = 0;
        foreach($collection as $order){
            $total += $order->getAmountBTC();
        }

        return $total;
    }

    public function mergeCompleted(){
        
        if(!$this->getCount("complete"))
            return false;

        $remove = array();
        $totalSum = 0;
        
        foreach($this->collection as $index => $order){
            if($order->getStatus() == "complete"){
                $remove[] = $index;
                $totalSum += $order->getAmountUSD();
            }
        }
        
        foreach($remove as $itemIndex){
            unset($this->collection[$itemIndex]);
        }

        $newOrder = new Order($totalSum);
        $this->addOrder($newOrder);


    } 

    // This function take the same parameters as Order::action. These parameters will be passed to all our managed orders.
    public function actions(){
        $params = func_get_args();

        //This should be our trade object
        $tradeObject = $params[0];

        $this->orders = $tradeObject->getOrders();


        foreach($this->collection as $order){
            // if we cant find our order in open orders then trigger an action
            if(!$this->orderIsOpen($order->getCurrentOrderID()))
                call_user_func_array(array($order, "action"), $params);
        }

        // Merge completed orders into one order
        // there is no good reason for this other than 
        // the fact that I felt like doing it. :D
        $this->mergeCompleted();


    } 

    public function orderIsOpen($oid = null){

        if(!$oid)
            return false;

        foreach($this->orders as $order){
            if($oid == $order['oid'])
                return $order;
        }

        return false;
    }

}


class Order {
    protected $amountUSD = "",
              $amountBTC = "",
              $purchasePrice = "",
              $sellPrice = "",
              $timestamp = "",
              $status = "bid",
              $currentOrderID = "";

    public function __construct($amount = null){
        $this->amountUSD = $amount;
        $this->timestamp = time();
    } 

    public function action($tradeObject, $callback = null){

        $ticker = $tradeObject->getTicker();
        $info = $tradeObject->getInfo();
        $fee = $info['Trade_Fee']; 
        $tickerBuyPrice = $ticker['buy']['value']; 
        $tickerSellPrice = $ticker['sell']['value'];

        $triggered = false;
        
        if($this->status == 'bid'){
                $this->purchasePrice = $tickerBuyPrice;
                $bid = ($this->amountUSD/$tickerBuyPrice);
                $this->amountBTC = self::subtractFee( $bid , $fee);
                $this->currentOrderID = $tradeObject->placeOrder("bid", $bid, $tickerBuyPrice);
                $this->fee = $fee;
                $this->setSellPrice();
                $triggered = $this->status; 
                $this->status = "ask";   

        }elseif($this->status == 'ask'){

            if($this->sellPrice < $tickerSellPrice)
                $this->sellPrice = $tickerSellPrice;

            $this->currentOrderID = $tradeObject->placeOrder("ask", $this->amountBTC, $this->sellPrice);
            $triggered = $this->status;
            $this->status = "pending";
            
        }elseif($this->status == "pending"){
        
            $triggered = $this->status;
            $this->status = "complete";
        
        }elseif($this->status == 'complete'){
       
            $triggered = $this->status;
             $this->reset();
        
        }

        if(is_callable($callback) && $triggered)
            call_user_func_array($callback, array($triggered, $this->purchasePrice, $this->sellPrice, $this->amountBTC, $this->amountUSD, $this->status));

        sleep(30);

    
    }

    public function setSellPrice(){
        $newPrice = $this->purchasePrice;
        while(self::subtractFee(($this->amountBTC*$newPrice), $this->fee) < $this->amountUSD){
            $newPrice = $newPrice + "0.10";
        }

        $this->sellPrice = $newPrice;

    }

    public function getAmountUSD(){
        return $this->amountUSD;
    }

    public static function subtractFee($amount = null, $fee =0.6){
        return ($amount-($amount*($fee/100)));
    }

    public function getStatus(){
        return $this->status;
    }

    public function getCurrentOrderID(){
        return $this->currentOrderID;
    }

    public function importOrder($order){
        
        switch($order['type']){
        case "bid": 
            $this->status = "ask";
            $this->sellPrice = $order['price']['value'];
            $this->purchasePrice = $order['price']['value'];
            $this->amountUSD = $order['amount']['value'];
            break;

        case "ask":
            $this->status = "pending";
            $this->sellPrice = $order['price']['value'];
            $this->purchasePrice = $order['price']['value'];
            $this->amountBTC = $order['amount']['value'];
            $this->amountUSD = $order['amount']['value'] * $order['price']['value'];
            break;
        }
        $this->currentOrderID = $order['oid'];
 
    }

    public function reset(){
        $this->amountBTC = 0;
        $this->purchasePrice = 0;
        $this->sellPrice = 0;
        $this->status = "bid";
        $this->currentOrderID = "";
    }
		
}



?>
