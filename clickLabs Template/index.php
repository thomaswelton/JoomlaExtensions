<?php  
/*------------------------------------------------------------------------
# author    Thomas Welton
-------------------------------------------------------------------------*/
defined( '_JEXEC' ) or die; 
JHTML::_( 'behavior.mootools' );

$this->setGenerator('clickLabs');

$document = JFactory::getDocument(); 

//Add stylesheet and JS via the Joomla document object,
$tpath = $this->baseurl.'/templates/'.$this->template;
$document->addStylesheet($tpath.'/css/template.css');
$document->addScript($tpath.'/scripts/script.js');

//Find all the script declarations in the document, combine into a string to be added to the bottom of the page.
$parameter_script = 'script';
$header = $document->getHeadData();
$scriptDeclarations = '';

foreach($header[$parameter_script] as $key=>$value){
	$scriptDeclarations .= $value;
}
$document->_script = array();


//Setup for Facebook
$u 		= JURI::getInstance( JURI::root() );
$port 	= ($u->getPort() == '') ? '' : ":".$u->getPort();
$xdPath	= $u->getScheme().'://'.$u->getHost().$port.$u->getPath().'/facebookXD.php';

?>
<!doctype html>
<!--[if lt IE 7]> <html class="ie6 oldie" lang="<?=$this->language?>"> <![endif]-->
<!--[if IE 7]>    <html class="ie7 oldie" lang="<?=$this->language?>"> <![endif]-->
<!--[if IE 8]>    <html class="ie8 oldie" lang="<?=$this->language?>"> <![endif]-->
<!--[if gt IE 8]><!-->  <html lang="<?=$this->language?>" xmlns:og="http://ogp.me/ns#" xmlns:fb="https://www.facebook.com/2008/fbml"> <!--<![endif]-->
	<head prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# website: http://ogp.me/ns/website#">
		<jdoc:include type="head" />
	</head>
	<body class="">
		
		<div id="container">
			<jdoc:include type="message" />
			<jdoc:include type="component" />
		</div>
		
		<jdoc:include type="modules" name="debug" />
		
		<div id="fb-root"></div>
		
		<script type="text/javascript"><?php echo $scriptDeclarations; ?></script>
		<script type="text/javascript">
			/* <![CDATA[ */	
			document.getElementsByTagName("html")[0].style.display="block";	
			
				(function() {
					var e = document.createElement('script'); e.async = true;
					e.src = document.location.protocol + '//connect.facebook.net/en_GB/all.js';
					document.getElementById('fb-root').appendChild(e);
				}());
				
			window.fbAsyncInit = function() {
				FB._https = (window.location.protocol == "https:");
				FB.init({appId: "", status: true, cookie: true, xfbml: true, channelUrl: "<?php echo $xdPath; ?>", oauth: true, authResponse: true});
				if(FB._inCanvas){
					FB.Canvas.setAutoResize();
					FB.Canvas.scrollTo(0,0);
				}
			};
			/* ]]> */
		</script>
	</body>
</html>
