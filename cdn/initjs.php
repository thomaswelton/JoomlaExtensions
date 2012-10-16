/* <![CDATA[ */
(function() {
	var cs = document.createElement('script'); cs.type = 'text/javascript'; cs.async = true;cs.src = '<?php echo $initScriptUrl; ?>';
	var s = document.getElementsByTagName('script')[0]; 
	
	window.onload = function(){ s.parentNode.insertBefore(cs, s); };
})();
/* ]]> */