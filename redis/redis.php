<?php defined( '_JEXEC' ) or die( 'Restricted access' );
jimport( 'joomla.plugin.plugin');

class redis extends JPlugin{
	
	private static $instance;
	private static $redis;
	private static $rediaParams;
	
	public function __construct(& $subject, $config) {
 		parent::__construct($subject, $config);
		$this->loadLanguage();
	}
	
	public static function getInstance() { 
	    if(!self::$instance) { 
	      self::$instance = new self(); 
	    } 

	    return self::$instance;
	}
	
	public function getParams(){
		if(!self::$rediaParams){
			$plugin = JPluginHelper::getPlugin('system', 'redis');
			self::$rediaParams = new JRegistry();
			self::$rediaParams->loadJSON($plugin->params);
		}
		
		return self::$rediaParams;	
	}
	

	public function getRedis(){
		if(!self::$redis){
			Predis\Autoloader::register();
			$single_server = array(
				'host'     => '127.0.0.1',
				'port'     => 6379
			);
			self::$redis = new Predis\Client($single_server);
			self::$redis->config('set','timeout',0);
		}
		
		return self::$redis;
	}
	

}


class plgSystemRedis extends redis
{
	public function __construct(& $subject, $config) {
 		parent::__construct($subject, $config);
 		$this->loadLanguage();
	}
}

?>