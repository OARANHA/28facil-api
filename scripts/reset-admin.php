<?php
/**
 * Script de Reset da Senha do Admin
 * 
 * Este script reseta a senha do usuário admin@28facil.com.br
 * para uma senha padrão conhecida.
 * 
 * Roda automaticamente no startup do container para garantir
 * acesso ao portal após redeploy via Portainer.
 */

// Carregar configurações do banco
require_once __DIR__ . '/../config/database.php';

echo "\n================================\n";
echo "28Facil API - Admin Password Reset\n";
echo "================================\n\n";

// Senha padrão
$defaultPassword = 'admin123';
$adminEmail = 'admin@28facil.com.br';

try {
    // Verificar se admin existe
    $stmt = $db->prepare("SELECT id, email, name FROM users WHERE email = :email");
    $stmt->execute(['email' => $adminEmail]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        echo "❌ Usuário admin não encontrado!\n";
        echo "   Criando usuário admin...\n\n";
        
        // Criar admin
        $hashedPassword = password_hash($defaultPassword, PASSWORD_BCRYPT);
        
        $createStmt = $db->prepare("
            INSERT INTO users (name, email, password_hash, role, status)
            VALUES (:name, :email, :password_hash, 'admin', 'active')
        ");
        
        $createStmt->execute([
            'name' => 'Administrador',
            'email' => $adminEmail,
            'password_hash' => $hashedPassword
        ]);
        
        echo "✅ Usuário admin criado com sucesso!\n";
        echo "   Email: $adminEmail\n";
        echo "   Senha: $defaultPassword\n\n";
        
    } else {
        echo "✅ Usuário admin encontrado: {$admin['name']} ({$admin['email']})\n";
        echo "   Resetando senha...\n\n";
        
        // Resetar senha
        $hashedPassword = password_hash($defaultPassword, PASSWORD_BCRYPT);
        
        $updateStmt = $db->prepare("
            UPDATE users 
            SET password_hash = :password_hash,
                status = 'active',
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $updateStmt->execute([
            'password_hash' => $hashedPassword,
            'id' => $admin['id']
        ]);
        
        echo "✅ Senha resetada com sucesso!\n";
        echo "   Email: $adminEmail\n";
        echo "   Nova senha: $defaultPassword\n\n";
    }
    
    // Limpar tentativas de login falhadas (se a tabela existir)
    try {
        $db->exec("DELETE FROM login_attempts WHERE email = '$adminEmail'");
        echo "✅ Tentativas de login falhadas foram limpas\n\n";
    } catch (Exception $e) {
        // Tabela pode não existir, ignorar
    }
    
    echo "================================\n";
    echo "✅ PRONTO! Você pode fazer login agora:\n";
    echo "================================\n";
    echo "URL: https://api.28facil.com.br/portal/\n";
    echo "Email: $adminEmail\n";
    echo "Senha: $defaultPassword\n";
    echo "================================\n\n";
    
    // Retornar sucesso
    exit(0);
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "\nDetalhes: " . $e->getTraceAsString() . "\n\n";
    exit(1);
}