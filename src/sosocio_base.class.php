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
	/**
	 * @var string
	 * API user key
	 * Set as X-Api-Key header
	 */
	protected $apiKey;
	# 
	/**
	 * @var string
	 * API user secret
	 * Deprecated
	 */
	protected $apiSecret;
	# API bundle certificate for SSL
	protected $bundleCertificate;
	# When debugging is enabled, no error is thrown when API call fails
	protected $debug = FALSE;

	# Mimetype for requests
	protected $mimeType = 'application/json';

	# Curl timeout
	protected $curlTimeOut = 60;

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

	private $result;
	protected $error;

	/**
	 * Get default curl options for request
	 *
	 * @return array curl options
	 */
	public function getDefaultCurlOptions(){
		$curlHttpHeaders = array();
		if ($this->apiKey && $this->apiSecret){
			$curlHttpHeaders = array('apiKey:'.$this->apiKey,'apiSecret:'.$this->apiSecret,'X-Requested-With:XMLHttpRequest','Accept:'.$this->mimeType);
		}
		if ($this->apiKey){
			$curlHttpHeaders = array('X-Api-Key:'.$this->apiKey,'X-Requested-With:XMLHttpRequest','Accept:'.$this->mimeType);
		}
		$curlHttpHeaders = array();
		return array(
			CURLOPT_CONNECTTIMEOUT	=> 10,
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_TIMEOUT			=> $this->curlTimeOut,
			CURLOPT_USERAGENT		=> 'sosocio',
			CURLOPT_HEADER 			=> true,
			CURLOPT_HTTPHEADER		=> $curlHttpHeaders,
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

	private function handleError($curlInfo, $result) {
		$errorResult = json_decode($result, true);

		if($curlInfo['http_code'] < 200 || $curlInfo['http_code'] >= 300){
			$code = $curlInfo['http_code'];
			if (PHP_SAPI!='cli') {
				$this->error = array(
					'code' => $errorResult && isset($errorResult['error'])
						? $errorResult['error']['code']
						: $code,
					'message' => $errorResult && isset($errorResult['error'])
						? $errorResult['error']['message']
						: trim($result)
				);
			}

			if(!$this->debug) {
				throw new \Exception($result);
				exit;
			}
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
			'previous' => isset($headers['x-pagination-previous']) ? $headers['x-pagination-previous'] : false,
			'next' => isset($headers['x-pagination-next']) ? $headers['x-pagination-next'] : false,
		);

		$this->totalRecords = isset($headers['x-total-records']) ? $headers['x-total-records'] : false;

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

		# Get curl info
		$curlInfo = curl_getinfo($ch);

	    # Check for curl execution error
	    if($curlError = curl_error($ch) && !$this->debug){
			throw new Exception($curlError . ' returned at ' . $curlInfo['url']);
		}

		# Checks for http codes
	    $this->handleError($curlInfo, $result);

	    switch($this->mimeType){
			case 'text/csv':
				$this->result = $result;
				break;
			case 'application/json':
				# Decode JSON and set result array to result property
				$this->result = $result ? json_decode($result,true) : array();
				break;
			default:
				# Decode JSON and set result array to result property
				$this->result = $result ? json_decode($result,true) : array();
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
