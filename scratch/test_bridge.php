<?php
require_once __DIR__ . '/../api/python_bridge.php';

$payload = [
    'student_id' => '1001',
    'semester' => 4
];

try {
    echo "Testing Python Bridge...\n";
    $result = call_python_model($payload);
    echo "Success!\n";
    print_r($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
