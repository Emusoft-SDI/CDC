<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/*
 * about.php loads this partial. It used to hold its own four-column footer (with a
 * hardcoded remote logo, a "©2023" line and links to five legal pages that were never
 * built). It now renders the single shared footer like every other public page.
 */
?>
<?= public_footer() ?>
