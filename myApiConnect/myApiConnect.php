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
	
	
	public function __construct(& $subject, $config) {
 		parent::__construct($subject, $config);
 		$this->loadLanguage();
		self::$instance = $this;
		
		//Custom OG tags for WU
		$ogTags = array();
		$ogTags['fb:app_id']		= $this->getFbAppId();
		$ogTags['og:url']			= 'http://apps.facebook.com/'.$this->getFbNamespace();
		//$ogTags['og:title']			= '';
		//$ogTags['og:description']	= '';
		//$ogTags['og:image']			= '';
		
		$this->setOpenGraphTags($ogTags);
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
	
	public function getFacebook(){
		if(!self::$facebook){		
			require_once JPATH_SITE.DS.'plugins'.DS.'system'.DS.'myApiConnect'.DS.'myApiConnectFacebook.php';
			$appId = self::getFbAppId();
			$secret = self::getFbSecret();
			if( $appId == '' || $secret == ''){
				if( !is_object(JError::getError()) || (is_object(JError::getError()) && JError::getError()->getCode() != '100') ){
					JError::raiseWarning( 100, 'myApi requires a Facebook Application ID',array('myApi' =>'100'));
					return  false;
				}
			}
			self::$facebook =  new myApiFacebook(array(   
				'appId'  => $appId,
				'secret' => $secret,
				'cookie' => true, // enable optional cookie support
			));
		}
		
		return self::$facebook;
	}
	
	public function getTwitter(){
		if(!self::$twitter){
			jimport( 'joomla.application.component.helper' );
		
		  	require_once JPATH_SITE.DS.'includes'.DS.'twitter'.DS.'EpiTwitter.php';
			self::$twitter = new EpiTwitter(	$consumer_key = $this->params->get('consumerKey'), 
										  $consumer_secret = $this->params->get('consumerSecret'), 
										  $oauthToken = $this->params->get('oauthToken'), 
										  $oauthTokenSecret = $this->params->get('oauthSecret')
									  );
		
			self::$twitter->appUserName = $this->params->get('twitterUsername');
		}
		
		return self::$twitter;
	}
	
	public function getOpenGrpahTags(){
		return 	self::$ogTags;
	}
	
	public function setOpenGraphTags($tags){
		self::$ogTags = array_merge(self::$ogTags,$tags);
	}
}


class plgSystemmyApiConnect extends myApi
{
	var $DOM = NULL;
	
	public function __construct(& $subject, $config) {
 		parent::__construct($subject, $config);
 		$this->loadLanguage();
	}
	
	function onAfterDispatch(){
		$mainframe = JFactory::getApplication();
		$document = JFactory::getDocument(); 
		if($document->getType() != 'html')
			return;
	}
	
	function onBeforeRender(){
		$myApi = myApi::getInstance();
		$tags = $myApi->getOpenGrpahTags();
		
		$mainframe = JFactory::getApplication();
		$document = JFactory::getDocument(); 
		if($document->getType() != 'html' || $mainframe->isAdmin()) return;
		
		foreach($tags as $key => $value) $document->addCustomTag('<meta property="'.$key.'" content="'.htmlspecialchars($value).'" />');
		
	}
	
	function onAfterRender(){
		$mainframe = JFactory::getApplication();
		$document = JFactory::getDocument(); 
		
		$myApi = myApi::getInstance();
		
		if ($mainframe->getName() != 'site' || $document->getType() != 'html' || $myApi->getFbAppId() == '') 
			return;
		
		
		$appId = $myApi->getFbAppId();
		
		$u 		= JURI::getInstance( JURI::root() );
		$port 	= ($u->getPort() == '') ? '' : ":".$u->getPort();
		$xdPath	= $u->getScheme().'://'.$u->getHost().$port.$u->getPath().'plugins/system/myApiConnect/facebookXD.php';
		
		$lang =& JFactory::getLanguage();
		$langCode = str_replace('-','_',$lang->getTag());
		
		$script = <<<EOD
/* <![CDATA[ */	
document.getElementsByTagName("html")[0].style.display="block";	

(function() {
	var e = document.createElement('script'); e.async = true;
	e.src = document.location.protocol + '//connect.facebook.net/{$langCode}/all.js';
	document.getElementById('fb-root').appendChild(e);
}());

window.fbAsyncInit = function() {
FB._https = (window.location.protocol == "https:");
FB.init({appId: "{$appId}", status: true, cookie: true, xfbml: true, channelUrl: "{$xdPath}", oauth: true, authResponse: true});
if(FB._inCanvas){
	FB.Canvas.setSize(760);
	FB.Canvas.setAutoResize(500);
	FB.Canvas.scrollTo(0,0);
}

$(document).fireEvent('fbAsyncInit');

};
/* ]]> */
EOD;
	
		$dom = $this->getDOM();
		$body = $dom->getElementsByTagName('body')->item(0);
		
		$fbroot = $dom->createElement('div');	
		$fbroot->setAttribute('id','fb-root');
		$body->appendChild($fbroot);
		
		$fbScript = $dom->createElement('script',$script);
		$fbScript->setAttribute('type','text/javascript');
		$body->appendChild($fbScript);
		
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