<?php
/*
	    Name       : OrderManager
        Author     : Diego O. Alejos
        Description: Classes used to manage the ticker trends and manage our orders.

*/
require_once "trade.php";
require_once "json.php";

class TickerTrends extends json {

    protected $mtgox,
              $max = 36;

    public function __construct($mtgox)
    {
        parent::__construct();
        $this->mtgox = $mtgox;
        // fills the ticker trend up so that EMA and SMA aren't insane on the first few cycles
        for($c = 0; $c< $this->max ;$c++){
            $this->updateTicker();
        }
    }
    
    // Fetches the ticker info and stores it in the object.
    // the object should only hold a certain number to items 
    // to properly calculate the trend
    
    public function updateTicker()
    {
    
        $val = $this->mtgox->getTicker();
        
        $arr = ["value"=>"", "value_int"=>"", "display"=>"", "currency"=>""];
    
        $defs = [
            "high" => $arr,
            "low" => $arr,
            "avg" => $arr,
            "vwap" => $arr,
            "vol" => $arr,
            "last_all" => $arr,
            "last_local" => $arr,
            "last_orig" => $arr,
            "last" => $arr,
            "buy" => $arr,
            "sell" => $arr,
        ];
    
        $val = array_merge($defs, $val);
        
        $this->insert($val);
        
        $this->setPercentChange();
        
        $this->tickerPear();
    }
    
    // removes any excess items;
    
    public function tickerPear()
    {
        $limit = $this->max;
       // remove anything over 20
        while($this->length() > $limit){
            $this->shift();
        }

    }
    
    // Set the percent change for new ticker items.
    public function setPercentChange()
    {
        
        $previous = $this->last()->last->value;

        $this->each(function($item) use (&$previous){
            $current = $item->last->value;
            
            if(!isset($item->percentChange))
                $item->percentChange = self::percentChange($current, $previous); 
            
            $previous = $item->last->value;
        });        
    
    }

    public function getEMA($divisor = null){
        
        $length = $this->length();
        $lastEMA = $this->getSMA();
        $multiplier = 2/($length+1);
	$subset = $this;

	if(!empty($divisor)){
	    $sliceAt = $length/$divisor;
	    $multiplier = 2/($sliceAt+1);
	    $subset = $subset->slice($sliceAt);
	}
		
        $subset->each(function($item) use (&$lastEMA, $multiplier){

            	$lastEMA = ($item->last->value * $multiplier) + ($lastEMA * (1 - $multiplier) );
	    
        });

        return $lastEMA;

    }


    public function getSMA(){
        
        $trend = 0;
  
        $this->each(function($item) use (&$trend){
            
            $trend = $trend + ($item->last->value); 
      
        });

        return $trend/$this->length();

    }
    
    public function detectSwing()
    {
        $EMA = $this->getEMA();
        $halfEMA = $this->getEMA(2);
	$SMA = $this->getSMA();
        
        // market trend is above zero we are currently climbing
        if($halfEMA < $EMA && $halfEMA > $SMA)
            return "sell";
        elseif($halfEMA > $EMA && $halfEMA < $SMA)
            return "buy";     
    
        return "hold";
        
    }
    
    public static function percentChange($newest = 0, $oldest = 0)
    {
        $change = $newest-$oldest;
    
        return ($change/$oldest)*100;
            
    }
    
    
    // Gets the latest entry from the ticker 
    // It's best to retrive from here because it's 
    // already been cast as a json object
    
    public function getTickerData()
    {
        return clone $this->last();
    }


    // returns a price change prediction based on EMA value
    public function predict($amount = null)
    {
        $ema = $this->getEMA();
    
        if(!$amount)
            throw new Exception("predict does not allow an empty value");
            
        
        return $amount + ($amount*$ema);

    }
    

}

class OrderManager extends json {
    
    private $mtgox,
            $wallet;
    
    public function __construct($mtgox)
    {
        $this->mtgox = $mtgox;
        $this->wallet = new json();
    }
    
    
    public function updateOrders()
    {
        $orders = $this->mtgox->getOrders();
        $this->reset($orders);
    }

    public function updateWallet()
    {
        $this->wallet = (array) $this->mtgox->getInfo();
        
        // for some reason this causes MtGox to be redeclared o_O
        //$this->wallet = new json($this->wallet);

    }

    public function update()
    {

        $this->updateWallet();
        $this->updateOrders();
               
    }


    public function getOrderBalance($type = "USD")
    {
        if(!in_array($type, array("USD", "BTC")))
            throw new Exception("getOrderBalance only accepts USD or BTC as valid types");
            
        $bal = 0;
        $orders = $this->where("status", "==", "open");
        
        if($type == "BTC")
            $orders = $orders->where("type", "=" , "ask");
        else
            $orders = $orders->where("type", "=" , "bid");
            
        if($orders->length() > 0){
            $orders->each(function($order) use ($type, &$bal){

                    if($type != "BTC"){
                            $bal += ($order->amount->value * $order->price->value); 

                    }else{
                        $bal += ($order->amount->value);
                    }            


            });

        }
        return $bal;
        
    }
    
    public function getAvailableBalance($type = "USD")
    {
        if(!in_array($type, array("USD", "BTC")))
            throw new Exception("getOrderBalance only accepts USD or BTC as valid types"); 
            
        $balance = $this->wallet['Wallets'][$type]['Balance']['value'];
        $busy = $this->getOrderBalance($type);
        return $balance-$busy;
                   
    }
    


}


?>
