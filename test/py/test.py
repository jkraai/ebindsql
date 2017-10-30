if (__name__ == '__main__'):

    sql = ("SELECT \n"
            "    {{:table_01_PK}}\n"
            "  , lname\n"
            "  , fname\n"
            "FROM {{:table_01}}\n"
            "WHERE 1=1\n"
            "  AND ID BETWEEN  {:ID_cond_01} AND {:ID_cond_02}\n"
            "  AND lname LIKE {:lname_cond}\n"
            "ORDER BY {{:table_01}}.{{:table_01_PK}}")

    params = {
        '{{:table_01}}': 'dbo.table_name_01',
        '{{:table_01_PK}}': 'ID',
        '{:lname_cond}': '%mith',
        '{:ID_cond_01}': min('233', '926'),
        '{:ID_cond_02}': max('233', '926')
    }

    query_bound = sql_ebind(sql, params);

    print query_bound['sql']
    print query_bound['params']
