<?php
/*
 * 调用远程RESTful的客户端类
 * 要求最低的PHP版本是5.2.0，并且还要支持以下库：cURL, Libxml 2.6.0
 * This class for invoke remote RESTful Webservice
 * The requirement of PHP version is 5.2.0 or above, and support as below:
 * cURL, Libxml 2.6.0
 *
 * @Version: 0.0.1 alpha
 * @Created: 11:06:48 2010/11/23
 * @Author:	Edison tsai<dnsing@gmail.com>
 * @Blog:	http://www.timescode.com
 * @Link:	http://www.dianboom.com
 */

 class RESTClient{
  
  #cURL Object
	private $ch;
  #Contains the last HTTP status code returned.
	public $http_code;
  #Contains the last API call.
	private $http_url;
  #Set up the API root URL.
	public $api_url;
  #Set timeout default.
	public $timeout = 10;
  #Set connect timeout.
	public $connecttimeout = 30; 
  #Verify SSL Cert.
	public $ssl_verifypeer = false;
  #Response format.
	public $format = ''; // Only support json & xml for extension
	public $decodeFormat = 'json'; //default is json
  #Decode returned json data.
	//public $decode_json = true;
  #Contains the last HTTP headers returned.
	public $http_info = array();
	public $http_header = array();
	private $contentType;
	private $postFields;
	private static $paramsOnUrlMethod = array('GET','DELETE');
	private static $supportExtension  = array('json','xml');
  #For tmpFile
	private $file = null;
  #Set the useragnet.
	private static $userAgent = 'Timescode_RESTClient v0.0.1-alpha';


	public function __construct(){

		$this->ch = curl_init();
		/* cURL settings */
		curl_setopt($this->ch, CURLOPT_USERAGENT, self::$userAgent);
		curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $this->connecttimeout);
		curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($this->ch, CURLOPT_AUTOREFERER, TRUE);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('Expect:'));
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, $this->ssl_verifypeer);
		curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, array($this, 'getHeader'));
		curl_setopt($this->ch, CURLOPT_HEADER, FALSE);

	}

     /**
      * Execute calls
      * @param $url String
      * @param $method String
      * @param $postFields String 
      * @param $username String
      * @param $password String
      * @param $contentType String 
      * @return RESTClient
      */
	public function call($url,$method,$postFields=null,$username=null,$password=null,$contentType=null){

		if (strrpos($url, 'https://') !== 0 && strrpos($url, 'http://') !== 0 && !empty($this->format)) {
				$url = "{$this->api_url}{$url}.{$this->format}";
			}

		$this->http_url		= $url;
		$this->contentType	= $contentType;
		$this->postFields	= $postFields;

		$url				= in_array($method, self::$paramsOnUrlMethod) ? $this->to_url() : $this->get_http_url();

		is_object($this->ch) or $this->__construct();

		switch ($method) {
		  case 'POST':
			curl_setopt($this->ch, CURLOPT_POST, TRUE);
			if ($this->postFields != null) {
			  curl_setopt($this->ch, CURLOPT_POSTFIELDS, $this->postFields);
			}
			break;
		  case 'DELETE':
			curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
			break;
		  case 'PUT':
			curl_setopt($this->ch, CURLOPT_PUT, TRUE);
			if ($this->postFields != null) {
				$this->file = tmpFile();
				fwrite($this->file, $this->postFields);
				fseek($this->file, 0);
			  curl_setopt($this->ch, CURLOPT_INFILE,$this->file);
			  curl_setopt($this->ch, CURLOPT_INFILESIZE,strlen($this->postFields));
			}
			break;
		}

		$this->setAuthorizeInfo($username, $password);
		$this->contentType != null && curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('Content-type:'.$this->contentType));

		curl_setopt($this->ch, CURLOPT_URL, $url);

		$response = curl_exec($this->ch);
		$this->http_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
		$this->http_info = array_merge($this->http_info, curl_getinfo($this->ch));

		$this->close();

		return $response;
	}


     /**
      * POST wrapper for insert data
      * @param $url String
      * @param $params mixed 
      * @param $username String
      * @param $password String
      * @param $contentType String
      * @return RESTClient
      */
     public function _POST($url,$params=null,$username=null,$password=null,$contentType=null) {
         $response = $this->call($url,'POST',$params,$username,$password,$contentType);
		 return $this->parseResponse($response);
     }

     /**
      * PUT wrapper for update data
      * @param $url String
      * @param $params mixed 
      * @param $username String
      * @param $password String
      * @param $contentType String
      * @return RESTClient
      */
     public function _PUT($url,$params=null,$username=null,$password=null,$contentType=null) {
         $response = $this->call($url,'PUT',$params,$username,$password,$contentType);
		 return $this->parseResponse($response);
     }

     /**
      * GET wrapper for get data
      * @param $url String
      * @param $params mixed
      * @param $username String
      * @param $password String
      * @return RESTClient
      */
     public function _GET($url,$params=null,$username=null,$password=null) {
         $response = $this->call($url,'GET',$params,$username,$password);
		 return $this->parseResponse($response);
     }

     /**
      * DELETE wrapper for delete data
      * @param $url String
      * @param $params mixed
      * @param $username String
      * @param $password String
      * @return RESTClient
      */
     public function _DELETE($url,$params=null,$username=null,$password=null) {
		 #Modified by Edison tsai on 09:50 2010/11/26 for missing part
		 $response = $this->call($url,'DELETE',$params,$username,$password);
		 return $this->parseResponse($response);
     }

	 /*
	 * Parse response, including json, xml, plain text
	 * @param $resp String
	 * @param $ext	String, including json/xml
	 * @return String
	 */
	 public function parseResponse($resp,$ext=''){
		
		$ext = !in_array($ext, self::$supportExtension) ? $this->decodeFormat : $ext;
		
		switch($ext){
				case 'json':
					$resp = json_decode($resp);break;
				case 'xml':
					$resp = self::xml_decode($resp);break;
		}
			return $resp;
	 }

	 /*
	 * XML decode
	 * @param $data String
	 * @param $toArray boolean, true for make it be array
	 * @return String
	 */
	  public static function xml_decode($data,$toArray=false){
		  /* TODO: What to do with 'toArray'? Just write it as you need. */
			$data = simplexml_load_string($data);
			return $data;
	  }

	  public static function objectToArray($obj){
			
	  }

	   /**
	   * parses the url and rebuilds it to be
	   * scheme://host/path
	   */
	  public function get_http_url() {
		$parts = parse_url($this->http_url);

		$port = @$parts['port'];
		$scheme = $parts['scheme'];
		$host = $parts['host'];
		$path = @$parts['path'];

		$port or $port = ($scheme == 'https') ? '443' : '80';

		if (($scheme == 'https' && $port != '443')
			|| ($scheme == 'http' && $port != '80')) {
		  $host = "$host:$port";
		}
		return "$scheme://$host$path";
	  }

	  /**
	   * builds a url usable for a GET request
	   */
	  public function to_url() {
		$post_data = $this->to_postdata();
		$out = $this->get_http_url();
		if ($post_data) {
		  $out .= '?'.$post_data;
		}
		return $out;
	  }

	  /**
	   * builds the data one would send in a POST request
	   */
	  public function to_postdata() {
		return http_build_query($this->postFields);
	  }

     /**
      * Settings that won't follow redirects
      * @return RESTClient
      */
     public function setNotFollow() {
         curl_setopt($this->ch,CURLOPT_AUTOREFERER,FALSE);
         curl_setopt($this->ch,CURLOPT_FOLLOWLOCATION,FALSE);
         return $this;
     }

     /**
      * Closes the connection and release resources
      * @return void
      */
     public function close() {
         curl_close($this->ch);
         if($this->file !=null) {
             fclose($this->file);
         }
     }

     /**
      * Sets the URL to be Called
	  * @param $url String
      * @return void
      */
     public function setURL($url) {
         $this->url = $url; 
     }

     /**
      * Sets the format type to be extension
	  * @param $format String
      * @return boolean
      */
	 public function setFormat($format=null){
		if($format==null)return false;
		$this->format = $format;
		return true;
	 }

     /**
      * Sets the format type to be decoded
	  * @param $format String
      * @return boolean
      */
	 public function setDecodeFormat($format=null){
		if($format==null)return false;
		$this->decodeFormat = $format;
		return true;
	 }

     /**
      * Set the Content-Type of the request to be send
      * Format like "application/json" or "application/xml" or "text/plain" or other
      * @param string $contentType
      * @return void
      */
     public function setContentType($contentType) {
         $this->contentType = $contentType;
     }

     /**
      * Set the authorize info for Basic Authentication
      * @param $username String
      * @param $password String
      * @return void
      */
     public function setAuthorizeInfo($username,$password) {
         if($username != null) { #The password might be blank
             curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
             curl_setopt($this->ch, CURLOPT_USERPWD, "{$username}:{$password}");
         }
     }

     /**
      * Set the Request HTTP Method
      * @param $method String
      * @return void
      */
     public function setMethod($method) {
         $this->method=$method;
     }

     /**
      * Set Parameters to be send on the request
      * It can be both a key/value par array (as in array("key"=>"value"))
      * or a string containing the body of the request, like a XML, JSON or other
      * Proper content-type should be set for the body if not a array
      * @param $params mixed
      * @return void
      */
     public function setParameters($params) {
         $this->postFields=$params;
     }

	  /**
	   * Get the header info to store.
	   */
	  public function getHeader($ch, $header) {
		$i = strpos($header, ':');
		if (!empty($i)) {
		  $key = str_replace('-', '_', strtolower(substr($header, 0, $i)));
		  $value = trim(substr($header, $i + 2));
		  $this->http_header[$key] = $value;
		}
		return strlen($header);
	  }
	  
 }
?>