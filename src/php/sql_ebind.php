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
 * consider a more sophisticated strategy a la Crockford's fulfill:
 *      1.  evaluate the replacement
 *          if a functional function, and we're supporting lazy evaluation, prep for that
 *          if it's a function, evaluate the function to get a scalar
 *          if it's a scalar, use it as-is
 *      2.  "If an encoder object was provided, call [one] of its functions. If the encoder is a function, call it."
 *      3.  if it's not a string, cast it to a string
 *      4.  "If the replacement is a string, then do the substitution, otherwise, leave the symbolic variable in its original state."
 *
 * @param string $sql  The sql query with params to be bound
 * @param Array  $bind Assoc array of params to bind
 *
 * @return Array(string sql with names replaced, array of normal params)
 */
function sql_ebind(
    string $sql,
    array $bind = [],
    array $config = []
) : array {
    // skip phase 1
    $skip_phase_1 = true;
    // positional parameter character
    $positional_parameter = '?';
    // whether to quietly replace unreplaced replacements with empty strings
    $quietly = true;
    // to hold $pattern matches
    $bind_matches = [];
    // to hold ordinal (ordered) list of replacements
    $ord_bind_list = [];
    // limit to catch endless replacement loops
    $loop_limit = 100;
    // prevend endless replacement
    $repeat = 0;
    $repeat_msg = __FUNCTION__ . ' repeat limit reached, check params for circular references in Phase ';

    foreach ($config as $key => $val) {
        switch ($key) {
            case 'skip_phase_1':
                $skip_phase_1 = (bool) $val;
                break;
            case 'positional_parameter':
                $positional_parameter = (string) $val;
                break;
            case 'quietly':
                $quietly = (bool) $val;
                break;
            case 'loop_limit':
                $loop_limit = (int) $val;
                break;
            default:
                trigger_error('Unknown config key ' . $key);
        }
    }

    // Phase 1 is security-wise risky, so skip it by default
    if (! $skip_phase_1) {
        // Phase 1:  inline replace from file(s)
        // {{{:filepath.ext}}}
        // $pattern = '/{{{:[A-Za-z][A-Za-z0-9_/.-]*}}}/';
        $pattern = '/{{{:[^}]+}}}/';
        // iterate until no more triple-curly-bracket expressions in $sql
        do {
            if ($repeat++ > $loop_limit) { throw new Exception($repeat_msg . 1); }
            $preg_match_count = preg_match_all($pattern, $sql . ' ', $matches);
            $subs = false;
            foreach ($matches[0] as $key) {
                // skip this match if not found in $bind
                $val = $bind[$key] ?? null;
                if ($val === NULL) continue;

                $filename = $val;
                if (file_exists($filename)) {
                    $contents = file_get_contents($filename);
                } else {
                    $contents = '';
                    trigger_error('Unable to locate ' . $filename);
                }
                $sql_p = str_replace($key, $contents, $sql);
                if ($sql_p != $sql) {
                    $sql = $sql_p;
                    $subs = true;
                }
            }
        } while ($subs);

        // quietly wipe out remaining {{{:x}}} statements
        if ($quietly) { $sql = preg_replace($pattern, '', $sql); }
    }

    // Phase 2:  Bind structural params
    // '{{:placeholder}}' => 'replacement string'
    // straight string substitution, don't add anything to $ord_bind_list
    $pattern = '/{{:[A-Za-z][A-Za-z0-9_]*}}/';
    // reset repeat count
    $repeat = 0;
    // iterate until no more double-curly-bracket expressions in $sql
    do {
        if ($repeat++ > $loop_limit) { throw new Exception($repeat_msg . 2); }
        $subs = false;
        $preg_match_count = preg_match_all($pattern, $sql . ' ', $matches);
        if ($preg_match_count !== 0 && $preg_match_count !== false) {
            // loop over matches, building a simple, ordered array to track replaceable params
            foreach ($matches[0] as $key) {
                // skip this match if not found in $bind
                $val = @$bind[$key];
                if ($val === NULL) continue;
                // special handling if $val is array
                $sql_p = is_array($val)
                    ? str_replace($key, implode(', ', $val), $sql)
                    : str_replace($key, $val, $sql);
                if ($sql_p != $sql) {
                    $sql = $sql_p;
                    $subs = true;
                }
            }
        }
    } while ($subs);

    // quietly wipe out remaining {{:x}} statements
    if ($quietly) { $sql = preg_replace($pattern, '', $sql); }

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
        $preg_match_count = preg_match_all($pattern, $sql . ' ', $matches, PREG_OFFSET_CAPTURE);
        if ($preg_match_count !== 0 && $preg_match_count !== false) {
            // loop over matches, building a simple, ordered array to track replaceable params
            foreach ($matches[0] as $key=>$val) {
                $bind_matches[$key] = trim($val[0]);
            }

            // loop over the ordered array
            foreach ($bind_matches as $key) {
                // fail silently and let the DB complain if something is missing
                $val = @$bind[$key];

                if (is_array($val) && @array_keys($val) !== range(0, count($val) - 1)) {
                    // special handling of assoc arrays
                    // associative array, join up $k => $v into key = 'val'
                    // "implode" & trim up
                    $join = $val;
                    array_walk($join, static fn(&$i, $k) => $i = "{$k}=?, ");
                    $join = trim(rtrim(implode("", $join), ', '));
                    // add to $sql & ord_bind_list
                    $sql = str_replace($key, $join, $sql);
                    $ord_bind_list = array_merge($ord_bind_list, array_values($val));
                } elseif (is_array($val) && count($val) > -1) {
                    // special handling of arrays, turn them into comma-separated list
                    $sql = str_replace($key, implode(', ', array_fill(0, count($val), $positional_parameter)), $sql);
                    $ord_bind_list = array_merge($ord_bind_list, $val);
                } else {
                    $sql = str_replace($key, $positional_parameter, $sql);
                    $ord_bind_list[] = $val;
                }
            }
        }
    } while ($preg_match_count > 0 && $preg_match_count !== false);

    // trim all-blank lines
    $sql = preg_replace("/\n\s*\n/", "\n", $sql);
    // trim outer spaces
    $sql = trim($sql);

    return Array(
        'sql'    => $sql,
        'params' => $ord_bind_list
    );
}
