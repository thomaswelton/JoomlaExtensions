<?php defined( '_JEXEC' ) or die( 'Restricted access' );
/*****************************************************************************
 **                                                                         ** 
 **                                         .o.                   o8o  	    **
 **                                        .888.                  `"'  	    **
 **     ooo. .oo.  .oo.   oooo    ooo     .8"888.     oo.ooooo.  oooo  	    **
 **     `888P"Y88bP"Y88b   `88.  .8'     .8' `888.     888' `88b `888  	    **
 **      888   888   888    `88..8'     .88ooo8888.    888   888  888  	    **
 **      888   888   888     `888'     .8'     `888.   888   888  888  	    **
 **     o888o o888o o888o     .8'     o88o     o8888o  888bod8P' o888o      **
 **                       .o..P'                       888             	    **
 **                       `Y8P'                       o888o            	    **
 **                                                                         **
 **                                                                         **
 **   Joomla! 1.5 Plugin myApiConnect                                       **
 **   @Copyright Copyright (C) 2011 - Thomas Welton                         **
 **   @license GNU/GPL http://www.gnu.org/copyleft/gpl.html                 **	
 **                                                                         **	
 **   myApiConnect is free software: you can redistribute it and/or modify  **
 **   it under the terms of the GNU General Public License as published by  **
 **   the Free Software Foundation, either version 3 of the License, or	    **	
 **   (at your option) any later version.                                   **
 **                                                                         **
 **   myApiConnect is distributed in the hope that it will be useful,	    **
 **   but WITHOUT ANY WARRANTY; without even the implied warranty of	    **
 **   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         **
 **   GNU General Public License for more details.                          **
 **                                                                         **
 **   You should have received a copy of the GNU General Public License	    **
 **   along with myApiConnect.  If not, see <http://www.gnu.org/licenses/>  **
 **                                                                         **			
 *****************************************************************************/
jimport( 'joomla.plugin.plugin');

class myApi extends JPlugin{
	
	private static $instance;
	private static $facebook;
	private static $myApiParams;
	private static $twitter;
	private static $ogTags = array();
	public 	$parsedSignedRequest = null;
	public 	$appData = null;
	
	public function __construct(& $subject, $config) {
 		parent::__construct($subject, $config);
 		$this->loadLanguage();
		self::$instance = $this;
	}
	
	public static function getInstance() { 
	    return self::$instance; 
	}
	
	public function getFbAppId(){
		return $this->params->get('appId');	
	}
	
	public function getFbSecret(){
		return $this->params->get('secret');	
	}
	
	public function getFbNamespace(){
		return $this->params->get('namespace');		
	}

	public function getModel(){
		JLoader::import('joomla.application.component.model');
		JLoader::import( 'myApi', JPATH_SITE . DS . 'plugins' . DS . 'system' . DS . 'myApiConnect');
	
		return JModel::getInstance('myApi', 'myApiModel');
	}
	
	public function getFacebook(){
		if(!self::$facebook){		
			require_once JPATH_SITE.DS.'plugins'.DS.'system'.DS.'myApiConnect'.DS.'myApiConnectFacebook.php';
			$appId = (string) self::getFbAppId();
			$secret = (string) self::getFbSecret();
			if( $appId !== '' && $secret !== ''){
				self::$facebook =  new myApiFacebook(array(   
					'appId'  => $appId,
					'secret' => $secret,
					'cookie' => true, // enable optional cookie support
				));
			}else{
				self::$facebook = false;	
			}
		}
		
		return self::$facebook;
	}

	public function getTabAppUrl(){
		$pageId = $this->params->get('pageId');
		$appId = self::getFbAppId();

		$tabUrl = "http://www.facebook.com/pages/null/{$pageId}?sk=app_{$appId}";
		return $tabUrl;
	}

	public function getCanvasUrl(){
		return 'http://apps.facebook.com/'.self::getFbNamespace().'/';	
	}

	
	public function getOpenGrpahTags(){
		return 	self::$ogTags;
	}
	
	public function setOpenGraphTags($tags){
		self::$ogTags = array_merge(self::$ogTags,$tags);
	}

	public function getUserAccessToken($userId){
		$facebook = $this->getFacebook();

		if($facebook->getUser()){
			return $facebook->getAccessToken();
		}else{
			$db = JFactory::getDBO();
			$query = $db->getQuery(true);

			$query->select('access_token')->from('#__facebook_users')->where('userId ='. (int) $userId);
			$db->setQuery($query);

			return $db->loadResult();
		}
	}

	public function setUserAccessToken($userId,$token){
		$db = JFactory::getDBO();
		$query = $db->getQuery(true);

		$query = "UPDATE ".$db->nameQuote('#__facebook_users')."  SET access_token =  ".$db->quote($token).";";
        $db->setQuery($query);

        return $db->query();
	}

	public function getParsedSignedRequest(){
		$raw = JRequest::getVar('signed_request',null);
		if(is_null($this->parsedSignedRequest) && !is_null($raw)){
			$this->parsedSignedRequest = $this->parse_signed_request($raw);
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
		
		$myApi = myApi::getInstance();
		
		// check sig
		$expected_sig = hash_hmac('sha256', $payload, $myApi->getFbSecret(), $raw = true);
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


class plgSystemmyApiConnect extends myApi
{
	var $DOM = NULL;
	
	public function __construct(& $subject, $config) {
 		parent::__construct($subject, $config);
 		$this->loadLanguage();
	}

	private function jsRedirect($location){
		$mainframe = JFactory::getApplication();
		echo '<script type="text/javascript">';
		echo "window.parent.location = '{$location}'";
		echo '</script>';
		$mainframe->close();
	}

	function onAfterInitialise(){
		$signed_request = $this->getParsedSignedRequest();

		if(is_null(JRequest::getVar('myApiRedirect',null)) && is_array($signed_request) && array_key_exists('app_data', $signed_request)){
			$parsedData = unserialize($signed_request['app_data']);

			if(is_array($parsedData) && array_key_exists('redirect', $parsedData)){
				$data = array_merge($parsedData['redirect'], array('myApiRedirect' => '1'));
				$query = http_build_query($data);
				$mainframe = JFactory::getApplication();

				$mainframe->redirect('index.php?'.$query);
				$mainframe->close();
			}
		}
	}
	
	function onAfterDispatch(){
		$mainframe = JFactory::getApplication();
		$document = JFactory::getDocument(); 
		if($document->getType() != 'html')
			return;

		$myApi = myApi::getInstance();

		if($mainframe->isAdmin()){
			$token = JRequest::getVar('oauth_token',null);
			$user = JFactory::getUser();
			if(!is_null($token) && $user->guest){
				$facebook = $myApi->getFacebook();
				$facebook->setAccessToken($token);
				$uid = $facebook->getUser();
				$mainframe->login($uid);
				$mainframe->redirect(JURI::current());
			}
		}else{
			$signedRequest = $myApi->getParsedSignedRequest();

			//Update the users access token
			$user = JFactory::getUser();
			if(!$user->guest && is_array($signedRequest) && array_key_exists('oauth_token',$signedRequest)){
				$this->setUserAccessToken($user->id, $signedRequest['oauth_token']);
			}

			//The myAPi pageId is the privalged page for this application. Admins of this page are admins of the site.
			$pageId = $this->params->get('pageId');
			if(is_array($signedRequest) && array_key_exists('page', $signedRequest) && array_key_exists('app_data', $signedRequest) && $signedRequest['page']['id'] == $pageId && $signedRequest['page']['admin'] == 1 && $signedRequest['app_data'] == 'adminRedirect'){
				$user = JFactory::getUser();

				if($user->guest){
					//Redirect to login
					$loginRedirect = 'http://facebook.com/pages/null/'.$pageId.'?sk=app_'.$this->params->get('appId').'&app_data=adminRedirect';
					$login = JURI::root().'index.php?option=com_myapi&task=createOrLogin&return='.urlencode(base64_encode($loginRedirect));
					$this->jsRedirect($login);
				}else{
					//Redirect to admin
					$userAccess = $this->params->get('usergroup');
					$userGroups = $user->getAuthorisedGroups();

					$token = $signedRequest['oauth_token'];
					if(in_array($userAccess,$userGroups)){
						//Login and redirect to admin
						$this->jsRedirect(JURI::root().'administrator/?oauth_token='.$token);
					}else{
						//Upgrade user
						$db = JFactory::getDBO();
						$query = "INSERT INTO ".$db->nameQuote('#__user_usergroup_map')." (`user_id`,`group_id`) VALUES (".$db->quote($user->id).",".$db->quote($userAccess).")";
						$db->setQuery($query);

						if($db->query()){
							$mainframe->logout();
							$this->jsRedirect(JURI::root().'administrator/?oauth_token='.$token);
						}
					}
				}
			}
		}
	}
	
	function onBeforeRender(){
		$myApi = myApi::getInstance();
		$tags = $myApi->getOpenGrpahTags();
		
		$mainframe = JFactory::getApplication();
		$document = JFactory::getDocument(); 
		if($document->getType() != 'html' || $mainframe->isAdmin()) return;
		
		foreach($tags as $key => $value) $document->addCustomTag('<meta property="'.$key.'" content="'.htmlspecialchars($value).'" />');
		
		$document->addStylesheet(JURI::root().'plugins'.DS.'system'.DS.'myApiConnect'.DS.'myApiConnect'.DS.'myApi.css');
		//$document->addScript(JURI::root().'plugins'.DS.'system'.DS.'myApiConnect'.DS.'myApiConnect'.DS.'myApiModal.js');
		$document->addScript(JURI::root().'plugins'.DS.'system'.DS.'myApiConnect'.DS.'myApiConnect'.DS.'myApi.js');

		$appId = $myApi->getFbAppId();
		
		$u 		= JURI::getInstance( JURI::root() );
		$port 	= ($u->getPort() == '') ? '' : ":".$u->getPort();
		$xdPath	= $u->getScheme().'://'.$u->getHost().$port.$u->getPath().'plugins/system/myApiConnect/facebookXD.php';
		
		$lang =& JFactory::getLanguage();
		$langCode = str_replace('-','_',$lang->getTag());
		
		$myApiJsOptions = json_encode(array('langCode' => $langCode,'channelUrl' => $xdPath));

		//Kick off the myApi JS, this includes FB onto your page
		$script = "myApi = new MyApi('{$appId}',{$myApiJsOptions});";
		$document->addScriptDeclaration($script);

	}
	
	function onAfterRender(){
		$mainframe = JFactory::getApplication();
		$document = JFactory::getDocument(); 
		
		$myApi = myApi::getInstance();
		
		if ($mainframe->getName() != 'site' || $document->getType() != 'html' || $myApi->getFbAppId() == '') 
			return;
	
		$dom = $this->getDOM();
		$body = $dom->getElementsByTagName('body')->item(0);
		
		//Add FB prefix to the head and html tag
		$head = $dom->getElementsByTagName('head')->item(0);
		$head->setAttribute('prefix','og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# website: http://ogp.me/ns/website#');
		
		$htmlTag = $dom->getElementsByTagName('html')->item(0);
		$htmlTag->setAttribute('xmlns:og','http://ogp.me/ns#');
		$htmlTag->setAttribute('xmlns:fb','https://www.facebook.com/2008/fbml');
		
		$this->setBody();	
	}
	
	public function getDOM(){
		if(is_null($this->DOM)){
			$html = JResponse::getBody();
			$this->DOM = new DOMDocument();
			$this->DOM->formatOutput = true;
			$this->DOM->loadHTML($html);
		}
		return $this->DOM;
	}
	
	public function setBody(){
		$dom = $this->getDOM();
		JResponse::setBody($dom->saveHTML());
	}
}

?>