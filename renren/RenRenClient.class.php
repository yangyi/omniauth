<?php
/*
 * 调用人人网RESTful API的客户端类，本类需要继承RESTClient类方可使用
 * 要求最低的PHP版本是5.2.0，并且还要支持以下库：cURL, Libxml 2.6.0
 * This class for invoke RenRen RESTful Webservice
 * It MUST be extends RESTClient
 * The requirement of PHP version is 5.2.0 or above, and support as below:
 * cURL, Libxml 2.6.0
 *
 * @Modified by Edison tsai on 14:41 2011/01/13 for wrap call_id & session_key in this class.
 *
 * @Version: 0.0.5 alpha
 * @Created: 17:09:40 2010/11/23
 * @Author:	Edison tsai<dnsing@gmail.com>
 * @Blog:	http://www.timescode.com
 * @Link:	http://www.dianboom.com
 */

require_once 'RESTClient.class.php';
// require_once 'config.inc.php'; #Include configure resources

 class RenRenClient extends RESTClient{

	private $_config;
	private	$_postFields	= '';
	private $_params		=	array();
	private $_currentMethod;
	private static $_sigKey = 'sig';
	private	$_sig			= '';
	private $_call_id		= '';
	private $_session_key	= '';
	private $_keyMapping	= array(
				'api_key'	=>	'',
				'method'	=>	'',
				'v'			=>	'',
				'format'	=>	'',
				'call_id'	=>	'',
				'session_key'=>	'',
			);
	
	public function __construct(){
		parent::__construct();
		
		$this->_config = $GLOBALS['config'];
		
		if(empty($this->_config->APIURL) || empty($this->_config->APIKey) || empty($this->_config->SecretKey)){
			throw new exception('Invalid API URL or API key or Secret key, please check config.inc.php');
		}

	}

     /**
      * GET wrapper
      * @param method String
      * @param parameters Array
      * @return mixed
      */
	public function GET(){

		$args = func_get_args();
		$this->_currentMethod	= trim($args[0]); #Method
		$this->paramsMerge($args[1])
			 ->getCallId()
			 ->getSessionKey()
			 ->setConfigToMapping()
			 ->generateSignature();

		#Invoke
		unset($args);

		return $this->_GET($this->_config->APIURL, $this->_params);
	
	}

     /**
      * POST wrapper
      * @param method String
      * @param parameters Array
      * @return mixed
      */
	public function POST(){

		$args = func_get_args();
		$this->_currentMethod	= trim($args[0]); #Method
		$this->paramsMerge($args[1])
			 ->getCallId()
			 ->getSessionKey()
			 ->setConfigToMapping()
			 ->generateSignature();

		#Invoke
		unset($args);
		return $this->_POST($this->_config->APIURL, $this->_params);
	
	}

     /**
      * PUT wrapper
      * @param method String
      * @param parameters Array
      * @return mixed
      */
	public function PUT(){

		$args = func_get_args();
		$this->_currentMethod	= trim($args[0]); #Method
		$this->paramsMerge($args[1])
			 ->getCallId()
			 ->getSessionKey()
			 ->setConfigToMapping()
			 ->generateSignature();

		#Invoke
		unset($args);

		return $this->_PUT($this->_config->APIURL, $this->_params);
	
	}

     /**
      * DELETE wrapper
      * @param method String
      * @param parameters Array
      * @return mixed
      */
	public function DELETE(){

		$args = func_get_args();
		$this->_currentMethod	= trim($args[0]); #Method
		$this->paramsMerge($args[1])
			 ->getCallId()
			 ->getSessionKey()
			 ->setConfigToMapping()
			 ->generateSignature();

		#Invoke
		unset($args);

		return $this->_DELETE($this->_config->APIURL, $this->_params);
	
	}

     /**
      * Generate signature for sig parameter
      * @param method String
      * @param parameters Array
      * @return RenRenClient
      */
	private function generateSignature(){

			$arr = array_merge($this->_params, $this->_keyMapping);
			ksort($arr);
			reset($arr);
			$str = '';
			foreach($arr AS $k=>$v){
				$str .= $k.'='.$v;
			}
			
			$this->_params = $arr;
			$str = md5($str.$this->_config->SecretKey);
			$this->_params[self::$_sigKey] = $str;
			$this->_sig = $str;

			unset($str, $arr);

			return $this;
	}

     /**
      * Parameters merge
      * @param $params Array
	  * @modified by Edison tsai on 15:56 2011/01/13 for fix non-object bug
      * @return RenRenClient
      */
	private function paramsMerge($params){

		if(!is_array($params) || count($params) < 1)return $this;

		$arr1 = explode(',', $this->_config->APIMapping[$this->_currentMethod]);
		
		#Maybe unsupport method
		if(empty($arr1) || count($arr1)<1) return (array)$params;

		$arr2 = array_combine($arr1, $params);

		if(count($arr2)<1 || !$arr2){

			foreach($arr1 AS $k=>$v){
				$arr2[$v] = $params[$k];
			} #end foreach

		} #end if

		$this->_params = $arr2;

		unset($arr1, $arr2);

		return $this;
	}

     /**
      * Setting mapping value
	  * @modified by Edison tsai on 15:04 2011/01/13 for add call id & session_key
      * @return RenRenClient
      */
	private function setConfigToMapping(){

			$this->_keyMapping['api_key']	= $this->_config->APIKey;
			$this->_keyMapping['method']	= $this->_currentMethod;
			$this->_keyMapping['v']			= $this->_config->APIVersion;
			$this->_keyMapping['format']	= $this->_config->decodeFormat;
			$this->_keyMapping['call_id']	= $this->_call_id;
			$this->_keyMapping['session_key']=$this->_session_key;

		return $this;
	}

	private function setAPIURL($url){
			$this->_config->APIURL = $url;
	}

  /**
    * Generate call id
	* @author Edison tsai
	* @created 14:48 2011/01/13
    * @return RenRenClient
    */
	public function getCallId(){
		$this->_call_id = str_pad(mt_rand(1, 9999999999), 10, 0, STR_PAD_RIGHT);
		return $this;
	}

  /**
    * Set call id
	* @param $call_id float or integer, default is zero '0'
	* @author Edison tsai
	* @created 15:06 2011/01/13
    * @return null
    */
	private function setCallId($call_id=0){
		$this->_call_id = $call_id;
		return $this->_call_id;
	}

  /**
    * Get session key
	* @author Edison tsai
	* @created 15:09 2011/01/13
    * @return RenRenClient
    */
	private function getSessionKey(){
		$this->isGotSessionKey() or $this->_session_key	= $_COOKIE[$this->_config->APIKey.'_session_key'];
		return $this;
	}

   /**
    * Set session key
	* @param $session_key, String
	* @author Edison tsai
	* @created 15:10 2011/01/13
    * @return RenRenClient
    */
	public function setSessionKey($session_key){
		!empty($session_key) and $this->_session_key = $session_key;
		return $this;
	}

  /**
    * Is got session key or not?
	* @author Edison tsai
	* @created 15:11 2011/01/13
    * @return boolean
    */
	private function isGotSessionKey(){
		return empty($this->_session_key) ? false : true;
	}

 }
?>