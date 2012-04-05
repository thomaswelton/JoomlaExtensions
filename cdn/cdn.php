<?php
// no direct access
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

class plgSystemCdn extends JPlugin
{
	var $DOM 		= null;
	var $cloudAuth	= null;
	var $cloudConn 	= null;
	var $container	= null;
	var $cache		= null;
	var $mainifestFiles	= array();
	
	
	function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
	}
	
	function onAfterInitialise()
	{
		
	}

	function onAfterRender()
	{
		$mainframe 	= JFactory::getApplication();
		$document 	= JFactory::getDocument();
        if ($mainframe->getName() != 'site' || $document->getType() != 'html' || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) {
            return true;
        }
		
		set_include_path(get_include_path() . PATH_SEPARATOR . JPATH_SITE.DS.'plugins'.DS.'system'.DS.'cdn'.DS.'cloudfiles');
		require('cloudfiles.php');
		
		$this->uploadDirectories();
		$this->parseImages();
		
		$this->compressStyles();
		$this->parseCSS();
		
		$this->compressScripts();
		$this->parseScripts();
		
		//$this->addManifest();
		
		$this->setBody();
	}
	
	private function log($msg){
		error_log($msg);
	}
	
	public function getCache(){
		if(is_null($this->cache)){
			$this->cache = JFactory::getCache('plg_cdn_'.$this->params->get('container','-').'_urls', '');
			$this->cache->setCaching(1);
			$this->cache->setLifeTime(0);
		}
		return $this->cache;
	}
	
	public function setRelPrefetch($el){
		$relValue = $el->getAttribute('rel');
		$relArray = array_merge(explode(' ',$relValue),array('dns-prefetch'));
		
		$el->setAttribute('rel',implode(' ',$relArray));
		return $el;
	}
	
	public function addManifest(){
		$dom = $this->getDOM();
		
		$htmlTag = $dom->getElementsByTagName('html')->item(0);
		$htmlTag->setAttribute('manifest',JURI::root().'/joomla.appcache');
		
		$manifest = "CACHE MANIFEST\n";
		$manifest .= "# ".time()."\n\n";
		$manifest .= "# Explicitly cached 'master entries'.\nCACHE:\n";
		
		$mainifestFiles = $this->mainifestFiles;
		
		foreach($mainifestFiles as $path){
			$manifest .= $path."\n";
		}
		$manifest .= "\n\nNETWORK:\n*";
		
		
		file_put_contents(JPATH_SITE.DS.'joomla.appcache',$manifest);
		
	}
	
	public function compressStyles(){
		$dom = $this->getDOM();
		$linkTags = $dom->getElementsByTagName('link');
		foreach($linkTags as $link){
			if($link->getAttribute('type') == 'text/css'){
				$cssFile = $link->getAttribute('href');
				
				if(JURI::isInternal($cssFile)){
					$u =& JURI::getInstance( $cssFile );
					$cssPath = $u->getPath();
					
					$path_parts = pathinfo($cssPath);
					$styleHash =  hash_file('md5',JPATH_SITE.DS.$cssPath); 
					$compressedPath = DS.'compressed'.$path_parts['dirname'].DS.$path_parts['filename'].'.compressed-'.$styleHash.'.'.$path_parts['extension'];
					
					if(!file_exists(JPATH_SITE.$compressedPath)){
						//Create compressed files
						require_once JPATH_SITE.DS.'plugins'.DS.'system'.DS.'cdn'.DS.'CSS.php';
						$cssData = file_get_contents(JPATH_SITE.DS.$cssPath);
						
						$minified = Minify_CSS::minify($cssData);
						
						$pathParts = explode(DS,$compressedPath);
						
						$compressedFolder = implode(DS,array_slice($pathParts,0,sizeof($pathParts) - 1));
						$compressedFolder = ($compressedFolder[0] === '/' ) ? substr($compressedFolder,1) : $compressedFolder;
						
						if(!JFolder::exists($compressedFolder)){
							mkdir($compressedFolder,0777,true);
						}
						
						file_put_contents(JPATH_SITE.$compressedPath,$minified);						
					}
					
					$link->setAttribute('href',$compressedPath);
					
				}
			}
		}
	}
	
	public function compressScripts(){
		$dom = $this->getDOM();
		$scriptTags = $dom->getElementsByTagName('script');
		foreach($scriptTags as $script){
			if($script->getAttribute('src') !== ''){
				$scriptFile = $script->getAttribute('src');
				if(JURI::isInternal($scriptFile)){
					$u =& JURI::getInstance( $scriptFile );
					$scriptPath = $u->getPath();
					
					$path_parts = pathinfo($scriptPath);
					$scriptHash =  hash_file('md5',JPATH_SITE.DS.$scriptPath); 
					$compressedPath = DS.'compressed'.$path_parts['dirname'].DS.$path_parts['filename'].'.compressed-'.$scriptHash.'.'.$path_parts['extension'];
					
					if(!file_exists(JPATH_SITE.$compressedPath)){
						//Create compressed files
						require_once JPATH_SITE.DS.'plugins'.DS.'system'.DS.'cdn'.DS.'jsmin.php';
						$scriptData = file_get_contents(JPATH_SITE.DS.$scriptPath);
						
						$minified = JSMin::minify($scriptData);
						
						$pathParts = explode(DS,$compressedPath);
						
						$compressedFolder = implode(DS,array_slice($pathParts,0,sizeof($pathParts) - 1));
						$compressedFolder = ($compressedFolder[0] === '/' ) ? substr($compressedFolder,1) : $compressedFolder;
						
						if(!JFolder::exists($compressedFolder)){
							mkdir($compressedFolder,0777,true);
						}
						
						file_put_contents(JPATH_SITE.$compressedPath,$minified);						
					}
					
					$script->setAttribute('src',$compressedPath);
					
				}
			}
		}
	}
	
	public function uploadDirectories(){
		$app = JFactory::getApplication();
		$templateDir = JPATH_SITE.DS.'templates'.DS.$app->getTemplate();
		
		$dirs = $this->params->get('assetsDirectories');
		
		foreach($dirs as $dir){
			$assets = JFolder::files($templateDir.DS.$dir, '', false);
		
			foreach($assets as $asset){	
				$u =& JURI::getInstance( '/templates/'.$app->getTemplate().'/'.$dir.'/'.$asset);
				$assetPath = $u->getPath();
				$cdnUrl = $this->getObjectUrl($assetPath);
			}
		}	
	}
	
	public function parseScripts(){
		$dom = $this->getDOM();
		$scriptTags = $dom->getElementsByTagName('script');
		
		$scriptFiles = array();
		$scriptDeclarations = array();
		$removeList = array(); 
		
		foreach($scriptTags as $script){
			$removeList[] = $script; 
			
			if($script->getAttribute('src') !== ''){
				$scriptFile = $script->getAttribute('src');
				
				if(JURI::isInternal($scriptFile)){
					$u =& JURI::getInstance( $scriptFile );
					
					if(sizeof($u->getQuery(true)) == 0){
						$scriptFiles[] = $script;
					}else{
						$this->log('Dynamic file not stored to CDN '.$u->getPath().'?'.$u->getQuery());
					}
				}else{
					$script = $this->setRelPrefetch($script);
				}
			}else{
				$scriptDeclarations[] = $script;	
			}
		}
		
		$body = $dom->getElementsByTagName('body')->item(0);
		
		
		//Combine all javascript files
		$combinedScritpPaths = array();
		$combinedScriptsName = '';
		foreach($scriptFiles as $script){
			$scriptFile = $script->getAttribute('src');
			$u =& JURI::getInstance( $scriptFile );
			$scriptPath = $u->getPath();
			$combinedScritpPaths[] = $scriptPath;
			$combinedScriptsName .= hash_file('md5',JPATH_SITE.DS.$scriptPath);
		}
		
		//Remove all the element that are now combined
		foreach( $removeList as $domElement ){ 
		  $domElement->parentNode->removeChild($domElement); 
		}
		
		$cache = $this->getCache();
		$combinedUrl = $cache->get($combinedScriptsName);
		if($combinedUrl === false){
			$combinedScripts = '';
			foreach($combinedScritpPaths as $index => $scriptPath){
				$combinedScripts .= file_get_contents(JPATH_SITE.DS.$scriptPath);
			}
			
			if(!JFolder::exists('compressed')){
				mkdir('compressed',0777,true);
			}

			$combinedPath = DS.'compressed'.DS.$combinedScriptsName.'.js';
			$combinedScripts .= "\ncdnInit();";

			file_put_contents(JPATH_SITE.DS.$combinedPath,$combinedScripts);

			$cdnUrl = $this->getObjectUrl($combinedPath,true);
			if($cdnUrl){
				$combinedUrl = $cdnUrl;
				$cache->store($cdnUrl, $combinedScriptsName);
				
			}else{
				error_log('Fatal error storing combined JS to the cloud');
			}
		}
		
		$combinedScript = '';
		foreach($scriptDeclarations as $el){
			$combinedScript .= $el->nodeValue;
		}
		array_push($this->mainifestFiles,$combinedUrl);
		$initScriptUrl = $combinedUrl;
		$initScript = $combinedScript;
		ob_start();
			include(JPATH_SITE.DS.'plugins'.DS.'system'.DS.'cdn'.DS.'initjs.php');
		$wrappedScript = ob_get_contents();
		ob_end_clean();
		
		require_once JPATH_SITE.DS.'plugins'.DS.'system'.DS.'cdn'.DS.'jsmin.php';
		
		$minifiedWrappedScript = JSMin::minify($wrappedScript);
		
		$scriptEl = $dom->createElement('script',$minifiedWrappedScript);
		$scriptEl->setAttribute('type','text/javascript');
		$body->appendChild($scriptEl);
	
	}
	
	public function parseCSS(){
		$dom = $this->getDOM();
		$linkTags = $dom->getElementsByTagName('link');
		$cssFiles = array();
		
		foreach($linkTags as $link){
			if($link->getAttribute('type') == 'text/css'){
				$cssFile = $link->getAttribute('href');
				try{
					if(JURI::isInternal($cssFile)){
						$u =& JURI::getInstance( $cssFile );
						if(sizeof($u->getQuery(true)) == 0){
							$cssFiles[] = $link;
						}else{
							$this->log('Dynamic file not stored to CDN '.$u->getPath().'?'.$u->getQuery());
						}
					}else{
						$link = $this->setRelPrefetch($link);
					}
				}catch(Exception $e){
					error_log('fail '.$cssFile.' - '.$e->getMessage());
				}
			}
			
		}
		
		//Combine all javascript files
		$combinedCssPaths = array();
		$combinedCssName = '';
		foreach($cssFiles as $link){
			$cssFile = $link->getAttribute('href');
			$u =& JURI::getInstance( $cssFile );
			$cssPath = $u->getPath();
			$combinedCssPaths[] = $cssPath;
			$combinedCssName .= hash_file('md5',JPATH_SITE.DS.$cssPath);
		}
		
		$cache = $this->getCache();
		$combinedUrl = $cache->get($combinedCssName);
		if($combinedUrl === false){
			$combinedCss = '';
			foreach($combinedCssPaths as $cssPath){
				$combinedCss .= file_get_contents(JPATH_SITE.DS.$cssPath);
			}

			if(!JFolder::exists('compressed')){
				mkdir('compressed',0777,true);
			}

			$combinedPath = DS.'compressed'.DS.$combinedCssName.'.css';

			file_put_contents(JPATH_SITE.DS.$combinedPath,$combinedCss);

			$cdnUrl = $this->getObjectUrl($combinedPath,true);
			if($cdnUrl){
				$combinedUrl = $cdnUrl;
				$cache->store($cdnUrl, $combinedCssName);
			}else{
				error_log('Fatal error storing combined CSS to the cloud');
			}
		}
		
		//Recheck as CDn store may have failed
		if($combinedUrl !== false){
			foreach($cssFiles as $link){
				$link->parentNode->removeChild($link);
			}
			
			$head = $dom->getElementsByTagName('head')->item(0);
			
			$linkEl = $dom->createElement('link');
			$linkEl->setAttribute('href',$combinedUrl);
			$linkEl->setAttribute('type','text/css');
			$linkEl->setAttribute('rel','stylesheet dns-prefetch');
			$head->appendChild($linkEl);
			
			array_push($this->mainifestFiles,$combinedUrl);
		}	
	}
	
	public function parseImages(){
		$dom = $this->getDOM();
		$images = $dom->getElementsByTagName('img');
		
		foreach($images as $image){
			$imgSrc = $image->getAttribute('src');
			if(JURI::isInternal($imgSrc)){
				$u =& JURI::getInstance( $imgSrc );
				$imagePath = $u->getPath();

				$cdnUrl = $this->getObjectUrl($imagePath,true);
				if($cdnUrl){
					$image->setAttribute('src',$cdnUrl);
					$image = $this->setRelPrefetch($image);
					array_push($this->mainifestFiles,$cdnUrl);
				}else{
					array_push($this->mainifestFiles,$imgSrc);
				}
			}else{
				$image = $this->setRelPrefetch($image);
				array_push($this->mainifestFiles,$imgSrc);
			}
		}
	}
	
	public function getObjectUrl($objectPath,$lastModified = ''){
		$objectPath = (substr($objectPath,0,1) == '/') ? substr($objectPath,1) : $objectPath;
		$headers = array();
		
		$lastModified = (filemtime(JPATH_SITE.DS.$objectPath)) ? filemtime(JPATH_SITE.DS.$objectPath) : '';
		$offset = 3600 * 24 * 365; 
		$headers['Cache-Control'] = "max-age={$offset}, must-revalidate";
		$headers['Expires'] = gmdate( 'D, d M Y H:i:s', time() + $offset ) . ' GMT';
		
		
		$objectName = ($objectPath[0] == "/") ? substr($objectPath,1) : $objectPath;
		$nameParts = explode('.',$objectName);
		
		//This might have to be optional, but the images need to be in the compressed dir if the css is being compressed
		$objectName = $nameParts[0];
		
		if($lastModified) $objectName .= '-'.$lastModified;
		$objectName .= '.'.$nameParts[1];
		
		$cache = $this->getCache();
		
		$url = $cache->get($objectName);
	//	$url = false;
		if($url === false){
			$object =& $this->createObject($objectName);
			$object->metadata = $headers;
			
			if($object){
				try{
					$object->load_from_filename(JPATH_SITE.DS.$objectPath);
					$url = $object->public_uri();
					$cache->store($url, $objectName);
					
				}catch(Exception $e){
					error_log('CDN Error writing file to cloud - '.$e->getMessage());
				}
			}
		}
		
		return $url;
	}
	
	public function createObject($objectName){
		$container = $this->getContainer();
		$object = false;
		try{
			$object = $container->create_object($objectName);
		}catch(Exception $e){
			error_log('CDN Error creating object - '.$e->getMessage());
		}
		return $object;
	}
	
	public function getContainer(){
		if(is_null($this->container)){
			$cloudConn = $this->getCloudConnection();
			if($cloudConn){
				$containerName = $this->params->get('container');
				if(trim($containerName) != ''){
					try{
						$this->container = $cloudConn->get_container($containerName);
						$this->container->make_public(295200);
					}catch(NoSuchContainerException $e){
						$newContainer = $this->createContainer($containerName);
						if($newContainer){
							$this->container = $newContainer;
							$this->container->make_public(295200);
						}
					}
				}else{
					error_log('CDN Get Container error - Please specify a container name in the CDN plugin parameters');
				}
			}
		}
		
		return $this->container;
	}
	
	public function createContainer($containerName){
		$cloudConn = $this->getCloudConnection();
		if($cloudConn){
			try{
				$newContainer = $cloudConn->create_container($containerName);
			}catch(SyntaxException $e){
				error_log('CDN Create Container Error - '.$e->getMessage());
				return false;
			}
			return $newContainer;
		}else{
			return false;
		}
	}
	
	public function getContainers(){
		$cloudConn = $this->getCloudConnection();
		if($cloudConn){
			try{
				$containers = $cloudConn->list_containers();
			}catch(InvalidResponseException $e){
				error_log('CDN Response error trying to get containers - '.$e->getMessage());
				return false;
			}
		}else{
			return false;
		}
		return $containers;
	}
	
	public function getCloudConnection(){
		if(is_null($this->cloudConn)){
			$auth = $this->getCloudAuth();
			if($auth){
				$this->cloudConn = new CF_Connection($auth);
			}
		}
		//If the authenticaion details are incorrect the could connection may still be null. Returns false, or a valid connection
		return (is_null($this->cloudConn)) ? false : $this->cloudConn;	
	}
	
	public function getCloudAuth(){
		if(is_null($this->cloudAuth)){
			$username = $this->params->get('username');
			$key = $this->params->get('apikey');
			
			if(trim($username) == '' || trim($key) == ''){
				error_log('CDN Authentication error - Enter your username and API key into the CDN plugin parameters');
				return false;	
			}
			
			$this->cloudAuth = new CF_Authentication($username, $key, NULL, UK_AUTHURL);
			
			try{
				$this->cloudAuth->authenticate();
			}catch(AuthenticationException $e){
				error_log('CDN Authentication error - '.$e->getMessage());
				return false;
			}
		}
		return $this->cloudAuth;
	}
	
	public function getDOM(){
		if(is_null($this->DOM)){
			$html = JResponse::getBody();
			$this->DOM = new DOMDocument();
			$this->DOM->loadHTML($html);
		}
		return $this->DOM;
	}
	
	public function setBody(){
		$dom = $this->getDOM();
		
		$head = $dom->getElementsByTagName('head')->item(0);
		$metaEl = $dom->createElement('meta');
		$metaEl->setAttribute('http-equiv',"Content-Type");
		$metaEl->setAttribute('content',"text/html; charset=utf-8");
		$metaEl->insertBefore($head->firstChild);
		
		
		require_once JPATH_SITE.DS.'plugins'.DS.'system'.DS.'cdn'.DS.'HTML.php';
		$minyHTML = Minify_HTML::minify($dom->saveHTML(),array('jsMinifier','cssMinifier','xhtml'));
		
		JResponse::setBody($minyHTML);
	}
}
