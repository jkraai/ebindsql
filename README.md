# eBindSQL
Enhanced named binding for SQL

Welcome to eBindSQL!
---------------

This is a named parameter binding helper for generic SQL with PHP, js, and Python implementations so far.  The implementation was inspired by https://stackoverflow.com/a/11594332/7307768, and should be easy to use or customize.  

The examples are Transact SQL, but the code will work out-of-the-box for other SQL dialects.  This is a simple, iterated string substitution technique that is published for writing SQL queries.  No checks are made to ensure that the output is valid SQL.  

This technique could be used out-of-the-box for many kinds of non-SQL code generation.

The problems to be solved are:
* when preparing SQL statements for execution, the '?' placeholders must have ordinal positional correspondence with a passed array of values to substitute for each '?'.  One-to-one correspondence and in the exact order
* '?' placeholders can only stand in for values, not column or table names
* traditional implementations are simple and flat and are expressively and functionally limiting

The first makes query maintenance tedious and error-prone when doing even the simplest refactoring, including shifting clauses and adding or removing conditions.

By using named parameters, the order can be rearranged without breaking the association, flexibility is gained and the ordinal correspondence requirement is shed at the expanse of having to name the parameters.  These params are delimited with curly braces.  {:normal_param_name}  (The "{:" was kept as a nod to older systems.)  Case sensitive identifiers follow a convention of a letter followed by any number of letters, numbers, '-' dash, and '_' underscore.

Two capabilities have been added. 
*  Normally a statement to be prepared can't have database, schema, table, or column names be replaceable.  This allows that flexibility by delimiting those params with double curly braces. {{:abnormal_param_name}}
*  Substitution loops are put inside while loops that repeat until no further replacements have been made.

Runaway behavior resulting from circular substitutions are caught with a loop counter.  It's cheaper in lines of code, readability, and maintenance than generating and validating a digraph.  Anyone who wishes to fork and digraph is most welcome to do so!  I could probably learn good things from reading the code.

These new capabilities don't introduce new exposures to SQL injection attacks as the generated statements still have to successfully make it through prepare mechanisms.

Advanced users who find this technique too limiting should look into tools like a macro processor, writing a little language, or creating the next big language.

Documentation by Example
---------------
### A simple substitution example
Where this was normal
```php
prepare("SELECT id, lname, fname FROM people where lname='?'")
```
We can use:
```php
prepare("SELECT id, lname, fname FROM people where lname={:name}")
```


### Usage example for simple parameters
```php
$sql = implode(" \n", Array(
    "SELECT ID, lname, fname",
    "FROM dbo.table_name_01",
    "WHERE ID = {:ID_cond}",
    "AND lname LIKE {:lname_cond}"
));

$params = Array(
    '{:lname_cond}' => '%mith',
    '{:ID_cond}' => '4d7ab00ae2561cbc1a58a1ccbf0192cf',
);
$query_bound = sql_ebind($sql, $params);
var_dump($query_bound); echo PHP_EOL;
```
should give 
```
array(2) {
  'sql' =>
  string(90) "SELECT ID, lname, fname
    FROM dbo.table_name_01
    WHERE ID = ?
      AND lname LIKE ?"
  'params' =>
  array(2) {
    [0] =>
    string(32) "4d7ab00ae2561cbc1a58a1ccbf0192cf"
    [1] =>
    string(5) "%mith"
  }
}
```


### Structural parameter and replacement looping:
```php
$sql = implode(" \n", Array(
    "SELECT {{:colname_01}}, lname, fname",
    "FROM dbo.table_name_01",
    "WHERE ID = {:wherecond_01}",
    "  AND lname LIKE {:wherecond_02}",
    "ORDER BY {{:colname_01_PrimaryKey}}",
));
$params = Array(
    '{{:colname_01}}' => 'ID',
    '{{:colname_01_PrimaryKey}}' => '{{:colname_01}}',
    '{:wherecond_01}' => '4d7ab00ae2561cbc1a58a1ccbf0192cf',
    '{:wherecond_02}' => '%mith',
);
$query_bound = sql_ebind($sql, $params);
var_dump($query_bound); echo PHP_EOL;
```
should give 
```
array(2) {
  'sql' =>
  string(100) "SELECT ID, lname, fname
    FROM dbo.table_name_01
    WHERE ID = ? 
      AND lname LIKE ?
    ORDER BY ID"
  'params' =>
  array(2) {
    [0] =>
    string(32) "4d7ab00ae2561cbc1a58a1ccbf0192cf"
    [1] =>
    string(5) "%mith"
  }
}
```
In the above example, note ```{{:colname_01_PrimaryKey}} -> {{:colname_01}} -> 'ID'```


### Number of parameters unknown ahead of time
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
    '{:wherecond_01}' => '4d7ab00ae2561cbc1a58a1ccbf0192cf',
    '{:wherecond_02}' => Array(3, 5, 7),
);
$query_bound = sql_ebind($sql, $params);
var_dump($query_bound); echo PHP_EOL;
```
Should give:
```
{
    "sql":"SELECT ID
        FROM dbo.table_name_01
        WHERE ID = ?
          OR field_name_02 IN ( ?, ?, ? )",
    "params":["4d7ab00ae2561cbc1a58a1ccbf0192cf",3,5,7]
}
```


### Multiple columns
```php
$sql = implode(" \n", Array(
    "SELECT {{:field_names}}",
    "FROM {{:table_name}}",
    "WHERE {{:id_field_name}} = {:wherecond_01}",
    "ORDER BY {{:field_names}",
));
$params = Array(
    '{{:field_names}}' => 'col1, col2, col3',
    '{{:id_field_name}}' => 'col1',
    '{{:table_name}}'  => 'dbo.table_name_01',
    '{:wherecond_01}'  => 1729
);
$query_bound = sql_ebind($sql, $params);
var_dump($query_bound); echo PHP_EOL;
```
should give:
```
array(2) {
  'sql' =>
  string(69) "SELECT col1, col2, col3
    FROM dbo.table_name_01
    WHERE col1 = ?
    ORDER BY col1, col2, col3"
  'params' =>
  array(1) {
    [0] =>
    int(1729)
  }
}
```


### General SELECT query builder
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
$sql = '';
if (!@empty($sql_params['{{:with_statement}}'])     ) $sql .= 'WITH     {{:with_statement}} ';
$sql                                                       .= 'SELECT   {{:select_list}} ';
if (!@empty($sql_params['{{:table_source}}'])       ) $sql .= 'FROM     {{:table_source}} ';
if (!@empty($sql_params['{{:wsearch_condition}}'])  ) $sql .= 'WHERE    {{:wsearch_condition}} ';
if (!@empty($sql_params['{{:group_by_expression}}'])) $sql .= 'GROUP BY {{:group_by_expression}} ';
if (!@empty($sql_params['{{:hsearch_condition}}'])  ) $sql .= 'HAVING   {{:hsearch_condition}} ';
if (!@empty($sql_params['{{:order_expression}}'])   ) $sql .= 'ORDER BY {{:order_expression}} ';

// add some more params
$sql_params['{{:table_name}}'] = 'Account';
$sql_params['{{:id_col}}'] = 'ID';

// fill in the actual ID being sought
$sql_params['{:ID}'] = 9;

// bind names
$query_bound = sql_ebind($sql, $sql_params);
var_dump($query_bound); echo PHP_EOL;
```
should give:
```
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


### Function to remove schema in TSQL:
```php
/*
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

$success = schema_remove('db_handle_stub', 'dbo_new', true);
```

TODO:  Need contributor examples:
* Easy
    * JOINs
    * subselects
* Medium
    * given param array, generate SELECT, UPDATE, and DELETE 
* Bigger
    * bad data hunter
    * a table->CRUD generator
    * relational model->CRUD generator
