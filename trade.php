<?php
/*
        Name       : MtGox API v1 Trading Class
        Author     : Diego O. Alejos
        Description: Simplifies MtGox API authentication and interaction methods
 
*/

class mtGox{
	private $key, 
		$secret,
		$certFile,
		$query = array(),
		$queryString="",
		$headers = array(),
		$basePath = "https://mtgox.com/api",
		$apiVersion = '1',
		$apiURL = "";
					
	function __construct($key = "", $secret = "", $cert = "mtgox-cert"){
	
			if(empty($key) || empty($secret))
				throw new Exception("Missing connection information");
				
				$this->key = $key;
				$this->secret = $secret;
				$this->certFile = $cert;
	}

	// Simplify placing orders and follow proper currency value handling
	public function placeOrder($type = null, $amount = null, $rate=null, $currency ="BTCUSD"){
		$type = strtolower($type);
		if($type !== "ask" && $type !== "bid")
			throw new Exception("first parameter of placeOrder() should be ask or bid");

		if(empty($amount))
			throw new Exception("Second parameter of placeOrder() cannot be empty");

		$amount = round($amount * 1E8);

		$this->setPath($currency, "private", "order", "add");
		$this->setParam("type", $type);
		$this->setParam('amount_int', $amount);
	
		if(!empty($rate)){
			$rate = round($rate * 1E5);
			$this->setParam("price_int", $rate);
		}
		return $this->sendRequest();
	}
	
	public function getInfo(){
		$this->setPath("generic", "private", "info");
		return $this->sendRequest();
	}
	
	public function getTicker($currency="BTCUSD"){
		$this->setPath($currency, "ticker");
		return $this->sendRequest();
	}
	
	public function getOrders(){
		$this->setPath("generic", "private", "orders");
		return $this->sendRequest();
	}
	
	public function setParam($key, $value){
		return	$this->query[$key] = $value;
	}
	

	
	private function createRequest(){
		// generate a nonce as microtime, with as-string handling to avoid problems with 32bits systems
		$mt = explode(' ', microtime());
		$this->setParam("nonce", $mt[1].substr($mt[0], 2, 6));
	 
		// generate the POST data string
		$this->queryString = http_build_query($this->query, '', '&');

		// generate the extra headers
		$this->headers = array(
							'Rest-Key: '.$this->key,
							'Rest-Sign: '.base64_encode(hash_hmac('sha512', $this->queryString, base64_decode($this->secret), true)),
						);	
	}
	



	public function setPath(){
		$argc = func_num_args();
		if(empty($argc)){
			throw new Exception("Method expects at least one argument");
		}

		$this->apiURL = $this->basePath."/".$this->apiVersion."/".trim(implode('/',func_get_args()), "/");	
	}	

	public function clear(){
		$this->query	= array();
		$this->headers = "";
		$this->queryString="";
		$this->apiURL = "";
	}
	
	
	public function sendRequest(){

		//builds the request
		$this->createRequest();
	 
		// our curl handle (initialize if required)
		static $ch = null;
		if (is_null($ch)) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MtGox PHP client; '.php_uname('s').'; PHP/'.phpversion().')');

		}
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($ch, CURLOPT_CAINFO, $this->certFile);	
		curl_setopt($ch, CURLOPT_URL, $this->apiURL);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $this->queryString);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);

		# clean the query data


		// run the query
		$res = curl_exec($ch);
		
		if ($res === false) 
			throw new Exception('Could not get reply: '.curl_error($ch));
		
		$dec = json_decode($res, true);
		if (!$dec) 
			throw new Exception('Invalid data received, please make sure connection is working and requested API exists');
		
		if ($dec['result']!='success')
			throw new Exception('Query Failed... ('.$dec['error'].':'.$this->apiURL.'?'.$this->queryString.')');
			
		$this->clear();

		return $dec['return'];
	
	}
	
}

								

?>
