<?php
require_once __DIR__ . '/../config.php';

// Ensure session is started for auth check
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
// Do not redirect here; let index.php handle authentication logic.
// This script will only serve as a flag or helper if needed,
// but for now, we leave it empty to avoid interference.
