import re
import sys
import types

def sql_ebind(sql, bind, bind_marker = '?'):

    """sql_ebind

    Enhanced name binding for SQL queries implemented in js

    Args:
        sql (string): The sql query with params to be bound
        bind (Dict): Dict of params to bind
        bind_marker (str): string to use for parameter placeholder

    Returns:
        Dict: {
            sql:    (string) ready-to-prepare SQL, 
            params: (List)   ordered list of params for sql
        }
    """

    bind_matches = []
    ord_bind_list = []
    loop_limit = 10

    # Bind abnormal params, such as structural objects
    pattern = re.compile("{{:[A-Za-z][A-Za-z0-9_]*}}")

     # prevend endless replacement
    repeat = 0;
    while True:
        matches_length = 0
        repeat += 1
        if repeat > loop_limit:
            raise ValueError(sys._getframe().f_code.co_name + ' repeat limit reached, check params for circular references')
        matches = re.findall(pattern, sql)
        matches_length = len(matches)
        # loop first to ???
        for i in range(len(matches)):
            match = matches[i]
            # to preserve order & position
            bind_matches.append(match)
            # no ? substitution for these parameters, direct substitution
            sql = re.sub(bind_matches[i], bind[bind_matches[i]], sql)
        if (matches_length == 0):
            break

    # reset
    bind_matches = []
    repeat = 0

    # Bind normal params
    pattern = re.compile("{:[A-Za-z][A-Za-z0-9_]*}")
    # iterate until no more single-curly-bracket expressions in $sql
    while True:
        matches_length = 0
        repeat += 1
        if repeat > loop_limit:
            raise ValueError(sys._getframe().f_code.co_name + ' repeat limit reached, check params for circular references')
        matches = re.findall(pattern, sql)
        matches_length = len(matches)
        for i in range(len(matches)):
            match = matches[i]
            bind_matches.append(match)
            field = bind_matches[i];
            if type(bind[field]) is types.ListType:
                sql = re.sub(
                        bind_matches[i],
                        ', '.join([bind_marker] * bind[field].length),
                        sql
                    )
                ord_bind_list = ord_bind_list + bind[field]
            else:
                sql = re.sub(bind_matches[i], bind_marker, sql)
                ord_bind_list.append(bind[field]);
        if (matches_length == 0):
            break

    return {'sql': sql, 'params': ord_bind_list}
