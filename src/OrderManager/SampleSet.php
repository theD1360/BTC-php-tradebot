<?php namespace OrderManager;
/*
        Name       : OrderManager
        Author     : Diego O. Alejos
        Description: Classes used to manage the ticker trends and manage our orders.

*/
use Utilities\Arr;

class SampleSet extends Arr {

	protected $max = 36,
			  $short = 16,
              $previous;

    public function insertSample($data){
        $set = $this->insert($data);

        $current = $set->end();

        if(!isset($this->previous)){
            $this->previous = $current;
        }

        $current->set("percentChange", self::calculateChange($current->last, $this->previous->last));

        $this->previous = $current;

        return $this;
    }


    public function getOpenPrice()
    {
    	return $this->first()->last;
    }

    public function getClosePrice()
    {
    	return $this->last()->last;
    }

    public function getAveragePrice()
    {
        $last = new Arr($this->flatten("last"));
        return $last->avg();   
    }

    public function getSMA()
    {

    	return $this->getAveragePrice();
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

            	$lastEMA = ($item->last * $multiplier) + ($lastEMA * (1 - $multiplier) );
	    
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

    public function change(){
        $changes = new Arr($this->flatten("percentChange"));
        return $changes->avg();        
    }

    
    public static function calculateChange($newest = 0, $oldest = 0)
    {
        $change = $newest-$oldest;
    
        return ($change/$oldest)*100;
            
    }
}