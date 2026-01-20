#!/usr/bin/env php
<?php

/**
 * Script para Revogar API Key
 * 
 * Uso:
 *   php scripts/revoke-key.php <key_id> [motivo]
 */

require_once __DIR__ . '/../config/database.php';

if (!$db) {
    die("❌ Erro: Não foi possível conectar ao banco de dados\n");
}

$keyId = isset($argv[1]) && is_numeric($argv[1]) ? (int)$argv[1] : null;
$reason = $argv[2] ?? 'Revogada manualmente';

if (!$keyId) {
    echo "\n❌ Revogar API Key\n";
    echo "================================\n\n";
    echo "Uso: php revoke-key.php <key_id> [motivo]\n\n";
    echo "Exemplo:\n";
    echo "  php revoke-key.php 5 \"Chave comprometida\"\n\n";
    exit(1);
}

// Verificar se existe
$stmt = $db->prepare("SELECT id, name, key_prefix, is_active FROM api_keys WHERE id = ?");
$stmt->bind_param('i', $keyId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if (!$row['is_active']) {
        echo "\n⚠️  Esta key já está revogada.\n\n";
        exit(0);
    }
    
    echo "\n⚠️  Revogar API Key\n";
    echo "================================\n\n";
    echo "ID:      {$row['id']}\n";
    echo "Nome:    {$row['name']}\n";
    echo "Prefixo: {$row['key_prefix']}\n";
    echo "Motivo:  $reason\n\n";
    
    echo "Tem certeza? (s/N): ";
    $confirm = trim(fgets(STDIN));
    
    if (strtolower($confirm) !== 's') {
        echo "\n❌ Operação cancelada.\n\n";
        exit(0);
    }
    
    // Revogar
    $updateStmt = $db->prepare("
        UPDATE api_keys 
        SET 
            is_active = 0,
            revoked_at = NOW(),
            revoked_reason = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $updateStmt->bind_param('si', $reason, $keyId);
    
    if ($updateStmt->execute()) {
        echo "\n✅ API Key revogada com sucesso!\n\n";
    } else {
        echo "\n❌ Erro ao revogar: " . $updateStmt->error . "\n\n";
    }
    
    $updateStmt->close();
} else {
    echo "\n❌ Key com ID $keyId não encontrada.\n\n";
}

$stmt->close();
$db->close();
