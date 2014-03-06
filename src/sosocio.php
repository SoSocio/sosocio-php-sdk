<?php
require_once('sosocio_base.class.php');
class sosocio extends sosocio_base{

	public function __construct($config = array()){

		$this->serverUrl	  = $config['apiUrl'];
		$this->apiKey		  = $config['apiKey'];
		$this->apiSecret	  = $config['apiSecret'];
	}
	
	/**
	 * Api request
	 * 
	 * @param str url: the request endpoint
	 * @param str method: the method of the request (GET,POST,PUT,DELETE)
	 * @param array args: POST,PUT: data | GET: where condition
	 * 
	 * @return array result: the result set from the API request
	 */
	public function api($url,$method='GET',$args=array()) {
		
		$params = array(
			'method' => $method
		);
		
		# Set parameters for request
		switch(strtoupper($method)){
			case 'GET':
				if(count($args) > 0) {
					$url = $this->addConditions($url,$args);
				}
				break;
			case 'PUT':
			case 'POST':
				$params['inputdata'] = $args;			
		}

		# Return result set
		return $this->makeRequest($url,$params);
	}
	
	/**
	 * Set caching
	 * enable caching for faster results
	 * 
	 * @param bool caching
	 * 
	 */
	public function setCaching($caching){
		if(!is_bool($caching)){
			throw new Exception('The parameter must be a boolean');
		}
		else{
			$this->caching = $caching;
		}
	}
	
	/**
	* Set curl timeout in seconds
	* 
	* @param int $timeout
	*/
	public function setCurlTimeOut($timeout){
		if(!is_integer($timeout)){
			throw new Exception('The parameter must be an integer');
		}
		else{
			$this->curlTimeOut = $timeout;
		}
	}
}
?>
