<?php

// change the CWD to avoid "../../" for every path
// ----------------------------------------------------------------------------
   chdir('../../');
// ----------------------------------------------------------------------------


// some variables
// ----------------------------------------------------------------------------
   $vrs = $_GET;// !! for testing only,  please don't do this in production  (:

   if (!isset($vrs['pth']))
   { $vrs['pth'] = 'examples/htm/htm.home.jsam'; }
// ----------------------------------------------------------------------------


// require jsam
// ----------------------------------------------------------------------------
   require_once('src/php/jsam.php');
// ----------------------------------------------------------------------------


// acquire some compilers
// ----------------------------------------------------------------------------
   JSAM::acquire('src/php/cmp.htm.php');  // for HTML
   JSAM::acquire('src/php/cmp.css.php');  // for CSS
// ----------------------------------------------------------------------------


	$rsl = JSAM::parse($vrs['pth'], $vrs);
   $rsl = JSAM::build($rsl, $vrs);

   foreach($rsl->headers as $hdr)
   { header($hdr); }

	echo $rsl->content;


?>
