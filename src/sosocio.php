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
	 * 
	 * @return array result: the result set from the API request
	 */
	public function api($url,$method='GET',$data=array()) {
		
		# Set parameters for request
		$parameters = array(
			'method' => $method,
			'inputdata' =>	$data
		);

		# Return result set
		return $this->makeRequest($url,$parameters);
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
}
?>
