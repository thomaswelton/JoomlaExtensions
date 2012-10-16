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
$document->addScript($tpath.'/scripts/modernizr.js');
$document->addScript($tpath.'/scripts/script.js');

?>
<!doctype html>
<!--[if lt IE 7]> <html class="ie6 oldie" lang="<?=$this->language?>"> <![endif]-->
<!--[if IE 7]>    <html class="ie7 oldie" lang="<?=$this->language?>"> <![endif]-->
<!--[if IE 8]>    <html class="ie8 oldie" lang="<?=$this->language?>"> <![endif]-->
<!--[if gt IE 8]><!-->  <html lang="<?=$this->language?>"> <!--<![endif]-->
	<head>
		<jdoc:include type="head" />
	</head>
	<body class="">
		<div id="container">
			<div id="content">
				<jdoc:include type="message" />
				<jdoc:include type="component" />
			</div>
		</div>
		
		<jdoc:include type="modules" name="debug" />
	</body>
</html>
