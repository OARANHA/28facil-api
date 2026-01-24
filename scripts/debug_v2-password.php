<?php
/**
 * Script de Debug de Senha
 * Verifica se a senha admin123 está funcionando corretamente
 */

require_once __DIR__ . '/../config/database.php';

echo "\n";
echo "================================\n";
echo "28Facil API - Password Debug\n";
echo "================================\n\n";

try {
    $db = Database::getConnection();
    
    // Buscar usuário admin
    $stmt = $db->prepare("
        SELECT id, name, email, password_hash, role, status
        FROM users 
        WHERE email = 'admin@28facil.com.br'
    ");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "❌ ERRO: Usuário admin não encontrado!\n";
        exit(1);
    }
    
    echo "✅ Usuário encontrado:\n";
    echo "   ID: {$user['id']}\n";
    echo "   Nome: {$user['name']}\n";
    echo "   Email: {$user['email']}\n";
    echo "   Role: {$user['role']}\n";
    echo "   Status: {$user['status']}\n";
    echo "\n";
    
    // Mostrar hash atual
    echo "📋 Hash atual no banco:\n";
    echo "   " . substr($user['password_hash'], 0, 60) . "...\n";
    echo "   Tamanho: " . strlen($user['password_hash']) . " caracteres\n";
    echo "\n";
    
    // Testar senha admin123
    echo "🔐 Testando senha 'admin123':\n";
    $testPassword = 'admin123';
    
    if (password_verify($testPassword, $user['password_hash'])) {
        echo "   ✅ SUCESSO! password_verify() retornou TRUE\n";
        echo "   ✅ A senha 'admin123' está correta!\n";
    } else {
        echo "   ❌ FALHOU! password_verify() retornou FALSE\n";
        echo "   ❌ A senha 'admin123' NÃO bate com o hash do banco\n";
        echo "\n";
        echo "🔧 CORRIGINDO: Resetando senha para 'admin123'...\n";
        
        // Gerar novo hash
        $newHash = password_hash($testPassword, PASSWORD_BCRYPT);
        
        // Atualizar no banco
        $updateStmt = $db->prepare("
            UPDATE users 
            SET password_hash = :password_hash,
                updated_at = NOW()
            WHERE email = 'admin@28facil.com.br'
        ");
        $updateStmt->execute(['password_hash' => $newHash]);
        
        echo "   ✅ Hash atualizado com sucesso!\n";
        echo "\n";
        echo "📋 Novo hash:\n";
        echo "   " . substr($newHash, 0, 60) . "...\n";
        echo "\n";
        
        // Verificar novamente
        echo "🔐 Verificando novamente...\n";
        if (password_verify($testPassword, $newHash)) {
            echo "   ✅ PERFEITO! Agora funciona!\n";
        } else {
            echo "   ❌ AINDA COM PROBLEMA! Algo está muito errado...\n";
        }
    }
    
    echo "\n";
    echo "================================\n";
    echo "✅ Debug concluído!\n";
    echo "================================\n";
    echo "Agora tente fazer login com:\n";
    echo "Email: admin@28facil.com.br\n";
    echo "Senha: admin123\n";
    echo "================================\n\n";
    
} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
