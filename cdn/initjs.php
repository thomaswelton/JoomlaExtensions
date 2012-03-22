/* <![CDATA[ */

(function() {
var cs = document.createElement('script'); cs.type = 'text/javascript'; cs.async = true;
cs.src = '<?php echo $initScriptUrl; ?>';
var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(cs, s);
})();

function cdnInit() {
  // quit if this function has already been called
  if (arguments.callee.done) return;
  // flag this function so we don't do the same thing twice
  arguments.callee.done = true;

  <?php echo $initScript; ?>

};
/* ]]> */