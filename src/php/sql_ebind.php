<?php
 /**
  * sql_ebind
  *
  * generic SQL enhanced named parameter binding
  *
  * Phase 1: Triple-curly-braced strings are replaced with contents of files
  *   like PHP's include()
  *   filename
  *   file can contain phase 1, phase 2, and phase 3 parts
  *
  * Phase 2: Double-curly-braced strings are replaced with strings passed in a mapping
  *   for DDL structural parts, like table or column names
  *   for things that '?' can't be used for
  *   value can contain double- and single-curly braced values
  *   value can contain phase 2 and phase 3 parts
  *   value can contain either
  *     atomic strings to stand for DB, schema, or table names
  *     arrays which get implod[ed](', ') for lists of, say, column names
  *
  * Phase 3: Single-curly-braced strings are replaced with SQL 'positional parameters'
  *   for atomic strings, dates, and numbers
  *   for regular ?-style sql replaceable parameters
  *   value can contain either
  *     atomic things like strings and ints
  *     arrays which get handled with " IN ( '?', '?', '?')
  *     associative arrays which get handled as key/value pairs for UPDATEs
  *
  * @param string $sql  The sql query with params to be bound
  * @param Array  $bind Assoc array of params to bind
  *
  * @return Array(string sql with names replaced, array of normal params)
  */
function sql_ebind($sql, array $bind = array()) {
    // TODO:  add support for param to silently replace
    // silent replacement as default behavior

    // positional parameter character
    $positional_parameter = '?';

    // to hold $pattern matches
    $bind_matches = null;
    // to hold ordinal (ordered) list of replacements
    $ord_bind_list = array();
    // limit to catch endless replacement loops
    $loop_limit = 100;

    // prevend endless replacement
    $repeat = 0;
    $repeat_msg = __FUNCTION__ . ' repeat limit reached, check params for circular references in Phase ';

    // loop over $bind once to gather hashes for phases
    $phase1 = array();                                                                                                                              
    $phase2 = array();                                                                                                                              
    $phase3 = array();
    foreach ($bind as $key => $val) {
        if (substr($key, 0, 4) == '{{{:') { // filename
            $phase1[$key] = $val;
        }
        else if (substr($key, 0, 3) == '{{:') {  // structural param
            $phase2[$key] = $val;
        }
        else if (substr($key, 0, 2) == '{:') {  // normal param
            $phase3[$key] = $val;
        }
        // else there's junk in $bind
    }

    // Phase 1:  inline replace from file(s)
    // {{{:filepath.ext}}}
    // iterate until no more triple-curly-bracket expressions in $sql
    do {
        if ($repeat++ > $loop_limit) {
            throw new Exception($repeat_msg . 1);
        }
        $subs = 0;
        foreach ($phase1 as $key => $val) {
            // skip if $key not found in $sql
            if (strpos($sql, $key) === -1) continue;
            $filename = $val;
            if (file_exists($filename)) {
                $contents = file_get_contents($filename);
            } else {
                $contents = '';
                trigger_error('Unable to locate ' . getcwd() . '/' . $filename);
            }
            $sql_p = str_replace($key, $contents, $sql);
            if ($sql_p != $sql) {
                $sql = $sql_p;
                $subs++;
            }
        }
    } while ($subs > 0);

    if (true) {
        // quietly wipe out remaining {{{:x}}} statements
        $sql = preg_replace('/{{{:[^}]+}}}/', '', $sql);
    }

    // Phase 2:  Bind structural params
    // '{{:placeholder}}' => 'replacement string'
    // straight string substitution, don't add anything to $ord_bind_list
    // reset repeat count
    $repeat = 0;
    // iterate until no more double-curly-bracket expressions in $sql
    do {
        if ($repeat++ > $loop_limit) {
            throw new Exception($repeat_msg . 2);
        }
        $subs = 0;
        // since we're not tracking positional replacements in this phase, loop over $phase2
        foreach ($phase2 as $key => $val) {
            if (strpos($sql, $key) === -1) continue;
            // add special handling if $val is array
            if (is_array($val) && count($val) > -1) {
                // special handling of arrays, implode(', ')
                $sql_p = str_replace($key, implode(', ', $val), $sql);
            }
            else {
                // simple string
                $sql_p = str_replace($key, $val, $sql);
            }
            if ($sql_p != $sql) {
                $sql = $sql_p;
                $subs++;
            }
        }
    } while ($subs > 0);

    if (true) {
        // quietly wipe out remaining {{:x}} statements
        $sql = preg_replace('/{{:[^}]+}}/', '', $sql);
    }

    // Phase 3:  Bind normal params
    // '{:placeholder}' => 'replacement string'
    $pattern = '/{:[A-Za-z][A-Za-z0-9_]*}/';
    // reset repeat count
    $repeat = 0;
    // iterate until no more single-curly-bracket expressions in $sql
    do {
        if ($repeat++ > $loop_limit) {
            throw new Exception($repeat_msg . 3);
        }
        $preg = preg_match_all($pattern, $sql . ' ', $matches, PREG_OFFSET_CAPTURE);
        if ($preg !== 0 and $preg !== false) {
            // loop over matches, building a simple, ordered array to track replaceable params
            foreach ($matches[0] as $key=>$val) {
                $bind_matches[$key] = trim($val[0]);
            }
            // loop over the ordered array
            foreach ($bind_matches as $key) {
                // fail silently and let the DB complain if something is missing
                $val = @$phase3[$key];

                if ( is_array($val) && @array_keys($val) !== range(0, count($val) - 1) ) {

                    // special handling of assoc arrays
                    // associative array, join up $k => $v into key = 'val'
                    // "implode" & trim up
                    $join = $val;
                    array_walk($join, function (&$i, $k) {
                        return $i = "$k=?, ";
                    });
                    $join = trim(rtrim(implode($join, ""), ', '));
                    // add to $sql & ord_bind_list
                    $sql = str_replace($key, $join, $sql);
                    $ord_bind_list = array_merge($ord_bind_list, array_values($val));
                }
                else if (is_array($val) && count($val) > -1) {
                    // special handling of arrays, turn them into comma-separated list
                    $sql = str_replace($key, implode(', ', array_fill(0, count($val), $positional_parameter)), $sql);
                    $ord_bind_list = array_merge($ord_bind_list, $val);
                } else {
                    $sql = str_replace($key, $positional_parameter, $sql);
                    $ord_bind_list[] = $val;
                }
            }
        }
    } while ($preg !== 0 and $preg !== false);

    // trim all-blank lines
    $sql = preg_replace("/\n\s*\n/", "\n", $sql);
    // trim outer spaces
    $sql = trim($sql);

    return Array(
        'sql'    => $sql,
        'params' => $ord_bind_list
    );
}

