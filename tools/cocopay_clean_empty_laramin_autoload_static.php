<?php

$path = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\vendor\\composer\\autoload_static.php';
$content = file_get_contents($path);
$content = preg_replace("/\\s*'Laramin\\\\\\\\Utility\\\\\\\\'\\s*=>\\s*16,\\R/", '', $content);
$content = preg_replace("/\\s*'Laramin\\\\\\\\Utility\\\\\\\\'\\s*=>\\s*\\R\\s*array \\(\\R\\s*\\),\\R/", '', $content);
file_put_contents($path, $content);
echo 'EMPTY_LARAMIN_AUTOLOAD_STATIC_REMOVED' . PHP_EOL;
