<?php
/**
 * Script de Debug de Senha
 * Verifica se o hash da senha no banco está correto
 */

require_once __DIR__ . '/../config/database.php';

echo "\n================================\n";
echo "Debug de Senha do Admin\n";
echo "================================\n\n";

$adminEmail = 'admin@28facil.com.br';
$testPassword = 'admin123';

try {
    // Buscar usuário
    $stmt = $db->prepare("SELECT id, name, email, password_hash, status FROM users WHERE email = :email");
    $stmt->execute(['email' => $adminEmail]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "❌ Usuário não encontrado!\n\n";
        exit(1);
    }
    
    echo "✅ Usuário encontrado:\n";
    echo "   ID: {$user['id']}\n";
    echo "   Nome: {$user['name']}\n";
    echo "   Email: {$user['email']}\n";
    echo "   Status: {$user['status']}\n";
    echo "   Hash no banco: {$user['password_hash']}\n\n";
    
    // Gerar novo hash para comparar
    $newHash = password_hash($testPassword, PASSWORD_BCRYPT);
    echo "Hash gerado agora: $newHash\n\n";
    
    // Verificar se o hash atual é válido
    $isValid = password_verify($testPassword, $user['password_hash']);
    
    echo "================================\n";
    echo "Teste de Verificação:\n";
    echo "================================\n";
    echo "Senha testada: '$testPassword'\n";
    echo "Resultado: " . ($isValid ? "✅ SENHA CORRETA" : "❌ SENHA INCORRETA") . "\n\n";
    
    if (!$isValid) {
        echo "\n⚠️  PROBLEMA DETECTADO!\n";
        echo "O hash no banco NÃO corresponde à senha 'admin123'\n";
        echo "\nPossíveis causas:\n";
        echo "1. Senha foi alterada manualmente no banco\n";
        echo "2. Hash foi gerado com algoritmo diferente\n";
        echo "3. Hash está corrompido ou truncado\n\n";
        
        echo "================================\n";
        echo "Corrigindo agora...\n";
        echo "================================\n\n";
        
        // Corrigir hash
        $correctHash = password_hash($testPassword, PASSWORD_BCRYPT);
        $updateStmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
        $updateStmt->execute(['hash' => $correctHash, 'id' => $user['id']]);
        
        echo "✅ Hash corrigido!\n";
        echo "Novo hash: $correctHash\n\n";
        
        // Verificar novamente
        $stmt->execute(['email' => $adminEmail]);
        $updatedUser = $stmt->fetch();
        $finalCheck = password_verify($testPassword, $updatedUser['password_hash']);
        
        echo "Verificação final: " . ($finalCheck ? "✅ OK!" : "❌ AINDA COM PROBLEMA") . "\n\n";
    }
    
    echo "================================\n";
    echo "✅ Você pode fazer login com:\n";
    echo "================================\n";
    echo "Email: $adminEmail\n";
    echo "Senha: $testPassword\n";
    echo "================================\n\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n\n";
    exit(1);
}