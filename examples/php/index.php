<?php

// change the CWD to avoid "../../"
// ----------------------------------------------------------------------------
   chdir('../../');
// ----------------------------------------------------------------------------


// require the jsam parser
// ----------------------------------------------------------------------------
   require_once('src/php/jsam.php');
// ----------------------------------------------------------------------------


// acquire compilers
// ----------------------------------------------------------------------------
   JSAM::acquire('src/php/cmp.htm.php');
   JSAM::acquire('src/php/cmp.css.php');
// ----------------------------------------------------------------------------

   $vrs = $_GET;

   if (!isset($vrs['pth']))
   { $vrs['pth'] = 'examples/htm/htm.home.jsam'; }


	$rsl = JSAM::parse($vrs['pth'], $vrs);
   $rsl = JSAM::build($rsl, $vrs);

   foreach($rsl->headers as $hdr)
   { header($hdr); }

	echo $rsl->content;


?>
