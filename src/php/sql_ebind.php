<?php
/* sql_ebind
 *
 * Enhanced name binding for SQL queries
 *
 * A normal parameter is that kind of thing that has a placeholder of '?'
 *
 * Adapted heavily from https://stackoverflow.com/a/11594332/7307768
 *
 * These placeholders must have ordinal positional correspondence with an
 * array of values to substibute for each '?'.  One-to-one correspondence
 * and in the exact order.
 *
 * This makes query maintenance painful and error-prone when doing things
 * like shifting clauses and adding conditions.
 *
 * By using named parameters, the order can be rearranged without breaking
 * the association, we gain flexibility and shed the ordinal correspondence
 * requirement at the expanse of having to name the parameters.  These
 * params are delimited with curly braces.  {:normal_param_name}
 *
 * Another feature was added.  Normally we can't have table or column names
 * be replaceable.  This function allows that flexibility by delimiting
 * those params with doubled curly braces. {{:abnormal_param_name}}
 *
 * Finally, there's the capability of 'including' the contents of another 
 * file.  {{{:filename}}}
 *
 * This new capability shouldn't introduce new exposure to SQL injection
 * since statements still have to make it through quoting and prepare
 * mechanisms.
 *
 * @param string $sql The sql query with params to be bound
 * @param Array  $bind  Assoc array of params to bind
 * @param string $bind_marker what to use
 *
 * @return Array(string sql with names replaced, array of normal params)
 */
function sql_ebind($sql, array $bind = array(), $bind_marker = '?') {

    // to hold $pattern matches
    $bind_matches = null;
    // to hold ordered list of replacements
    $ord_bind_list = Array();
    // limit to catch endless replacement loops
    $loop_limit = 1000;

    // prevend endless replacement
    $repeat = 0;

    // Phase 1:  inline replace from file(s)
    $pattern = "/{{{:([A-Za-z0-9\/._-]+)}}}/";
    do {
        if ($repeat++ > $loop_limit) {
            throw new Exception(__FUNCTION__ . ' repeat limit reached, check params for circular references');
        }
        $preg = preg_match_all($pattern, $sql . ' ', $matches);
        if ($preg !== 0 and $preg !== false) {
            // loop over matches
            foreach ($matches[1] as $key => $val) {
                $contents = '';
                if (file_exists($val)) {
                    $contents = file_get_contents($val);
                }
                $sql = str_replace('{{{:'.$val.'}}}', $contents, $sql);
            }
        }
    } while ($preg !== 0 and $preg !== false);

    // Phase 2:  Bind structural params
    $pattern = "/{{:[A-Za-z][A-Za-z0-9_]*}}/";
    // straight string substitution, don't add anything to $ord_bind_list
    // reset repeat count
    $repeat = 0;
    // iterate until no more double-curly-bracket expressions in $sql
    do {
        if ($repeat++ > $loop_limit) {
            throw new Exception(__FUNCTION__ . ' repeat limit reached, check params for circular references');
        }
        $preg = preg_match_all($pattern, $sql . ' ', $matches, PREG_OFFSET_CAPTURE);
        if ($preg !== 0 and $preg !== false) {
            // loop over matches
            foreach ($matches[0] as $key => $val) {
                $bind_matches[$key] = trim($val[0]);
            }
            foreach ($bind_matches as $field) {
                // if no match, set to empty
                // allows for empty clauses to be omitted
                $replaceiwth = empty($bind[$field]) ? '' : $bind[$field];
                $sql = str_replace($field, $replaceiwth, $sql);
            }
        }
        unset($bind_matches);
    } while ($preg !== 0 and $preg !== false);

    // Phase 3:  Bind normal params
    $pattern = "/{:[A-Za-z][A-Za-z0-9_]*}/";
    // reset repeat count
    $repeat = 0;
    // iterate until no more single-curly-bracket expressions in $sql
    do {
        if ($repeat++ > $loop_limit) {
            throw new YETIException(__FUNCTION__ . ' repeat limit reached, check params for circular references');
        }
        $preg = preg_match_all($pattern, $sql . ' ', $matches, PREG_OFFSET_CAPTURE);
        if ($preg !== 0 and $preg !== false) {
            foreach ($matches[0] as $key=>$val) {
                $bind_matches[$key] = trim($val[0]);
            }
            foreach ($bind_matches as $field) {
                if (is_array($bind[$field])) {
                    $sql = str_replace($field, implode(', ', array_fill(0, count($bind[$field]), $bind_marker)), $sql);
                    $ord_bind_list = array_merge($ord_bind_list, $bind[$field]);
                } else {
                    $sql = str_replace($field, $bind_marker, $sql);
                    $ord_bind_list[] = $bind[$field];
                }
            }
        }
    } while ($preg !== 0 and $preg !== false);

    return Array(
        'sql'    => $sql,
        'params' => $ord_bind_list
    );
}
