<?php

$dir = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\bootstrap\\cache';

echo 'DIR=' . $dir . PHP_EOL;
echo 'EXISTS=' . (is_dir($dir) ? 'yes' : 'no') . PHP_EOL;
echo 'WRITABLE=' . (is_writable($dir) ? 'yes' : 'no') . PHP_EOL;

foreach (glob($dir . DIRECTORY_SEPARATOR . '*.tmp') ?: [] as $tmp) {
    @unlink($tmp);
    echo 'REMOVED_TMP=' . basename($tmp) . PHP_EOL;
}

foreach (['packages.php', 'services.php'] as $name) {
    $path = $dir . DIRECTORY_SEPARATOR . $name;
    if (is_file($path)) {
        @unlink($path);
        echo 'REMOVED_EXISTING=' . $name . PHP_EOL;
    }
}

$test = $dir . DIRECTORY_SEPARATOR . 'natcodev-write-test.tmp';
$target = $dir . DIRECTORY_SEPARATOR . 'natcodev-write-test.php';
file_put_contents($test, "<?php return [];\n");
$renamed = @rename($test, $target);
echo 'RENAME_TEST=' . ($renamed ? 'ok' : 'failed') . PHP_EOL;
if (is_file($target)) {
    unlink($target);
}
if (is_file($test)) {
    unlink($test);
}

echo 'CACHE_REPAIR_CHECK_DONE' . PHP_EOL;
