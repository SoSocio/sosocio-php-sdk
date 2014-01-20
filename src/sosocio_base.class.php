<?php
# Check for required Curl extension
if (!function_exists('curl_init')) {
	throw new Exception('You need to have the CURL PHP extension.');
}
# Check for required JSON extension
if (!function_exists('json_decode') || !function_exists('json_encode')) {
	throw new Exception('You need to have the JSON PHP extension.');
}

class sosocio_base{
	
	# API endpoint
	protected $serverUrl;
	# API user key
	protected $apiKey;
	# API user secret
	protected $apiSecret;
	# Flag to disable cached results
	protected $caching = true;
	# Request data
	private $arrData;

	# Default request parameters
	private $defaultData = 	array(
		'options'	=>	array(),
		'inputdata'	=>	array()
	);

	/**
	 * Get default curl options for request
	 * 
	 * @return array curl options
	 */
	public function getDefaultCurlOptions(){
		return array(
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 60,
			CURLOPT_USERAGENT      => 'sosocio',
			CURLOPT_HTTPHEADER		=> array('apiKey:'.$this->apiKey,'apiSecret:'.$this->apiSecret)
		);
	}

	/**
	 * Build URL for API request
	 * 
	 * @param str $url: the request endpoint
	 * 
	 * @return str $finalUrl
	 */
	public function buildUrl($url){
		# URL for request starts with the server url
		$finalUrl  = $this->serverUrl;
		# Analyze request endpoint
		$urlParts = parse_url($url);
		# Add request endpoint path to final URL
		$finalUrl .= $urlParts['path'];
		
        if($this->caching){
			if(!empty($urlParts['query'])){
            	$urlParts['query'] = 'live=1&'.$urlParts['query'];
			}
			else{
				$urlParts['query'] = 'live=1';
			}
		}
		
		if(isset($urlParts['query'])){
			# Parse the query string 
			parse_str($urlParts['query'],$arrQueryParts);
			
			$finalUrl .= '?'.http_build_query($arrQueryParts); 
		}
		
		# Return final url
		return $finalUrl;
	}
	
	/**
	 * Decode JSON result from API
	 * 
	 */
	private function decodeJSON(){
		# Decode JSON and set result array to result property
		$this->result = json_decode($this->result,true);
	}

	/**
	 * Set Curl options for request
	 * 
	 * @return array curl options
	 */
	private function setCurlOptions() {

		# Get default curl options
		$curlOptions = $this->getDefaultCurlOptions();
		
		# Set method of curl request
		$curlOptions[CURLOPT_CUSTOMREQUEST] = strtoupper($this->arrData['method']);
	
		if(isset($this->arrData['inputdata']['files'])) {
		
			# Get files from api request
			$files = $this->arrData['inputdata']['files'];
	
			# If there are files to be uploaded, add these to curl post fields
			if(count($files)){
				$i = 0;
				$arrFiles = array();
				foreach($files as $file){
					$arrFiles['file'.$i] = '@'.$file['tmp_name'];
					$i++;
				}
				$curlOptions[CURLOPT_POSTFIELDS] = $arrFiles;
			}
		}
		else {
			if(count($this->arrData['inputdata'])){
				$curlOptions[CURLOPT_POSTFIELDS] = json_encode($this->arrData['inputdata']);
			}
		}

		# Return curl options
		return $curlOptions;
	}
	
	/**
	 * Make API request
	 * 
	 * @param str url: the API request endpoint
	 * @param array data: the API request data
	 * 
	 * @return array result: the result set from the API request
	 */
	public function makeRequest($url,$data=array()){
		# Set API request data (combine default data with request data)
		$this->arrData = array_merge($this->defaultData, $data);
		
		# Build URL for request
		$url = $this->buildUrl($url);
		
		# Execute API request
		$this->executeCurl($url);
	
		# Return results
	    return $this->result;
	}

	/**
	 * Execute Curl request
	 * 
	 * @param str url
	 * 
	 */
	private function executeCurl($url){
		
		# Initialize Curl request
		$ch = curl_init($url);
	
		# Set curl request options
		$opts = $this->setCurlOptions();
	    curl_setopt_array($ch, $opts);
		
		# Execute curl and store result in class result property
	    $this->result = curl_exec($ch);

	    # Decode the result set
		$this->decodeJSON();

		# Close curl request
	    curl_close($ch);
	}

}
?>
