<?php namespace OrderManager\BitcoinCharts;
/*
 * Bitcoin Charts API Library
 * 
 * @author    Brandon Beasley <http://brandonbeasley.com/>
 * @copyright Copyright (C) 2011 Brandon Beasley
 * @license   GNU GENERAL PUBLIC LICENSE (Version 3, 29 June 2007)
 * 
 *          Please consider donating if you use this library:
 *            
 *              1PPkz4tQepxyXiEf9xjyZS8cTkxN9Q6uPN
 * 
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * 
 */


class BitcoinCharts {
    
    
    //API Configuration
    const API_URL       = 'http://api.bitcoincharts.com/v1/';
    const RESULT_FORMAT = 'array'; //default is 'json'
    const API_EXT       = '.json';
    const HISTORY_EXT   = '.csv';
    const RETURN_TYPE   = 'array';
  
    
    private $weightedPrices    = array('weighted_prices');
    private $marketsData       = array('markets');
    private $historicTradeData = array('trades');
        
    //array headers for coverting trades.csv to array
    private $csvKeys           = array('time', 'price', 'amount'); 
               
    
    public function __call($method, $params = NULL) {
        
        $this->_validateMethod($method);
        
        if ($params != NULL){
            $params = $this->_buildParams($method, $params);
        } 
        
        $url = $this->_buildUrl($method, $params);
        
        $result = $this->_connect($url);
                        
        if ($method != 'trades'){
            $result = $this->_formatJson($result);
        } else {
            $result = $this->_formatCSV($result);
        }
        
        return $result;
    }
    
           
    private function _buildUrl($method, $options = NULL){
                       
        if ($method === 'trades'){
            $url = self::API_URL . $method . self::HISTORY_EXT . '?';
            foreach($options as $k => $v ){
                $url .= $k . '=' . $v . '&';
            }
        } else {
            $url = self::API_URL . $method . self::API_EXT;
        }
               
        return $url;
    }
    
    private function _buildParams($method, $params){
        
        $options = NULL;

        foreach ($params as $options){
                foreach($options as $k => $v){
                    $paramsArray[$k] = $v;
                }
            }
                                   
        return $paramsArray;
    }
            
    private function _validateMethod($method){
                           
        if(in_array($method, $this->weightedPrices) 
                OR in_array($method, $this->marketsData) 
                      OR in_array($method, $this->historicTradeData)){
                        return TRUE; 
        } else {
            die('FAILURE: Unknown Method'); 
        }
    }
    
    private function _formatJson($results){
        
        if(self::RESULT_FORMAT == strtolower('array')){
        $results = json_decode($results, true);
        }
        
        return $results;
    }
    
    private function _formatCSV($results){
        
        if(self::RESULT_FORMAT == strtolower('array')){
            
            foreach(str_getcsv($results, "\n") as $row){
                
                $csvArray[] = array_combine($this->csvKeys, str_getcsv($row));
            }
        }
       
        return $csvArray;
    }
        
    private function _connect($url, $params = NULL){
        
        //open connection
        $ch = curl_init();
                        
        //set the url
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_HEADER, TRUE);
                                
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; BTChash; '
            .php_uname('s').'; PHP/'.phpversion().')');
	
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
                
        //curl_setopt($ch, CURLOPT_VERBOSE, 1);
        
        //execute CURL connection
        $returnData = curl_exec($ch);
                
        //$code = $this->returnCode($returnData);        
        
        if( $returnData === false)
        {
            die('<br />Connection error:' . curl_error($ch));
        }
        else
        {
            //Log successful CURL connection
        }
        
        //close CURL connection
        curl_close($ch);
        
                        
        return $returnData;
    }
   
}

