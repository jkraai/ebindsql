Welcome to php_sql_named_bind!
===================
This is a named parameter binding helper for generic SQL written in PHP.

It should be easy to use or customize for different uses, different frameworks, and even different programming languages.


Where this was normal:
```php
$db_res = query("SELECT id, lname, fname FROM people where lname='?'", array('Smith'));
```
We can use:
```php
$db_res = query("SELECT id, lname, fname FROM people where lname={:name}", array('Smith'));
```

Example, usage example for simple parameter:
```php
$sql = implode(" \n", Array(
    "SELECT ID, lname, fname",
    "FROM dbo.table_name_01",
    "WHERE ID = {:ID_cond}",
    "AND lname LIKE {:lname_cond}"
));

$params = Array(
    '{:lname_cond}' => '%mith',
    '{:ID_cond}' => '2c92c0f95ccf2766015cd11c48a93690',
);
$query_bound = sql_ebind($sql, $params);
$this->db->query($query_bound['sql'], $query_bound['params']);
var_dump($query_bound); echo PHP_EOL;
```
should give
```php
array(2) {
  'sql' =>
  string(90) "SELECT ID, lname, fname
    FROM dbo.table_name_01
    WHERE ID = ?
      AND lname LIKE ?"
  'params' =>
  array(2) {
    [0] =>
    string(32) "2c92c0f95ccf2766015cd11c48a93690"
    [1] =>
    string(5) "%mith"
  }
}
```

Example for structural parameter:
```php
$sql = implode(" \n", Array(
    "SELECT {{:colname_01}}, lname, fname",
    "FROM dbo.table_name_01",
    "WHERE ID = {:wherecond_01}",
    "  AND lname LIKE {:wherecond_02}",
    "ORDER BY {{:colname_01}}",
));

$params = Array(
    '{{:colname_01}}' => 'ID',
    '{:wherecond_01}' => '2c92c0f95ccf2766015cd11c48a93690',
    '{:wherecond_02}' => '%mith',
);
$query_bound = sql_ebind($sql, $params);
$this->db->query($query_bound['sql'], $query_bound['params']);
var_dump($query_bound); echo PHP_EOL;
```
should give
```php
array(2) {
  'sql' =>
  string(100) "SELECT ID, lname, fname
    FROM dbo.table_name_01
    WHERE ID = ? AND lname LIKE ?
    ORDER BY ID"
  'params' =>
  array(2) {
    [0] =>
    string(32) "2c92c0f95ccf2766015cd11c48a93690"
    [1] =>
    string(5) "%mith"
  }
}
```

Example with the number of parameters unknown ahead of time:
```php
$sql = implode(" \n", Array(
    "SELECT {{:field_name}}",
    "FROM {{:table_name}}",
    "WHERE {{:field_name}} = {:wherecond_01}",
    "  OR field_name_02 IN ( {:wherecond_02} )",
  ));

$params = Array(
    '{{:field_name}}' => 'ID',
    '{{:table_name}}' => 'dbo.table_name_01',
    '{:wherecond_01}' => '2c92c0f95ccf2766015cd11c48a93690',
    '{:wherecond_02}' => Array(3, 5, 7),
);
$query_bound = sql_ebind($sql, $params);
$this->db->query($query_bound['sql'], $query_bound['params']);
var_dump($query_bound); echo PHP_EOL;
```
Should give"
```php
{
    "sql":"SELECT ID
        FROM dbo.table_name_01
        WHERE ID = ?
          OR field_name_02 IN ( ?, ?, ? )",
    "params":["2c92c0f95ccf2766015cd11c48a93690",3,5,7]
}
```

Example for multiple columns:
```php
$sql = implode(" \n", Array(
    "SELECT {{:field_names}}",
    "FROM {{:table_name}}",
    "WHERE {{:id_field_name}} = {:wherecond_01}",
));
$params = Array(
    '{{:field_names}}' => 'col1, col2, col3',
    '{{:id_field_name}}' => 'col1',
    '{{:table_name}}'  => 'dbo.table_name_01',
    '{:wherecond_01}'  => 1729
);
$query_bound = sql_ebind($sql, $params);
$this->db->query($query_bound['sql'], $query_bound['params']);
var_dump($query_bound); echo PHP_EOL;
```

should give:
```php
array(2) {
  'sql' =>
  string(69) "SELECT col1, col2, col3
    FROM dbo.table_name_01
    WHERE col1 = ?"
  'params' =>
  array(1) {
    [0] =>
    int(1729)
  }
}
```

Example for general SELECT query builder:
```php
// note that replacement values contain replacement keys
// that get replaced inside a do{} loop
$sql_params = Array(
    '{{:with_statement}}'      => '',
    '{{:select_list}}'         => '*',
    '{{:table_source}}'        => 'dbo.{{:table_name}}',
    '{{:wsearch_condition}}'   => '{{:id_col}} = {:ID}',
    '{{:group_by_expression}}' => '',
    '{{:hsearch_condition}}'   => '',
    '{{:order_expression}}'    => '{{:id_col}}',
);

// build the query
$sql_select = 'SELECT   {{:select_list}} ';
// prepend WITH clause
if (!@empty($sql_params['{{:with_statement}}']))
    $sql_select = 'WITH     {{:with_statement}} ' . $sql_select;
if (!@empty($sql_params['{{:table_top}}']))
    $sql_select .= 'TOP      {{:table_top}} ';
if (!@empty($sql_params['{{:table_source}}']))
    $sql_select .= 'FROM     {{:table_source}} ';
if (!@empty($sql_params['{{:wsearch_condition}}']))
    $sql_select .= 'WHERE    {{:wsearch_condition}} ';
if (!@empty($sql_params['{{:group_by_expression}}']))
    $sql_select .= 'GROUP BY {{:group_by_expression}} ';
if (!@empty($sql_params['{{:hsearch_condition}}']))
    $sql_select .= 'HAVING   {{:hsearch_condition}} ';
if (!@empty($sql_params['{{:order_expression}}']))
    $sql_select .= 'ORDER BY {{:order_expression}} ';

// add some more params
$sql_params['{{table_name}}'] = 'Account';
$sql_params['{{id_col}}'] = 'ID';

// fill in the actual ID being sought
$sql_params['{:ID}'] = 9;

// bind names
$query_bound = sql_ebind($sql_select, $sql_params);
$this->db->query($query_bound['sql'], $query_bound['params']);
var_dump($query_bound); echo PHP_EOL;
```

should give:
```php
array(2) {
  'sql' =>
  string(58) "SELECT * FROM dbo.Account WHERE ID = ? ORDER BY ID"
  'params' =>
  array(1) {
    [0] =>
    int(9)
  }
}
```

Use the same parameters for a DELETE statement
```php
// build the query
$sql_delete = 'SELECT   {{:select_list}} ';
// prepend WITH clause
if (!@empty($sql_params['{{:with_statement}}']))
    $sql_delete = 'WITH     {{:with_statement}} ' . $sql_delete;
if (!@empty($sql_params['{{:table_top}}']))
    $sql_delete .= 'TOP      {{:table_top}} ';
if (!@empty($sql_params['{{:table_source}}']))
    $sql_delete .= 'FROM     {{:table_source}} ';
if (!@empty($sql_params['{{:wsearch_condition}}']))
    $sql_delete .= 'WHERE    {{:wsearch_condition}} ';

// bind names
$query_bound = sql_ebind($sql_delete, $sql_params);
$this->db->query($query_bound['sql'], $query_bound['params']);
var_dump($query_bound); echo PHP_EOL;
```

should give:
```php
array(2) {
  'sql' =>
  string(58) "DELETE * FROM dbo.Account WHERE ID = ?"
  'params' =>
  array(1) {
    [0] =>
    int(9)
  }
}
```

Use them for an UPDATE statement
```php
// add a set_list and use it in an update statement
$sql_params['{{:set_list}}'] = 'something or other';

$sql_update = 'UPDATE ';
if (!@empty($sql_params['{{:table_alias}}']))
    $sql_update .= ' {{:table_alias}} ';
// prepend WITH clause
if (!@empty($sql_params['{{:with_statement}}']))
    $sql_update = 'WITH {{:with_statement}} ' . $sql_update;
if (!@empty($sql_params['{{:table_top}}']))
    $sql_update .= 'TOP {{:table_top}} ';
if (!@empty($sql_params['{{:set_list}}']))
    $sql_update .= 'SET {{:set_list}} ';
if (!@empty($sql_params['{{:wsearch_condition}}']))
    $sql_update .= 'WHERE {{:wsearch_condition}} ';


// bind names
$query_bound = sql_ebind($sql_delete, $sql_params);
$this->db->query($query_bound['sql'], $query_bound['params']);
var_dump($query_bound); echo PHP_EOL;
```

should give:
```php
array(2) {
  'sql' =>
  string(58) "UPDATE dbo.Account SET something or other WHERE ID = ?"
  'params' =>
  array(1) {
    [0] =>
    int(9)
  }
}
```


Example of a function to remove schema in TSQL:
```php
/*
 * drop_schema
 *
 * UNTESTED
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

    // what types are we going after?
    // not a comprehensive list
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
            "  AND type_desc = {:type_desc:}",
        ));
        $sql_params = Array(
            '{:schema_name}' => $schema,
            '{:type_desc}' => $type_desc,
        );
        $bound = sql_ebind($sql,$sql_params);
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

$success = schema_remove('db_handle_stub', 'dbo_new', true);

var_dump($query_bound); echo PHP_EOL;
```

