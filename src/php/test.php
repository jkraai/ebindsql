<?php

require_once './sql_ebind.php';

$bound = sql_ebind(
    "SELECT id, lname, fname FROM people where lname = {:lname}",
    array('{:lname}' => 'Jones')
);
$encoded = json_encode($bound);
$expected = '{"sql":"SELECT id, lname, fname FROM people where lname = ?","params":["Jones"]}';
if ($encoded == $expected) { echo "query success!\n"; } else { echo "query failed\n"; };


// Usage for simple parameters

$sql = implode(" \n", Array(
    "SELECT ID, lname, fname",
    "FROM dbo.table_name_01",
    "WHERE ID = {:ID_cond}",
    "  AND lname LIKE {:lname_cond}"
));

$params = Array(
    '{:lname_cond}' => '%mith',
    '{:ID_cond}' => '4d7ab00ae2561cbc1a58a1ccbf0192cf',
);

$bound = sql_ebind($sql, $params);
$encoded = json_encode($bound);
$expected = '{"sql":"SELECT ID, lname, fname \nFROM dbo.table_name_01 \nWHERE ID = ? \n  AND lname LIKE ?","params":["4d7ab00ae2561cbc1a58a1ccbf0192cf","%mith"]}';
if ($encoded == $expected) { echo "query success!\n"; } else { echo "query failed\n"; };

// will give
//
// array(2) {
//   'sql' =>
//   string(90) "SELECT ID, lname, fname
//     FROM dbo.table_name_01
//     WHERE ID = ?
//       AND lname LIKE ?"
//   'params' =>
//   array(2) {
//     [0] =>
//     string(32) "4d7ab00ae2561cbc1a58a1ccbf0192cf"
//     [1] =>
//     string(5) "%mith"
//   }
// }


// Simple Replacement Macros & Readability

$sql = implode(" \n", Array(
    "SELECT {{:YYYYMM}} AS YYYYMM, COUNT(*) AS KOUNT",
    "FROM dbo.Accounts",
    "GROUP BY {{:YYYYMM}}",
));
$params = Array(
    '{{:YYYYMM}}' => "CAST(YEAR({{:dt_field}}) AS VARCHAR(4)) + '-' + RIGHT('00' + CAST(MONTH({{:dt_field}}) AS VARCHAR(2)), 2)",
    '{{:dt_field}}' => 'CreatedDate',
);
$bound = sql_ebind($sql, $params);
$encoded = json_encode($bound);
$expected = '{"sql":"SELECT CAST(YEAR(CreatedDate) AS VARCHAR(4)) + \'-\' + RIGHT(\'00\' + CAST(MONTH(CreatedDate) AS VARCHAR(2)), 2) AS YYYYMM, COUNT(*) AS KOUNT \nFROM dbo.Accounts \nGROUP BY CAST(YEAR(CreatedDate) AS VARCHAR(4)) + \'-\' + RIGHT(\'00\' + CAST(MONTH(CreatedDate) AS VARCHAR(2)), 2)","params":[]}';
if ($encoded == $expected) { echo "query success!\n"; } else { echo "query failed\n"; };

// Structural parameter and replacement looping

$sql = implode(" \n", Array(
    "SELECT {{:colname_01}}, lname, fname",
    "FROM {{:table_name}}",
    "WHERE ID = {:wherecond_01}",
    "  AND lname LIKE {:wherecond_02}",
    "ORDER BY {{:colname_01_PrimaryKey}}",
));
$params = Array(
    '{{;table_name}}' => 'dbo.table_name_01',
    '{{:colname_01}}' => 'ID',
    '{{:colname_01_PrimaryKey}}' => '{{:colname_01}}',
    '{:wherecond_01}' => '4d7ab00ae2561cbc1a58a1ccbf0192cf',
    '{:wherecond_02}' => '%mith',
);
$bound = sql_ebind($sql, $params);
$encoded = json_encode($bound);
$expected = '{"sql":"SELECT ID, lname, fname \nFROM  \nWHERE ID = ? \n  AND lname LIKE ? \nORDER BY ID","params":["4d7ab00ae2561cbc1a58a1ccbf0192cf","%mith"]}';
if ($encoded == $expected) { echo "query success!\n"; } else { echo "query failed\n"; };

// will give
//
// array(2) {
//   'sql' =>
//   string(100) "SELECT ID, lname, fname
//     FROM dbo.table_name_01
//     WHERE ID = ?
//       AND lname LIKE ?
//     ORDER BY ID"
//   'params' =>
//   array(2) {
//     [0] =>
//     string(32) "4d7ab00ae2561cbc1a58a1ccbf0192cf"
//     [1] =>
//     string(5) "%mith"
//   }
// }

// In the above example, note {{:colname_01_PrimaryKey}} -> {{:colname_01}} -> 'ID'

// Number of parameters unknown ahead of time

$sql = <<< EOQ
SELECT {{:field_name}}
FROM {{:table_name}}
WHERE {{:field_name}} = {:wherecond_01}
  OR field_name_02 IN ( {:wherecond_02} )
EOQ;

$params = Array(
    '{{:field_name}}' => 'ID',
    '{{:table_name}}' => 'dbo.table_name_01',
    '{:wherecond_01}' => '4d7ab00ae2561cbc1a58a1ccbf0192cf',
    '{:wherecond_02}' => Array(3, 5, 7),
);
$bound = sql_ebind($sql, $params);
$encoded = json_encode($bound);
$expected = '{"sql":"SELECT ID\r\nFROM dbo.table_name_01\r\nWHERE ID = ?\r\n  OR field_name_02 IN ( ?, ?, ? )","params":["4d7ab00ae2561cbc1a58a1ccbf0192cf",3,5,7]}';
if ($encoded == $expected) { echo "query success!\n"; } else { echo "query failed\n"; };

// will give
//
// {
//     "sql":"SELECT ID
//         FROM dbo.table_name_01
//         WHERE ID = ?
//           OR field_name_02 IN ( ?, ?, ? )",
//     "params":["4d7ab00ae2561cbc1a58a1ccbf0192cf",3,5,7]
// }


// Multiple columns

$sql = implode(" \n", Array(
    "SELECT {{:field_names}}",
    "FROM {{:table_name}}",
    "WHERE {{:id_field_name}} = {:wherecond_01}",
    "ORDER BY {{:field_names}}",
));
$params = Array(
    '{{:field_names}}'   => 'col1, col2, col3',
    '{{:id_field_name}}' => 'col1',
    '{{:table_name}}'    => 'dbo.table_name_01',
    '{:wherecond_01}'    => 1729
);
$bound = sql_ebind($sql, $params);
$encoded = json_encode($bound);
$expected = '{"sql":"SELECT col1, col2, col3 \nFROM dbo.table_name_01 \nWHERE col1 = ? \nORDER BY col1, col2, col3","params":[1729]}';
if ($encoded == $expected) { echo "query success!\n"; } else { echo "query failed\n"; };

// will give
//
// array(2) {
//   'sql' =>
//   string(69) "SELECT col1, col2, col3
//     FROM dbo.table_name_01
//     WHERE col1 = ?
//     ORDER BY col1, col2, col3"
//   'params' =>
//   array(1) {
//     [0] =>
//     int(1729)
//   }
// }



$params = Array(
    '{{:id_field_name}}' => 'col1',
    '{{:table_name}}'    => 'dbo.table_name_01',
);
$update = implode(" \n", Array(
    "UPDATE {{:table_name}}",
    "SET {{:id_field_name}} = {:val_new}",
    "WHERE {{:id_field_name}} = {:val_old}",
));
$params['{:val_new}'] = '~intermediate~';
$params['{:val_old}'] = 'A';
$bound = sql_ebind($update, $params);var_dump($bound); echo PHP_EOL;

$params['{:val_new}'] = 'A';
$params['{:val_old}'] = 'B';
$bound = sql_ebind($update, $params);var_dump($bound); echo PHP_EOL;

$params['{:val_new}'] = 'B';
$params['{:val_old}'] = '~intermediate~';
$bound = sql_ebind($update, $params);var_dump($bound); echo PHP_EOL;





// Over General SELECT query builder

$sql = '{{:GEN_SQL_SELECT}}';

$gen_sql_select = <<<'EOS'
{{:WITH_clause}}
SELECT {{:field_list}}
{{:FROM_clause}}
{{:WHERE_clause}}
{{:GROUPBY_clause}}
{{:HAVING_clause}}
{{:ORDERBY_clause}}
EOS;

$sql_params = Array(
    '{{:GEN_SQL_SELECT}}'      => $gen_sql_select,
    '{{:WITH_clause}}'         => '',
    '{{:FROM_clause}}'         => 'FROM {{:table_source}}',
    '{{:WHERE_clause}}'        => 'WHERE {{:wsearch_condition}}',
    '{{:GROUPBY_clause}}'      => '',
    '{{:HAVING_clause}}'       => '',
    '{{:ORDERBY_clause}}'      => 'ORDER BY {{:order_expression}}',
    '{{:field_list}}'          => '*',
    '{{:table_source}}'        => 'dbo.{{:table_name}}',
    '{{:wsearch_condition}}'   => '{{:id_col}} = {:ID}',
    '{{:group_by_expression}}' => '',
    '{{:hsearch_condition}}'   => '',
    '{{:order_expression}}'    => '{{:id_col}}',
);

// add some more params
$sql_params['{{:table_name}}'] = 'Account';
$sql_params['{{:id_col}}'] = 'ID';

// fill in the actual ID being sought
$sql_params['{:ID}'] = 9;

// bind names
$bound = sql_ebind($sql, $sql_params);
$encoded = json_encode($bound);
$expected = '{"sql":"SELECT *\r\nFROM dbo.Account\r\nWHERE ID = ?\r\nORDER BY ID","params":[9]}';
if ($encoded == $expected) { echo "query success!\n"; } else { echo "query failed\n"; };

// will give
//
// array(2) {
//   'sql' =>
//   string(58) "SELECT * FROM dbo.Account WHERE ID = ? ORDER BY ID"
//   'params' =>
//   array(1) {
//     [0] =>
//     int(9)
//   }
// }


// normal query, with array support

// like we're using the results of a query to do another
$row = array(
    'ID'   => '192837465',
    'col1' => 'val1',
    'col2' => 'val2',
    'col3' => 'val3',
);

$sql = implode(" \n", Array(
    'SELECT {{:cols}}',
    'FROM {{:table}}',
    'WHERE {{:id_col}} = {:id}'
));

// get the ID to update
$row_id = $row['ID'];

$params = Array(
    '{{:cols}}'   => array_keys($row),
    '{{:table}}'  => 'dbo.some_table',
    '{{:id_col}}' => 'ID',
    '{:id}'       => $row_id,
);
$bound = sql_ebind($sql, $params);
$encoded = json_encode($bound);
$expected = '{"sql":"SELECT ID, col1, col2, col3 \nFROM dbo.some_table \nWHERE ID = ?","params":["192837465"]}';
if ($encoded == $expected) { echo "query success!\n"; } else { echo "query failed\n"; };


// Another INSERT, with array support

$row = array(
    'ID'   => '192837465',
    'col1' => 'val1',
    'col2' => 'val2',
    'col3' => 'val3',
);

$sql = implode(" \n", Array(
    'INSERT INTO {{:table}}',
    '  ( {{:cols}} )',
    '  ( {:values} )',
));

$params = Array(
    '{{:table}}' => 'dbo.some_table',
    '{{:cols}}'  => array_keys($row),
    '{:values}'  => array_values($row),
);
$bound = sql_ebind($sql, $params);
$encoded = json_encode($bound);
$expected = '{"sql":"INSERT INTO dbo.some_table \n  ( ID, col1, col2, col3 ) \n  ( ?, ?, ?, ? )","params":["192837465","val1","val2","val3"]}';
if ($encoded == $expected) { echo "query success!\n"; } else { echo "query failed\n"; };


// UPDATE with single column key and array support

$row = array(
    'ID'   => '192837465',
    'col1' => 'val1',
    'col2' => 'val2',
    'col3' => 'val3',
);

$sql = implode(" \n", Array(
    'UPDATE {{:table}}',
    'SET {:row_kv}',
    'WHERE {{:id_col}} = {:id}'
));

// get the ID to update
$row_id = $row['ID'];
// but don't try to overwrite the primary key
unset($row['ID']);

$params = Array(
    '{{:table}}'  => 'dbo.some_table',
    '{:row_kv}'   => $row,
    '{{:id_col}}' => 'ID',
    '{:id}'       => $row_id,
);

$bound = sql_ebind($sql, $params);
$encoded = json_encode($bound);
$expected = '{"sql":"UPDATE dbo.some_table \nSET col1=?, col2=?, col3=? \nWHERE ID = ?","params":["val1","val2","val3","192837465"]}';
if ($encoded == $expected) { echo "query success!\n"; } else { echo "query failed\n"; };


// UPDATE with composite primary key and array support

function UPDATE_composite_pk($table_name, $pk_cols, $row) {

    $sql = implode(" \n", array(
        'UPDATE {{:table}}',
        'SET {:set_clause}',
        'WHERE {{:pk_where}}',
    ));

    $params = array(
        '{{:table}}'        => $table_name,
        '{:row_new_values}' => array_values($row),
        '{:set_clause}'     => $row,
    );

    // build and add {{:pk_where}} to $params
    $pk_where = array();
    foreach($pk_cols as $pk_col) {
        $pk_where[] = "$pk_col = {:pk_$pk_col}";
        $params["{:pk_$pk_col}"] = $row[$pk_col];
    }
    $pk_where = implode(' AND ', $pk_where);
    $params['{{:pk_where}}'] = $pk_where;

    return sql_ebind($sql, $params);
}

$row = array(
    'ID'   => '192837465',
    'col1' => 'val1',
    'col2' => 'val2',
    'col3' => 'val3',
);

$bound = UPDATE_composite_pk('dbo.some_table', array('ID', 'col3'), $row);
$encoded = json_encode($bound);
$expected = '{"sql":"UPDATE dbo.some_table \nSET ID=?, col1=?, col2=?, col3=? \nWHERE ID = ? AND col3 = ?","params":["192837465","val1","val2","val3","192837465","val3"]}';
if ($encoded == $expected) { echo "query success!\n"; } else { echo "query failed\n"; };


// generic t-sql UPSERT, as an extension of UPDATE_composite_pk

/**
 * UPSERT
 *
 * returns an insert-or-updae string of sql and corresponding parameters
 * suitable for passing to server prepare routine
 *
 * calculated primary keys not supported
 *
 * @param string $table_name MSSQL DB resource handle
 * @param array  $pk_cols    array of names of columns that comprise the table's primary key
 * @param array  $row        assoc array of colname => value pairs
 *
 * @return array(sql, array(positional parameters))
 */
function UPSERT($table_name, $pk_cols, $row) {

    $sql = implode(" \n", array(
        'IF (NOT EXISTS(',
        '  SELECT * FROM {{:table}} WHERE {{:pk_where}} )',
        ')',
        'BEGIN',
        '  INSERT INTO {{:table}} ( {{:table_columns}} )',
        '  VALUES( {:row_new_values} )',
        'END',
        'ELSE',
        'BEGIN',
        '  UPDATE {{:table}}',
        '  SET {:set_clause}',
        '  WHERE {{:pk_where}}',
        'END',
    ));

    $params = array(
        '{{:table}}'         => $table_name,
        '{{:table_columns}}' => array_keys($row),
        '{:row_new_values}'  => array_values($row),
        '{:set_clause}'      => $row,
    );

    // build and add {{:pk_where}} to $params
    $pk_where = array();
    foreach($pk_cols as $pk_col) {
        $pk_where[] = "$pk_col = {:pk_$pk_col}";
        $params["{:pk_$pk_col}"] = $row[$pk_col];
    }
    $pk_where = implode(' AND ', $pk_where);
    $params['{{:pk_where}}'] = $pk_where;

    return sql_ebind($sql, $params);
}

$row = array(
    'ID'   => '192837465',
    'col1' => 'val1',
    'col2' => 'val2',
    'col3' => 'val3',
);


$bound = UPSERT('dbo.some_table', array('ID'), $row);
$encoded = json_encode($bound);
$expected = '{"sql":"IF (NOT EXISTS( \n  SELECT * FROM dbo.some_table WHERE ID = ? ) \n) \nBEGIN \n  INSERT INTO dbo.some_table ( ID, col1, col2, col3 ) \n  VALUES( ?, ?, ?, ? ) \nEND \nELSE \nBEGIN \n  UPDATE dbo.some_table \n  SET ID=?, col1=?, col2=?, col3=? \n  WHERE ID = ? \nEND","params":["192837465","192837465","val1","val2","val3","192837465","val1","val2","val3","192837465"]}';
if ($encoded == $expected) { echo "query success!\n"; } else { echo "query failed\n"; };

$sql = implode(" \n", [
    "ALTER TABLE {{:foreign_schema}}.{{:foreign_table}}",
    "   ADD CONSTRAINT FK_{{:primary_schema}}_{{:primary_table}}_{{:foreign_schema}}_{{:foreign_table}} FOREIGN KEY (TempID)",
    "       REFERENCES Sales.SalesReason (SalesReasonID)",
    "       {{:on_delete}}",
    "       {{:on_update}}",
]);

$params = [
    '{{:primary_schema}}' => 'Sales',
    '{{:primary_table}}' => 'TempSalesReason',
    '{{:primary_coldef}}' => 'TempId',
    '{{:foreign_schema}}' => 'Sales',
    '{{:foreign_table}}' => 'SalesReason',
    '{{:foreign_coldef}}' => 'SalesReasonID',
    '{{:on_delete}}' => 'ON DELETE CASCADE',
    '{{:on_update}}' => 'ON UPDATE CASCADE',
];

$bound = sql_ebind($sql, $params);
$encoded = json_encode($bound);
$expected = '{"sql":"ALTER TABLE Sales.SalesReason \n   ADD CONSTRAINT FK_Sales_TempSalesReason_Sales_SalesReason FOREIGN KEY (TempID) \n       REFERENCES Sales.SalesReason (SalesReasonID) \n       ON DELETE CASCADE \n       ON UPDATE CASCADE","params":[]}query failed';
if ($encoded == $expected) { echo "query success!\n"; } else { echo "query failed\n"; };





$sql = implode(" \n", [
    "SELECT name, year, month",
    "FROM (",
    "    SELECT name, year, month",
    "       , ROW_NUMBER()",
    "           OVER (",
    "               PARTITION BY name, year",
    "               ORDER BY year DESC, month DESC",
    "           ) AS rn",
    "    FROM Main",
    ") t",
    "WHERE rn <= {{:partition_width}};",
]);

$params = [
    '{{:table}}' => 'sys.objects',
    '{{:cols}}' => 'name, year',
    '{{:p-cols}}' => 'month',
    '{{:partition_width}}' => '2',
];

$bound = sql_ebind($sql, $params);
$encoded = json_encode($bound);
echo "$encoded\n"; exit;
$expected = '{"sql":"ALTER TABLE Sales.SalesReason \n   ADD CONSTRAINT FK_Sales_TempSalesReason_Sales_SalesReason FOREIGN KEY (TempID) \n       REFERENCES Sales.SalesReason (SalesReasonID) \n       ON DELETE CASCADE \n       ON UPDATE CASCADE","params":[]}query failed';
if ($encoded == $expected) { echo "query success!\n"; } else { echo "query failed\n"; };








exit;


// Function to remove entire schema in T-SQL:

/**
 * drop_schema
 *
 * NOT FULLY TESTED.  Please let author know how this bold function
 * works out for you :-)
 *
 * drop all objects from a mssql schema, then drop the schema
 *
 * @param resource $db     MSSQL DB resource handle
 * @param string   $schema Schema name
 * @param bool     $t      list generated SQL, but don't affect rows
 *
 * @return bool success
 */
function schema_remove($db, $schema, $t = false) {

    // non-empty schemas can't be dropped
    // let's empty the given schema

    // what types are we going after?
    // not a comprehensive list of types
    // probably engine version dependent
    $type_names = Array(
        'SEQUENCE_OBJECT'                  => 'SEQUENCE',
        'SQL_INLINE_TABLE_VALUED_FUNCTION' => 'FUNCTION',
        'SQL_SCALAR_FUNCTION'              => 'FUNCTION',
        'SQL_STORED_PROCEDURE'             => 'FUNCTION',
        'SQL_TABLE_VALUED_FUNCTION'        => 'FUNCTION',
        'SQL_TRIGGER'                      => 'TRIGGER',
        'SYNONYM'                          => 'SYNONYM',
        'USER_TABLE'                       => 'TABLE',
        'VIEW'                             => 'VIEW',
    );

    // get & drop all type_desc
    foreach ($type_names as $type_desc => $keyword) {

        // get objects to be DROPped
        $sql = implode(" \n", Array(
            "SELECT SCHEMA_NAME(schema_id) + '.' + name as fullname",
            "FROM sys.objects",
            "WHERE SCHEMA_NAME(schema_id) = {:schema_name}",
            "  AND type_desc = {:type_desc}",
        ));
        $sql_params = Array(
            '{:schema_name}' => $schema,
            '{:type_desc}' => $type_desc,
        );
        $bound = sql_ebind($sql, $sql_params);
        $db_res = $db->query($bound['sql'], $bound['params']);
        if ($t) {
            var_dump($bound); echo PHP_EOL;
        }

        // DROP 'em
        foreach ($db_res->result_array() as $row) {
            $bound = DBEenhancedBind(
                'DROP {{:keyword}} [{{:schema_name}}].[{{:tablename}}]',
                Array(
                    '{{:schema_name}}' => $schema,
                    '{{:keyword}}' => $keyword,
                    '{{:tablename}}' => $row[0],
                )
            );
            if ($t) {
                var_dump($bound); echo PHP_EOL;
            } else {
                $db_res = $db->query($bound['sql'], $bound['params']);
            }
        }
    }

    // DROP the now empty schema
    $bound = sql_ebind(
        'DROP SCHEMA {{:schema_name}}',
        Array('{{:schema_name}}' => $schema)
    );
    if ($t) {
        var_dump($bound); echo PHP_EOL;
        $affected = 1;
    } else {
        $db_res = $db->query($bound['sql'], $bound['params']);
        $affected = $db->affected_rows();
    }

    return ($affected > 0);
}

// $success = schema_remove('db_handle_stub', 'dbo_new', true);
