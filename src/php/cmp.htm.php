<?php

// MODULARIZE (say whaat?)
// ------------------------------------
// $module = new Object();
// $module->exports = new Object();

   $exports = new Object();
// ------------------------------------


   $exports->config = JSAM::parse('src/cfg/cfg.htm.jsam');

   $exports->render = function($slf, $dfn, $vrs=null)
   {
      if (!function_exists('buildHTM'))
      {
         function buildHTM($dfn, $lvl, $cfg, $vrs)
         {
         // buffers
         // -------------------------------------------------------------------
            $rsl = '';
            $ind = '';
         // -------------------------------------------------------------------


         // indentation
         // -------------------------------------------------------------------
            for ($l=0; $l<$lvl; $l++)
            { $ind .= '   '; }
         // -------------------------------------------------------------------


         // build node list
         // -------------------------------------------------------------------
            foreach($dfn as $key => $node)
            {
            // locals
            // ----------------------------------------------------------------
               if (is_int($key))
               {
                  $name = array_keys($node)[0];
                  $fkvl = $node[$name];
               }
               else
               {
                  $name = $key;
                  $fkvl = $node;
               }

               $type = gettype($fkvl);
               $attr = array();
               $data = null;
            // ----------------------------------------------------------------

            // if property name refers mime type
            // ----------------------------------------------------------------
               foreach ($node as $k => $v)
               {
                  if (($k !== $name) && ($k !== 'src'))
                  {
                     if (preg_match('/^[a-z\/]+$/', $k) && (gettype($v) === 'array') && isset(JSAM::$forge[$k]))
                     {
                        $node['src'] = array();
                        $node['src'][$k] = $v;
                        unset($node[$k]);
                     }
                  }
               }
            // ----------------------------------------------------------------

            // auto attributes from config
            // ----------------------------------------------------------------
               if (isset($cfg['autoAttr'][$name]))
               {
                  foreach ($cfg['autoAttr'][$name] as $k => $v)
                  { $attr[$k] = $v; }
               }
            // ----------------------------------------------------------------

            // attributes from string
            // ----------------------------------------------------------------
               if ($type === 'string')
               {
               // locals
               // -------------------------------------------------------------
                  $aLst = array('#'=>'id', '.'=>'class');
               // -------------------------------------------------------------

               // quick src path
               // -------------------------------------------------------------
                  if (preg_match('/^[a-zA-Z0-9\/\._]+$/', $fkvl) && !isset($node['src']) && (file_exists($fkvl)))
                  {
                     $node['src'] = $fkvl;
                     $fkvl = null;
                  }
               // -------------------------------------------------------------

               // quick atrs, or key-val atrs
               // -------------------------------------------------------------
                  if (($fkvl !== null) && isset($node['src']))
                  {
                     if (isset($aLst[$fkvl[0]]))
                     {
                     // string quick attributes
                     // -------------------------------------------------------
                        $atr = explode(' ', $fkvl);

                        foreach ($atr as $pty)
                        {
                           $pty = trim($pty);

                           if (strlen($pty) > 0)
                           {
                              $k = $pty[0];
                              $v = substr($pty, 1, strlen($pty));

                              if (!isset($attr[$aLst[$k]]))
                              { $attr[$aLst[$k]] = $v; }
                              else
                              { $attr[$aLst[$k]] .= ' '.$v; }
                           }
                        }

                        $fkvl = null;
                     // -------------------------------------------------------
                     }
                     else
                     {
                     // if first key's value is compiler reference
                     // -------------------------------------------------------
                        if ((gettype($node['src']) === 'array') && preg_match('/^[a-z\/\+\-]+$/', $fkvl) && isset(JSAM::$forge[$fkvl]))
                        {
                           $temp = $node['src'];
                           $node['src'] = array();
                           $node['src'][$fkvl] = $temp;

                           $fkvl = null;
                        }
                     // -------------------------------------------------------
                     }
                  // ----------------------------------------------------------
                  }

               // quick atrs, or key-val atrs
               // -------------------------------------------------------------
                  if ($fkvl !== null)
                  {
                     if (strpos($cfg['srcNodes'], $name) !== false)
                     {
                        $attr['alt'] = $fkvl;
                        $fkvl = null;
                     }
                  }
               // -------------------------------------------------------------
               }
            // ----------------------------------------------------------------

            // gather attributes
            // ----------------------------------------------------------------
               foreach ($node as $k => $v)
               {
                  if (($k !== $name) && ($k !== 'src'))
                  {
                     if (!isset($attr[$k]))
                     { $attr[$k] = $v; }
                     else
                     { $attr[$k] .= ' '.$v; }
                  }
               }
            // ----------------------------------------------------------------


            // contents
            // ----------------------------------------------------------------
               if (isset($node['src']))
               {
                  if
                  (
                     (gettype($node['src']) == 'string')
                     && (strpos($cfg['srcNodes'], $name) !== false)
                     && preg_match('/^[a-zA-Z0-9\/\.\-_]+$/', $node['src'])
                     && (file_exists($node['src']))
                  )
                  {
                     $pth = $node['src'];
                     $ext = pathinfo($pth)['extension'];
                     $sze = (filesize($pth) / 1024);
                     $mbd = $cfg['embedRef'];
                     $ref = true;

                     if (is_readable($pth))
                     {
                        if (!isset($attr['title']) && isset($attr['alt']))
                        { $attr['title'] = $attr['alt']; }

                        if (isset($mbd[$ext]) && (strpos($mbd[$ext]['tags'], $name) !== false))
                        {
                           if (isset($mbd[$ext]['size']) && isset($mbd[$ext]['mime']))
                           {
                              if ($sze <= $mbd[$ext]['size'])
                              {
                                 $ref = false;
                                 $attr['src'] = 'data:'.$mbd[$ext]['mime'].';base64,'.base64_encode(file_get_contents($pth));
                              }
                           }
                        }

                        if ($ref === true)
                        {
                           $cpa = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
                           $rpa = explode('/', $pth);

                           foreach($cpa as $k => $v)
                           {
//                              echo $rpa[$k].' '.$v."\n";
                              if (isset($rpa[$k]) && ($rpa[$k] == $v))
                              { array_shift($rpa); }

                              if (isset($rpa[$k+1]) && ($rpa[$k+1] !== $cpa[$k+1]))
                              {
                                 array_unshift($rpa, '..');
                                 break;
                              }
                           }

                           $pth = implode($rpa, '/');

                           $attr['src'] = $pth;
                        }
                     }
                     else
                     { $attr['alt'] = 'forbidden: '.$pth; }
                  }
                  else
                  { $data = $node['src']; }
               }
               elseif (count($attr) < 1)
               { $data = $fkvl; }
               else
               { $data = null; }
            // ----------------------------------------------------------------


            // auto contents
            // ----------------------------------------------------------------
               if (isset($cfg['autoList'][$name]))
               {
                  $anl = $cfg['autoList'][$name];

                  if (gettype($data) == 'array')
                  { $data = array_merge($anl, $data); }
                  // elseif ($data === null)
                  // { $data = $anl; }
                  // else
                  // {
                  //    $data .= '';
                  //    $elm = 'span';
                  //
                  //    if (strlen($data) > 72)
                  //    { $elm = 'p'; }
                  //
                  //    $data = array('p'=>$data);
                  // }
               }
            // ----------------------------------------------------------------


            // node start
            // ----------------------------------------------------------------
               $rsl .= "\n".$ind.'<'.$name;

               foreach($attr as $nme => $pty)
               {
                  $rsl .= ' '.$nme.'="'.$pty.'"';
               }

               $rsl .= '>';
            // ----------------------------------------------------------------

            // node contents
            // ----------------------------------------------------------------
               if ($data !== null)
               {
                  if (gettype($data) == 'array')
                  {
                     $kn = array_keys($data)[0];

                     if (preg_match('/^[a-z\/\+\-]+$/', $kn) && (gettype($data[$kn]) === 'array') && isset(JSAM::$forge[$kn]))
                     { $data = JSAM::$forge[$kn]->render($data, $vrs)->content; }
                  }

                  if (gettype($data) == 'array')
                  {
                     $lvl++;
                     $rsl .= buildHTM($data, $lvl, $cfg, $vrs);
                     $lvl--;
                  }
                  else
                  {
                     if (strpos($data, "\n") !== false)
                     {
                        $data = explode("\n", $data);
                        foreach($data as $idx => $txt)
                        { $data[$idx] = $ind.'   '.$data[$idx]; }
                        $data = implode($data, "\n");
                     }
                     else
                     {
                        // do stuff
                     }

                     $rsl .= $data;
                  }
               }
            // ----------------------------------------------------------------

            // node end
            // ----------------------------------------------------------------
               if (strpos($cfg['voidList'], ' '.$name.' ') === false)
               {
                  if ((gettype($data) == 'array') || (strpos($data, "\n") !== false))
                  { $rsl .= "\n".$ind; }

                  $rsl .= '</'.$name.'>';
               }
            // ----------------------------------------------------------------
            }
         // -------------------------------------------------------------------

         // return result
         // -------------------------------------------------------------------
            return $rsl;
         // -------------------------------------------------------------------
         }
      }

      $dfn = $dfn['text/html'];
      $htm = '';

      if (isset($dfn[0]['head']))
      { $dfn = array(0=>array('html'=>$dfn)); }

      if (isset($dfn[0]['html']))
      { $htm .= '<!DOCTYPE html>'; }

      $htm .= buildHTM($dfn, 0, $slf->config, $vrs);

      $rsl = new Object();
      $rsl->headers = array();
      $rsl->content = $htm;

      return $rsl;
   };

?>
