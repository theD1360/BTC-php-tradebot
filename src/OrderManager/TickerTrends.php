<?php namespace OrderManager;
/*
        Name       : OrderManager
        Author     : Diego O. Alejos
        Description: Classes used to manage the ticker trends and manage our orders.

*/

use OrderManager\BitcoinCharts\BitcoinCharts;
use Utilities\Arr;
use DateTime;

class TickerTrends extends Arr {

    protected $client,
              $max = 36,
              $short = 16,
              $lastTransactionPrice,
              $current,
              $lastAction = "hold";

    public function __construct($client)
    {
        //parent::__construct();
        $this->client = $client;
        $this->lastTransactionPrice = new Arr();

    }
    
    // Fetches the ticker info and stores it in the object.
    // the object should only hold a certain number to items 
    // to properly calculate the trend
    
    public function updateTicker()
    {

        $val = $this->client->ticker();
        $this->insertTickerData(time(), $val->toArray());

    }

    private function insertTickerData($timestamp, $data = [])
    {

        $date = new DateTime();
        $date->setTimestamp((int) $timestamp);
        $date_formatted = $date->format("m-d-G");

        if(!$this->has($date_formatted)){
            $sample_set = new SampleSet([]);
            $this->set($date_formatted, $sample_set);
        }    
        
        $this->current = $this->get($date_formatted);
        
        $this->current->insertSample(new Arr($data));
                
        $this->tickerPear();
    }

    public function fillHistoric($symbol){

        $trends = $this;
        $bitcoinCharts = new BitcoinCharts();
        $tradeData = $bitcoinCharts->trades(array('symbol' => $symbol));
        $tradeData = new Arr(array_reverse($tradeData));
        $new_data = $tradeData->each(function($item) use (&$trends){
            $trends->insertTickerData($item->time, ["last"=>$item->price]);
        });

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
        if($this->lastTransactionPrice->isEmpty()){
            $this->lastTransactionPrice->insert($this->getSMA());
        }

        $change = $this->current->change();
        $EMA = $this->getEMA();
        $halfEMA = $this->getShortEMA();
        $SMA = $this->getSMA();
        $last = $this->getTickerData()->last;

        if($change < 0 && $halfEMA < $EMA && $last > $this->transactionAvg() && ($this->lastAction == "hold" || $this->lastAction == "buy")){
            $this->lastTransactionPrice->insert($last);
            $this->lastAction = "sell";
            return $this->lastAction;
        }
        elseif($change > 0 && $halfEMA > $EMA && $last < $this->transactionAvg() && ($this->lastAction == "hold" || $this->lastAction == "sell")){
            $this->lastTransactionPrice->insert($last);
            $this->lastAction = "buy";
            return $this->lastAction;
        }

        // cleanup to avoid memory leaks.

        while($this->lastTransactionPrice->length() > 5){
            $this->lastTransactionPrice->shift();
        }

        return "hold";
        
    }

    public function transactionAvg(){
        return $this->lastTransactionPrice->avg();
    }
    
    // Gets the latest entry from the ticker 
    // It's best to retrive from here because it's 
    // already been cast as a json object
    
    public function getTickerData()
    {
        $last = $this->current;
        return $last->end();
    }

    public function getHourlyAvg()
    {
        return $this->current->getSMA();
    }

    public function change(){
        $trend = 0;
  
        $this->each(function($item) use (&$trend){
            $trend += $item->change(); 
        });

        return $trend/$this->length();    
    }


    // returns a price change prediction based on EMA value
    public function predict($amount = null)
    {
        $change = $this->percentChange();
    
        if(!$amount)
            throw new Exception("predict does not allow an empty value");
            
        
        return $amount + ($amount*$change);

    }
    

}