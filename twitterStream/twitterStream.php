<?php defined( '_JEXEC' ) or die( 'Restricted access' );
jimport( 'joomla.plugin.plugin');


class plgSystemtwitterStream extends JPlugin
{
	public function __construct(& $subject, $config) {
 		parent::__construct($subject, $config);
 		$this->loadLanguage();
	}
	
	function onNewTweet($status){
		error_log('from plugin');
		error_log($status);
		return;
	}
}

?>