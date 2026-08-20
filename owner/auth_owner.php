<?php
// Shared access check for every owner page and owner API.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (($_SESSION['role'] ?? '') !== 'owner' || empty($_SESSION['owner_id'])) {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_starts_with($script, 'api_') || str_ends_with($script, '_ajax.php')) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    header('Location: ../login.php');
    exit;
}

$owner_id = (int) $_SESSION['owner_id'];
