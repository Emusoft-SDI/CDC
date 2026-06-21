<?php
// Centralized Schema Management
// This file contains all JIT (Just-In-Time) schema creation functions.
// These are now isolated from the main library logic.

function academy_ensure_schema(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) { return; }
    $ensured = true;

    // ... [Content of academy_ensure_schema] ...
    // (I will need to paste the full content here)
}

function admin_ensure_schema(PDO $pdo): void { 
    // ... [Content of admin_ensure_schema] ...
}
// ...
