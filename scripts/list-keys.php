#!/usr/bin/env php
<?php

/**
 * Script para Listar API Keys
 * 
 * Uso:
 *   php scripts/list-keys.php [user_id] [--all]
 */

require_once __DIR__ . '/../config/database.php';

if (!$db) {
    die("❌ Erro: Não foi possível conectar ao banco de dados\n");
}

$userId = isset($argv[1]) && is_numeric($argv[1]) ? (int)$argv[1] : null;
$showAll = in_array('--all', $argv);

echo "\n🔑 API Keys - 28Facil\n";
echo "================================\n\n";

// Montar query
$query = "SELECT 
    id, key_prefix, name, user_id,
    permissions, rate_limit, usage_count,
    is_active, last_used_at, created_at,
    expires_at
    FROM api_keys";

$conditions = [];
if ($userId) {
    $conditions[] = "user_id = $userId";
}
if (!$showAll) {
    $conditions[] = "is_active = 1";
}

if ($conditions) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}

$query .= " ORDER BY created_at DESC";

$result = $db->query($query);

if ($result->num_rows === 0) {
    echo "Nenhuma key encontrada.\n\n";
    exit(0);
}

echo sprintf("%-5s %-15s %-25s %-8s %-10s %-8s %-10s\n",
    'ID', 'Prefixo', 'Nome', 'User', 'Usos', 'Status', 'Criada'
);
echo str_repeat('-', 90) . "\n";

while ($row = $result->fetch_assoc()) {
    $status = $row['is_active'] ? '✅ Ativa' : '❌ Inativa';
    $created = date('d/m/Y', strtotime($row['created_at']));
    
    echo sprintf("%-5d %-15s %-25s %-8s %-10d %-8s %-10s\n",
        $row['id'],
        $row['key_prefix'],
        substr($row['name'], 0, 25),
        $row['user_id'] ?: 'N/A',
        $row['usage_count'],
        $status,
        $created
    );
}

echo "\n";
echo "Total: " . $result->num_rows . " key(s)\n\n";

$db->close();
