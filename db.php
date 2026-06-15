<?php
/**
 * Shared database connection helper.
 * Returns a mysqli instance; aborts with JSON error on failure.
 */

require_once __DIR__ . '/config.php';

function getDbConnection(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
        exit;
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}
