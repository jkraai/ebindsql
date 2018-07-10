<?php
/*
 *
 */

require './../src/php/sql_ebind.php';

$json_str = file_get_contents('common/tests.json');
$tests = json_decode($json_str, true);

// construct a test that doesn't quite fit in a json structure
$row = array(
    'ID' => '192837465',
    'col1' => 'val1',
    'col2' => 'val2',
    'col3' => 'val3'
);

$tests['tests'][] = array(
  'description' => 'Phase 3 associative array support--variable INSERT',
  'input' => array(
    'sql' => 'UPDATE {{:table}} SET {:row_kv} WHERE {{:id_col}} = {:id}',
    'params' => array(
      '{{:table}}'  => 'dbo.some_table',
      '{:row_kv}'   => $row,
      '{{:id_col}}' => 'ID',
      '{:id}'       => $row['ID'],
    )
  ),
  'output' => array(
    'sql' => 'UPDATE dbo.some_table SET ID=?, col1=?, col2=?, col3=? WHERE ID = ?',
    'params' => array(
      '192837465',
      'val1',
      'val2',
      'val3',
      '192837465',
    )
  )
);

foreach($tests['tests'] as $test) {

    $desc     = $test['description'];
    $input    = $test['input'];
    $expected = $test['output'];

    $actual = sql_ebind(
        $input['sql'],
        $input['params']
    );

    echo 'test description: ' . $test['description'] . PHP_EOL;

    if ($actual['sql'] !== $expected['sql']) {
        echo 'error in sql output' . PHP_EOL;
        echo '  expected: ' . json_encode($expected['sql']) . PHP_EOL;
        echo '    actual: ' . json_encode($actual['sql']) . PHP_EOL;
    }
    else {
        echo '  sql OK' . PHP_EOL;
    }
    if ($actual['params'] !== $expected['params']) {
        echo 'error in param output' . PHP_EOL;
        echo '  expected: ' . json_encode($expected['params']) . PHP_EOL;
        echo '    actual: ' . json_encode($actual['params']) . PHP_EOL;
    }
    else {
        echo '  params OK' . PHP_EOL;
    }
}


//
// Example of a function to remove schema in TSQL:
// ```php
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
