<?php namespace OrderManager;
/*
        Name       : OrderManager
        Author     : Diego O. Alejos
        Description: Classes used to manage the ticker trends and manage our orders.

*/
use Utilities\Arr;

class SampleSet extends Arr {

	protected $max = 36,
			  $short = 16;

    // Set the percent change for new ticker items.
    public function setPercentChange()
    {
        $length = $this->length();
        $multiplier = 2/($length+1);
       
        $previous = $this->last();

        $this->each(function($current) use (&$previous, $multiplier){
            
            if(!isset($current->percentChange))
                $current->percentChange = (self::percentChange($current->last->value, $previous->last->value) + $previous->percentChange) / 2; 
          
                $previous = $current;
        });        
    
    }

    public function getOpenPrice()
    {
    	return $this->first()->last->value;
    }

    public function getClosePrice()
    {
    	return $this->last()->last->value;
    }

    public function getAveragePrice()
    {
    	$sum = 0;
    	$length = $this->length();

    	$this->each(function($item) use (&$sum){
    		$sum += $item->last->value;
    	});
    
    	return $sum/$length;

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

            	$lastEMA = ($item->last->value * $multiplier) + ($lastEMA * (1 - $multiplier) );
	    
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

    
    public static function percentChange($newest = 0, $oldest = 0)
    {
        $change = $newest-$oldest;
    
        return ($change/$oldest)*100;
            
    }
}