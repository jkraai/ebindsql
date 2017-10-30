sql = [
    "SELECT ID, lname, fname",
    "FROM dbo.table_name_01",
    "WHERE ID = {:ID_cond}",
    "AND lname LIKE {:lname_cond}"
].join(" \n");

params = {
    '{:lname_cond}': '%mith',
    '{:ID_cond}': '4d7ab00ae2561cbc1a58a1ccbf0192cf',
};

sql_ebind(sql, params);
