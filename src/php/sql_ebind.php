// sql_ebind
//
// Enhanced name binding for SQL queries implemented in js
//
// @param string $sql The sql query with params to be bound
// @param Array  $bind  Assoc array of params to bind
// @param string $bind_marker what to use
//
// @return Object(string sql with names replaced, array of normal params)
//
function sql_ebind(sql, bind, bind_marker) {
    'use strict';
        if(! bind_marker) {
      bind_marker = '?';
    }
    var bind_matches = null;
    var ord_bind_list = [];
    var loop_limit = 1000;

    // Bind abnormal params
    // straight string substitution, don't add anything to $ord_bind_list
    var pattern = new RegExp("{{:[A-Za-z][A-Za-z0-9_]*}}");

    // prevend endless replacement
    var repeat = 0;
    var i, field, matches;

    // iterate until no more double-curly-bracket expressions in $sql
    do {
        if (repeat++ > loop_limit) {
            throw arguments.callee.name + ' repeat limit reached, check params';
        }
        // $preg = preg_match_all($pattern, $sql . ' ', $matches, PREG_OFFSET_CAPTURE);
        matches = sql.match(pattern);
        if (matches.length > 0) {
            for(i=0; i<matches.length; i++) {
                bind_matches[i] = (matches[i]).trim();
            }
            // foreach($bind_matches as $field)
            for(i=0; i<bind_matches.length; i++) {
                // sorry, no arrays allowed here
                sql.replace(new RegExp(bind_matches[i]), bind[bind_matches[i]]);
                // no ? substitution for these parameters, direct substitution
            }
        }
    } while (matches.length > 0);

    // reset 
    bind_matches = [];
    repeat = 0;

    // Bind normal params
    pattern = new RegExp("/[^']{:[A-Za-z][A-Za-z0-9_]*}[^']/");
    // iterate until no more single-curly-bracket expressions in $sql
    do {
        if (repeat++ > loop_limit) {
            throw arguments.callee.name + ' repeat limit reached, check params';
        }
        matches = sql.match(pattern);
        if (matches.length > 0) {
            for(i=0; i<matches.length; i++) {
                bind_matches[i] = (matches[i]).trim();
            }
            // foreach($bind_matches as $field) 
            for(i=0; i<bind_matches.length; i++) {
                field = bind_matches[i];
                if ((bind[field]).isArray()) {
                    sql.replace(
                        new RegExp(bind_matches[i], 'g'), 
                        Array(bind[field].length)
                            .fill(bind_marker)
                            .join(', ')
                    );
                    ord_bind_list = ord_bind_list.concat(bind[field]);
                } else {
                    sql.replace(new RegExp(bind_matches[i]), bind[bind_matches[i]]);
                    ord_bind_list.push(bind[field]);
                }
            }
        }
    } while (matches.length > 0);

    return {'sql': sql, 'params': ord_bind_list};
}
