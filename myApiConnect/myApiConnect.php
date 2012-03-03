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
	
	public function __construct(& $subject, $config) {
 		parent::__construct($subject, $config);
		$this->loadLanguage();
		self::$instance = $this;
	}
	
	public static function getInstance() { 
	    return self::$instance; 
	}
	
	public function getParams(){
		if(!self::$myApiParams){
			$plugin = JPluginHelper::getPlugin('system', 'myApiConnect');
			self::$myApiParams = new JRegistry();
			self::$myApiParams->loadJSON($plugin->params);
		}
		
		return self::$myApiParams;	
	}
	
	public function getFbAppId(){
		$params = self::getParams();
		return $params->get('appId');	
	}
	
	public function getFbSecret(){
		$params = self::getParams();
		return $params->get('secret');	
	}
	
	public function getFacebook(){
		if(!self::$facebook){		
			require_once JPATH_SITE.DS.'plugins'.DS.'system'.DS.'myApiConnect'.DS.'facebook'.DS.'myApiConnectFacebook.php';
			$params = plgSystemmyApiConnect::getParams();
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
			$params = self::getParams();
		
		  	require_once JPATH_SITE.DS.'plugins'.DS.'system'.DS.'myApiConnect'.DS.'twitter'.DS.'EpiTwitter.php';
			self::$twitter = new EpiTwitter(	$consumer_key = $params->get('consumerKey'), 
										  $consumer_secret = $params->get('consumerSecret'), 
										  $oauthToken = $params->get('oauthToken'), 
										  $oauthTokenSecret = $params->get('oauthSecret')
									  );
		
			//self::$twitter->appUserName = $params->get('twitterUsername');
		}
		
		return self::$twitter;
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
	
	function onAfterRender(){
		$mainframe = JFactory::getApplication();
		$document = JFactory::getDocument(); 
		
		$myApi = myApi::getInstance();
		
		if ($mainframe->getName() != 'site' || $document->getType() != 'html' || $myApi->getFbAppId() == '') 
			return;
		
		
		$appId = $myApi->getFbAppId();
		
		$u 		= JURI::getInstance( JURI::root() );
		$port 	= ($u->getPort() == '') ? '' : ":".$u->getPort();
		$xdPath	= $u->getScheme().'://'.$u->getHost().$port.$u->getPath().'plugins/system/myApiConnect/facebook/facebookXD.php';

		$script = <<<EOD
/* <![CDATA[ */	
document.getElementsByTagName("html")[0].style.display="block";	

(function() {
	var e = document.createElement('script'); e.async = true;
	e.src = document.location.protocol + '//connect.facebook.net/en_GB/all.js';
	document.getElementById('fb-root').appendChild(e);
}());

window.fbAsyncInit = function() {
FB._https = (window.location.protocol == "https:");
FB.init({appId: "{$appId}", status: true, cookie: true, xfbml: true, channelUrl: "{$xdPath}", oauth: true, authResponse: true});
if(FB._inCanvas){
	FB.Canvas.setAutoResize();
	FB.Canvas.scrollTo(0,0);
}
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