<?php 

namespace OmniAuth;
require 'oauth2/oauth2.php';
require 'oauth1/tmhOAuth.php';
require 'renren/RenRenClient.class.php';

class Base
{
    
    static $strategies = array();
    
    function __construct() {
    }
    
    static function useIt($provider, $options)
    {
        $class = 'OmniAuth\\' . ucwords($provider);
        static::$strategies[$provider] = new $class($options);
    }
    
    static function strategy($provider) {
        return static::$strategies[$provider];
    }
}

class Strategy
{
    
    static function curlIt($url, $params, $method = 'GET', $format = 'json')
    {
        $ch = curl_init();

        // set URL and other appropriate options
        foreach ($params as $key => $value) {
            $pa[] = "$key=" . urlencode($value);
        }
        curl_setopt($ch, CURLOPT_URL, "$url?" .  implode('&', $pa));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, TRUE);
        }
        $text = curl_exec($ch);
        // echo "response is $text";
        $response = json_decode($text, TRUE);
        curl_close($ch);
        return $response;
    }

}


class Renren extends Strategy
{
    static $conf = array(
        'strategy' => 'oauth2',
        'authorize' => 'https://graph.renren.com/oauth/authorize',
        'access_token' => 'https://graph.renren.com/oauth/token',
        'session_key' => 'https://graph.renren.com/renren_api/session_key',
        'api' => 'https://api.renren.com/restserver.do'
    );
    
    function __construct($options)
    {

        $client = new \OAuth2_Client(
                $options['api_key'],
                $options['secret'],
                $options['callback']);
    
        $configuration = new \OAuth2_Service_Configuration(
                static::$conf['authorize'],
                static::$conf['access_token']);
        
        $dataStore = new \OAuth2_DataStore_Session();
        
        $this->_service = new \OAuth2_Service($client, $configuration, $dataStore, "publish_feed email");
        
        $config->APIURL		= 'http://api.xiaonei.com/restserver.do'; //RenRen网的API调用地址，不需要修改
        $config->APIKey		= $options['api_key'];	//你的API Key，请自行申请
        $config->SecretKey	= $options['secret'];	//你的API 密钥
        $config->APIVersion	= '1.0';	//当前API的版本号，不需要修改
        $config->decodeFormat	= 'json';	//默认的返回格式，根据实际情况修改，支持：json,xml
        /*
         *@ 以下接口内容来自http://wiki.dev.renren.com/wiki/API，编写时请遵守以下规则：
         *  key  (键名)		: API方法名，直接Copy过来即可，请区分大小写
         *  value(键值)		: 把所有的参数，包括required及optional，除了api_key,method,v,format不需要填写之外，
         *					  其它的都可以根据你的实现情况来处理，以英文半角状态下的逗号来分割各个参数。
         */
        $config->APIMapping		= array( 
        		'admin.getAllocation' => '',
        		'connect.getUnconnectedFriendsCount' => '',
        		'friends.areFriends' => 'uids1,uids2',
        		'friends.get' => 'page,count',
        		'friends.getFriends' => 'page,count',
        		'notifications.send' => 'to_ids,notification',
        		'users.getInfo'	=> 'uids,fields',
        		/* 更多的方法，请自行添加 
        		   For more methods, please add by yourself.
        		*/
        );
        
        $GLOBALS['_renren_config'] = $config;
    }
    
    function authorizeUrl()
    {
        return $this->_service->authorize();
    }
    
    function callback($code = null)
    {
        if ($code == null) {
            $code = $_GET['code'];
        }
        $token = $this->_service->getAccessToken($code);
        $this->access_token = $token->getAccessToken();
    }
    
    function getUserInfo()
    {
        $url = static::$conf['session_key'];
        $sk = static::curlIt($url, array('oauth_token' => $this->access_token));
        
        $session_key = $sk["renren_token"]["session_key"];

        $renren = new \RenRenClient();
        $renren->setSessionKey($session_key);
        $user = $renren->POST('users.getLoggedInUser');
        $result = $renren->POST('users.getInfo', array($user->uid));
        return array(
            'uid' => $result[0]->uid,
            'nickname' => $result[0]->name
        );
    }
}

class OAuth extends Strategy {
    function __construct($options)
    {
        $this->_service = new \tmhOAuth(array(
          'consumer_key'    => $options['api_key'],
          'consumer_secret' => $options['secret'],
          'host' => static::$conf['host'],
          'use_ssl' => false,
          'oauth_http_header' => static::$conf['oauth_http_header']
        ));
        $this->_options = $options;
    }
    
    function authorizeUrl()
    {
        $oauth = $this->_service;
        $oauth->request('GET', $oauth->url(static::$conf['request_token'], ''), array(
          'oauth_callback' => $this->_options['callback']
        ));
        
        $_SESSION['oauth'] = $oauth->extract_params($oauth->response['response']);
        $oauth_token = $_SESSION['oauth']['oauth_token'];
        $aurl = $oauth->url(static::$conf['authorize'], '') . "?oauth_token=$oauth_token";
        return $aurl;
    }
    
    function callback()
    {
        $tmhOAuth = $this->_service;
        $tmhOAuth->config['user_token']  = $_SESSION['oauth']['oauth_token'];
        $tmhOAuth->config['user_secret'] = $_SESSION['oauth']['oauth_token_secret'];

        $tmhOAuth->request('POST', $tmhOAuth->url('oauth/access_token', ''), array(
          'oauth_verifier' => $_REQUEST['oauth_verifier']
        ));
        $_SESSION['access_token'] = $tmhOAuth->extract_params($tmhOAuth->response['response']);
        unset($_SESSION['oauth']);
    }
    
    function getUserInfo()
    {
        return "userinfo";
    }
}

class Tsina extends OAuth
{
    static $conf = array(
        'host' => 'api.t.sina.com.cn',
        'authorize' => 'oauth/authorize',
        'request_token' => 'oauth/request_token',
        'access_token' => 'oauth/access_token',
        'oauth_http_header' => true
    );
    
    
}

class Qzone extends OAuth
{
    static $conf = array(
        'host' => 'openapi.qzone.qq.com',
        'authorize' => 'oauth/qzoneoauth_authorize',
        'request_token' => 'oauth/qzoneoauth_request_token',
        'access_token' => 'oauth/qzoneoauth_access_token',
        'oauth_http_header' => false
    );
    
    function authorizeUrl()
    {
        $oauth = $this->_service;
        $oauth->request('GET', $oauth->url(static::$conf['request_token'], ''), array(
          'oauth_callback' => $this->_options['callback']
        ));
        
        $_SESSION['oauth'] = $oauth->extract_params($oauth->response['response']);
        $oauth_token = $_SESSION['oauth']['oauth_token'];
        $params = array(
            'oauth_token' => $oauth_token,
            'oauth_consumer_key' => $this->_options['api_key'],
            'oauth_callback' => $this->_options['callback']
        );
        $aurl = $oauth->url(static::$conf['authorize'], '');
        
        foreach ($params as $key => $value) {
            $kv[] = $key . "=" . urlencode($value);
        }
        
        return $aurl . "?" . implode("&", $kv);
    }
    
}