# eBindSQL
Enhanced named binding for SQL

```php
$sql = implode(" \n", [
    "SELECT {{:YYYYMM}} AS YYYYMM, COUNT(*) AS KOUNT",
    "FROM {{:table}}",
    "WHERE {{:dt_field}} >= '{:dt_limit}'",
    "GROUP BY {{:YYYYMM}}",
]);
$params = [
    '{{:table}}' => 'dbo.Accounts',
    '{{:dt_field}}' => 'CreatedDate',
    '{:dt_limit}' => date('Y-m-d', strtotime('-1 years')), // 1 year ago
    '{{:YYYYMM}}' => "CAST(YEAR({{:dt_field}}) AS VARCHAR(4)) + '-' + RIGHT('00' + CAST(MONTH({{:dt_field}}) AS VARCHAR(2)), 2)",
];
$bound1 = sql_ebind($sql, $params);
echo $bound1['sql'];

$params['{:table}'] = 'dbo.OldAccounts';
$params['{:dt_field}'] = 'DisabledDate';
$bound2 = sql_ebind($sql, $params);
echo $bound2['sql'];
```
yields
```sql
SELECT CAST(YEAR(CreatedDate) AS VARCHAR(4)) + '-' + RIGHT('00' + CAST(MONTH(CreatedDate) AS VARCHAR(2)), 2) AS YYYYMM, COUNT(*) AS KOUNT
FROM dbo.Accounts
WHERE CreatedDate >= '2021-04-01'
GROUP BY CAST(YEAR(CreatedDate) AS VARCHAR(4)) + '-' + RIGHT('00' + CAST(MONTH(CreatedDate) AS VARCHAR(2)), 2)

SELECT CAST(YEAR(CreatedDate) AS VARCHAR(4)) + '-' + RIGHT('00' + CAST(MONTH(CreatedDate) AS VARCHAR(2)), 2) AS YYYYMM, COUNT(*) AS KOUNT
FROM dbo.OldAccounts
WHERE DisabledDate >= '2021-04-01'
GROUP BY CAST(YEAR(CreatedDate) AS VARCHAR(4)) + '-' + RIGHT('00' + CAST(MONTH(CreatedDate) AS VARCHAR(2)), 2)
```

# Welcome!
This is a named parameter binding helper for generic SQL with PHP, js, and Python implementations so far.  The implementation was inspired by a need for https://stackoverflow.com/a/11594332/7307768 in CodeIgniter, and should be easy to use directly or to customize for other needs.

The examples are Transact SQL, but the code will work out-of-the-box for other SQL dialects.  This is a simple, iterated string substitution technique that is published for writing SQL queries.  No checks are made to ensure that the output is valid SQL.  

This technique could be used out-of-the-box for many kinds of non-SQL code generation.

To be sure, this is simplistic string interpolation.  This implementation also handles positional replacement strings, so it's specialized for SQL.

## The problems to be solved are:
| Problem                                                                                                                                                                                                                                            | Solution                                                                                                                                                     |
|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Few SQL implementations support named parameters.                                                                                                                                                                                                  | 1. This makes it difficult to write SQL that is portable across SQL implementations.<br/>  2. This tool effectively adds that capability.                    |
| When preparing SQL statements for execution, the ```?``` placeholders must have ordinal positional correspondence with a passed array of values to substitute for each ```?```.  In other words, one-to-one correspondence and in the exact order. | This makes query maintenance tedious and error-prone when doing even the simplest refactoring, including shifting clauses and adding or removing conditions. |
| Query editing and maintenance is error-prone.                                                                                                                                                                                                      | By using named parameters, the order of columns and clauses can be rearranged without breaking the association.                                              |


zzz Flexibility is gained and the ordinal correspondence requirement is shed at the expanse of having to name the parameters.  These params are delimited with  brackets, here called braces.  ```{:normal_param_name}```  (The ```{:``` was chosen as a nod to days of yore.)  Case-sensitive identifiers follow a convention of a letter followed by any number of letters, numbers, ```-``` dash, and ```_``` underscore. zzz


## New capabilities have been added beyond normal SQL parameter string interpolation: 
* Normally a statement to be prepared can't have database, schema, table, or column names be replaceable.  This allows that flexibility by delimiting those params with double braces. ```{{:abnormal_param_name}}```
* Substitution loops are put inside while loops that repeat until no further replacements have been made. 
* Runaway behavior resulting from circular substitutions are caught, eventually, with a loop counter and/or string change detection.  It's cheaper in lines of code, readability, and maintenability than using graph methods to detect loops.
* These new capabilities don't introduce new exposures to SQL injection attacks as the generated statements still have to successfully make it through existing mechanisms.

Advanced users who find this technique too limiting should look into tools like a macro processor, writing a little language, or creating their own big language.

## How it Works
The function operates in three phases:
* Phase 1:  Triple-braced key strings are replaced with contents of files named in the value string
* Phase 2:  Double-braced key strings are replaced with value strings and can contain double- or single-braced strings or with literal strings
* Phase 3:  Single-braced key strings are replaced with SQL parameter placeholders and can contain single-braced strings or literal strings

Phase 1 is just an old-PHP-style, "put the text of that file here."  The file is read and the contents are substituted in place of the key string.  The file name is relative to the current working directory.  This is useful for putting whole clauses, subqueries, etc. into the SQL statement in ways that support reuse.  The process repeats until no substitutions are made.

Phase 2 is a simple string substitution.  The key string is replaced with the value string.  The process repeats until no substitutions are made.

Phase 3 is also a simple string substitution, but the key string is replaced with a SQL parameter placeholder.  The key string is also added to an array of parameters that will be passed to the preparable statement.  The process repeats until no substitutions are made.

## Phase order matters
Phases are done in order, so putting a triple-braced filename value into a double-braced key won't work.  Likewise, putting a double-braced value into a single-braced key probably won't work for generating valid SQL, however the tool can be used to generate templates for itself to consume at a later run.

## What Feels Kinda Klunky is What Makes it Work for SQL
A vanilla string interpolator won't return the ordered array of parameters that a prepared statement needs.  The function returns a structure with two parts:
* a SQL string
* an ordered array or list of replaced parameters

# Documentation by Example
## A simple substitution
Where this was normal
```php
$sql = "SELECT id, lname, fname FROM people where lname='?'";
```
We can use:
```php
$sql = sql_ebind(
    "SELECT id, lname, fname FROM people where lname = {:lname}", 
    array('{:lname}' => 'Jones')
);
```

## Usage for simple parameters
```php
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

var_dump($bound); echo PHP_EOL;
```
will give 
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

## Simple Replacement Macros & Readability
```php
// Which is more readable?
$sql = implode(" \n", Array(
    "SELECT CAST(YEAR(CreatedDate) AS VARCHAR(4)) + '-' + RIGHT('00' + CAST(MONTH(CreatedDate) AS VARCHAR(2)), 2) AS YYYYMM, COUNT(*) AS KOUNT",
    "FROM dbo.Accounts",
    "GROUP BY CAST(YEAR(CreatedDate) AS VARCHAR(4)) + '-' + RIGHT('00' + CAST(MONTH(CreatedDate) AS VARCHAR(2)), 2)",
));

// or

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
var_dump($bound);
```
will give 
```
array(2) {
  'sql' =>
  string(268) "SELECT CAST(YEAR(CreatedDate) AS VARCHAR(4)) + '-' + RIGHT('00' + CAST(MONTH(CreatedDate) AS VARCHAR(2)), 2) AS YYYYMM, COUNT(*) AS KOUNT
  FROM dbo.Accounts
  GROUP BY CAST(YEAR(CreatedDate) AS VARCHAR(4)) + '-' + RIGHT('00' + CAST(MONTH(CreatedDate) AS VARCHAR(2)), 2)"
  'params' =>
  array(0) {
  }
}
```

## Structural parameter and replacement looping
```php
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
var_dump($bound); echo PHP_EOL;
```
will give 
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

## Number of parameters unknown ahead of time
```php
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
var_dump($bound); echo PHP_EOL;
```
will give
```
{
    "sql":"SELECT ID
        FROM dbo.table_name_01
        WHERE ID = ?
          OR field_name_02 IN ( ?, ?, ? )",
    "params":["4d7ab00ae2561cbc1a58a1ccbf0192cf",3,5,7]
}
```

## Multiple columns
```php
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
var_dump($bound); echo PHP_EOL;
```
will give
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





## make safety checking statements for ad-hoc maintenance

## swap values in a table
```php
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

```
will give
```
array(2) {
  ["sql"]=>
  string(54) "UPDATE dbo.table_name_01 
SET col1 = ? 
WHERE col1 = ?"
  ["params"]=>
  array(2) {
    [0]=>
    string(14) "~intermediate~"
    [1]=>
    string(1) "A"
  }
}

array(2) {
  ["sql"]=>
  string(54) "UPDATE dbo.table_name_01 
SET col1 = ? 
WHERE col1 = ?"
  ["params"]=>
  array(2) {
    [0]=>
    string(1) "A"
    [1]=>
    string(1) "B"
  }
}

array(2) {
  ["sql"]=>
  string(54) "UPDATE dbo.table_name_01 
SET col1 = ? 
WHERE col1 = ?"
  ["params"]=>
  array(2) {
    [0]=>
    string(1) "B"
    [1]=>
    string(14) "~intermediate~"
  }
}

```

## Over General SELECT query builder
```php
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
var_dump($bound); echo PHP_EOL;
```
will give
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

## Same as previous with contents of a file
```php
/* contents of ../sql/general_select.sql
    {{:WITH_clause}}
    SELECT {{:field_list}}
    {{:FROM_clause}}
    {{:WHERE_clause}}
    {{:GROUPBY_clause}}
    {{:HAVING_clause}}
    {{:ORDERBY_clause}}
*/
$sql = '{{:GEN_SQL_SELECT}}';
$sql_params['{{{:GEN_SQL_SELECT}}' => '../sql/general_select.sql';
// bind names
$bound = sql_ebind($sql, $sql_params);
var_dump($bound); echo PHP_EOL;
```

## normal query, with array support
```php
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
var_dump($bound); echo PHP_EOL;

$expected = '{"sql":"SELECT ID, col1, col2, col3 \nFROM dbo.some_table \nWHERE ID = ?","params":["192837465"]}';
if (json_encode($bound) == $expected) { echo "normal query success!\n"; }
else { echo "normal query failed\n"; };
```

## Another INSERT, with array support
```php
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
var_dump($bound); echo PHP_EOL;

$expected = '{"sql":"INSERT INTO dbo.some_table ( ID, col1, col2, col3 ) \n  ( ?, ?, ?, ? )","params":["192837465","val1","val2","val3"]}';

if (json_encode($bound) == $expected) { echo "INSERT success!\n"; }
else { echo "INSERT failed\n"; };
```

## UPDATE with single column key and array support
```php
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
var_dump($bound); echo PHP_EOL;

$expected = '{"sql":"UPDATE dbo.some_table \nSET col1=?, col2=?, col3=? \nWHERE ID = ?","params":["val1","val2","val3","192837465"]}';

if (json_encode($bound) == $expected) { echo "UPDATE success!\n"; }
else { echo "UPDATE failed\n"; };
```

## UPDATE with composite primary key and array support
```php
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
var_dump($bound); echo PHP_EOL;

$expected = '{"sql":"UPDATE dbo.some_table \nSET ID=?, col1=?, col2=?, col3=? \nWHERE ID = ? AND col3 = ?","params":["192837465","val1","val2","val3","192837465","val3"]}';

if (json_encode($bound) == $expected) { echo "UPDATE composite pk success!\n"; }
else { echo "UPDATE composite pk failed\n"; };
```

## generic t-sql UPSERT, as an extension of UPDATE_composite_pk
```php
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
var_dump($bound); echo PHP_EOL;

$expected = '{"sql":"IF (NOT EXISTS( \n  SELECT * FROM dbo.some_table WHERE ID = ? ) \n) \nBEGIN \n  INSERT INTO dbo.some_table ( ID, col1, col2, col3 ) \n  VALUES( ?, ?, ?, ? ) \nEND \nELSE \nBEGIN \n  UPDATE dbo.some_table \n  SET ID=?, col1=?, col2=?, col3=? \n  WHERE ID = ? \nEND","params":["192837465","192837465","val1","val2","val3","192837465","val1","val2","val3","192837465"]}';

if (json_encode($bound) == $expected) { echo "UPSERT success!\n"; }
else { echo "UPSERT failed\n"; };
```

```sql
$sql = implode(" \n", [
  "ALTER TABLE {{:foreign_schema}}.{{:foreign_table}}",
     "ADD CONSTRAINT FK_{{:primary_schema}}-{{:primary_table}}_{{:foreign_schema}}-{{:foreign_table}} FOREIGN KEY (TempID)",
        "REFERENCES Sales.SalesReason (SalesReasonID)",
        "{{:on_delete}}",
        "{{:on_update}}",
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

$bound1 = sql_ebind($sql, $params);
echo $bound1['sql'];

```
yields
```
ALTER TABLE Sales.SalesReason 
   ADD CONSTRAINT FK_Sales_TempSalesReason_Sales_SalesReason FOREIGN KEY (TempID) 
       REFERENCES Sales.SalesReason (SalesReasonID) 
       ON DELETE CASCADE 
       ON UPDATE CASCADE
```

## Function to remove entire schema in T-SQL:
```php
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

$success = schema_remove('db_handle_stub', 'dbo_new', true);
```

# TODO:  Concise contributor examples are welcome!
* Easy
    * JOINs
    * subselects
* Medium
    * given param array, generate SELECT, UPDATE, and DELETE 
* Bigger
    * simple outlying data hunter
      * ```foreach table { foreach column { show top & bottom ten values } }```
    * a table->CRUD generator
    * relational model->CRUD generator
    * "whadda we need!?"  "yet another 'lightweight' ORM!!!"  right?   hello?  anyone still here?
