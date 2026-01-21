<?php
// Endpoint direto para api.json - bypass do index.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$specPath = __DIR__ . '/../api.json';

if (file_exists($specPath)) {
    echo file_get_contents($specPath);
} else {
    http_response_code(404);
    echo json_encode([
        'error' => 'API specification not found',
        'path' => $specPath,
        'exists' => file_exists($specPath)
    ]);
}
