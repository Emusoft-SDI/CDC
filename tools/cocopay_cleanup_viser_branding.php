<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay';
$core = $root . '/core';

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=cocopay;charset=utf8mb4',
    'root',
    'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$replacements = [
    'Viserbank' => 'NATCODEV Coconut Co-op',
    'ViserBank' => 'NATCODEV Coconut Co-op',
    'Viser Bank' => 'NATCODEV Coconut Co-op',
    'viserbank' => 'natcodevcoop',
    'viserlab@site.com' => 'info@natcodevcoop.local',
    'info@viserlab.com' => 'info@natcodevcoop.local',
    'ViserAdmin' => 'NATCODEV',
    'ViserLab LLC' => 'NATCODEV Coconut Farmers Cooperative',
    'ViserLab' => 'NATCODEV',
    'viserlab.com' => 'natcodevcoop.local',
];

$pdo->beginTransaction();

$primaryKeys = [];
foreach ($pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = 'cocopay'
      AND CONSTRAINT_NAME = 'PRIMARY'
    ORDER BY TABLE_NAME, ORDINAL_POSITION
") as $keyColumn) {
    $primaryKeys[$keyColumn['TABLE_NAME']][] = $keyColumn['COLUMN_NAME'];
}

$columns = $pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'cocopay'
      AND DATA_TYPE IN ('char','varchar','tinytext','text','mediumtext','longtext','json')
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $column) {
    if (count($primaryKeys[$column['TABLE_NAME']] ?? []) !== 1) {
        continue;
    }

    $table = str_replace('`', '``', $column['TABLE_NAME']);
    $field = str_replace('`', '``', $column['COLUMN_NAME']);
    $primaryKey = str_replace('`', '``', $primaryKeys[$column['TABLE_NAME']][0]);

    $rows = $pdo->query("SELECT `$primaryKey` AS row_key, `$field` AS value FROM `$table` WHERE LOWER(`$field`) LIKE '%viser%'")->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        continue;
    }

    $update = $pdo->prepare("UPDATE `$table` SET `$field` = ? WHERE `$primaryKey` = ?");
    foreach ($rows as $row) {
        $value = (string) $row['value'];
        $updated = str_replace(array_keys($replacements), array_values($replacements), $value);
        $update->execute([$updated, $row['row_key']]);
    }
}

$pdo->prepare("UPDATE general_settings SET sms_from = ? WHERE LOWER(sms_from) LIKE '%viser%'")->execute(['NATCODEV']);
$pdo->commit();

$files = [
    $root . '/assets/global/js/firebase/configs.js' => 'var firebaseConfig = { apiKey: "", authDomain: "natcodev-cocopay.local", projectId: "natcodev-cocopay-local", storageBucket: "natcodev-cocopay-local", messagingSenderId: "", appId: "", measurementId: "" };' . PHP_EOL,
    $root . '/assets/global/css/installer.css' => "/* NATCODEV Coconut Co-op installer stylesheet */\n/* Laravel Software Setup Module */\n",
];

foreach ($files as $file => $contents) {
    file_put_contents($file, $contents);
}

$viewUpdates = [
    $core . '/resources/views/admin/system/support.blade.php' => [
        '<a href="https://viserlab.com/support" target="_blank" class="btn btn--primary h-45 w-100">@lang(\'Get Support\')</a>' =>
            '<a href="{{ route(\'admin.ticket.index\') }}" class="btn btn--primary h-45 w-100">@lang(\'Open Local Support Desk\')</a>',
    ],
    $core . '/resources/views/admin/system/info.blade.php' => [
        "@lang('ViserAdmin Version')" => "@lang('System Build Version')",
    ],
    $core . '/resources/views/admin/reports.blade.php' => [
        '<a href="https://viserlab.com/support" target="_blank" class="btn btn-sm btn-outline--primary"><i class="las la-headset"></i> @lang(\'Request for Support\')</a>' =>
            '<a href="{{ route(\'admin.ticket.index\') }}" class="btn btn-sm btn-outline--primary"><i class="las la-headset"></i> @lang(\'Open Support Desk\')</a>',
    ],
];

foreach ($viewUpdates as $file => $map) {
    $contents = file_get_contents($file);
    $contents = str_replace(array_keys($map), array_values($map), $contents);
    file_put_contents($file, $contents);
}

$compatibilityClass = $core . '/app/View/Components/ViserForm.php';
if (file_exists($compatibilityClass)) {
    unlink($compatibilityClass);
}

echo "VISER_BRANDING_CLEANUP_OK\n";
