<?php
defined('_JEXEC') or die();
 
jimport('joomla.event.plugin');

class plgSystemGoogleAnalytics extends JPlugin
{
	var $DOM = NULL;
	
	function onAfterRender(){
		
		$domainName = JURI::base();
		$script = <<<EOD
var _gaq = _gaq || [];
_gaq.push(['_setAccount', 'UA-27718974-1']);
_gaq.push(['_setDomainName', '{$domainName}']);
_gaq.push(['_trackPageview']);

(function() {
var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
})();	
EOD;
		
		
		$dom = $this->getDOM();
		$body = $dom->getElementsByTagName('body')->item(0);
		
		
		$scriptTag = $dom->createElement('script',$script);	
		$scriptTag->setAttribute('type','text/javascript');
		$body->appendChild($scriptTag);
		
		$this->setBody($scriptTag);
		
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