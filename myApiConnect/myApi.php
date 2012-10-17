<?php

// No direct access.
defined('_JEXEC') or die;

jimport('joomla.application.component.modelitem');

/**
 * Ohm model.
 */
class myApiModelmyapi extends JModelItem
{
    public $parsedSignedRequest = NULL;
    public $facebook = NULL;

    private function jsRedirect($location){
        $mainframe = JFactory::getApplication();
        echo '<script type="text/javascript">';
        echo "window.parent.location = '{$location}'";
        echo '</script>';
        $mainframe->close();
    }

    public function returnJson($array){
        $mainframe = JFactory::getApplication();
        header('Content-Type: application/json');
        echo json_encode($array);
        $mainframe->close();
    }
    
    public function getFacebook(){
        if(is_null($this->facebook)){
            $myApi = myApi::getInstance();
            $this->facebook = $myApi->getFacebook();
        }
        
        return $this->facebook;
    }

    public function getUserId($uid, $token = null){
        $db     = $this->getDBO();
        $query  = $db->getQuery(true);
        $query->select('userId as id')->from($db->nameQuote('#__facebook_users'));
        $query->where('uid = '.$db->quote($uid));
        $db->setQuery($query,0,1);
        $user = $db->loadObject();
        
        if(!is_object($user) || !property_exists($user, 'id')){
            $user = $this->newUser($uid, $token);   
        }else if(!is_null($token)){
            $myApi = myApi::getInstance();
            $myApi->setUserAccessToken($user->id,$token); 
        }
        
        return $user->id;
    }

    public function loginWithFb(){
        $mainframe =& JFactory::getApplication();
        $signedRequest = $this->getParsedSignedRequest();
        $user = JFactory::getUser();

        $myApi = myApi::getInstance();
        $facebook = $this->getFacebook();

        if($facebook->getUser()){
            //Being logged into Joomla does not guraentee the FB app is still installed
            try{
                $permsCheck = $facebook->api('/'.$facebook->getUser().'?fields=id');
            }catch(Exception $e){
                $mainframe->logout();
                $user = JFactory::getUser();
            }
        }

        $redirectUrl = JURI::root().'index.php?option=com_clothesshow&view=submit';

        if($user->guest && !is_null(JRequest::getVar('code',NULL))){
            //This is for the come back on a new PHP login which redirects back to the tab
            $token_url = "https://graph.facebook.com/oauth/access_token?"
               . "client_id=" . $facebook->getAppId() . "&client_secret=" . $facebook->getApiSecret() . "&code=" . JRequest::getVar('code') . "&redirect_uri=" . urlencode($redirectUrl);

            $response = file_get_contents($token_url);
            $params = null;
            parse_str($response, $params);

            $api = $facebook->api('/me?fields=id',array('access_token' => $params['access_token']));
            $uid = $api['id'];

            $facebook->setAccessToken($params['access_token']);

            $userId = $this->getUserId($uid, $params['access_token']);
            $options = array('uid' => $uid);
            $error = $mainframe->login($uid,$options);
            $user = JFactory::getUser();

            $myApi = myApi::getInstance();
            $tabUrl = $myApi->getTabAppUrl();
            $mainframe =& JFactory::getApplication();
            $myApiRedirect = array('redirect' => array('option' => 'com_clothesshow', 'view' => 'submit'));
            $redirectToFB = $tabUrl.'&app_data='.serialize($myApiRedirect);

            $mainframe->redirect($redirectToFB);
            $mainframe->close();

        }else if(is_array($signedRequest) && array_key_exists('user_id',$signedRequest) && $user->guest){
            //If we have landed on a page that gives a signed request containing user information

            $mainframe = JFactory::getApplication();
            $options = array('uid' => $signedRequest['user_id']);
            $mainframe->login($signedRequest['user_id'],$options);
        }else if($user->guest && !is_null(JRequest::getVar('access_token',NULL))){
            //If the user is a guest, but we have been passed an access token from a JS login

            $acess_token = JRequest::getVar('access_token',NULL);

            $api = $facebook->api('/me?fields=id',array('access_token' => $acess_token));
            $uid = $api['id'];

            $userId = $this->getUserId($uid, $acess_token);
            $options = array('uid' => $uid);
            $error = $mainframe->login($uid,$options);
        }else if($user->guest && $facebook->getUser()){
            //Otherwise checking the PHP session
            try{
                $permsCheck = $facebook->api('/me?fields=id');
                $uid = $permsCheck['id'];

                $userId = $this->getUserId($uid);
                $options = array('uid' => $uid);
                $error = $mainframe->login($uid,$options);
            }catch(Exception $e){
                error_log($e->getMessage());
            }
        }

        $user = JFactory::getUser();

        if($user->guest || (! count($_COOKIE) > 0 && strpos($_SERVER['HTTP_USER_AGENT'], 'Safari'))){
            //Do a php login - Redirect the user the the Facebook login page, then back here to set a cooke then back to the tab
            $params = array(
              'scope' => 'email,user_photos',
              'redirect_uri' => $redirectUrl
            );

            $loginUrl = $facebook->getLoginUrl($params);
            $this->jsRedirect($loginUrl);
        }

        return (!$user->guest);
    }
    
    protected function newUser($uid,$token = NULL){
        $mainframe =& JFactory::getApplication();

        // Get required system objects
        $user       = clone(JFactory::getUser());
        $config     =& JFactory::getConfig();

        $usersConfig = &JComponentHelper::getParams( 'com_users' );
        // Initialize new usertype setting
        $newUsertype = 'Registered';
        
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $randomPassword = "";    
        for ($p = 0; $p < 8; $p++) {
            $randomPassword .= $characters[mt_rand(0, strlen($characters) - 1)];
        }
        
        $facebook = $this->getFacebook();

        $token = (is_null($token)) ? $facebook->getAccessToken() : $token;
        $me = $facebook->api('/'.$uid.'?fields=name,email');

        $newUser['name']        = $me['name'];
        $newUser['username']    = $uid;
        $newUser['password']    = $newUser['password2'] = $randomPassword;
        $newUser['email']       = $me['email'];
        
        // Set some initial user values
        $user->set('id', 0);
        $date =& JFactory::getDate();
        $user->set('registerDate', $date->toMySQL());
        
        jimport('joomla.application.component.helper');
        $config = JComponentHelper::getParams('com_users');
        // Default to Registered.
        $defaultUserGroup = $config->get('new_usertype', 2);    
        $user->set('usertype'       , 'deprecated');
        $user->set('groups'     , array($defaultUserGroup));
        
        if(! $user->bind( $newUser, 'usertype' )){
            error_log('FB user bind error '.$user->getError()); 
            return false;
        }
        if(! $user->save()){
            error_log('FB user save error '.$user->getError()); 
            return false;   
        }
        
        $db = JFactory::getDBO();
        
        $query = "INSERT INTO ".$db->nameQuote('#__facebook_users')." (userId,uid,name,access_token) VALUES(".$db->quote($user->id).",".$db->quote($uid).",".$db->quote($newUser['name']).",".$db->quote($token).")";
        $db->setQuery($query);
        if(!$db->query()){
            error_log($db->getErrorMsg());
            return $db->getErrorMsg();  
        }
        
        return $user;
    }

    public function getParsedSignedRequest(){
        if(is_null($this->parsedSignedRequest) && !is_null(JRequest::getVar('signed_request',NULL))){
            $this->parsedSignedRequest = $this->parse_signed_request(JRequest::getVar('signed_request'));
        }   
        return $this->parsedSignedRequest; 
    }

    //Parse the signed request if found to find the correct url to show
    protected function parse_signed_request($signed_request) {
        list($encoded_sig, $payload) = explode('.', $signed_request, 2); 
        
        // decode the data
        $sig = $this->base64_url_decode($encoded_sig);
        $data = json_decode($this->base64_url_decode($payload), true);
        
        if (strtoupper($data['algorithm']) !== 'HMAC-SHA256') {
            error_log('Unknown algorithm. Expected HMAC-SHA256');
            return null;
        }
        
        $facebook = $this->getFacebook();
        // check sig
        $expected_sig = hash_hmac('sha256', $payload, $facebook->getApiSecret(), $raw = true);
        if ($sig !== $expected_sig) {
            error_log('Bad Signed JSON signature! when decoding signed request');
            return NULL;
        }
        
        return $data;
    }

    public function base64_url_decode($input) {
      return base64_decode(strtr($input, '-_', '+/'));
    }
}
