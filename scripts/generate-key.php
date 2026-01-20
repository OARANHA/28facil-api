#!/usr/bin/env php
<?php

/**
 * Script para Gerar Nova API Key
 * 
 * Uso:
 *   php scripts/generate-key.php "Nome da Key" [user_id] [permissions] [rate_limit]
 * 
 * Exemplos:
 *   php scripts/generate-key.php "Minha Key"
 *   php scripts/generate-key.php "Key Admin" 1 "read,write,delete" 5000
 */

require_once __DIR__ . '/../config/database.php';

if (!$db) {
    die("❌ Erro: Não foi possível conectar ao banco de dados\n");
}

// Parse argumentos
$name = $argv[1] ?? null;
$userId = isset($argv[2]) ? (int)$argv[2] : null;
$permissions = isset($argv[3]) ? explode(',', $argv[3]) : ['read'];
$rateLimit = isset($argv[4]) ? (int)$argv[4] : 1000;

if (!$name) {
    echo "\n🔑 Gerador de API Keys - 28Facil\n";
    echo "================================\n\n";
    echo "Uso: php generate-key.php \"Nome\" [user_id] [permissions] [rate_limit]\n\n";
    echo "Exemplos:\n";
    echo "  php generate-key.php \"Minha Key\"\n";
    echo "  php generate-key.php \"Key Admin\" 1 \"read,write,delete\" 5000\n\n";
    exit(1);
}

// Gerar key
echo "\n🔑 Gerando nova API Key...\n\n";

$secret = bin2hex(random_bytes(24));
$fullKey = '28fc_' . $secret;
$keyHash = hash('sha256', $fullKey);
$keyPrefix = '28fc_' . substr($secret, 0, 8);

// Inserir no banco
$stmt = $db->prepare("
    INSERT INTO api_keys (
        key_hash, key_prefix, user_id, name, 
        permissions, rate_limit, is_active, 
        created_at, updated_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, 1, NOW(), NOW()
    )
");

$permissionsJson = json_encode($permissions);
$stmt->bind_param('ssissi', $keyHash, $keyPrefix, $userId, $name, $permissionsJson, $rateLimit);

if ($stmt->execute()) {
    $keyId = $db->insert_id;
    
    echo "✅ API Key criada com sucesso!\n\n";
    echo "ID:          $keyId\n";
    echo "Nome:        $name\n";
    echo "Usuário ID:  " . ($userId ?: 'N/A') . "\n";
    echo "Permissões:  " . implode(', ', $permissions) . "\n";
    echo "Rate Limit:  $rateLimit req/hora\n";
    echo "Prefixo:     $keyPrefix\n\n";
    echo "⚠️  API KEY (guarde em local seguro!): \n\n";
    echo "    $fullKey\n\n";
    echo "⚠️  Esta key NÃO será mostrada novamente!\n\n";
} else {
    echo "❌ Erro ao criar key: " . $stmt->error . "\n";
    exit(1);
}

$stmt->close();
$db->close();
