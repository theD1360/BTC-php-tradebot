<?php namespace OrderManager;
/*
	    Name       : OrderManager
        Author     : Diego O. Alejos
        Description: Classes used to manage the ticker trends and manage our orders.

*/
use Utilities\Arr;

class OrderManager extends Arr {
    
    private $mtgox,
            $wallet;
    
    public function __construct($mtgox)
    {
        $this->mtgox = $mtgox;
        $this->wallet = new Arr();
    }
    
    
    public function updateOrders()
    {
        $orders = $this->mtgox->getOrders();
        $this->reset($orders);
    }

    public function updateWallet()
    {
        $this->wallet = (array) $this->mtgox->getInfo();

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
