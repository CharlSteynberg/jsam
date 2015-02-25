<?php

// JS-LIKE OBJECTS
// ========================================================================================================
   class Object
   {
      function __construct($members = array())
      {
         foreach ($members as $name => $value)
         { $this->$name = $value; }
      }

      function __call($name, $args)
      {
         if (is_callable($this->$name))
         {
            array_unshift($args, $this);
            return call_user_func_array($this->$name, $args);
         }
      }
   }
// ========================================================================================================




// JSAM PARSER
// ========================================================================================================
   class JSAM
   {
   // compilers
   // -------------------------------------------------------------------------
      public static $comp = array();
   // -------------------------------------------------------------------------



   // configuration
   // -------------------------------------------------------------------------
      public static $conf = null;
   // -------------------------------------------------------------------------



   // error handling
   // -------------------------------------------------------------------------
      private function halt($msg='', $err='', $fnm='', $pos='', $crp='')
      {
         if ($msg === '')
         { exit(); }

         if ($err === '')
         { $err = 'Reference'; }

         $stk = '';

         if ($fnm !== '')
         {
            if ($pos === '')
            { $pos = array('?', '?'); }

            $stk = '   ('.$fnm.' : '.$pos[0].','.$pos[1].')';

            if ($crp !== '')
            { $crp = '   ['.$crp.']'; }
         }

         echo "\n".'JSAM '.$err.' Error:  '.$msg.$stk.$crp;
         exit;
      }
   // -------------------------------------------------------------------------



   // get JSAM configuration
   // -------------------------------------------------------------------------
      public function set_conf($pth)
      {
         if (!file_exists($pth))
         { self::halt('path "'.$pth.'" is undefined'); }

         self::$conf = json_decode(file_get_contents($pth), true);
      }
   // -------------------------------------------------------------------------



   // get vars & globals
   // -------------------------------------------------------------------------
      private function get_vars(&$vrs)
      {
         $tpe = gettype($vrs);

         if (($tpe !== 'object') && ($tpe !== 'array'))
         { $vrs = array(); }

         if ($tpe == 'object')
         { $vrs = json_decode(json_encode($vrs)); }

         date_default_timezone_set(self::$conf['ltz']);

         $vrs['TimeStamp'] = round(microtime(true), 3);
         $vrs['Date'] = strtotime('today midnight');
         $vrs['Time'] = (time() - $vrs['Date']);

         $rsl = array();

         foreach ($vrs as $k => $v)
         {
            if (gettype($v) == 'object')
            { $v = (array)$v; }

            $rsl[$k] = $v;
         }

         $vrs = $rsl;

         return $rsl;
      }
   // -------------------------------------------------------------------------



   // GET COMPILER MODULE
   // -------------------------------------------------------------------------
      public function acquire($pth)
      {
         require($pth);

         if (isset($module->exports))
         { $mod = $module->exports; }
         elseif (isset($module))
         { $mod = $module; }
         elseif (isset($exports))
         { $mod = $exports; }
         else
         { self::halt('extension module expected from: "'.$pth.'"'); }

         if (!isset($mod->config['mimeType']))
         { self::halt('config & mime-type expected from: "'.$pth.'"'); }

         self::$comp[$mod->config['mimeType']] = $mod;

         return true;
      }
   // -------------------------------------------------------------------------



   // prepare context
   // -------------------------------------------------------------------------
      public function parse($dfn, &$vrs=null, $obj=false, $ers=true)
      {
      // arguments legend
      // ----------------------------------------------------------------------
      // $dfn ~ definition
      // $vrs ~ variables
      // $obj ~ return as object
      // $ers ~ report errors
      // ----------------------------------------------------------------------

      // locals
      // ----------------------------------------------------------------------
         $vrs = self::get_vars($vrs);                 // get JSAM context vars
         $fnm = null;                                 // file-name
      // ----------------------------------------------------------------------

      // if definition is a path, get contents
      // ----------------------------------------------------------------------
         if ((gettype($dfn) === 'string') && (preg_match('/^[a-zA-Z0-9-\/\._]+$/', $dfn)))
         {
            if (!file_exists($dfn))
            { self::halt('path "'.$dfn.'" is undefined'); }

            $fnm = basename($dfn);
            $dfn = file_get_contents($dfn);
         }
      // ----------------------------------------------------------------------

      // minify jsam text, check for errors if $ers == true
      // ----------------------------------------------------------------------
         $dfn = str_replace(';)', ')', '('.self::minify($dfn, $fnm, $ers).')');
      // ----------------------------------------------------------------------

      // parse jsam text
      // ----------------------------------------------------------------------
         $rsl = self::parse_exp($dfn, $vrs);
      // ----------------------------------------------------------------------

      // if object is required, do this trick, hey don't judge, it works ;)
      // ----------------------------------------------------------------------
         if ($obj === true)
         { $rsl = json_decode(json_encode($rsl)); }
      // ----------------------------------------------------------------------

      // return result
      // ----------------------------------------------------------------------
         return $rsl;
      // ----------------------------------------------------------------------
      }
   // -------------------------------------------------------------------------



   // minify jsam text, halt on -and report errors
   // -------------------------------------------------------------------------
      private function minify($jd, $fn, $er)
      {
      // arguments legend
      // ----------------------------------------------------------------------
      // $jd ~ jsam document
      // $av ~ available variables
      // $fn ~ file name
      // $er ~ error reporting
      // ----------------------------------------------------------------------


      // constants (never changes during runtime)
      // ----------------------------------------------------------------------
         $dl = chr(186);                        // delimiter      (double pipe)
         $ph = chr(176);                        // place holder   (doped block)
         $ds = strlen($jd);                     // document size
         $mi = ($ds-1);                         // maximum index
         $st = array('"'=>1, "'"=>1, '`'=>1);   // string tokens  (each toggle)
         $ct = array('//'=>"\n", '/*'=>'*/');   // comment tokens (begin & end)
         $ws = "\r \n \t ";                      // white space
      // ----------------------------------------------------------------------


      // variables (changes during runtime)
      // ----------------------------------------------------------------------
         $lc = array(1,0);                      // curent Line and Column

         $cn = 'doc';                           // context name
         $ca = array($cn);                      // context array
         $cl = 0;                               // context array
         $cb = null;                            // context boolean

         $dc = '';                              // double characters
         $cr = 'ds';                            // current reference
         $pr = $cr;                             // previous reference

         $sc = false;                           // string context   (quoted)
         $vc = false;                           // void context     (commented)
         $rs = '';                              // result
      // ----------------------------------------------------------------------


      // no error checking
      // ----------------------------------------------------------------------
         if ($er === false)
         {
         // walk, minify
         // -------------------------------------------------------------------
            for ($i=0; $i<$ds; $i++)
            {
            // character variables
            // ----------------------------------------------------------------
               $pc = ($i>0 ? $jd[$i-1] : null);             // previous character
               $cc = $jd[$i];                               // current character
               $nc = ($i<$mi ? $jd[$i+1] : null);           // next character
               $dc = (($nc !== null) ? ($cc.$nc) : null);   // double chars
            // ----------------------------------------------------------------


            // void context (comment) toggle
            // ----------------------------------------------------------------
               if ($sc === false)
               {
                  if ($vc === false)
                  {
                     if (($dc !== null) && isset($ct[$dc]))
                     { $vc = $ct[$dc]; }
                     elseif (($pc.$cc) == '*/')
                     { continue; }
                  }
                  else
                  {
                     if ($cc == $vc)
                     { $vc = false; }
                     elseif (($dc !== null) && ($dc == $vc))
                     {
                        $vc = false;
                        continue;
                     }
                  }
               }
            // ----------------------------------------------------------------


            // skip the rest if current char is commented or escaped
            // ----------------------------------------------------------------
               if ($vc !== false)
               { continue; }
               elseif (($sc !== false) && ($pc == '\\') && ($cc !== $sc))
               { continue; }
            // ----------------------------------------------------------------


            // quoted string context toggle
            // ----------------------------------------------------------------
               if (isset($st[$cc]))
               {
                  if ($sc === false)
                  { $sc = $cc; }
                  else if (($cc === $sc) && ($pc !== '\\'))
                  { $sc = false; }
               }
               else
               {
                  if ($sc !== false)
                  {
                     if (($sc !== '`') && ($cc == "\n"))
                     { $sc = false; }

                     if ($cc == '\\')
                     { continue; }
                  }
               }
            // ----------------------------------------------------------------


            // skip if whitespace
            // ----------------------------------------------------------------
               if (($sc === false) && (strpos($ws, $cc) !== false))
               { continue; }
            // ----------------------------------------------------------------

            // fix string & add to result
            // ----------------------------------------------------------------
               if (($sc === false) && (($cc == '}') || ($cc == ']')))
               {
                  $xz = substr($rs, -1, 1);
                  if (($xz == ',') || ($xz == ';'))
                  { $rs = substr($rs, 0, -1); }
               }

               if (isset($st[$cc]) && ($pc != '\\'))
               { $rs .= $ph; }
               else
               { $rs .= $cc; }
            // ----------------------------------------------------------------
            }
         // -------------------------------------------------------------------

         // return trimmed result
         // -------------------------------------------------------------------
            return trim($rs);
         // -------------------------------------------------------------------
         }
      // ----------------------------------------------------------------------


      // check errors
      // ----------------------------------------------------------------------
         if ($er === true)
         {
         // locals
         // -------------------------------------------------------------------
            $jc = self::$conf;                  // jsam config
            $co = $jc['cat'];                   // context operators
            $rd = $jc['dsc'];                   // reference description
            $xm = $jc['crm'][$cn];              // context matrix
            $xr = $xm[$pr];                     // reference matrix
            $rp = "$cn.$pr.$cr";                // reference path
         // -------------------------------------------------------------------


         // walk, check errors, minify
         // -------------------------------------------------------------------
            for ($i=0; $i<$ds; $i++)
            {
            // character variables
            // ----------------------------------------------------------------
               $pc = ($i>0 ? $jd[$i-1] : null);             // previous character
               $cc = $jd[$i];                               // current character
               $nc = ($i<$mi ? $jd[$i+1] : null);           // next character
               $dc = (($nc !== null) ? ($cc.$nc) : null);   // double chars
            // ----------------------------------------------------------------


            // line & column count
            // ----------------------------------------------------------------
               if ($cc == "\n") {$lc[0]++; $lc[1]=0;} else {$lc[1]++;}
            // ----------------------------------------------------------------


            // void context (comment) toggle
            // ----------------------------------------------------------------
               if ($sc === false)
               {
                  if ($vc === false)
                  {
                     if (($dc !== null) && isset($ct[$dc]))
                     { $vc = $ct[$dc]; }
                     elseif (($pc.$cc) == '*/')
                     { continue; }
                  }
                  else
                  {
                     if ($cc == $vc)
                     { $vc = false; }
                     elseif (($dc !== null) && ($dc == $vc))
                     {
                        $vc = false;
                        continue;
                     }
                  }
               }
            // ----------------------------------------------------------------


            // skip the rest if current char is commented or escaped
            // ----------------------------------------------------------------
               if ($vc !== false)
               { continue; }
               elseif (($sc !== false) && ($pc == '\\') && ($cc !== $sc))
               { continue; }
            // ----------------------------------------------------------------


            // quoted string context toggle
            // ----------------------------------------------------------------
               if (isset($st[$cc]))
               {
                  if ($sc === false)
                  { $sc = $cc; }
                  else if (($cc === $sc) && ($pc !== '\\'))
                  { $sc = false; }
               }
               else
               {
                  if ($sc !== false)
                  {
                     if (($sc !== '`') && ($cc == "\n"))
                     { $sc = false; }

                     if ($cc == '\\')
                     { continue; }
                  }
               }
            // ----------------------------------------------------------------


            // define context references
            // ----------------------------------------------------------------
               if (($cr !== null) && ($cr != 'ws'))
               {
                  $pr = $cr;                         // pvs ref  (for sub context)
                  $xr = $xm[$pr];                    // set reference matrix
               }

               $cr = null;                           // reset current reference

               if ($sc !== false) {$cr='qs';}        // ref is in str context
               elseif (isset($st[$cc]))
               { $cr='qs'; }  // ref is a str ctx char

               if (($sc===false) && ($cr===null))    // ref is not str & is unset
               {
                  if (isset($jc['tkn'][$cc]))
                  {
                     $cr = $jc['tkn'][$cc];          // ref is a token

                     if (($cn !== 'exp') && ($cr == 'mm') && (($pr == 'dn') || ($pr == 'pt')))
                     { $cr = 'pt'; }
                  }
                  else
                  {
                     if (ctype_space($cc))
                     { $cr = 'ws'; }                 // white space
                     else
                     {
                        if (is_numeric($cc))
                        { $cr = 'dn'; }              // decimal number
                        else
                        { $cr = 'pt'; }              // plain text
                     }
                  }
               }

               if (($cn == 'obj') && (strpos('os kn ld cd', $pr) !== false) && (strpos('pt qs dn so', $cr) !== false))
               { $cr = 'kn'; }
               elseif (($cr == 'dn') && ($pr == 'pt'))
               { $cr = 'pt'; }
               elseif (($pr == 'dn') && ($cr == 'pt'))
               {
                  $cr = 'pt';
                  $pr = 'pt';

                  $xr = $xm[$pr];
               }


               if ($i == $mi) {$cr = 'de';}          // document end

               $rp = "$cn.$pr.$cr";                  // reference path
            // ----------------------------------------------------------------


            // validate context references if not string
            // ----------------------------------------------------------------
               $er = 0;

               if ($xr[$cr] < 1) {$er++;}                     // if ref booln is 0
               if (($cr == 'de') && ($cn != 'doc')) {$er++;}  // if brace mismatch

               if ($er > 0)
               { self::halt('unexpected "'.$rd[$cr].'"', 'Syntax', $fn, $lc, $rp); }
            // ----------------------------------------------------------------


            // define current context name and level
            // ----------------------------------------------------------------
               if ($sc === false)
               {
                  $cb = (isset($co[$cc]) ? $co[$cc][1] : null);  // get ctx bolean

                  if ($cb !== null)
                  {
                     if ($cb > 0)
                     { $ca[] = $co[$cc][0]; }                    // ctx level up
                     elseif ($cn == $co[$cc][0])
                     { array_pop($ca); }                         // ctx level down

                     end($ca);

                     $cl = key($ca);                             // context level
                     $cn = $ca[$cl];                             // context name
                     $cb = null;                                 // reset boolean
                     $xm = $jc['crm'][$cn];                      // set ctx matrix
                  }
               }
            // ----------------------------------------------------------------


            // skip if whitespace
            // ----------------------------------------------------------------
               if (($sc === false) && (strpos($ws, $cc) !== false))
               { continue; }
            // ----------------------------------------------------------------

            // fix string & add to result
            // ----------------------------------------------------------------
               if (($sc === false) && (($cc == '}') || ($cc == ']')))
               {
                  $xz = substr($rs, -1, 1);
                  if (($xz == ',') || ($xz == ';'))
                  { $rs = substr($rs, 0, -1); }
               }

               if (isset($st[$cc]) && ($pc != '\\'))
               { $rs .= $ph; }
               else
               { $rs .= $cc; }
            // ----------------------------------------------------------------
            }
         // -------------------------------------------------------------------

         // return trimmed result
         // -------------------------------------------------------------------
            return trim($rs);
         // -------------------------------------------------------------------
         }
      // ----------------------------------------------------------------------
      }
   // -------------------------------------------------------------------------



   // simple identifier data type
   // -------------------------------------------------------------------------
      private function typeOf($d)
      {
         $t = gettype($d);

         if (($t == 'integer') || ($t == 'double')) {$t = 'nbr';}
         elseif ($t == 'string') {$t = 'str';}
         elseif ($t == 'boolean') {$t = 'bln';}
         elseif ($t == 'NULL') {$t = 'nul';}
         elseif ($t == 'array')
         {
            $t = 'arr';
            foreach ($d as $k => $v)
            { if (!is_int($k)) {$t = 'obj'; break;} }
         }

         return $t;
      }
   // -------------------------------------------------------------------------



   // create and assign context and value according to tree-path
   // -------------------------------------------------------------------------
      private function sapv($p, &$t, $v=null)
      {
         $p = is_array($p) ? $p : explode('.', $p);
         $x =& $t;

         foreach ($p as $s)
         {
            if (!isset($x[$s]))
            { $x[$s] = array(); }

            $x =& $x[$s];
         }

         $x = $v;
      }
   // -------------------------------------------------------------------------


   // retrieve context value from tree-path
   // -------------------------------------------------------------------------
      private function gapv($p, $t)
      {
         if (is_array($p))
         {
            if (count($p) < 1)
            { return null; }
         }
         else
         { $p = explode('.', $p); }

         $c =& $t;

         foreach ($p as $s)
         {
            if (!isset($c[$s]))
            { return null; }

            $c = $c[$s];
         }

         return $c;
      }
   // -------------------------------------------------------------------------



   // parse string to data
   // -------------------------------------------------------------------------
      private function str_to_data($xp, &$av)
      {
      // arguments legend
      // ----------------------------------------------------------------------
      // $xp ~ expression
      // $av ~ available vars
      // ----------------------------------------------------------------------

      // locals
      // ----------------------------------------------------------------------
         $se = array($xp[0], substr($xp,-1,1));       // start & end chars
         $st = chr(176);                              // string toggle token
         $eo = '+-*/=<>?&|%(),;';                     // expression operators
         $et = null;                                  // expression type
         $ev = null;                                  // expression value
         $ir = null;                                  // identifier reference
      // ----------------------------------------------------------------------

      // set exp params :: reference, type, value
      // ----------------------------------------------------------------------
         if($xp == 'null')
         { $et='nul'; $ev=null; }
         elseif ($xp == 'true')
         { $et='bln'; $ev=true; }                           // boolean (true)
         elseif ($xp == 'false')
         { $et='bln'; $ev=false; }                          // boolean (false)
         elseif (is_numeric($xp))
         { $et='nbr'; $ev=($xp-0); }                        // number
         elseif (($se[0]==$st) && ($se[1]==$st) && (substr_count($xp,$st)==2))
         { $et='str'; $ev=trim($xp, $st); }                 // string
         elseif ((strlen($xp)<3) && (strpos($eo,$xp[0]) !== false))
         { $et='opr'; $ev=$xp; }                            // operator
         elseif (($se[0] == '{') && ($se[1] == '}'))
         { $et='obj'; $ev=$xp; }                            // object
         elseif (($se[0] == '[') && ($se[1] == ']'))
         { $et='arr'; $ev=$xp; }                            // array
         else
         {
            if (preg_match('/^[a-zA-Z0-9\.\$_]+$/', $xp))
            {
               $ir = $xp;
               $fk = explode('.', $xp)[0];
               $ak = array_key_exists($fk, $av);

               if ($ak)
               { $ev=self::gapv($xp,$av); }
               else
               { $ev=$xp; }

               $et = self::typeOf($ev);
            }
            else
            {
               $ol = str_split($eo);
               $et = 'txt'; $ev = $xp;                // plain text

               foreach ($ol as $oc)
               {
                  $sp = strpos($xp, $oc);

                  if ($sp !== false)
                  {
                     if ($oc == '%')
                     {
                        $l = ($sp -1);
                        $r = ($sp +1);
                        $v = "\r \n \t";

                        if (isset($xp[$l]) && (strpos($v, $xp[$l]) !== false))
                        { $et = 'exp'; break; }

                        if (isset($xp[$r]) && (strpos($v, $xp[$r]) !== false))
                        { $et = 'exp'; break; }
                     }
                     else
                     { $et = 'exp'; break; }          // expression
                  }
               }

               if ($et == 'exp')
               {
                  $ev = trim($ev);

                  if (($ev[0].$ev[1] == '((') && (substr_count($ev, '(') > substr_count($ev, ')')))
                  { $ev = substr($ev, 1, strlen($ev)); }
                  else
                  {
                     $tp = substr($ev, -2, 2);

                     if (($tp == '))') && (substr_count($ev, ')') > substr_count($ev, '(')))
                     { $ev = substr($ev, 0, -1); }
                  }
               }
            }
         }
      // ----------------------------------------------------------------------

      // return exp array:: ref, tpe, val
      // ----------------------------------------------------------------------
         return array('ref'=>$ir, 'tpe'=>$et, 'val'=>$ev);
      // ----------------------------------------------------------------------
      }
   // -------------------------------------------------------------------------



   // calculate expressions
   // -------------------------------------------------------------------------
      private function calc_exp($ld, $op, $rd, &$av)
      {
      // arguments legend
      // ----------------------------------------------------------------------
      // $ld ~ left definition
      // $op ~ operator
      // $rd ~ right definition
      // $av ~ available vars
      // ----------------------------------------------------------------------

      // data type pair
      // ----------------------------------------------------------------------
         $dtp = $ld['tpe'].$rd['tpe'];
      // ----------------------------------------------------------------------

      // change double opr
      // ----------------------------------------------------------------------
         if (($op == '||') || ($op == '&&'))
         { $op = $op[0]; }
      // ----------------------------------------------------------------------


      // add
      // ----------------------------------------------------------------------
         if ($op == '+')
         {
         // boolean to string
         // -------------------------------------------------------------------
            if ($ld['tpe']=='bln'){$ld['val']=$ld['val']?'true':'false';}
            if ($rd['tpe']=='bln'){$rd['val']=$rd['val']?'true':'false';}
         // -------------------------------------------------------------------

         // quick cases
         // -------------------------------------------------------------------
            switch ($dtp)
            {
               case 'nbrnbr' : return ($ld['val']+$rd['val']);
               case 'strstr' : return ($ld['val'].$rd['val']);
               case 'strbln' : return ($ld['val'].$rd['val']);
               case 'blnstr' : return ($ld['val'].$rd['val']);
               case 'blnbln' : return ($ld['val'].$rd['val']);
               case 'blnnbr' : return ($ld['val'].$rd['val']);
               case 'nbrbln' : return ($ld['val'].$rd['val']);
            }
         // -------------------------------------------------------------------

         // negative number on left eats string
         // -------------------------------------------------------------------
            if ($dtp == 'nbrstr')
            {
               if ($ld['val'] < 0)
               { return substr($rd['val'], ($ld['val'] * -1), strlen($rd['val'])); }
               else
               { return $ld['val'].$rd['val']; }
            }
         // -------------------------------------------------------------------

         // negative number on right eats string
         // -------------------------------------------------------------------
            if ($dtp == 'strnbr')
            {
               if ($rd['val'] < 0)
               { return substr($ld['val'], 0, $rd['val']); }
               else
               { return $ld['val'].$rd['val']; }
            }
         // -------------------------------------------------------------------

         // array shift :: neg on left
         // -------------------------------------------------------------------
            if ($dtp == 'nbrarr')
            {
               $rs = $rd['val'];

               if ($ld['val'] < 0)
               {
                  $ld['val'] *= -1;

                  for ($i=0; $i<$ld['val']; $i++)
                  { array_shift($rs); }
               }

               return $rs;
            }
         // -------------------------------------------------------------------

         // array pop :: neg on right
         // -------------------------------------------------------------------
            if ($dtp == 'arrnbr')
            {
               $rs = $ld['val'];

               if ($rd['val'] < 0)
               {
                  $rd['val'] *= -1;

                  for ($i=0; $i<$rd['val']; $i++)
                  { array_pop($rs); }
               }

               return $rs;
            }
         // -------------------------------------------------------------------
         }
      // ----------------------------------------------------------------------


      // subtract
      // ----------------------------------------------------------------------
         if ($op == '-')
         {
         // quick cases
         // -------------------------------------------------------------------
            switch ($dtp)
            {
               case 'nbrnbr' : return ($ld['val'] - $rd['val']);
               case 'nbrstr' : return substr($rd['val'], $ld['val'], strlen($rd['val']));
               case 'strnbr' : return substr($ld['val'], 0, (0-$rd['val']));
               case 'strstr' : return str_replace($rd['val'], '', $ld['val']);
            }
         // -------------------------------------------------------------------

         // array pop
         // -------------------------------------------------------------------
            if ($dtp == 'arrnbr')
            {
               $rs = $ld['val'];

               for ($i=0; $i<$rd['val']; $i++)
               { array_pop($rs); }

               return $rs;
            }
         // -------------------------------------------------------------------
         }
      // ----------------------------------------------------------------------


      // multiply
      // ----------------------------------------------------------------------
         if ($op == '*')
         {
         // quick cases
         // -------------------------------------------------------------------
            switch ($dtp)
            {
               case 'nbrnbr' : return ($ld['val'] * $rd['val']);
            }
         // -------------------------------------------------------------------

         // left string multiply
         // -------------------------------------------------------------------
            if ($dtp == 'strnbr')
            {
               $rsl = '';
               for ($i=0; $i<$rd['val']; $i++) { $rsl .= $ld['val']; }
               return $rsl;
            }
         // -------------------------------------------------------------------
         }
      // ----------------------------------------------------------------------


      // devide
      // ----------------------------------------------------------------------
         if ($op == '/')
         {
         // fix zero denominators
         // -------------------------------------------------------------------
            if (($rd['val'] === 0) || ($rd['val'] === '') || ($rd['val'] === null) || ($rd['tpe'] == 'bln'))
            { return null; }
         // -------------------------------------------------------------------

         // quick cases
         // -------------------------------------------------------------------
            switch ($dtp)
            {
               case 'nbrnbr' : return ($ld['val'] / $rd['val']);
               case 'strnbr' : return str_split($ld['val'], $rd['val']);
               case 'strstr' : return explode($rd['val'], $ld['val']);
            }
         // -------------------------------------------------------------------
         }
      // ----------------------------------------------------------------------


      // less than && greater than
      // ----------------------------------------------------------------------
         if (($op == '<') || ($op == '>'))
         {
         // for string length comparrisson
         // ----------------------------------------------------------------------
            if ($dtp == 'strnbr')
            { $ld['val'] = strlen($ld['val']); }
            elseif ($dtp == 'strstr')
            {
               $ld['val'] = strlen($ld['val']);
               $rd['val'] = strlen($rd['val']);
            }
         // ----------------------------------------------------------------------

         // less than
         // ----------------------------------------------------------------------
            if (($op == '<') && ($ld['val'] < $rd['val']))
            { return true; }
         // ----------------------------------------------------------------------

         // greater than
         // ----------------------------------------------------------------------
            if (($op == '>') && ($ld['val'] > $rd['val']))
            { return true; }
         // ----------------------------------------------------------------------

         // default
         // ----------------------------------------------------------------------
            return false;
         // ----------------------------------------------------------------------
         }
      // ----------------------------------------------------------------------


      // assign
      // ----------------------------------------------------------------------
         if ($op == '=')
         {
         // global
         // -------------------------------------------------------------------
            if (ctype_upper($ld['ref'][0]))
            {
               if (!array_key_exists($ld['ref'], $av))
               {
                  $av[$ld['ref']] = $rd['val'];
                  return $rd['val'];
               }

               return false;
            }
         // -------------------------------------------------------------------

         // local
         // -------------------------------------------------------------------
            $av[$ld['ref']] = $rd['val'];
            return true;
         // -------------------------------------------------------------------
         }
      // ----------------------------------------------------------------------


      // split str <>
      // ----------------------------------------------------------------------
         if ($op == '<>')
         {
            $rs = array();                                  // result array
            $sa = explode($rd['val'], $ld['val']);          // string array

            foreach ($sa as $si)
            {
               if (strlen($si) > 0)
               { $rs[] = $si; }
            }

            return $rs;
         }
      // ----------------------------------------------------------------------


      // join arr ><
      // ----------------------------------------------------------------------
         if ($op == '><')
         {
            $rs = array();                                  // result array
            $oa = $ld['val'];                               // original array

            foreach ($oa as $ai)
            {
               $tp = self::typeOf($ai);

               if (($tp == 'str') && (strlen($ai) > 0))
               { $rs[] = $ai; }
            }

            return implode($rs, $rd['val']);
         }
      // ----------------------------------------------------------------------


      // modulus modifier
      // ----------------------------------------------------------------------
         if ($op == '%')
         {
         // fix zero denominators
         // -------------------------------------------------------------------
            if (($rd['val'] === 0) || ($rd['val'] === '') || ($rd['val'] === null) || ($rd['tpe'] == 'bln'))
            { return null; }
         // -------------------------------------------------------------------

         // arithmic
         // -------------------------------------------------------------------
            if ($dtp == 'nbrnbr')
            { return ($ld['val'] % $rd['val']); }
         // -------------------------------------------------------------------

         // data type
         // -------------------------------------------------------------------
            if ($rd['val'] == 'type')
            { return self::typeOf($ld['val']); }
         // -------------------------------------------------------------------


         // case
         // -------------------------------------------------------------------
            if ((strlen($rd['val']) === 7) && (substr($rd['val'], 3, 4) === 'Case'))
            {
            // locals
            // ----------------------------------------------------------------
               $co = substr($rd['val'], 0, 3);        // case option
               $ot = $ld['val'];                      // original text
            // ----------------------------------------------------------------

            // word case
            // ----------------------------------------------------------------
               if ($co == 'wrd')
               { return ucwords(strtolower($ot)); }
            // ----------------------------------------------------------------

            // upper case
            // ----------------------------------------------------------------
               if ($co == 'upr')
               { return strtoupper($ot); }
            // ----------------------------------------------------------------

            // lower case
            // ----------------------------------------------------------------
               if ($co == 'lwr')
               { return strtolower($ot); }
            // ----------------------------------------------------------------

            // camel case
            // ----------------------------------------------------------------
               if ($co == 'cml')
               {
                  $at = implode(explode('-', $ot), ' ');       // altered text
                  $at = implode(explode('_', $at), ' ');
                  $at = implode(explode('/', $at), ' ');

                  $at = ucwords(strtolower($at));
                  $at = implode(explode(' ', $at), '');

                  $fc = strtolower($at[0]);
                  $at = substr($at, 1, strlen($at));

                  return $fc.$at;
               }
            // ----------------------------------------------------------------

            // default is original text
            // ----------------------------------------------------------------
               return $ot;
            // ----------------------------------------------------------------
            }
         // -------------------------------------------------------------------


         // number sequence string
         // -------------------------------------------------------------------
            if (($rd['val'] == 'nth') && is_numeric($ld['val']))
            {
               $nr = $ld['val'].'';
               $ns = 'th';

               if (strlen($nr) < 2)
               { $nr = '0'.$nr; }

               $nr = substr($nr, -2, 2);

               if ($nr[0] != '1')
               {
                  if ($nr[1] == '1')
                  { $ns = 'st'; }
                  elseif ($nr[1] == '2')
                  { $ns = 'nd'; }
                  elseif ($nr[1] == '3')
                  { $ns = 'rd'; }
               }

               return $ld['val'].$ns;
            }
         // -------------------------------------------------------------------


         // length
         // -------------------------------------------------------------------
            if ($rd['val'] == 'length')
            {
            // locals
            // ----------------------------------------------------------------
               $tp = $ld['tpe'];
               $vl = $ld['val'];
               $rs = 0;
            // ----------------------------------------------------------------

            // string
            // ----------------------------------------------------------------
               if ($tp == 'str')
               { return strlen($vl); }
            // ----------------------------------------------------------------

            // number
            // ----------------------------------------------------------------
               if ($tp == 'nbr')
               { return strlen($vl.''); }
            // ----------------------------------------------------------------

            // array
            // ----------------------------------------------------------------
               if ($tp == 'arr')
               { return count($vl); }
            // ----------------------------------------------------------------

            // object
            // ----------------------------------------------------------------
               if ($tp == 'obj')
               { return count($vl); }
            // ----------------------------------------------------------------

            // default
            // ----------------------------------------------------------------
               return null;
            // ----------------------------------------------------------------
            }
         // -------------------------------------------------------------------


         // to:*
         // -------------------------------------------------------------------
            if (substr($rd['val'], 0, 2) === 'to')
            {
            // locals
            // ----------------------------------------------------------------
               $ls = $ld['val'];
               $tp = self::typeOf($ls);
               $rv = $rd['val'];
               $dp = (strpos($rv, ' ') ? strpos($rv, ' ') : strlen($rv));

               $to = substr($rv, 2, ($dp -2));
               $md = substr($rv, ($dp +1), strlen($rv));
            // ----------------------------------------------------------------

            // toDate || toTime
            // ----------------------------------------------------------------
               if (($to == 'Date') || ($to == 'Time'))
               {
                  $tn = strtolower($to);

                  if (($tp != 'str') && ($tp != 'nbr'))
                  { return null; }

                  if ($tp == 'str')
                  {
                     if (!is_numeric($ls))
                     { return null; }

                     $ls -= 0;
                  }

                  $ld['ref'] = '$'.$tn;
                  $ld['tpe'] = 'nbr';
                  $ld['val'] = $ls;
                  $rd['val'] = $md;
               }
            // ----------------------------------------------------------------

            // toStr
            // ----------------------------------------------------------------
               if ($to == 'Str')
               {
                  if ($tp == 'nul')
                  { return 'null'; }

                  if ($tp == 'nbr')
                  { return $ls.''; }

                  if ($tp == 'bln')
                  { return ($ls ? 'true':'false'); }

                  if (($tp == 'arr') || ($tp == 'obj'))
                  { return json_encode($ls); }
               }
            // ----------------------------------------------------------------

            // toNbr
            // ----------------------------------------------------------------
               if ($to == 'Nbr')
               {
                  if ($tp == 'nul')
                  { return 0; }

                  if (($tp == 'str') && is_numeric($ls));
                  { return ($ls -0); }

                  if ($tp == 'bln')
                  { return ($ls ? 1:0); }

                  if (($tp == 'arr') || ($tp == 'obj'))
                  { return json_encode($ls); }
               }
            // ----------------------------------------------------------------

            // toBln
            // ----------------------------------------------------------------
               if ($to == 'Bln')
               {
                  if ($tp == 'nul')
                  { return false; }

                  if ($tp == 'nbr')
                  {
                     if ($ls === 0) { return false; }
                     if ($ls === 1) { return true; }
                  }

                  if ($tp == 'str')
                  {
                     switch ($tp)
                     {
                        case '1'    : return true;
                        case '0'    : return false;
                        case 'yes'  : return true;
                        case 'no'   : return false;
                        case 'on'   : return true;
                        case 'off'  : return false;
                        case 'true' : return true;
                        case 'false': return false;
                     }
                  }
               }
            // ----------------------------------------------------------------
            }
         // -------------------------------------------------------------------


         // date
         // -------------------------------------------------------------------
            if ($ld['ref'] == 'Date')
            {
            // locals
            // ----------------------------------------------------------------
               $ts = $ld['val'];
               $ms = strtolower($rd['val'].'');
               $ml = strlen($ms);
               $rs = array();
               $dl = '';
            // ----------------------------------------------------------------

            // word: year
            // ----------------------------------------------------------------
               if ($ms === 'year')
               { return date('Y', $ts); }
            // ----------------------------------------------------------------

            // word: month
            // ----------------------------------------------------------------
               if ($ms === 'month')
               { return date('F', $ts); }
            // ----------------------------------------------------------------

            // word: day
            // ----------------------------------------------------------------
               if ($ms === 'day')
               { return date('l', $ts); }
            // ----------------------------------------------------------------

            // get the deliminator
            // ----------------------------------------------------------------
               for ($i=0; $i<$ml; $i++)
               {
                  if (strpos('ymd', $ms[$i]) === false)
                  { $dl = $ms[$i]; break; }
               }
            // ----------------------------------------------------------------

            // count references
            // ----------------------------------------------------------------
               $yc = substr_count($ms, 'y');
               $mc = substr_count($ms, 'm');
               $dc = substr_count($ms, 'd');
            // ----------------------------------------------------------------

            // set num year
            // ----------------------------------------------------------------
               if ($yc > 0)
               {
                  $yn = date('Y', $ts);
                  $rs[] = substr($yn, (0-$yc), strlen($yn));
               }
            // ----------------------------------------------------------------

            // set num month
            // ----------------------------------------------------------------
               if ($mc > 0)
               {
                  if ($mc > 1)
                  { $rs[] = date('m', $ts); }
                  else
                  { $rs[] = date('n', $ts); }
               }
            // ----------------------------------------------------------------

            // set num day
            // ----------------------------------------------------------------
               if ($dc > 0)
               {
                  if ($dc > 1)
                  { $rs[] = date('d', $ts); }
                  else
                  { $rs[] = date('j', $ts); }
               }
            // ----------------------------------------------------------------

            // return deliminator joined result
            // ----------------------------------------------------------------
               return implode($rs, $dl);
            // ----------------------------------------------------------------
            }
         // -------------------------------------------------------------------


         // time (other)
         // -------------------------------------------------------------------
            if ($rd['val'] == 'time')
            {
               $tp = self::typeOf($ld['val']);

               if (($tp != 'str') && ($tp != 'nbr'))
               { return null; }

               if ($tp == 'str')
               {
                  if (!is_numeric($ld['val']))
                  { return null; }

                  $ld['val'] -= 0;
               }

               return array('ref'=>'Time', 'tpe'=>'nbr', 'val'=>$ld['val']);
            }
         // -------------------------------------------------------------------


         // time
         // -------------------------------------------------------------------
            if ($ld['ref'] == 'Time')
            {
            // locals
            // ----------------------------------------------------------------
               $ts = ($av['Date'] + $ld['val']);
               $ms = strtolower($rd['val'].'');
               $ml = strlen($ms);
               $rs = array();
               $dl = '';
            // ----------------------------------------------------------------

            // get the deliminator
            // ----------------------------------------------------------------
               for ($i=0; $i<$ml; $i++)
               {
                  if (strpos('hms', $ms[$i]) === false)
                  { $dl = $ms[$i]; break; }
               }
            // ----------------------------------------------------------------

            // count references
            // ----------------------------------------------------------------
               $hc = substr_count($ms, 'h');
               $mc = substr_count($ms, 'm');
               $sc = substr_count($ms, 's');
            // ----------------------------------------------------------------

            // set hours
            // ----------------------------------------------------------------
               if ($hc > 0)
               {
                  if ($hc > 1)
                  { $rs[] = date('H', $ts); }
                  else
                  { $rs[] = date('G', $ts); }
               }
            // ----------------------------------------------------------------

            // set minutes
            // ----------------------------------------------------------------
               if ($mc > 0)
               {
                  $uv = date('i', $ts);

                  if (($mc < 2) && ($uv[0] == '0'))
                  { $uv = $uv[1]; }

                  $rs[] = $uv;
               }
            // ----------------------------------------------------------------

            // set seconds
            // ----------------------------------------------------------------
               if ($sc > 0)
               {
                  $uv = ''.date('s', $ts);

                  if (($sc < 2) && ($uv[0] == '0'))
                  { $uv = $uv[1]; }

                  $rs[] = $uv;
               }
            // ----------------------------------------------------------------

            // return deliminator joined result
            // ----------------------------------------------------------------
               return implode($rs, $dl);
            // ----------------------------------------------------------------
            }
         // -------------------------------------------------------------------

         // time :: AM/PM
         // -------------------------------------------------------------------
            if (($rd['val'] == 'am/pm') && (strlen($ld['val'].'') > 0))
            {
               $tm = $ld['val'].'';
               $hr = $tm[0];
               $ap = '';

               if (strlen($tm) > 1)
               {
                  $hr .= $tm[1];

                  if (!is_numeric($hr[1]))
                  { $hr = $hr[0]; }
               }

               if (is_numeric($hr))
               {
                  $tm = substr($tm, strlen($hr), strlen($tm));

                  if (($hr == '0') || ($hr == '00'))
                  {
                     $hr = '12';
                     $ap = 'am';
                  }

                  $hr -= 0;

                  if ($hr < 12)
                  { $ap = 'am'; }


                  if (($hr >= 12) && ($ap !== 'am'))
                  {
                     $hr -= 12;
                     $ap = 'pm';
                  }

                  $tm = $hr.$tm.' '.$ap;
               }

               return $tm;
            }
         // -------------------------------------------------------------------
         }
      // ----------------------------------------------------------------------


      // if-else else-if :: ternary operators
      // ----------------------------------------------------------------------
         if (($op == '?') || ($op == ':'))
         {
            $l = $ld['val'];
            $t = $ld['tpe'];
            $r = $rd['val'];

            if (($t !== 'bln') && ($t !== 'nul'))
            { return $l; }

            if (($op == '?') && ($l === true))
            { return $r; }

            if (($op == ':') && ($l === null))
            { return $r; }

            return null;
         }
      // ----------------------------------------------------------------------


      // logical or
      // ----------------------------------------------------------------------
         if ($op == '|')
         {
            $l = $ld['val'];
            $r = $rd['val'];

            if (($l !== 0) && ($l !== null) && ($l !== false) && ($l !== ''))
            { return $l; }
            else
            { return $r; }
         }
      // ----------------------------------------------------------------------


      // logical and
      // ----------------------------------------------------------------------
         if ($op == '&')
         {
            if ($ld['val'] && $rd['val'])
            { return true; }
            else
            { return false; }
         }
      // ----------------------------------------------------------------------


      // double operators
      // ----------------------------------------------------------------------
         if (strlen($op) == 2)
         {
         // right data
         // -------------------------------------------------------------------
            if (($op == '++') || ($op == '--'))
            {
               $op = $op[0].'=';
               $rd = array('ref'=>null, 'tpe'=>'nbr', 'val'=>1);
            }
         // -------------------------------------------------------------------

         // left ref data
         // -------------------------------------------------------------------
            $lr = null;
            $gl = null;

            if (array_key_exists($ld['ref'], $av))
            { $lr = $av[$ld['ref']]; }

            if ($lr !== null)
            { $lr = array('ref'=>null, 'tpe'=>self::typeOf($lr), 'val'=>$lr); }
            else
            { $lr = $ld; }
         // -------------------------------------------------------------------

         // comparrisson
         // -------------------------------------------------------------------
            if (strpos('== <= >=', $op) !== false)
            {
            // data type pair
            // ----------------------------------------------------------------------
               $dtp = $lr['tpe'].$rd['tpe'];
            // ----------------------------------------------------------------------

            // for string length comparrisson
            // ----------------------------------------------------------------------
               if ($dtp == 'strnbr')
               { $lr['val'] = strlen($lr['val']); }
               elseif (($dtp == 'strstr') && ($op != '=='))
               {
                  $lr['val'] = strlen($lr['val']);
                  $rd['val'] = strlen($rd['val']);
               }
            // ----------------------------------------------------------------------

               if ($op == '==')
               {
                  if ($lr['val'] === $rd['val'])
                  { return true; }
                  else
                  { return false; }
               }

               if ($op == '<=')
               {
                  if ($lr['val'] <= $rd['val'])
                  { return true; }
                  else
                  { return false; }
               }

               if ($op == '>=')
               {
                  if ($lr['val'] >= $rd['val'])
                  { return true; }
                  else
                  { return false; }
               }
            }
         // -------------------------------------------------------------------

         // calculate result
         // -------------------------------------------------------------------
            $rs = self::calc_exp($lr, $op[0], $rd, $av);
         // -------------------------------------------------------------------

         // if ref is defined
         // -------------------------------------------------------------------
            if ($op[1] == '=')
            { $av[$ld['ref']] = $rs; }
         // -------------------------------------------------------------------

         // return result
         // -------------------------------------------------------------------
            return $rs;
         // -------------------------------------------------------------------
         }
      // ----------------------------------------------------------------------


      // return null as default
      // ----------------------------------------------------------------------
         return null;
      // ----------------------------------------------------------------------
      }
   // -------------------------------------------------------------------------



   // parse jsam text
   // -------------------------------------------------------------------------
      private function parse_exp($xp, &$av)
      {
      // arguments legend
      // ----------------------------------------------------------------------
      // $xp ~ expression
      // $av ~ available variables
      // ----------------------------------------------------------------------

      // remove matching parenthesis
      // ----------------------------------------------------------------------
      // !! trim($xp, '()');  // renders unbalanced braces
      // ----------------------------------------------------------------------
         if ((($xp[0] == '(') && substr($xp, -1, 1) == ')'))
         { $xp = substr($xp, 1, -1); }

         if (($xp[0] == '(') && (substr($xp, -1, 1) == ')') && (substr_count($xp, '(') < 2) && (substr_count($xp, ')') < 2))
         { $xp = substr($xp, 1, -1); }
      // ----------------------------------------------------------------------


      // expression data
      // ----------------------------------------------------------------------
         $exp = self::str_to_data($xp, $av);
      // ----------------------------------------------------------------------


      // return data if not expression, else run exp
      // ----------------------------------------------------------------------
         if ($exp['tpe'] !== 'exp')
         {
            if ($exp['tpe'] == 'obj')
            { return self::parse_obj($exp['val'], $av); }
            elseif ($exp['tpe'] == 'arr')
            { return self::parse_arr($exp['val'], $av); }
            else
            { return $exp['val']; }
         }
         else
         {
         // locals
         // ----------------------------------------------------------------------
            $xp = $exp['val'];                           // expression text
            $st = chr(176);                              // string toggle token

            $pt = 'nul';                                 // previous type (=null)
            $it = $pt;                                   // current type  (=null)
            $ir = null;                                  // identifier reference

            $rc = array('({[',']})');                    // record chars bgn & end
            $rl = array();                               // record level
            $qs = 0;                                     // quoted string

            $sl = strlen($xp);                           // string length

            $aa = array(array(array('')));               // argument array
            $ai = 0;                                     // argument index
            $si = 0;                                     // sub argument index
            $di = 0;                                     // definition index

            $eo = '+-*/=<>?:&|%';                        // expression operators
         // ----------------------------------------------------------------------


         // prepare expression
         // ----------------------------------------------------------------------
            for ($i=0; $i<$sl; $i++)
            {
            // current character
            // -------------------------------------------------------------------
               $c = $xp[$i];
            // -------------------------------------------------------------------

            // "record as string" toggle
            // -------------------------------------------------------------------
               if ($c == $st){ $qs = (($qs < 1) ? 1 : 0); }

               if (strpos($rc[0], $c) !== false)
               {
                  $fi = (-1 - strpos($rc[0], $c));
                  $ec = substr($rc[1], $fi, 1);
                  $rl[] = $ec;

               }  // bgn
               elseif (strpos($rc[1], $c) !== false)
               {
                  end($rl);
                  $ec = $rl[key($rl)];

                  if ($c == $ec)
                  { array_pop($rl); }

               }  // end
            // -------------------------------------------------------------------

            // build definiion sequence
            // -------------------------------------------------------------------
               if ((count($rl) > 0) || ($qs > 0))
               { $aa[$ai][$si][$di] .= $c; }             // record as string
               else
               {
               // arg & sub-arg define
               // ----------------------------------------------------------------
                  if ($c == ';')
                  {
                     $aa[] = array(array(''));
                     end($aa);
                     $ai = key($aa);
                     $si = 0;
                     $di = 0;
                     continue;
                  }

                  if ($c == ',')
                  {
                     $aa[$ai][] = array('');
                     end($aa[$ai]);
                     $si = key($aa[$ai]);
                     $di = 0;
                     continue;
                  }
               // ----------------------------------------------------------------

               // build current item
               // ----------------------------------------------------------------
                  if (strpos($eo, $c) === false)         // not an operator
                  { $aa[$ai][$si][$di] .= $c; }          // build plain text
                  else
                  {
                  // sequence began with operator
                  // -------------------------------------------------------------
                     if (strlen($aa[$ai][$si][$di]) < 1)
                     { $aa[$ai][$si][$di] = 'null'; }
                  // -------------------------------------------------------------

                  // add operator to new slot
                  // -------------------------------------------------------------
                     $aa[$ai][$si][] = $c; $di++;
                  // -------------------------------------------------------------

                  // only add next if next char is available
                  // -------------------------------------------------------------
                     if ($i <= ($sl -1))
                     {
                     // if double operator :: add to previous & advance iterator
                     // ----------------------------------------------------------
                        if ((isset($xp[$i+1])) && (strpos($eo,$xp[$i+1]) !== false))
                        {
                           if (isset($xp[$i+2]) && is_numeric($xp[$i+2]) && ($xp[$i+1] == '-'))
                           { /* do nothing :: fixes decrement */ }
                           else
                           { $aa[$ai][$si][$di] .= $xp[$i+1]; $i++; }

                           if ($i == ($sl -1))
                           { continue; }
                        }
                     // ----------------------------------------------------------

                     // add sequence buffer to new slot
                     // ----------------------------------------------------------
                        $aa[$ai][$si][] = ''; $di++;
                     // ----------------------------------------------------------

                     // negative number
                     // ----------------------------------------------------------
                        if ((isset($xp[$i+2])) && ($xp[$i+1] == '-') && (is_numeric($xp[$i+2])))
                        { $aa[$ai][$si][$di] .= '-'; $i++; }
                     // ----------------------------------------------------------
                     }
                  // -------------------------------------------------------------
                  }
               // ----------------------------------------------------------------
               }
            // -------------------------------------------------------------------
            }
         // ----------------------------------------------------------------------


         // convert string items to data
         // ----------------------------------------------------------------------
            foreach ($aa as $ai => $sa)                  // arguments
            {
               foreach ($sa as $si => $da)               // sub arguments
               {
                  foreach ($da as $di => $ei)            // expression items
                  {
                  // convert to data :: ref tpe val
                  // -------------------------------------------------------------
                     $aa[$ai][$si][$di] = self::str_to_data(trim($ei), $av);
                  // -------------------------------------------------------------
                  }
               }
            }
         // ----------------------------------------------------------------------


         // expression locals
         // -------------------------------------------------------------------
            $eal = $aa;                                  // exp arg list
            $rsl = null;                                 // result default
            $eac = count($eal);                          // exp arg count
            $red = array();                              // runtime exp data
         // -------------------------------------------------------------------


         // walk through args & sub-args and calculate $red values
         // -------------------------------------------------------------------
            foreach ($eal as $aai => $arg)
            {
            // set runtime arg array
            // ----------------------------------------------------------------
               $red[$aai] = array();
            // ----------------------------------------------------------------

            // walk through args
            // ----------------------------------------------------------------
               foreach ($arg as $sai => $sub)
               {
               // definition calculated result
               // -------------------------------------------------------------
                  $dcr = null;
                  $omt = false;
               // -------------------------------------------------------------

               // walk through subs
               // -------------------------------------------------------------
                  foreach ($sub as $dai => $def)
                  {
                  // set null to 0 where appropriate
                  // ----------------------------------------------------------
                     if (isset($sub[$dai+2]) && ($def['tpe']=='nul') && ($sub[$dai+1]['tpe']=='opr') && ($sub[$dai+2]['tpe']=='nbr'))
                     {
                        if (strpos('+-*/', $sub[$dai+1]['val'][0]) !== false)
                        {
                           $def['tpe'] = 'nbr';
                           $def['val'] = 0;
                        }
                     }
                  // ----------------------------------------------------------

                  // if current is exp, parse it
                  // ----------------------------------------------------------
                     if ($def['tpe'] == 'exp')
                     {
                        $tmp = $def['val'];
                        $tmp = self::parse_exp($tmp, $av);

                        $def['val'] = $tmp;
                        $def['tpe'] = self::typeOf($tmp);

                     }
                  // ----------------------------------------------------------

                  // start of calc sequence
                  // ----------------------------------------------------------
                     if ($dai<1) {$dcr=$def;}         // first item to result
                  // ----------------------------------------------------------

                  // calc sequence on opr
                  // ----------------------------------------------------------
                     if ($def['tpe'] == 'opr')
                     {
                     // get val from ref
                     // -------------------------------------------------------
                        $lr = $dcr['ref'];

                        if (($lr !== null) && (!isset($dcr['rcr'])))
                        {
                           if (isset($av[$lr])) {$dcr['val'] = $av[$lr];}
                        }
                     // -------------------------------------------------------

                     // next sequence item (for increment or decrementing opr)
                     // -------------------------------------------------------
                        $nsi = null;
                     // -------------------------------------------------------

                     // set next sequence item (value)
                     // -------------------------------------------------------
                        if (array_key_exists($dai+1, $sub))
                        {
                           $rr = $sub[$dai+1]['ref'];

                           if ($rr !== null)
                           {
                              if (isset($av[$rr])) {$sub[$dai+1]['val'] = $av[$rr];}
                           }
                        // ----------------------------------------------------

                        // if next is exp, parse it
                        // ----------------------------------------------------
                           if ($sub[$dai+1]['tpe'] == 'exp')
                           {
                              $tmp = $sub[$dai+1]['val'];
                              $tmp = self::parse_exp($tmp, $av);

                              $sub[$dai+1]['val'] = $tmp;
                              $sub[$dai+1]['tpe'] = self::typeOf($tmp);
                           }
                        // ----------------------------------------------------

                        // next sequence item
                        // ----------------------------------------------------
                           $nsi = $sub[$dai+1];
                        // ----------------------------------------------------
                        }
                     // -------------------------------------------------------

                     // calc sequence result
                     // -------------------------------------------------------
                        $val = self::calc_exp($dcr, $def['val'], $nsi, $av);
                        $tpe = self::typeOf($val);
                        $ref = $dcr['ref'];
                        $opr = $def['val'];

                        $dcr['ref'] = $ref;
                        $dcr['tpe'] = $tpe;
                        $dcr['val'] = $val;

                        $oro = array('='=>1, '+='=>1, '-='=>1, '*='=>1, '/='=>1);

                        if (isset($oro[$opr]))
                        { $omt = true; }
                     // -------------------------------------------------------
                     }
                  // ----------------------------------------------------------
                  }
               // -------------------------------------------------------------

               // set current sub to anwser
               // -------------------------------------------------------------
                  if (!$omt)
                  { $red[$aai][] = $dcr['val']; }
//                  print_r($dcr);
               // -------------------------------------------------------------
               }
            // ----------------------------------------------------------------
            }
         // -------------------------------------------------------------------


         // build result
         // -------------------------------------------------------------------
            $eac = count($red);
            $rsl = null;

            if ($eac < 2)
            {
               if (count($red[0]) > 0)
               {
                  if (count($red[0]) > 1)
                  {
                     $rsl = $red[0];
                  }
                  else
                  { $rsl = $red[0][0]; }
               }
            }
            else
            {
               foreach ($red as $erv)
               {
                  $tpe = self::typeOf($erv);

                  if (count($erv) > 0)
                  {
                     $tpe = self::typeOf($erv[0]);

                     if (($tpe == 'arr') || ($tpe == 'obj'))
                     {
                        if (count($erv[0]) > 0)
                        { $rsl[] = $erv[0]; }
                     }
                  }
               }

               end($rsl);
               $rfk = key($rsl);

               if ((count($rsl) < 2) && (is_int($rfk)))
               { $rsl = $rsl[0]; }
            }
         // -------------------------------------------------------------------


         // return result
         // -------------------------------------------------------------------
            return $rsl;
         // -------------------------------------------------------------------
         }
      // ----------------------------------------------------------------------
      }
   // -------------------------------------------------------------------------



   // parse objects
   // -------------------------------------------------------------------------
      private function parse_obj($str, &$av)
      {
      // remove matching braces
      // ----------------------------------------------------------------------
      // !! trim($str, '{}');                      // renders unbalanced braces
      // ----------------------------------------------------------------------
         $fc = $str[0];
         $lc = substr($str, -1, 1);

         if (($fc == '{') && (substr_count($str, '{') > substr_count($str, '}')))
         { $str = substr($str, 1, strlen($str)); }

         if (($lc == '}') && (substr_count($str, '}') > substr_count($str, '{')))
         { $str = substr($str, 0, -1); }

         $fc = $str[0];
         $lc = substr($str, -1, 1);

         if (($fc == '{') && ($lc == '}'))
         {
            $tmp = substr($str, 1, -1);

            if (substr_count($tmp, '{') == substr_count($tmp, '}'))
            { $str = $tmp; }
         }
      // ----------------------------------------------------------------------

      // locals
      // ----------------------------------------------------------------------
         $st = chr(176);                              // string token
         $sl = strlen($str);                          // string length
         $rc = array('({[',']})');                    // record chars bgn & end
         $rl = array();                               // record level
         $qs = 0;                                     // quoted string
         $oo = ':,;';                                 // object operators
         $sb = '';                                    // string buffer
         $ok = null;                                  // obkect key
         $ov = null;                                  // obkect value
         $rs = array();                               // result
      // ----------------------------------------------------------------------

      // walk & assign
      // ----------------------------------------------------------------------
         for ($i=0; $i<$sl; $i++)
         {
         // character
         // -------------------------------------------------------------------
            $c = $str[$i];
         // -------------------------------------------------------------------

         // "record as string" toggle
         // -------------------------------------------------------------------
            if ($c == $st){ $qs = (($qs < 1) ? 1 : 0); }

            if (strpos($rc[0], $c) !== false)
            {
               $fi = (-1 - strpos($rc[0], $c));
               $ec = substr($rc[1], $fi, 1);
               $rl[] = $ec;

            }  // bgn
            elseif (strpos($rc[1], $c) !== false)
            {
               end($rl);
               $ec = $rl[key($rl)];

               if ($c == $ec)
               { array_pop($rl); }

            }  // end
         // -------------------------------------------------------------------

         // build definiion
         // -------------------------------------------------------------------
            if ((count($rl) > 0) || ($qs > 0))
            { $sb .= $c; }                            // record as string
            else
            {
               if (strpos($oo, $c) === false)
               { $sb .= $c; }

               if ($c == ':')
               {
                  $ok = trim($sb, $st);
                  $sb = '';

                  $rs[$ok] = null;
               }
               else
               {
                  if (($c == ',') || ($c == ';') || ($i == ($sl -1)))
                  {
                     $ov = $sb;
                     $sb = '';
                     $vd = self::str_to_data($ov, $av);
                     $tp = $vd['tpe'];

                     if ($tp == 'exp')
                     { $rs[$ok] = self::parse_exp($vd['val'], $av); }
                     elseif ($tp == 'obj')
                     { $rs[$ok] = self::parse_obj($vd['val'], $av); }
                     elseif ($tp == 'arr')
                     { $rs[$ok] = self::parse_arr($vd['val'], $av); }
                     else
                     { $rs[$ok] = $vd['val']; }
                  }
               }
            }
         // -------------------------------------------------------------------
         }
      // ----------------------------------------------------------------------

      // result
      // ----------------------------------------------------------------------
         return $rs;
      // ----------------------------------------------------------------------
      }
   // -------------------------------------------------------------------------



   // parse arrays
   // -------------------------------------------------------------------------
      private function parse_arr($str, &$av)
      {
      // remove matching braces
      // ----------------------------------------------------------------------
      // !! trim($str, '{}');                      // renders unbalanced braces
      // ----------------------------------------------------------------------
         if ((($str[0] == '[') && substr($str, -1, 1) == ']'))
         { $str = substr($str, 1, -1); }
      // ----------------------------------------------------------------------

      // locals
      // ----------------------------------------------------------------------
         $st = chr(176);                              // string token
         $sl = strlen($str);                          // string length
         $rc = array('({[',']})');                    // record chars bgn & end
         $rl = array();                               // record level
         $qs = 0;                                     // quoted string
         $ec = '';                                    // record end char
         $sb = '';                                    // string buffer
         $li = null;                                  // list item
         $rs = array();                               // result
      // ----------------------------------------------------------------------

      // walk & assign
      // ----------------------------------------------------------------------
         for ($i=0; $i<$sl; $i++)
         {
         // character
         // -------------------------------------------------------------------
            $c = $str[$i];
         // -------------------------------------------------------------------

         // "record as string" toggle
         // -------------------------------------------------------------------
            if ($c == $st){ $qs = (($qs < 1) ? 1 : 0); }

            if (strpos($rc[0], $c) !== false)
            {
               $fi = (-1 - strpos($rc[0], $c));
               $ec = substr($rc[1], $fi, 1);
               $rl[] = $ec;

            }  // bgn
            elseif (strpos($rc[1], $c) !== false)
            {
               end($rl);
               $ec = $rl[key($rl)];

               if ($c == $ec)
               { array_pop($rl); }

            }  // end
         // -------------------------------------------------------------------

         // build definiion
         // -------------------------------------------------------------------
            if ((count($rl) > 0) || ($qs > 0))
            { $sb .= $c; }                            // record as string
            else
            {
               if ($c != ',')
               { $sb .= $c; }

               if (($c == ',') || ($i == ($sl -1)))
               {
                  $li = $sb;
                  $sb = '';

                  $vd = self::str_to_data($li, $av);
                  $tp = $vd['tpe'];

                  if ($tp == 'exp')
                  { $rs[] = self::parse_exp($vd['val'], $av); }
                  elseif ($tp == 'obj')
                  { $rs[] = self::parse_obj($vd['val'], $av); }
                  elseif ($tp == 'arr')
                  { $rs[] = self::parse_arr($vd['val'], $av); }
                  else
                  { $rs[] = $vd['val']; }
               }
            }
         // -------------------------------------------------------------------
         }
      // ----------------------------------------------------------------------

      // result
      // ----------------------------------------------------------------------
         return $rs;
      // ----------------------------------------------------------------------
      }
   // -------------------------------------------------------------------------



   // BUILD RESULT WITH COMPILER
   // -------------------------------------------------------------------------
      public function build($dfn, $vrs=null)
      {
         $cmp = key((array)$dfn);

         if (isset(self::$comp[$cmp]))
         { $rsl = self::$comp[$cmp]->render($dfn, $vrs); }
         else
         { self::halt('compiler for type: "'.$cmp.'" is undefined'); }

         return $rsl;
      }
   // -------------------------------------------------------------------------
   }
// ========================================================================================================

?>
