<?php
require_once __DIR__ . '/../../shared.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        acad_admin_redirect('registry', 'Session expired.', true);
    }
}
