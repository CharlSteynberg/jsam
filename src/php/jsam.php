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
   // public closures & properties
   // -------------------------------------------------------------------------
      public static $forge = array();  // compilers
      public static $conf = null;      // configuration
   // -------------------------------------------------------------------------


   // covert array to JSAM globals (dollar keys)
   // -------------------------------------------------------------------------
      private function toJsamGlobals(&$vrs)
      {
         $rsl = array();

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

         $vrs = $rsl;

         return $rsl;
      }
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


   // parse expression item string data
   // -------------------------------------------------------------------------
      private function exp_data($xp, &$gv, &$lv)
      {
      // arguments legend
      // ----------------------------------------------------------------------
      // $xp ~ expression
      // $gv ~ available vars  (global & defined)
      // ----------------------------------------------------------------------

      // locals
      // ----------------------------------------------------------------------
         $se = array($xp[0], substr($xp,-1,1));       // start & end chars
         $st = chr(176);                              // string toggle token
         $eo = '+-*/=<>(),;';                         // expression operators
         $et = null;                                  // expression type
         $ev = null;                                  // expression value
         $ir = null;                                  // identifier reference
      // ----------------------------------------------------------------------

      // set exp params :: reference, type, value
      // ----------------------------------------------------------------------
         if($xp == 'null')
         { $et='nul'; $ev=null; }
         elseif ($xp == 'true')
         { $et='bln'; $ev=true; }                     // boolean (true)
         elseif ($xp == 'false')
         { $et='bln'; $ev=false; }                    // boolean (false)
         elseif (is_numeric($xp))
         { $et='nbr'; $ev=($xp-0); }                  // number
         elseif (($se[0]==$st) && ($se[1]==$st) && (substr_count($xp,$st)==2))
         { $et='str'; $ev=trim($xp, $st); }           // string
         elseif ((strlen($xp)<3) && (strpos($eo,$xp[0]) !== false))
         { $et='opr'; $ev=$xp; }                      // operator
         elseif (($se[0] == '{') && ($se[1] == '}'))
         { $et='obj'; $ev=self::pobj($xp, $gv); }     // object
         elseif (($se[0] == '[') && ($se[1] == ']'))
         { $et='arr'; $ev=self::parr($xp, $gv); }     // array
         else
         {
            if (preg_match('/^[a-zA-Z0-9\.\$_]+$/', $xp))
            {
               $ir = $xp;
               $fk = explode('.', $xp)[0];
               $gk = array_key_exists($fk, $gv);
               $lk = array_key_exists($fk, $lv);

               if ($gk)
               { $ev=self::gapv($xp,$gv); }
               elseif ($lk)
               { $ev=self::gapv($xp,$lv); }
               else
               { $ev=null; }

               $et = self::typeOf($ev);
            }
            else
            {
               $ol = str_split($eo);
               $et = 'txt'; $ev = $xp;                // plain text

               foreach ($ol as $oc)
               {
                  if (strpos($xp, $oc) !== false)
                  { $et = 'exp'; break; }             // expression
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


   // prepare expression
   // -------------------------------------------------------------------------
      private function prep_exp($xp, &$gv, &$lv)
      {
      // legend
      // ----------------------------------------------------------------------
      // $xp ~ expression
      // $gv ~ global vars
      // ----------------------------------------------------------------------

      // locals
      // ----------------------------------------------------------------------
         $st = chr(176);                              // string toggle token

         $pt = 'nul';                                 // previous type (=null)
         $it = $pt;                                   // current type  (=null)
         $ir = null;                                  // identifier reference

         $rc = array($st.'({[',']})'.$st);            // record chars bgn & end
         $rl = 0;                                     // record level

         $sl = strlen($xp);                           // string length

         $aa = array(array(array('')));               // argument array
         $ai = 0;                                     // argument index
         $si = 0;                                     // sub argument index
         $di = 0;                                     // definition index

         $eo = '+-*/=<>';                             // expression operators
      // ----------------------------------------------------------------------


      // build exp parts
      // ----------------------------------------------------------------------
         for ($i=0; $i<$sl; $i++)
         {
         // current character
         // -------------------------------------------------------------------
            $c = $xp[$i];
         // -------------------------------------------------------------------

         // "record as string" toggle
         // -------------------------------------------------------------------
            if (($rl < 1) && (strpos($rc[0], $c) !== false)) {$rl++;}   // bgn
            elseif(($rl>0)&& (strpos($rc[1], $c) !== false)) {$rl--;}   // end
         // -------------------------------------------------------------------

         // build definiion sequence
         // -------------------------------------------------------------------
            if ($rl > 0)
            { $aa[$ai][$si][$di] .= $c; }             // record as string
            else
            {
            // arg & sub-arg define
            // ----------------------------------------------------------------
               if ($c == ';')
               {
                  $aa[] = array();
                  end($aa);
                  $ai = key($aa);
                  $si = 0;
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


      // convert string items to expression data array
      // ----------------------------------------------------------------------
         foreach ($aa as $ai => $sa)                  // arguments
         {
            foreach ($sa as $si => $da)               // sub arguments
            {
               foreach ($da as $di => $ei)            // expression items
               {
               // reset :: ref tpe val
               // -------------------------------------------------------------
                  $r=null; $t=null; $v=null;
               // -------------------------------------------------------------

               // convert to data :: ref tpe val
               // -------------------------------------------------------------
                  $aa[$ai][$si][$di] = self::exp_data($ei, $gv, $lv);
               // -------------------------------------------------------------
               }
            }
         }
      // ----------------------------------------------------------------------

      // return result as expression sequence array
      // ----------------------------------------------------------------------
         return $aa;
      // ----------------------------------------------------------------------
      }
   // -------------------------------------------------------------------------



   // calculate expressions
   // -------------------------------------------------------------------------
      private function calc_exp($ld, $op, $rd, &$gv, &$lv)
      {
      // arguments legend
      // ----------------------------------------------------------------------
      // $ld ~ left definition
      // $op ~ operator
      // $rd ~ right definition
      // $gv ~ global vars
      // $lv ~ local vars
      // ----------------------------------------------------------------------

      // data type pair
      // ----------------------------------------------------------------------
         $dtp = $ld['tpe'].$rd['tpe'];
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


      // less than
      // ----------------------------------------------------------------------
         if ($op == '<')
         {
            if ($ld['val'] < $rd['val'])
            { return true; }
            else
            { return false; }
         }
      // ----------------------------------------------------------------------


      // greater than
      // ----------------------------------------------------------------------
         if ($op == '>')
         {
            if ($ld['val'] > $rd['val'])
            { return true; }
            else
            { return false; }
         }
      // ----------------------------------------------------------------------


      // assign
      // ----------------------------------------------------------------------
         if ($op == '=')
         {
         // global
         // -------------------------------------------------------------------
            if ($ld['ref'][0] == '$')
            {
               if (!array_key_exists($ld['ref'], $gv))
               {
                  $gv[$ld['ref']] = $rd['val'];
                  return $rd['val'];
               }

               return false;
            }
         // -------------------------------------------------------------------

         // local
         // -------------------------------------------------------------------
            $lv[$ld['ref']] = $rd['val'];
            return $rd['val'];
         // -------------------------------------------------------------------
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

            if (($ld['ref'][0] == '$') && array_key_exists($ld['ref'], $gv))
            { $lr = $gv[$ld['ref']]; $gl = 'gv'; }    // on global var
            elseif (array_key_exists($ld['ref'], $lv))
            { $lr = $lv[$ld['ref']]; $gl = 'lv'; }    // on local var

            if ($lr !== null)
            { $lr = array('ref'=>null, 'tpe'=>self::typeOf($lr), 'val'=>$lr); }
            else
            { $lr = $ld; }
         // -------------------------------------------------------------------

         // comparrisson
         // -------------------------------------------------------------------
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
         // -------------------------------------------------------------------

         // calculate result
         // -------------------------------------------------------------------
            $rs = self::calc_exp($lr, $op[0], $rd, $gv, $lv);
         // -------------------------------------------------------------------

         // if ref is defined
         // -------------------------------------------------------------------
            if (($gl !== null) && ($op[1] == '='))
            {
               if ($gl == 'gv')
               { $gv[$ld['ref']] = $rs; }
               elseif ($gl == 'lv')
               { $lv[$ld['ref']] = $rs; }
            }
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



   // parse expressions
   // -------------------------------------------------------------------------
      private function parse_exp($xp, &$gv, &$lv)
      {
      // arguments legend
      // ----------------------------------------------------------------------
      // $xp ~ expression
      // $gv ~ global vars
      // $lv ~ local vars
      // ----------------------------------------------------------------------

      // remove matching parenthesis recursively
      // ----------------------------------------------------------------------
      // !! trim($xp, '()');  // renders unbalanced braces
      // ----------------------------------------------------------------------
         while (($xp[0] == '(') && (substr($xp, -1, 1) == ')'))
         { $xp = substr($xp, 1, -1); }
      // ----------------------------------------------------------------------


      // expression data
      // ----------------------------------------------------------------------
         $exp = self::exp_data($xp, $gv, $lv);
      // ----------------------------------------------------------------------


      // return data if not expression, else run exp
      // ----------------------------------------------------------------------
         if ($exp['tpe'] !== 'exp')
         { return $exp['val']; }
         else
         {
         // prepare expression
         // -------------------------------------------------------------------
            $eal = self::prep_exp($exp['val'],$gv,$lv);  // exp arg list
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

                        if ($lr !== null)
                        {
                           if     (isset($gv[$lr])) {$dcr['val'] = $gv[$lr];}
                           elseif (isset($lv[$lr])) {$dcr['val'] = $lv[$lr];}
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
                              if     (isset($gv[$rr])) {$sub[$dai+1]['val'] = $gv[$rr];}
                              elseif (isset($lv[$rr])) {$sub[$dai+1]['val'] = $lv[$rr];}
                           }
                        // ----------------------------------------------------

                        // if next is exp, parse it
                        // ----------------------------------------------------
                           if ($sub[$dai+1]['tpe'] == 'exp')
                           {
                              $tmp = $sub[$dai+1]['val'];
                              $tmp = self::parse_exp($tmp, $gv, $lv);

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
                        $dcr['val'] = self::calc_exp($dcr, $def['val'], $nsi, $gv, $lv);
                     // -------------------------------------------------------
                     }
                  // ----------------------------------------------------------
                  }
               // -------------------------------------------------------------

               // set current sub to anwser
               // -------------------------------------------------------------
                  $red[$aai][$sai] = $dcr['val'];
               // -------------------------------------------------------------
               }
            // ----------------------------------------------------------------
            }
         // -------------------------------------------------------------------


         // determine result
         // -------------------------------------------------------------------
            if ($eac == 1)
            {
               if (count($red[0]) > 1)
               { $rsl = $red[0]; }
               else
               { $rsl = $red[0][0]; }
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
      private function pobj($str, &$gv)
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
      private function parr($str, &$gv)
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
      private function get_context($jd, &$gv, $fn)
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

         $lv = array();          // local variables
         $pa = array();          // path array
         $df = array();          // definition
         $rs = array();          // result
      // ----------------------------------------------------------------------


      // walk through code
      // ----------------------------------------------------------------------
         for ($i=0; $i<$ds; $i++)
         {
         // character variables
         // ----------------------------------------------------------------------
            $pc = ($i>0 ? $jd[$i-1] : null);             // previous character
            $cc = $jd[$i];                               // current character
            $nc = ($i<$mi ? $jd[$i+1] : null);           // next character
            $dc = (($nc !== null) ? ($cc.$nc) : null);   // double chars
         // ----------------------------------------------------------------------

         // line & column count
         // ----------------------------------------------------------------------
            if ($cc == "\n") {$lc[0]++; $lc[1]=0;} else {$lc[1]++;}
         // ----------------------------------------------------------------------


         // void context (comment) toggle
         // ----------------------------------------------------------------------
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
         // ----------------------------------------------------------------------


         // skip the rest if current char is commented or escaped
         // ----------------------------------------------------------------------
            if ($vc !== false)
            { continue; }
            elseif (($sc !== false) && ($pc == '\\') && ($cc !== $sc))
            { continue; }
         // ----------------------------------------------------------------------


         // quoted string context toggle
         // ----------------------------------------------------------------------
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
         // ----------------------------------------------------------------------


         // define context references
         // ----------------------------------------------------------------------
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
         // ----------------------------------------------------------------------


         // validate context references if not string
         // ----------------------------------------------------------------------
            $er = 0;

            if ($xr[$cr] < 1) {$er++;}                     // if ref booln is 0
            if (($cr == 'de') && ($cn != 'doc')) {$er++;}  // if brace mismatch

            if ($er > 0)
            { self::halt('Syntax', 'unexpected "'.$rd[$cr].'"', $lc, $fn, $rp); }
         // ----------------------------------------------------------------------

         // define current context name and level
         // ----------------------------------------------------------------------
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
         // ----------------------------------------------------------------------


         // build expressions
         // ----------------------------------------------------------------------
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
                  $xr = self::parse_exp($eb, $gv, $lv);
                  $eb = '';

                  echo "\n---------------\n";
//                  var_dump($xr);
                  print_r($xr);
                  echo "\n---------------";
//                  exit;
               }
            }
         // ----------------------------------------------------------------------


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
      public function parse($dfn, &$vrs=null, $obj=false)
      {
      // convert given vars tp JSAM globals
      // ----------------------------------------------------------------------
         $vrs = self::toJsamGlobals($vrs);
      // ----------------------------------------------------------------------

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
         $rsl = self::get_context($dfn, $vrs, $fnm);
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
