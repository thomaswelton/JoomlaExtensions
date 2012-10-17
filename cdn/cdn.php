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
	
	
	function __construct(& $subject, $config){
		parent::__construct($subject, $config);
	}

	function onAfterRender(){
		$p = JProfiler::getInstance('Application');

		$p->mark('CDN System Plugin Fired');
		
		
		$mainframe 	= JFactory::getApplication();
		$document 	= JFactory::getDocument();
        if ($mainframe->getName() != 'site' || $document->getType() != 'html') {
            return true;
        }
		
		set_include_path(get_include_path() . PATH_SEPARATOR . JPATH_SITE.DS.'plugins'.DS.'system'.DS.'cdn'.DS.'cloudfiles');
		require('cloudfiles.php');
		
		if($this->params->get('cdnImages')){
			$this->parseImages();
			$p->mark('parse images done');
		}
		
		if($this->params->get('combinedStyles')){
			$this->combinedStyles();
			$p->mark('combine styles done');
		}
		if($this->params->get('compressStyles')){
			$this->compressStyles();
			$p->mark('compress styles done');
		}
		if($this->params->get('cdnCss')){
			$this->uploadDirectories();
			$p->mark('cdn directories done');
			$this->cdnCss();
			$p->mark('cdn css done');
		}
		if($this->params->get('compressScripts')){
			$this->compressScripts();
			$p->mark('compress scripts done');
		}
		if($this->params->get('combineScripts')){
			$this->combineScripts();
			$p->mark('combine scripts done');
		}
		if($this->params->get('cdnScripts')){
			$this->cdnScripts();
			$p->mark('cdn scripts done');
		}
		
		if($this->params->get('deferScripts') && !(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')){
			$this->deferScripts();
			$p->mark('defer scripts done');
		}
		
		$this->setBody();
		$p->mark('CDN plugin done');
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

	public function combineFiles($filesArray){
		//Get a unique ID for this combination fo files wither their current modification dates
		$fileID = '';
		foreach($filesArray as $filePath){
			$fileID .= hash_file('md5',JPATH_SITE.DS.$filePath);
		}

		$cache = $this->getCache();
		$combinedUrl = $cache->get($fileID);
		if($combinedUrl === false){
			$combinedContent = '';
			foreach($filesArray as $index => $filePath){
				$combinedContent .= ' '.file_get_contents(JPATH_SITE.DS.$filePath);
			}

			$newPath = DS.'plg_system_cdn_generated.'.$fileID.'.'.pathinfo($filesArray[0], PATHINFO_EXTENSION);

			if(JFile::write(JPATH_SITE.$newPath,$combinedContent, true)){
				$combinedUrl = $newPath;
				$cache->store($newPath, $fileID);
			}
		}
		return $combinedUrl;
	}
	
	public function setRelPrefetch($el){
		$relValue = $el->getAttribute('rel');
		$relArray = array_merge(explode(' ',$relValue),array('dns-prefetch'));
		
		$el->setAttribute('rel',implode(' ',$relArray));
		return $el;
	}
	
	public function uploadDirectories(){
		$app = JFactory::getApplication();
		$templateDir = JPATH_SITE.DS.'templates'.DS.$app->getTemplate();
		
		$dirs = $this->params->get('assetsDirectories');
		
		foreach($dirs as $dir){
			$assets = JFolder::files($templateDir.DS.$dir, '', true,true);
			$stripPath = $templateDir.DS.$dir;
			foreach($assets as $asset){	
				$asset = substr($asset,strlen($stripPath));
				$u =& JURI::getInstance( JURI::root().'templates/'.$app->getTemplate().'/'.$dir.$asset);
				
				$siteRoot = JURI::getInstance(JURI::root());
				$assetPath = substr($u->getPath(),strlen($siteRoot->getPath()));
				
				$cdnUrl = $this->getObjectUrl($assetPath,false);
			}
		}	
	}
	
	public function compressStyles(){
		$dom = $this->getDOM();
		$linkTags = $dom->getElementsByTagName('link');
		foreach($linkTags as $link){
			if($link->getAttribute('type') == 'text/css'){
				$cssFile = $link->getAttribute('href');

				$compressedPath = $this->getCompressedFile($cssFile);
				if($compressedPath){
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
				$compressedPath = $this->getCompressedFile($scriptFile);
				if($compressedPath){
					$script->setAttribute('src',$compressedPath);
				}
			}
		}
	}

	public function getCompressedFile($path){
		//Only compress internal files
		if(JURI::isInternal($path)){
			$u =& JURI::getInstance( $path );
			$siteRoot = JURI::getInstance(JURI::root());
			$filePath = substr($u->getPath(),strlen($siteRoot->getPath()));
			
			$fileID =  hash_file('md5',JPATH_SITE.DS.$filePath);

			$cache = $this->getCache();
			$compressedPath = $cache->get($fileID);
			if($compressedPath === false){
				$path_parts = pathinfo($filePath);
				$compressedPath = DS.$path_parts['dirname'].DS.$path_parts['filename'].'.plg_system_cdn_generated.compressed-'.$fileID.'.'.$path_parts['extension'];

				if(!file_exists(JPATH_SITE.$compressedPath)){
					//Create compressed files
					$uncompressed = file_get_contents(JPATH_SITE.DS.$filePath);

					if($path_parts['extension'] === 'css'){
						require_once JPATH_SITE.DS.'plugins'.DS.'system'.DS.'cdn'.DS.'CSS.php';
						$minified = Minify_CSS::minify($uncompressed);
					}else if($path_parts['extension'] === 'js'){
						require_once JPATH_SITE.DS.'plugins'.DS.'system'.DS.'cdn'.DS.'jsmin.php';
						$minified = JSMin::minify($uncompressed);
					}
					
					$pathParts = explode(DS,$compressedPath);
					
					$compressedFolder = implode(DS,array_slice($pathParts,0,sizeof($pathParts) - 1));
					$compressedFolder = ($compressedFolder[0] === '/' ) ? substr($compressedFolder,1) : $compressedFolder;
					
					if(!JFolder::exists($compressedFolder)){
						mkdir($compressedFolder,0777,true);
					}
					chmod($compressedFolder, 0777);
					if(!JFile::write(JPATH_SITE.$compressedPath,$minified, true)){
						return false;	
					}				
				}
				$cache->store($compressedPath, $fileID);
			}
			return $compressedPath;
		}
		return false;
	}
	
	
	public function combinedStyles(){
		$dom = $this->getDOM();
		$linkTags = $dom->getElementsByTagName('link');
		$cssFiles = array();
		
		$ignoredCss = explode('\n',$this->params->get('ignoreCombineCss'));
		$ignoredCssFiles = array();
		
		foreach($linkTags as $link){
			if($link->getAttribute('type') == 'text/css'){
				$cssFile = $link->getAttribute('href');
				if(!in_array(basename($cssFile),$ignoredCss)){
					if(JURI::isInternal($cssFile)){
						$u =& JURI::getInstance( $cssFile );

						if(sizeof($u->getQuery(true)) == 0){
							$cssFiles[] = $link;
						}else{
							$this->log('Dynamic/Ignored file not combined '.$u->getPath().'?'.$u->getQuery());
						}
					}
				}else{
					$ignoredCssFiles[] = $link;
				}
			}
			
		}
		
		//Combine all CSS files
		$combinedCssPaths = array();
		foreach($cssFiles as $link){
			$cssFile = $link->getAttribute('href');
			$u =& JURI::getInstance( $cssFile );
			$cssPath = $u->getPath();
			$combinedCssPaths[] = $cssPath;
		}

		$combinedUrl = $this->combineFiles($combinedCssPaths);
		
		foreach($cssFiles as $link){
			$link->parentNode->removeChild($link);
		}

		$head = $dom->getElementsByTagName('head')->item(0);

		$linkEl = $dom->createElement('link');
		$linkEl->setAttribute('href',$combinedUrl);
		$linkEl->setAttribute('type','text/css');
		$linkEl->setAttribute('rel','stylesheet');
		$head->appendChild($linkEl);
		
		foreach($ignoredCssFiles as $link){
			$cssFile = $link->getAttribute('href');
			$link->parentNode->removeChild($link);
			$linkEl = $dom->createElement('link');
			$linkEl->setAttribute('href',$cssFile);
			$linkEl->setAttribute('type','text/css');
			$linkEl->setAttribute('rel','stylesheet');
			$head->appendChild($linkEl);
		}		
	}
	
	public function cdnCss(){
		$dom = $this->getDOM();
		$linkTags = $dom->getElementsByTagName('link');
		$cache = $this->getCache('cdn_urls');
		
		foreach($linkTags as $link){
			if($link->getAttribute('type') == 'text/css'){
				$cssFile = $link->getAttribute('href');
				if(JURI::isInternal($cssFile)){
					$u =& JURI::getInstance( $cssFile );
					if(sizeof($u->getQuery(true)) == 0){
						$cdnPath = $cache->get($cssFile);
						if($cdnPath == false){
							$cdnUrl = $this->getObjectUrl($cssFile,true);
							if($cdnUrl){
								$cdnPath = $cdnUrl;
								$cache->store($cdnUrl, $cssFile);
							}else{
								error_log('Fatal error storing CSS to the cloud');
							}
						}
						
						$link->setAttribute('href',$cdnPath);
					}
				}
			}
			$link = $this->setRelPrefetch($link);
		}
	}
	
	public function combineScripts(){
		$dom = $this->getDOM();
		$scriptTags = $dom->getElementsByTagName('script');
		
		$scriptFiles = array();
		$scriptDeclarations = array();
		
		foreach($scriptTags as $script){
			if($script->getAttribute('src') !== ''){
				$scriptFile = $script->getAttribute('src');

				if(JURI::isInternal($scriptFile)){
					$u =& JURI::getInstance( $scriptFile );

					if(sizeof($u->getQuery(true)) == 0){
						$scriptFiles[] = $script;
					}else{
						$this->log('Dynamic file not stored to CDN '.$u->getPath().'?'.$u->getQuery());
					}
				}
			}else{
				$scriptDeclarations[] = $script;
			}
		}
		
		//Combine all javascript files
		$combinedScriptPaths = array();
		
		foreach($scriptFiles as $script){
			$scriptFile = $script->getAttribute('src');
			$u =& JURI::getInstance( $scriptFile );
			$scriptPath = $u->getPath();
			$combinedScriptPaths[] = $scriptPath;
		}

		$combinedUrl = $this->combineFiles($combinedScriptPaths);
		
		foreach($scriptFiles as $script){
			$script->parentNode->removeChild($script);	
		}
		
		$head = $dom->getElementsByTagName('head')->item(0);

		$scriptEl = $dom->createElement('script');
		$scriptEl->setAttribute('src',$combinedUrl);
		$scriptEl->setAttribute('type','text/javascript');

		$head->appendChild($scriptEl);

		//Combine script blocks
		$combinedScript = '';
		foreach($scriptDeclarations as $script){
			$combinedScript .= $script->nodeValue;
		}
		$scriptBlock = $dom->createElement('script' ,$combinedScript);
		$scriptBlock->setAttribute('type','text/javascript');
		$head->appendChild($scriptBlock);

		foreach($scriptDeclarations as $script){
			$script->parentNode->removeChild($script);	
		}
		
	}
	
	public function deferScripts(){
		$dom = $this->getDOM();
		$scriptTags = $dom->getElementsByTagName('script');
		
		$scriptDeclarations = array();
		$removeList = array(); 
		
		foreach($scriptTags as $script){
			$removeList[] = $script;
			if($script->getAttribute('src') == ''){
				$position = $script->getAttribute('data-cdn-position') == '' ? 'bottom' : $script->getAttribute('data-cdn-position');
				$scriptDeclarations[$position][] = $script;
			}else{
				$initScriptUrl = $script->getAttribute('src');
			}
		}
		
		if(sizeof($scriptDeclarations['bottom']) > 0){
			$combinedScript          = " ";
			foreach($scriptDeclarations['bottom'] as $el){
				$combinedScript .= " ".$el->nodeValue;
			}
			$initScript = $combinedScript;
			ob_start();
				include(JPATH_SITE.DS.'plugins'.DS.'system'.DS.'cdn'.DS.'initjs.php');
			$wrappedScript  = ob_get_contents();
			ob_end_clean();

			$scriptEl              = $dom->createElement('script',$wrappedScript);
			$scriptEl->setAttribute('type','text/javascript');
			$body = $dom->getElementsByTagName('body')->item(0);
			$body->appendChild($scriptEl);
		}

		if(array_key_exists('top',$scriptDeclarations) && sizeof($scriptDeclarations['top']) > 0){
			$combinedScript          = "";
			foreach($scriptDeclarations['top'] as $el){
				$combinedScript .= " ".$el->nodeValue;
			}
			$topScript = $combinedScript;
				
			$scriptEl = $dom->createElement('script',$topScript);
			$scriptEl->setAttribute('type','text/javascript');

			$head = $dom->getElementsByTagName('head')->item(0);
			$head->appendChild($scriptEl);
		}
		
		foreach($removeList as $script){
			$script->parentNode->removeChild($script);	
		}
	}
	
	public function cdnScripts(){
		$scripts = $this->getInternalScripts();
		$cache = $this->getCache();
		foreach($scripts as $script){
			$srciptSrc = $script->getAttribute('src');
			$u =& JURI::getInstance( $srciptSrc );
			if(sizeof($u->getQuery(true)) == 0){
				$cdnPath = $cache->get($srciptSrc);
				if($cdnPath == false){
					$cdnUrl = $this->getObjectUrl($srciptSrc,true);
					if($cdnUrl){
						$cdnPath = $cdnUrl;
						$cache->store($cdnUrl, $srciptSrc);
					}else{
						error_log('Fatal error storing JS to the cloud');
					}
				}
				
				$script->setAttribute('src',$cdnPath);
			}
		}
	}
	
	public function getInternalScripts(){
		$dom = $this->getDOM();
		$scriptTags = $dom->getElementsByTagName('script');
		
		$scriptFiles = array();

		foreach($scriptTags as $script){
			if($script->getAttribute('src') !== ''){
				$scriptFile = $script->getAttribute('src');
				if(JURI::isInternal($scriptFile)){
					$scriptFiles[] = $script;
				}
			}
		}
		return $scriptFiles;
	}
	
	public function parseImages(){
		$dom = $this->getDOM();
		$images = $dom->getElementsByTagName('img');
		
		foreach($images as $image){
			$imgSrc = $image->getAttribute('src');
			if(JURI::isInternal($imgSrc)){
				$u =& JURI::getInstance( $imgSrc );
				$siteRoot = JURI::getInstance(JURI::root());
				$imagePath = substr($u->getPath(),strlen($siteRoot->getPath()));

				$cdnUrl = $this->getObjectUrl($imagePath,true);
				if($cdnUrl){
					$image->setAttribute('src',$cdnUrl);
					array_push($this->mainifestFiles,$cdnUrl);
				}else{
					array_push($this->mainifestFiles,$imgSrc);
				}
			}else{
				array_push($this->mainifestFiles,$imgSrc);
			}
		}
	}
	
	public function getObjectUrl($objectPath,$cacheBreak = true){
		$objectPath = (substr($objectPath,0,1) == '/') ? substr($objectPath,1) : $objectPath;
		$headers = array();
		
		$lastModified = (filemtime(JPATH_SITE.DS.$objectPath)) ? filemtime(JPATH_SITE.DS.$objectPath) : '';
		$offset = 3600 * 24 * 365; 
		$headers['Cache-Control'] = "max-age={$offset}, must-revalidate";
		$headers['Expires'] = gmdate( 'D, d M Y H:i:s', time() + $offset ) . ' GMT';
		
		$objectName = ($objectPath[0] == "/") ? substr($objectPath,1) : $objectPath;
		
		if($cacheBreak){
			$nameParts = explode('.',$objectName);
		
			//This might have to be optional, but the images need to be in the compressed dir if the css is being compressed
			$objectName = $nameParts[0];
		
			$objectName .= '-'.$lastModified;
			$objectName .= '.'.$nameParts[1];
		}
		
		$cache = $this->getCache();
		
		$url = $cache->get($objectName);
		if($url === false){
			$object =& $this->createObject($objectName);
			$object->metadata = $headers;
			
			if($object){
				try{
					$object->load_from_filename(JPATH_SITE.DS.$objectPath);
					$url = $object->public_ssl_uri();
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
}
