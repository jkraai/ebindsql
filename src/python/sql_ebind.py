import re
import sys
import os

def sql_ebind(sql, bind = {}, bind_marker = '?'):
    """
    sql_ebind

    see sql_ebind doc on GitHub

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

    # to hold pattern matches
    bind_matches = {}
    # to hold ordered list of replacements
    ord_bind_list = []
    # limit to catch endless replacement loops
    loop_limit = 1000
    subs = 0

    # loop over bind once to gather hashes for phases
    phase1 = {}
    phase2 = {}
    phase3 = {}
    for key in bind.iterkeys():
        val = bind[key]
        if (type(val) != 'function'):  # key is an object
            continue
        if (key.index('{{{:') == 0): # filename
            phase1[key] = val
        elif (key.index('{{:') == 0):  # structural param
            phase2[key] = val
        elif (key.index('{:') == 0):  # normal param
            phase3[key] = val
        # else there's junk in bind

    # Phase 1:  inline replace from file
    repeat = 0
    while True:
        if repeat > loop_limit:
            raise ValueError(sys._getframe().f_code.co_name + ' repeat limit reached, check params for circular references')
        subs = 0
        for key in phase1.iterkeys():
            val = phase1[key]
            if (sql.index(key) > -1):
                filename = val
                contents = ''
                if (os.path.isfile(filename)):
                    with open(filename) as f:
                        contents = f.read()
                else:
                    raise ValueError('Unable to locate ' + os.getcwd() + '/' + filename)
                sql = sql.replace(key, contents)
                subs += 1

    # Phase 2:  Bind abnormal params, such as structural objects
    repeat = 0
    while True:
        if repeat > loop_limit:
            raise ValueError(sys._getframe().f_code.co_name + ' repeat limit reached, check params for circular references')
        subs = 0
        for key in phase2.iterkeys():
            val = phase2[key]
            if (sql.index(key) > -1):
                sql = sql.replace(key, val)
                subs += 1

    # Phase 3:  Bind normal params
    pattern = re.compile("{:[A-Za-z][A-Za-z0-9_]*}")
    bind_matches = []
    repeat = 0
    # iterate until no more single-curly-bracket expressions in sql
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
            field = bind_matches[i]
            if type(phase3[field]) == list:
                sql = re.sub(bind_matches[i], (', ').join([bind_marker] * phase3[field].length), sql)
                ord_bind_list = ord_bind_list + phase3[field]
            else:
                sql = re.sub(bind_matches[i], bind_marker, sql)
                ord_bind_list.append(phase3[field])
            subs += 1
        if (matches_length == 0):
            break

    return {'sql': sql, 'params': ord_bind_list}
