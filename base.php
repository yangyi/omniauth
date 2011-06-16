<?php 

namespace OmniAuth;
require 'oauth2/oauth2.php';
require 'oauth1/tmhOAuth.php';
require 'renren/RenRenClient.class.php';

class Base
{
    static $platforms = array(
        'renren' => array(
            'strategy' => 'oauth2',
            'authorize' => 'https://graph.renren.com/oauth/authorize',
            'access_token' => 'https://graph.renren.com/oauth/token',
            'session_key' => 'https://graph.renren.com/renren_api/session_key',
            'api' => 'https://api.renren.com/restserver.do'
        )
    );
    
    
    function __construct($platform, $options) {
        $this->_platform = $platform;
        $this->_options = $options;

        $conf = static::$platforms[$platform];
        
        $client = new \OAuth2_Client(
                $options['api_key'],
                $options['secret'],
                $options['callback']);
    
        $configuration = new \OAuth2_Service_Configuration(
                $conf['authorize'],
                $conf['access_token']);
        
        $dataStore = new \OAuth2_DataStore_Session();
        
        $this->_service = new \OAuth2_Service($client, $configuration, $dataStore, "publish_feed email");
        
    }
    
    static function useIt($platform, $options)
    {
        return new Base($platform, $options);
    }
    
    function authorizeUrl()
    {
        return $this->_service->authorize();
    }
    
    function callback($code)
    {
        $token = $this->_service->getAccessToken($code);
        $this->access_token = $token->getAccessToken();
    }
    
    function getUserInfo()
    {
        $url = static::$platforms[$this->_platform]['session_key'];
        $sk = static::curlIt($url, array('oauth_token' => $this->access_token));
        
        $session_key = $sk["renren_token"]["session_key"];

        $conf = static::$platforms[$this->_platform];
        
        $renren = new \RenRenClient();
        $renren->setSessionKey($session_key);
        $user = $renren->POST('users.getLoggedInUser');
        $result = $renren->POST('users.getInfo', array($user->uid));
        return array(
            'uid' => $result[0]->uid,
            'nickname' => $result[0]->name
        );
    }

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