<?php

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=cocopay;charset=utf8mb4',
    'root',
    'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$term = '%viser%';
$textTypes = "'char','varchar','tinytext','text','mediumtext','longtext','json'";

$columns = $pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'cocopay'
      AND DATA_TYPE IN ($textTypes)
    ORDER BY TABLE_NAME, ORDINAL_POSITION
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $column) {
    $table = str_replace('`', '``', $column['TABLE_NAME']);
    $field = str_replace('`', '``', $column['COLUMN_NAME']);

    $count = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE LOWER(`$field`) LIKE LOWER(?)");
    $count->execute([$term]);
    $matches = (int) $count->fetchColumn();

    if ($matches === 0) {
        continue;
    }

    $sample = $pdo->prepare("SELECT `$field` FROM `$table` WHERE LOWER(`$field`) LIKE LOWER(?) LIMIT 1");
    $sample->execute([$term]);
    $value = (string) $sample->fetchColumn();
    $value = preg_replace('/\s+/', ' ', $value);
    $value = mb_substr($value, 0, 180);

    echo "{$column['TABLE_NAME']}.{$column['COLUMN_NAME']} | {$matches} | {$value}\n";
}

echo "\nfrontends rows containing viser:\n";
$rows = $pdo->query("
    SELECT id, data_keys, data_values
    FROM frontends
    WHERE LOWER(data_values) LIKE '%viser%'
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $value = preg_replace('/\s+/', ' ', (string) $row['data_values']);
    $value = mb_substr($value, 0, 220);
    echo "{$row['id']} | {$row['data_keys']} | {$value}\n";
}
