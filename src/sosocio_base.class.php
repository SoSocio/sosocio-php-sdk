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
	
	const
		HTTP_100='Continue',
		HTTP_101='Switching Protocols',
		HTTP_200='OK',
		HTTP_201='Created',
		HTTP_202='Accepted',
		HTTP_203='Non-Authorative Information',
		HTTP_204='No Content',
		HTTP_205='Reset Content',
		HTTP_206='Partial Content',
		HTTP_300='Multiple Choices',
		HTTP_301='Moved Permanently',
		HTTP_302='Found',
		HTTP_303='See Other',
		HTTP_304='Not Modified',
		HTTP_305='Use Proxy',
		HTTP_307='Temporary Redirect',
		HTTP_400='Bad Request',
		HTTP_401='Unauthorized',
		HTTP_402='Payment Required',
		HTTP_403='Forbidden',
		HTTP_404='Not Found',
		HTTP_405='Method Not Allowed',
		HTTP_406='Not Acceptable',
		HTTP_407='Proxy Authentication Required',
		HTTP_408='Request Timeout',
		HTTP_409='Conflict',
		HTTP_410='Gone',
		HTTP_411='Length Required',
		HTTP_412='Precondition Failed',
		HTTP_413='Request Entity Too Large',
		HTTP_414='Request-URI Too Long',
		HTTP_415='Unsupported Media Type',
		HTTP_416='Requested Range Not Satisfiable',
		HTTP_417='Expectation Failed',
		HTTP_500='Internal Server Error',
		HTTP_501='Not Implemented',
		HTTP_502='Bad Gateway',
		HTTP_503='Service Unavailable',
		HTTP_504='Gateway Timeout',
		HTTP_505='HTTP Version Not Supported';
	
	# API endpoint
	protected $serverUrl;
	# API user key
	protected $apiKey;
	# API user secret
	protected $apiSecret;
	# API bundle certificate for SSL
	protected $bundleCertificate;
	
	protected $mimeType = 'application/json';
	
	# Curl timeout
	protected $curlTimeOut = 60;
	
	# Http codes used to check for errors
	public $httpCodes = array(200,201);
	
	# Total record count api
	public $totalRecords;
	
	# Pagination api
	public $pagination;
	
	private $responseHeaders;
	
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
			CURLOPT_CONNECTTIMEOUT	=> 10,
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_TIMEOUT			=> $this->curlTimeOut,
			CURLOPT_USERAGENT		=> 'sosocio',
			CURLOPT_HEADER 			=> true,
			CURLOPT_HTTPHEADER		=> array('apiKey:'.$this->apiKey,'apiSecret:'.$this->apiSecret,'X-Requested-With:XMLHttpRequest','Accept:'.$this->mimeType),
			CURLOPT_CAINFO			=> $this->bundleCertificate
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
		if(isset($urlParts['path']) && !empty($urlParts['path'])){
			$finalUrl .= $urlParts['path'];
		}
		
		if(isset($urlParts['query'])){
			# Parse the query string 
			parse_str($urlParts['query'],$arrQueryParts);
			
			$finalUrl .= '?'.http_build_query($arrQueryParts);
		}
		
		# Return final url
		return $finalUrl;
	}
	
	private function handleError($ch){

		$curlInfo = curl_getinfo($ch);

		if($curlError = curl_error($ch)){
			throw new Exception($curlError);
		}

		if(!in_array($curlInfo['http_code'],$this->httpCodes)){
			$code = $curlInfo['http_code'];
			$reason=@constant('self::HTTP_'.$code);
			if (PHP_SAPI!='cli')
				header('HTTP/1.1 '.$code.' '.$reason);
				echo $reason;
			exit;
		}
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
					$finfo = finfo_open(FILEINFO_MIME_TYPE);
					$postFieldOption = $this->getCurlValue($file['tmp_name'], finfo_file($finfo,$file['tmp_name']), $file['name']);
					$arrFiles['file'.$i] = $postFieldOption;//'@'.$file['tmp_name'];
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
	
	private function getCurlValue($filename, $contentType, $postname) {
	    // PHP 5.5 introduced a CurlFile object that deprecates the old @filename syntax
	    // See: https://wiki.php.net/rfc/curl-file-upload
	    if (function_exists('curl_file_create')) {
	        return curl_file_create($filename, $contentType, $postname);
	    }
	
	    // Use the old style if using an older version of PHP
	    $value = "@{$filename};filename=" . $postname;
	    if ($contentType) {
	        $value .= ';type=' . $contentType;
	    }
	
	    return $value;
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

	private function formatResponseHeaders(){
		$headers = array();
		$explodedHeaders = explode("\n", $this->responseHeaders);

        foreach ($explodedHeaders as $i => $h) {
            $h = explode(':', $h, 2);
           
            if (isset($h[1])) {
                $headers[$h[0]] = trim($h[1]);
            }
        }

		$this->pagination = array(
			'previous' => isset($headers['X-Pagination-Previous']) ? $headers['X-Pagination-Previous'] : false,
			'next' => isset($headers['X-Pagination-Next']) ? $headers['X-Pagination-Next'] : false,
		);
		
		$this->totalRecords = isset($headers['X-Total-Records']) ? $headers['X-Total-Records'] : false;

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
		$curlResponse = curl_exec($ch);
		
		$header_size 			= curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$this->responseHeaders 	= substr($curlResponse, 0, $header_size);
		$result 				= substr($curlResponse, $header_size);

		# Format headers		
	    $this->formatResponseHeaders();

	    # Checks for http codes
	    $this->handleError($ch);
	    
	    switch($this->mimeType){
			case 'text/csv':
				$this->result = $result;
				break;
			case 'application/json':
				# Decode JSON and set result array to result property
				$this->result = json_decode($result,true);
				break;
		}

		# Close curl request
	    curl_close($ch);
	}
	
	/**
	* Add where conditions
	* 
	* @param string $url
	* @param array $conditions
	*/
	protected function addConditions($url, $conditions){
		
		# If no conditions, return the url with all its conditions
		if(!is_array($conditions)){
			return $url;
		}
		
		# Parse the url so we can get all conditions
		$urlParts = parse_url($url);
				
		# Convert url conditions to array
		if(isset($urlParts['path'])) {
			# The endpoint of the API call without the extra conditions in the url
			$urlEndpoint = $urlParts['path'];
		}
		else {
			throw new Exception('Please provide an URL endpoint in SDK call');
		}
		
		# Encode where conditions
		if(isset($conditions['where'])) {
			$conditions['where'] = json_encode($conditions['where']);
		}
		
		# Encode search conditions
		if(isset($conditions['search'])) {
			$conditions['search'] = json_encode($conditions['search']);
		}
		
		# If there are extra conditions in the url, convert these
		if(isset($urlParts['query'])){
			# Parse the query string and store the (extra) conditions in $urlConditions
			parse_str($urlParts['query'],$urlConditions);
			
			# Debugging info: SDK users cannot provide the same conditions via URL and SDK call
			foreach($urlConditions as $paramName => $paramValue){
				if(array_key_exists($paramName, $conditions)){
					throw new Exception('Either provide '.$paramName.' parameter via url, or via SDK, cannot have both');
				}
			}
			
			# Merge SDK conditions and URL conditions
			$conditions = array_merge($conditions, $urlConditions);
		}
		
		return $urlEndpoint . '?'.http_build_query($conditions);
	}

}
?>
