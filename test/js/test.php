sql = [
    "SELECT ID, lname, fname",
    "FROM dbo.table_name_01",
    "WHERE ID = {:ID_cond}",
    "AND lname LIKE {:lname_cond}"
].join(" \n");

params = {
    '{:lname_cond}': '%mith',
    '{:ID_cond}': '2c92c0f95ccf2766015cd11c48a93690',
};

sql_ebind(sql, params);
