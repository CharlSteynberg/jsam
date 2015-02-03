<?php

// CONVERT VARIABLES TO JSAM GLOBALS
// ========================================================================================================
   function jsam_globals($vrs)
   {
      $rsl = array('null'=>null, 'true'=>true, 'false'=>false);

      if ($vrs === null)
      { return $rsl; }

      $tpe = gettype($vrs);

      if ($tpe == 'object')
      { $vrs = (array)$vrs; }
      else
      {
         if ($tpe !== 'array')
         {
            echo 'JSAM globals expected as object or array';
            exit;
         }
      }

      foreach ($vrs as $k => $v)
      {
         $k = '$'.$k;

         if (gettype($v) == 'object')
         { $v = (array)$v; }

         $rsl[$k] = $v;
      }

      return $rsl;
   }
// ========================================================================================================


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
   // public closures & properties
   // -------------------------------------------------------------------------
      public static $forge = array();  // compilers
      public static $conf = null;      // configuration
   // -------------------------------------------------------------------------

   // error handling
   // -------------------------------------------------------------------------
      private function halt($err, $msg, $pos, $fnm, $crp)
      {
         if ($fnm !== null)
         { $apn = '('.$fnm.' : '.$pos[0].','.$pos[1].')'; }
         else
         { $apn = '('.$pos[0].','.$pos[1].')'; }

         echo "\n".'JSAM '.$err.' Error: '.$msg.'   '.$apn.'   ['.$crp.']';
         exit;
      }
   // -------------------------------------------------------------------------

   // create and assign context and value according to tree-path
   // -------------------------------------------------------------------------
      private function sapv(&$t, $p, $v=null)
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
      private function gapv($t, $p)
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


   // parse expressions
   // -------------------------------------------------------------------------
      private function pexp($xp, $av)
      {
      // $xp ~ expression
      // $av ~ available vars  (global & defined)

      // remove matching parenthesis recursively
      // ----------------------------------------------------------------------
      // !! trim($xp, '()');  // renders unbalanced braces
      // ----------------------------------------------------------------------
         while (($xp[0] == '(') && (substr($xp, -1, 1) == ')'))
         { $xp = substr($xp, 1, -1); }
      // ----------------------------------------------------------------------

      // locals
      // ----------------------------------------------------------------------
         $rs = null;          // result
         $st = chr(176);      // string toggle

         $pt = 'nul';         // previous type (=null)
         $it = $pt;           // current type  (=null)
         $ir = null;

         $sc = 0;             // string context
         $el = 0;             // expression level

         $sl = strlen($xp);   // string length
         $ds = array('');     // definition sequence
         $si = 0;             // sequence index
         $et = null;          // expression type

         $eo = '+-*/=<>';     // expression operators

         $of = array          // operator functions
         (
            '+'=> function($l, $r)
                  {
                     if (($l[0] == 'nbr') && ($r[0] == 'nbr'))
                     { return ($l[1] + $r[1]); }

                     return null;
                  },

            '-'=> function($l, $r)
                  {
                     if (($l[0] == 'nbr') && ($r[0] == 'nbr'))
                     { return ($l[1] - $r[1]); }

                     return null;
                  },

            '*'=> function($l, $r)
                  {
                     if (($l[0] == 'nbr') && ($r[0] == 'nbr'))
                     { return ($l[1] * $r[1]); }

                     return null;
                  },

            '/'=> function($l, $r)
                  {
                     if (($l[0] == 'nbr') && ($r[0] == 'nbr'))
                     { return ($l[1] / $r[1]); }

                     return null;
                  },

            '='=> function($l, $r)
                  {
                     return ($r[1]);
                  },
         );
      // ----------------------------------------------------------------------

      // set expression type
      // ----------------------------------------------------------------------
         if ($xp[0] == '{') { $et = 'obj'; }
         elseif($xp[0] == ']') { $et = 'arr'; }
         elseif(preg_match('/^[a-zA-Z0-9\._]+$/',trim($xp,'()')))
         {
            $xp = trim($xp, '()');
            $et='ref';
         }
         else
         {
            $ol = str_split($eo);

            foreach ($ol as $oc)
            {
               if (strpos($xp, $oc) !== false)
               {
                  $et = 'smp';                        // simple exp
                  if (strpos($xp, ',') !== false)
                  { $et = 'cmp'; break;}              // compound exp
               }
            }
         }
      // ----------------------------------------------------------------------

      // if obj (declarative exp) :: return parsed object
      // ----------------------------------------------------------------------
         if ($et == 'obj') { return self::pobj($xp, $av); }
      // ----------------------------------------------------------------------

      // if arr (declarative exp) :: return parsed array
      // ----------------------------------------------------------------------
         if ($et == 'arr') { return self::parr($xp, $av); }
      // ----------------------------------------------------------------------

      // if exp (reference exp)
      // ----------------------------------------------------------------------
         if ($et == 'ref')
         {
            if (is_numeric($xp))
            { return ($xp-0); }
            else
            {
               if(array_key_exists($xp, $av))
               { return self::gapv($av, $xp); }
               else
               { return 'undefined'; }
            }
         }
      // ----------------------------------------------------------------------

      // if cmp (compound exp)
      // ----------------------------------------------------------------------
         if ($et == 'cmp')
         {
            $al = explode(',', $xp);   // argument list

            $fa = $al[0];              // declare argument
            $wa = $al[1];              // boolean argument
            $da = $al[2];              // iterare argument

            echo '!! TODO :: recursive exp';
            exit;
         }
      // ----------------------------------------------------------------------


      // if smp (simple exp)
      // ----------------------------------------------------------------------
         if ($et == 'smp')
         {
            for ($i=0; $i<$sl; $i++)
            {
            // current character
            // ----------------------------------------------------------------
               $c = $xp[$i];
            // ----------------------------------------------------------------

            // string && expression toggle
            // ----------------------------------------------------------------
               if ($c == $st)
               { if ($sc == 0) {$sc = 1;} else {$sc = 0;} }

               if ($sc == 0)
               { if ($c == '(') {$el++;} elseif ($c == ')') {$el--;} }
            // ----------------------------------------------------------------

            // build definiion sequence
            // ----------------------------------------------------------------
               if (($sc == 1) || ($el > 0))
               { $ds[$si] .= $c; }                          // build exp or str
               else
               {
                  if (strpos($eo, $c) === false)            // not an operator
                  { $ds[$si] .= $c; }                       // build plain text
                  else
                  {
                  // end of current sequence item
                  // ----------------------------------------------------------
                     if (strlen($ds[$si]) < 1)
                     { $ds[$si] = 'null'; }

                     if (($si > 0) && strpos($eo, $ds[$si]) !== false)
                     { $ds[$si] = $c; }

                     $ds[] = $c;                         // new seq token

                     if ($i < ($sl -1))
                     { $ds[] = ''; }                     // new seq bfr

                     end($ds);                           // to last key
                     $si = key($ds);                     // ref last key
                  // ----------------------------------------------------------
                  }
               }
            // ----------------------------------------------------------------
            }
         // -------------------------------------------------------------------


         // prepare & calculate sequence items
         // -------------------------------------------------------------------
            foreach ($ds as $si => $sv)
            {
            // set references
            // ----------------------------------------------------------------
               $pt = $it;
//               $ir = null;
            // ----------------------------------------------------------------
var_dump($sv);
            // prepare :: (item type) & (item value)
            // ----------------------------------------------------------------
               if ((strlen($sv) < 1) || ($sv === 'null'))
               { $it = 'nul'; $iv = null; }                    // null
               elseif((strlen($sv) == 1) && (strpos($eo, $sv) !== false))
               { $it = 'opr'; $iv = $sv; }                     // operator
               elseif(is_numeric($sv))
               {$it='nbr'; $iv = ($sv-0);}                     // number
               elseif (strpos($sv, $st) !== false)
               { $it = 'str'; $iv = trim($sv, $st); }          // quoted string
               elseif($sv==='true')
               {$it='bln'; $iv=true; }                         // boolean true
               elseif($sv==='false')
               {$it='bln'; $iv=false;}                         // boolean false
               else
               {
               // get variable results, set tpe & val
               // -------------------------------------------------------------
                  if (($sv[0] == '(') && (substr($sv, -1, 1) == ')'))
                  { $sv = self::pexp($sv, $av); }             // expression
                  elseif (array_key_exists($sv, $av))
                  { $ir = $sv; $sv = $av[$sv]; }               // identifier
                  else
                  { $av[$sv] = null; $ir = $sv; $sv = null; }  // new var

                  $it = self::typeOf($sv);
                  $iv = $sv;
               // -------------------------------------------------------------
               }
            // ----------------------------------------------------------------

            // if simple argument sequence is not ready yet
            // ----------------------------------------------------------------
               if ($si < 2)
               {
                  if ($si < 1) {$rs = $iv;}                // item 0 to result
                  continue;
               }
            // ----------------------------------------------------------------

            // set itm & rsl definitions
            // ----------------------------------------------------------------
               $id = array($it, $iv);
               $rd = array(self::typeOf($rs), $rs);
            // ----------------------------------------------------------------

            // if previous reference is opr: set it, else (opr = null)
            // ----------------------------------------------------------------
               $op = (($pt == 'opr') ? $ds[$si-1] : null);
            // ----------------------------------------------------------------

            // calculate: (rsl-tpe & rsl-val), (opr), (itm-tpe & itm-val)
            // ----------------------------------------------------------------
               if ($op !== null)
               {
                  $rs = $of[$op]($rd, $id);

                  if ($ir !== null)
                  { $av[$ir] = $rs; }
               }
            // ----------------------------------------------------------------
            }
         // -------------------------------------------------------------------
         }
      // ----------------------------------------------------------------------


      // return result
      // ----------------------------------------------------------------------
         return $rs;
      // ----------------------------------------------------------------------
      }
   // -------------------------------------------------------------------------


   // parse objects
   // -------------------------------------------------------------------------
      private function pobj($str, &$gdv)
      {
         $rsl = null;
         $str = trim($str, '{}');

         echo 'object parsing not implemented, yet';
         exit;

         return $rsl;
      }
   // -------------------------------------------------------------------------


   // parse arrays
   // -------------------------------------------------------------------------
      private function parr($str, &$gdv)
      {
         $rsl = null;
         $str = trim($str, '[]');

         echo 'array parsing not implemented, yet';
         exit;

         return $rsl;
      }
   // -------------------------------------------------------------------------



   // parse JSAM context into a multi-dimensional array, with given vars
   // -------------------------------------------------------------------------
      private function get_context($jd, $gv, $fn)
      {
      // arguments
      // ----------------------------------------------------------------------
      // $jd ~ jsam document
      // $gv ~ global variables
      // $fn ~ file name
      // ----------------------------------------------------------------------

      // constants (never changes during runtime)
      // ----------------------------------------------------------------------
         $js = self::$conf;
         $dl = chr(186);                       // delimiter       (double pipe)
         $ph = chr(176);                       // place holder    (doped block)
         $ds = strlen($jd);                    // document size
         $mi = ($ds-1);                        // maximum index
         $st = array('"'=>1, "'"=>1, '`'=>1);  // string tokens   (each toggle)
         $ct = array('//'=>"\n", '/*'=>'*/');  // comment tokens  (begin & end)
         $rd = $js['dsc'];                     // reference description
      // ----------------------------------------------------------------------


      // variables (changes during runtime)
      // ----------------------------------------------------------------------
         $lc = array(1,0);       // curent Line and Column

         $cn = 'doc';            // context name
         $ca = array($cn);       // context array
         $cl = 0;                // context array
         $co = $js['cat'];       // context operators
         $cb = null;             // context boolean

         $dc = '';               // double characters
         $cr = 'db';             // current reference
         $pr = $cr;              // previous reference

         $xm = $js['crm'][$cn];  // context matrix
         $xr = $xm[$pr];         // reference matrix
         $rp = "$cn.$pr.$cr";    // reference path
         $el = 0;                // expression level

         $sc = false;            // string context    (quoted)
         $vc = false;            // void context      (commented)

         $kb = '';               // key buffer
         $vb = '';               // value buffer
         $sb = '';               // string buffer
         $eb = '';               // expression buffer

         $pa = array();          // path array
         $df = array();          // definition
         $rs = array();          // result
      // ----------------------------------------------------------------------


      // walk through code
      // ----------------------------------------------------------------------
         for ($i=0; $i<$ds; $i++)
         {
         // character variables
         // -------------------------------------------------------------------
            $pc = ($i>0 ? $jd[$i-1] : null);             // previous character
            $cc = $jd[$i];                               // current character
            $nc = ($i<$mi ? $jd[$i+1] : null);           // next character
            $dc = (($nc !== null) ? ($cc.$nc) : null);   // double chars
         // -------------------------------------------------------------------

         // line & column count
         // -------------------------------------------------------------------
            if ($cc == "\n") {$lc[0]++; $lc[1]=0;} else {$lc[1]++;}
         // -------------------------------------------------------------------


         // void context (comment) toggle
         // -------------------------------------------------------------------
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
         // -------------------------------------------------------------------


         // skip the rest if current char is commented or escaped
         // -------------------------------------------------------------------
            if ($vc !== false)
            { continue; }
            elseif (($sc !== false) && ($pc == '\\') && ($cc !== $sc))
            { continue; }
         // -------------------------------------------------------------------


         // quoted string context toggle
         // -------------------------------------------------------------------
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
         // -------------------------------------------------------------------


         // define context references
         // -------------------------------------------------------------------
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
               if (isset($js['tkn'][$cc]))
               { $cr = $js['tkn'][$cc]; }         // ref is a token
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

            if (($cn == 'obj') && (strpos('os kn ld cd', $pr) !== false) && (strpos('pt qs nr so', $cr) !== false))
            { $cr = 'kn'; }

            if ($i == $mi) {$cr = 'de';}          // document end

            $rp = "$cn.$pr.$cr";                  // reference path
         // -------------------------------------------------------------------

//echo "$cr ";

         // validate context references if not string
         // -------------------------------------------------------------------
            $er = 0;

            if ($xr[$cr] < 1) {$er++;}                     // if ref booln is 0
            if (($cr == 'de') && ($cn != 'doc')) {$er++;}  // if brace mismatch

            if ($er > 0)
            { self::halt('Syntax', 'unexpected "'.$rd[$cr].'"', $lc, $fn, $rp); }
         // -------------------------------------------------------------------

         // define current context name and level
         // -------------------------------------------------------------------
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
                  $xm = $js['crm'][$cn];                      // set ctx matrix
               }
            }
         // -------------------------------------------------------------------


         // build context
         // -------------------------------------------------------------------
            if ($cr == 'es')
            { $el++; }

            if (($el > 0) && ($cr != 'ws'))
            {
               if (isset($st[$cc]) && ($pc != '\\'))
               { $eb .= $ph; }
               else
               { $eb .= $cc; }
            }

            if ($cr == 'ee')
            {
               $el--;

               if ($el < 1)
               {
                  $xr = self::pexp($eb, $gv);
                  $eb = '';

                  echo "\n---------------\n";
                  var_dump($xr);
                  echo "---------------\n";
                  exit;
               }
            }
         // -------------------------------------------------------------------


         }
      // ----------------------------------------------------------------------

      // if only def is defined, return it, else return rsl
      // ----------------------------------------------------------------------
         if (count($rs) < 1)
         { return $df; }
         else
         { return $rs; }
      // ----------------------------------------------------------------------
      }
   // -------------------------------------------------------------------------


   // prepare context
   // -------------------------------------------------------------------------
      public function parse($dfn, $vrs=null, $obj=false)
      {
      // get cofig if not set
      // ----------------------------------------------------------------------
         if (self::$conf === null)
         { self::$conf = json_decode(file_get_contents('src/cfg/jsam.json'), true); }
      // ----------------------------------------------------------------------

      // if definition is a path, get contents
      // ----------------------------------------------------------------------
         if (gettype($dfn) === 'string')
         {
            if (file_exists($dfn))
            {
               $fnm = basename($dfn);
               $dfn = file_get_contents($dfn);
            }
            else
            {
               echo "\n".'JSAM Reference Error: "'.$dfn.'" does not exist';
               exit;
            }
         }
         else
         { $fnm = null; }
      // ----------------------------------------------------------------------


      // get result as multi-dimensional array
      // ----------------------------------------------------------------------
         $rsl = self::get_context($dfn, jsam_globals($vrs), $fnm);
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
         {
            echo 'JSAM extension module expected from: "'.$pth.'"';
            exit;
         }

         if (!isset($mod->config['mimeType']))
         {
            echo "\n".'JSAM config & mime-type expected from: "'.$pth.'"';
            exit;
         }

         self::$forge[$mod->config['mimeType']] = $mod;

         return true;
      }
   // -------------------------------------------------------------------------


   // BUILD RESULT WITH COMPILER
   // -------------------------------------------------------------------------
      public function build($dfn, $vrs=null)
      {
         $cmp = key((array)$dfn);

         if (isset(self::$forge[$cmp]))
         { $rsl = self::$forge[$cmp]->render($dfn, jsam_globals($vrs)); }
         else
         {
            echo "\n".'JSAM compiler for type: "'.$cmp.'" is undefined';
            exit;
         }

         return $rsl;
      }
   // -------------------------------------------------------------------------
   }
// ========================================================================================================

?>
