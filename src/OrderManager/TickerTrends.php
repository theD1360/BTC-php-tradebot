<?php namespace OrderManager;
/*
        Name       : OrderManager
        Author     : Diego O. Alejos
        Description: Classes used to manage the ticker trends and manage our orders.

*/
use Utilities\Arr;
use DateTime;

class TickerTrends extends Arr {

    protected $mtgox,
              $max = 36,
              $short = 16;

    public function __construct($mtgox)
    {
        //parent::__construct();
        $this->mtgox = $mtgox;

    }
    
    // Fetches the ticker info and stores it in the object.
    // the object should only hold a certain number to items 
    // to properly calculate the trend
    
    public function updateTicker()
    {

        $date = new DateTime();
        $date_formatted = $date->format("m-d-G");

        if(!$this->has($date_formatted)){
            $sample_set = new SampleSet();
            $this->set($date_formatted, $sample_set);
        }    

        $this->current = $this->get($date_formatted);

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
        
        $this->current->insert($val);
        
        $this->current->setPercentChange();
        
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

    public function getEMA($sliceAt = null){
        
        $length = $this->length();
        $lastEMA = $this->getSMA();
        $multiplier = 2/($length+1);
    	$subset = $this;

    	if(!empty($sliceAt)){
    	    $multiplier = 2/($sliceAt+1);
    	    $subset = $subset->slice($sliceAt, null, true);
    	}
		
        $subset->each(function($item) use (&$lastEMA, $multiplier){

            	$lastEMA = ($item->getEMA() * $multiplier) + ($lastEMA * (1 - $multiplier) );
	    
        });

        return $lastEMA;

    }

    public function getShortEMA()
    {
     
        
        if($this->length() < $this->max)
            $slice = ($this->length()/2) - 1 ;
        else
            $slice = $this->max - $this->short;


        return $this->getEMA($slice);
    }

    public function getSMA(){
        
        $trend = 0;
  
        $this->each(function($item) use (&$trend){
            
            $trend += $item->getAveragePrice(); 
      
        });

        return $trend/$this->length();

    }
    
    public function detectSwing()
    {
        $price = $this->getHourlyAvg();
        $EMA = $this->getEMA();
        $halfEMA = $this->getShortEMA();
        $SMA = $this->getSMA();
        
        // market trend is above zero we are currently climbing
        if($price < $halfEMA && $halfEMA > $SMA && $halfEMA > $EMA)
            return "sell";
        elseif($price > $halfEMA && $halfEMA < $SMA && $halfEMA < $EMA)
            return "buy";     

        return "hold";
        
    }

    
    
    // Gets the latest entry from the ticker 
    // It's best to retrive from here because it's 
    // already been cast as a json object
    
    public function getTickerData()
    {
        $last = clone $this->current;
        return $last->last();
    }

    public function getHourlyAvg()
    {
        return $this->current->getSMA();
    }


    // returns a price change prediction based on EMA value
    public function predict($amount = null)
    {
        $changes = new Arr($this->current->flatten("percentChange"));
        $change = $changes->avg()/100;
    
        if(!$amount)
            throw new Exception("predict does not allow an empty value");
            
        
        return $amount + ($amount*$change);

    }
    

}