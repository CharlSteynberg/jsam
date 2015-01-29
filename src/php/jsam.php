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
      public static $forge = array();

   // error handling
   // -------------------------------------------------------------------------
      private function halt($err, $msg, $pos, $fnm=null)
      {
         if ($fnm !== null)
         { $apn = '('.$fnm.' : '.$pos[0].','.$pos[1].')'; }
         else
         { $apn = '('.$pos[0].','.$pos[1].')'; }

         echo 'JSAM '.$err.' Error: '.$msg.'   '.$apn;
         exit;
      }
   // -------------------------------------------------------------------------

   // create and assign context and value according to tree-path
   // -------------------------------------------------------------------------
      private function set_ctx(&$t, $p, $v=null)
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
      private function get_ctx($t, $p)
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


   // parse expressions
   // -------------------------------------------------------------------------
      private function parse_exp($x, $h, $v)
      {
         $r = '';
         $q = 0;
         $e = 0;
         $b = '';
         $s = array('');
         $k = 0;
         $a = '+ - * /';

         $x = trim($x, '()');

//               if (($x[0] == '(') && (substr($x, -1, 1) == ')'))
//               { $x = substr($x, 1, -1); }

         $l = strlen($x);

         for ($i=0; $i<$l; $i++)
         {
            $c = $x[$i];

            if ($c == $h)
            {
               if ($q == 0)
               { $q = 1; }
               else
               { $q = 0; }
            }

            if ($q < 1)
            {
               if ($c == '(')
               { $e = 1; }

               if ($c == ')')
               { $e = 0; }
            }

            if (($q == 1) || ($e == 1))
            { $s[$k] .= $c; }
            else
            {
               if (strpos($a, $c) === false)
               { $s[$k] .= $c; }
               else
               {
                  if (is_numeric($s[$k]))
                  { $s[$k] = (($s[$k] -1) +1); }

                  $s[] = $c;
                  $s[] = '';
               }
            }

            end($s);
            $k = key($s);
         }

         if (is_numeric($s[$k]))
         { $s[$k] = (($s[$k] -1) +1); }

         foreach($s as $k => $i)
         {
            if (gettype($i) == 'string')
            {
               if (($i[0] == $h) && (substr($i, -1, 1) == $h))
               { $i = substr($i, 1, -1); }
               elseif (($i[0] == '(') && (substr($i, -1, 1) == ')'))
               { $i = parse_exp($i, $h, $v); }
               else
               {
                  if (array_key_exists($i, $v))
                  { $i = $v[$i]; }
               }

               if (is_numeric($i))
               { $i = (($i -1) +1); }
            }

            if ($k < 1)
            { $r = $i; }

            $o = null;

            if (isset($s[$k-1]) && (strpos($a, ($s[$k-1].'')) !== false))
            { $o = $s[$k-1]; }

            if ($o !== null)
            {
               $rt = gettype($r);
               $it = gettype($i);

               if (($rt == 'string') || ($it == 'string'))
               {
                  if ($o == '+')
                  { $r = ($r.$i); }
                  else
                  { $r = 'NaN'; }
               }
               elseif ((($rt == 'integer') || ($rt == 'double')) && (($it == 'integer') || ($it == 'double')))
               {
                  if ($o == '+')
                  { $r = ($r + $i); }
                  elseif ($o == '-')
                  { $r = ($r - $i); }
                  elseif ($o == '*')
                  { $r = ($r * $i); }
                  elseif ($o == '/')
                  { $r = ($r / $i); }
               }
            }
         }

         return $r;
      }
   // -------------------------------------------------------------------------

   // parse JSAM context into a multi-dimensional array with given vars
   // -------------------------------------------------------------------------
      private function get_context($str, $vrs, $fnm)
      {
      // constants
      // ----------------------------------------------------------------------
         $dlm = chr(186);
         $hld = chr(176);
         $len = strlen($str);
         $stl = array('"'=>1, "'"=>1, '`'=>1);
         $ccl = array('//'=>"\n", '/*'=>'*/');
         $cac = array(
            '('=>array('exp', 1),
            ')'=>array('exp', 0),
            '{'=>array('obj', 1),
            '}'=>array('obj', 0),
            '['=>array('arr', 1),
            ']'=>array('arr', 0)
         );

         $tcl = array(
            '('  => 'es',
            ')'  => 'ee',
            '{'  => 'os',
            ':'  => 'va',
            ';'  => 'ed',
            '}'  => 'oe',
            '['  => 'as',
            ','  => 'ld',
            ']'  => 'ae',
            '+'  => 'ao',
            '-'  => 'so',
            '*'  => 'mo',
            '/'  => 'do',
            '.'  => 'ft',
         );

         $vcr = array(
            'ds' => array('dsc'=>'document start',       'rul'=>array('doc'=>'ws es', 'exp'=>'', 'obj'=>'', 'arr'=>'')),
            'de' => array('dsc'=>'document end',         'rul'=>array('doc'=>'', 'exp'=>'', 'obj'=>'', 'arr'=>'')),
            'es' => array('dsc'=>'expression start',     'rul'=>array('doc'=>'', 'exp'=>'os as ir qs nr es', 'obj'=>'', 'arr'=>'')),
            'ee' => array('dsc'=>'expression end',       'rul'=>array('doc'=>'de ed ws', 'exp'=>'ee ao', 'obj'=>'oe ao so mo do ws', 'arr'=>'ld')),

            'os' => array('dsc'=>'object start',         'rul'=>array('doc'=>'', 'exp'=>'', 'obj'=>'pn ws', 'arr'=>'')),
            'pn' => array('dsc'=>'property name',        'rul'=>array('doc'=>'', 'exp'=>'', 'obj'=>'pn va', 'arr'=>'')),
            'va' => array('dsc'=>'value assign',         'rul'=>array('doc'=>'', 'exp'=>'', 'obj'=>'qs es os as ir nr ws pt', 'arr'=>'')),
            'oe' => array('dsc'=>'object end',           'rul'=>array('doc'=>'', 'exp'=>'ee', 'obj'=>'oe ws ld', 'arr'=>'ws ae ld')),

            'as' => array('dsc'=>'array start',          'rul'=>array('doc'=>'', 'exp'=>'', 'obj'=>'', 'arr'=>'nr qs ws os as ae')),
            'ld' => array('dsc'=>'list dilimiter',       'rul'=>array('doc'=>'', 'exp'=>'', 'obj'=>'ws pn oe', 'arr'=>'nr ws qs ae os as es')),
            'ed' => array('dsc'=>'expression dilimiter', 'rul'=>array('doc'=>'ws es de', 'exp'=>'', 'obj'=>'ws pn oe', 'arr'=>'')),
            'ae' => array('dsc'=>'array end',            'rul'=>array('doc'=>'', 'exp'=>'ee', 'obj'=>'oe ld', 'arr'=>'ld ws ae')),

            'qs' => array('dsc'=>'quoted string',        'rul'=>array('doc'=>'', 'exp'=>'qs ee ao', 'obj'=>'qs oe ld ed ao ws', 'arr'=>'qs ae ld')),
            'ir' => array('dsc'=>'identifier reference', 'rul'=>array('doc'=>'', 'exp'=>'ir ee ft ws ao so mo do', 'obj'=>'ir oe ft ws ao so mo do ld ed pt', 'arr'=>'')),
            'nr' => array('dsc'=>'number',               'rul'=>array('doc'=>'', 'exp'=>'nr ee ft ws ao so mo do', 'obj'=>'nr oe ld ws ao so mo do pt ed', 'arr'=>'nr ld ae')),

            'ao' => array('dsc'=>'addition operator',    'rul'=>array('doc'=>'', 'exp'=>'ir qs nr es ws', 'obj'=>'ir qs ws nr es', 'arr'=>'')),
            'so' => array('dsc'=>'subtract operator',    'rul'=>array('doc'=>'', 'exp'=>'nr ws', 'obj'=>'ir ws nr es', 'arr'=>'')),
            'mo' => array('dsc'=>'multiply operator',    'rul'=>array('doc'=>'', 'exp'=>'nr ws', 'obj'=>'ir ws nr es', 'arr'=>'')),
            'do' => array('dsc'=>'devision operator',    'rul'=>array('doc'=>'', 'exp'=>'nr ws', 'obj'=>'ir ws nr es', 'arr'=>'')),

            'ft' => array('dsc'=>'fragment token',       'rul'=>array('doc'=>'', 'exp'=>'ir nr', 'obj'=>'ir', 'arr'=>'')),
            'pt' => array('dsc'=>'plain text',           'rul'=>array('doc'=>'', 'exp'=>'', 'obj'=>'pt ld ed ws oe', 'arr'=>'')),
            'ws' => array('dsc'=>'white space',          'rul'=>array('doc'=>'', 'exp'=>'', 'obj'=>'', 'arr'=>'')),
         );
      // ----------------------------------------------------------------------

      // variables
      // ----------------------------------------------------------------------
         $pos = array(1,0);
         $lvl = array('doc');
         $clk = 0;
         $rle = 0;
         $cak = null;
         $cab = null;
         $bld = array();

         $qot = false;
         $cmt = false;

         $dbl = '?';
         $pnb = '';
         $pvb = '';
         $vrb = '';

         $ctx = 'doc';
         $old = 'doc';
         $ocp = null;
         $pvs = 'ds';
         $ref = 'ds';

         $cpa = array();
         $cpo = array();

         $def = array();
         $rsl = array();
      // ----------------------------------------------------------------------


      // walk through code
      // ----------------------------------------------------------------------
         for ($i=0; $i<$len; $i++)
         {
         // line count && column count
         // -------------------------------------------------------------------
            if ($str[$i] == "\n")
            {
               $pos[0]++;
               $pos[1] = 0;
            }
            else
            { $pos[1]++; }
         // -------------------------------------------------------------------


         // current char and double chars
         // -------------------------------------------------------------------
            $c = $str[$i];

            if ($i < ($len-1))
            { $dbl = $c.$str[$i+1]; }
         // -------------------------------------------------------------------


         // comment toggle
         // -------------------------------------------------------------------
            if ($qot === false)
            {
               if ($cmt === false)
               {
                  if (isset($ccl[$dbl]))
                  {
                     $cmt = $ccl[$dbl];
                     $i++;
                     continue;
                  }
               }
               else
               {
                  if ($c == $cmt)
                  {
                     $cmt = false;
                     continue;
                  }
                  else
                  {
                     if ($dbl == $cmt)
                     {
                        $cmt = false;
                        $i++;
                        continue;
                     }
                  }
               }
            }
         // -------------------------------------------------------------------


         // proceed if comment is off
         // -------------------------------------------------------------------
            if ($cmt === false)
            {
            // quotes toggle
            // ----------------------------------------------------------------
               if (isset($stl[$c]))
               {
                  if ($qot === false)
                  {
                     $qot = $c;
//                        continue;
                  }
                  else
                  {
                     if (($c === $qot) && ($str[$i-1] !== '\\'))
                     {
                        $qot = false;
//                           continue;
                     }
                  }
               }
               else
               {
                  if ($qot !== false)
                  {
                     if (($qot !== '`') && ($c == "\n"))
                     {
                        $qot = false;
                        continue;
                     }

                     if ($c == '\\')
                     {
                        if ($str[$i+1] !== $qot)
                        { $i += 1; }

                        continue;
                     }
                  }
               }
            // ----------------------------------------------------------------


            // set/get current ref
            // ----------------------------------------------------------------
               if (($ref !== null) && ($ref != 'ws'))
               { $pvs = $ref; }

               $ref = null;

               if ($qot === false)
               {
                  if (isset($tcl[$c]))
                  { $ref = $tcl[$c]; }
                  else
                  {
                     if (strlen(trim($c)) > 0)
                     {
                        if (is_numeric($c))
                        { $ref = 'nr'; }
                        else
                        { $ref = 'ir'; }
                     }
                     else
                     { $ref = 'ws'; }
                  }

                  if (($ref == 'ft') && ($pvs == 'nr') && (is_numeric($dbl[1])))
                  { $ref = 'nr'; }

                  if (($pvs == 'qs') && isset($stl[$c]))
                  { $ref = 'qs'; }

                  if (($pvs == 'ir') && ($ref == 'nr'))
                  { $ref = 'ir'; }

                  if (($pvs == 'pt') && (strpos('nr ir', $ref) !== false))
                  { $ref = 'pt'; }

                  if (($pvs == 'nr') && (strlen($vrb) < 1) && ($ref == 'ir'))
                  { $ref = 'pt'; }

                  if (($pvs == 'pn') && ($c == ' '))
                  { $ref = 'pn'; }
               }
               else
               { $ref = 'qs'; }

               if (($ctx == 'obj') && (strpos('os pn ld ed', $pvs) !== false) && (strpos('ir qs nr so', $ref) !== false))
               { $ref = 'pn'; }

               if (($ctx == 'obj') && ($ref == 'ir') && (!preg_match('/^[a-zA-Z0-9\_\$]+$/', ($vrb.$c))))
               { $ref = 'pt'; }

               if ($i == ($len-1))
               { $ref = 'de'; }
            // ----------------------------------------------------------------


            // validate context
            // ----------------------------------------------------------------
               if ($ref !== null)
               {
               // syntax validation
               // -------------------------------------------------------------
                  if (!isset($vcr[$pvs]['rul'][$ctx]) || (strpos($vcr[$pvs]['rul'][$ctx], $ref) === false))
                  {
                     echo "$pvs $ctx $ref\n";
                     self::halt('Syntax', 'unexpected "'.$vcr[$ref]['dsc'].'"', $pos, $fnm);
                  }

                  if (($ref == 'de') && ($ctx != 'doc'))
                  { self::halt('Syntax', 'unexpected "'.$vcr[$ref]['dsc'].'"', $pos, $fnm); }
               // -------------------------------------------------------------

               // expression validation
               // -------------------------------------------------------------
//                  if (($clk > 1) && ($ctx == 'exp') && (($ref == 'os') || ($ref == 'as')))
//                  { self::halt('Expression', 'unexpected "'.$vcr[$ref]['dsc'].'"', $pos, $fnm); }
               // -------------------------------------------------------------
               }
            // ----------------------------------------------------------------

            // root level expression count
            // ----------------------------------------------------------------
               if (($ctx == 'doc') && ($ref == 'es'))
               { $rle++; }
            // ----------------------------------------------------------------

            // parse context
            // ----------------------------------------------------------------
               if (($ref !== null) && ($ctx != 'doc'))
               {
               // reset identifiers
               // -------------------------------------------------------------
                  $dfn = true;

                  if ($rle > 1)
                  {
                     $dfn = false;
                     $vrs = array_merge($vrs, $def);
                  }

                  $val = null;
               // -------------------------------------------------------------

               // check variable references
               // -------------------------------------------------------------
                  if (($ref == 'ir') || ($ref == 'ft'))
                  { $vrb .= $c; }
                  else
                  {
                     if (strlen($vrb) > 0)
                     {
//                        if (($vrb !== 'null') && (get_ctx($vrs, $vrb) === null))
//                        { self::halt('Reference', '"'.$vrb.'" is undefined', $pos, $fnm); }
                     }

                     $vrb = '';
                  }
               // -------------------------------------------------------------

               // property name
               // -------------------------------------------------------------
                  if ($ref == 'pn')
                  {
                     if (!isset($stl[$c]))
                     { $pnb .= $c; }
                     else
                     {
                        if ($str[$i -1] === '\\')
                        { $pnb .= $c; }
                     }
                  }
               // -------------------------------------------------------------

               // property value
               // -------------------------------------------------------------
                  if (strpos('exp obj arr', $ctx) !== false)
                  {
                     if (strpos('es qs ir nr ft ao so mo do pt ee', $ref) !== false)
                     {
                        if (isset($stl[$c]) && ($str[$i -1] != '\\'))
                        { $pvb .= $hld; }
                        else
                        { $pvb .= $c; }
                     }
                  }
               // -------------------------------------------------------------

               // parse buffers
               // -------------------------------------------------------------
                  if (strpos('os va oe as ae ld ed', $ref) !== false)
                  {
                  // create new entry in path array
                  // ----------------------------------------------------------
                     if (($ref == 'os') || ($ref == 'as'))
                     { $cpa[] = $hld; }
                  // ----------------------------------------------------------

                  // trim key name
                  // ----------------------------------------------------------
                     $pnb = trim($pnb);
                  // ----------------------------------------------------------

                  // get value
                  // ----------------------------------------------------------
                     if (strlen($pvb) > 0)
                     {
                        $val = $pvb;

                        if ($val == ')')
                        { $val = null; }
                     }
                  // ----------------------------------------------------------

                  // get path entry key
                  // ----------------------------------------------------------
                     end($cpa);
                     $cpk = key($cpa);
                  // ----------------------------------------------------------

                  // if path array has a key and any buffer is available
                  // ----------------------------------------------------------
                     if (($cpk !== null) && ((strlen($pnb) > 0) || (strlen($pvb) > 0)))
                     {
                     // path keys
                     // -------------------------------------------------------
                        if ($cpa[$cpk] === $hld)
                        {
                        // increment parent key if numeric
                        // ----------------------------------------------------
                           $idx = 0;

                           if ($cpk > 0)
                           { $idx = ($cpk -1); }

                           if (is_int($cpa[$idx]))
                           { $cpa[$idx]++; }
                           else
                           {
                              if ($cpa[$idx] === $hld)
                              { $cpa[$idx] = 0; }
                           }
                        // ----------------------------------------------------

                        // new array key
                        // ----------------------------------------------------
                           if ($ctx == 'arr')
                           { $cpa[$cpk] = 0; }
                        // ----------------------------------------------------

                        // new object key
                        // ----------------------------------------------------
                           if ($ctx == 'obj')
                           { $cpa[$cpk] = $pnb; }
                        // ----------------------------------------------------
                        }
                        else
                        {
                        // increment numerical key
                        // ----------------------------------------------------
                           if (is_int($cpa[$cpk]))
                           { $cpa[$cpk]++; }
                        // ----------------------------------------------------

                        // new object key
                        // ----------------------------------------------------
                           if (($ctx == 'obj') && (strlen($pnb) > 0))
                           { $cpa[$cpk] = $pnb; }
                        // ----------------------------------------------------
                        }
                     // -------------------------------------------------------


                     // halt on redefine
                     // -------------------------------------------------------
                        if ($dfn === true)
                        {
                           if ((self::get_ctx($vrs, $cpa) !== null) || (self::get_ctx($def, $cpa) !== null))
                           { self::halt('Reference', '"'.$cpa[$cpk].'" is already defined', $pos, $fnm); }
                        }
                        else
                        {
                           if (self::get_ctx($rsl, $cpa) !== null)
                           { self::halt('Reference', '"'.$cpa[$cpk].'" is already defined', $pos, $fnm); }
                        }
                     // -------------------------------------------------------


                     // set path and value only if value exists
                     // -------------------------------------------------------
                        if ($val !== null)
                        {
                           $val = self::parse_exp($val, $hld, $vrs);

                           if ($dfn === true)
                           {
//                                 echo 'def '.implode($cpa, '.')." = $val\n";
                              self::set_ctx($def, $cpa, $val);
                           }
                           else
                           {
//                                 echo 'rsl '.implode($cpa, '.')." = $val\n";
                              self::set_ctx($rsl, $cpa, $val);
                           }
                        }
                     // -------------------------------------------------------

                     // flush buffers
                     // -------------------------------------------------------
                        $pnb = '';
                        $pvb = '';
                     // -------------------------------------------------------
                     }
                  // ----------------------------------------------------------


                  // if path array has a key and level is closed
                  // ----------------------------------------------------------
                     if (($cpk !== null) && (($ref == 'oe') || ($ref == 'ae')))
                     { array_pop($cpa); }
                  // ----------------------------------------------------------
                  }
               // -------------------------------------------------------------
               }
            // ----------------------------------------------------------------


            // set/get current context
            // ----------------------------------------------------------------
               $cab = null;

               if (($qot === false) && ($ref !== null))
               {
                  if (isset($cac[$c]))
                  { $cab = $cac[$c][1]; }

                  if ($cab !== null)
                  {
                     if ($cab > 0)
                     { $lvl[] = $cac[$c][0]; }
                     else
                     {
                        if ($ctx == $cac[$c][0])
                        { $old = array_pop($lvl); }
                     }
                  }

                  $clk = (count($lvl) -1);
                  $ctx = $lvl[$clk];
               }
            // ----------------------------------------------------------------
            }
         // -------------------------------------------------------------------
         }
      // ----------------------------------------------------------------------


      // if only def is defined, return it, else return rsl
      // ----------------------------------------------------------------------
         if (count($rsl) < 1)
         { return $def; }
         else
         { return $rsl; }
      // ----------------------------------------------------------------------
      }
   // -------------------------------------------------------------------------


   // prepare context
   // -------------------------------------------------------------------------
      public function parse($dfn, $vrs=null, $obj=false)
      {
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
               echo 'JSAM Reference Error: "'.$dfn.'" does not exist';
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
            echo 'JSAM config & mime-type expected from: "'.$pth.'"';
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
            echo 'JSAM compiler for type: "'.$cmp.'" is undefined';
            exit;
         }

         return $rsl;
      }
   // -------------------------------------------------------------------------
   }
// ========================================================================================================

?>
