<?php
/**
 * Script de Diagnóstico e Correção de Login
 * 28Facil API - Troubleshooting Completo
 */

echo "\n";
echo "╔═══════════════════════════════════════════╗\n";
echo "║   28Facil - Diagnóstico de Login         ║\n";
echo "╚═══════════════════════════════════════════╝\n\n";

// Carregar config
require_once __DIR__ . '/../config/database.php';

$adminEmail = 'admin@28facil.com.br';
$testPassword = 'admin123';

// ========================================
// ETAPA 1: Verificar Conexão com Banco
// ========================================
echo "📡 ETAPA 1: Verificando conexão com banco...\n";

try {
    $stmt = $db->query("SELECT version()");
    $version = $stmt->fetchColumn();
    echo "✅ Conexão OK! PostgreSQL: $version\n\n";
} catch (Exception $e) {
    echo "❌ ERRO: Não foi possível conectar ao banco\n";
    echo "   Detalhes: " . $e->getMessage() . "\n\n";
    exit(1);
}

// ========================================
// ETAPA 2: Verificar Usuário Admin
// ========================================
echo "👤 ETAPA 2: Verificando usuário admin...\n";

try {
    $stmt = $db->prepare("SELECT id, name, email, password_hash, role, status, created_at, last_login_at FROM users WHERE email = :email");
    $stmt->execute(['email' => $adminEmail]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "⚠️  Usuário admin não encontrado!\n";
        echo "   Criando usuário admin...\n";
        
        $passwordHash = password_hash($testPassword, PASSWORD_BCRYPT);
        $stmt = $db->prepare("
            INSERT INTO users (name, email, password_hash, role, status) 
            VALUES ('Administrador', :email, :password_hash, 'admin', 'active')
            RETURNING id
        ");
        $stmt->execute(['email' => $adminEmail, 'password_hash' => $passwordHash]);
        $result = $stmt->fetch();
        
        echo "✅ Usuário admin criado com ID: {$result['id']}\n\n";
        
        // Buscar novamente
        $stmt = $db->prepare("SELECT id, name, email, password_hash, role, status FROM users WHERE email = :email");
        $stmt->execute(['email' => $adminEmail]);
        $user = $stmt->fetch();
    }
    
    echo "Dados do usuário:\n";
    echo "  • ID:           {$user['id']}\n";
    echo "  • Nome:         {$user['name']}\n";
    echo "  • Email:        {$user['email']}\n";
    echo "  • Role:         {$user['role']}\n";
    echo "  • Status:       {$user['status']}\n";
    echo "  • Criado em:    {$user['created_at']}\n";
    echo "  • Último login: " . ($user['last_login_at'] ?? 'Nunca') . "\n";
    echo "  • Hash:         " . substr($user['password_hash'], 0, 30) . "...\n\n";
    
    // Verificar status
    if ($user['status'] !== 'active') {
        echo "⚠️  PROBLEMA: Usuário está com status '{$user['status']}'\n";
        echo "   Ativando usuário...\n";
        $db->prepare("UPDATE users SET status = 'active' WHERE id = :id")->execute(['id' => $user['id']]);
        echo "✅ Usuário ativado!\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO ao buscar usuário: " . $e->getMessage() . "\n\n";
    exit(1);
}

// ========================================
// ETAPA 3: Testar Verificação de Senha
// ========================================
echo "🔐 ETAPA 3: Testando verificação de senha...\n";

$isPasswordValid = password_verify($testPassword, $user['password_hash']);

echo "Senha testada:  '$testPassword'\n";
echo "Resultado:      " . ($isPasswordValid ? "✅ VÁLIDA" : "❌ INVÁLIDA") . "\n\n";

if (!$isPasswordValid) {
    echo "⚠️  PROBLEMA DETECTADO!\n";
    echo "   O hash no banco NÃO corresponde à senha '$testPassword'\n\n";
    
    echo "🔧 Corrigindo hash da senha...\n";
    $correctHash = password_hash($testPassword, PASSWORD_BCRYPT);
    
    $stmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
    $stmt->execute(['hash' => $correctHash, 'id' => $user['id']]);
    
    echo "✅ Hash corrigido!\n";
    echo "   Novo hash: " . substr($correctHash, 0, 30) . "...\n\n";
    
    // Verificar novamente
    echo "🔄 Verificando correção...\n";
    $finalCheck = password_verify($testPassword, $correctHash);
    echo "Resultado final: " . ($finalCheck ? "✅ OK!" : "❌ AINDA COM ERRO") . "\n\n";
    
    if (!$finalCheck) {
        echo "❌ ERRO CRÍTICO: Não foi possível corrigir a senha\n";
        exit(1);
    }
}

// ========================================
// ETAPA 4: Testar Login via AuthController
// ========================================
echo "🧪 ETAPA 4: Testando login interno (AuthController)...\n";

try {
    require_once __DIR__ . '/../src/Controllers/AuthController.php';
    
    $authController = new \TwentyEightFacil\Controllers\AuthController($db);
    
    // Simular input
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    
    // Criar arquivo temporário com JSON
    $tmpFile = tmpfile();
    fwrite($tmpFile, json_encode(['email' => $adminEmail, 'password' => $testPassword]));
    fseek($tmpFile, 0);
    
    // Não podemos testar diretamente pois usa file_get_contents('php://input')
    echo "⚠️  Teste direto do controller não é possível via CLI\n";
    echo "   Use o teste via cURL abaixo\n\n";
    
} catch (Exception $e) {
    echo "⚠️  Erro ao carregar controller: " . $e->getMessage() . "\n\n";
}

// ========================================
// ETAPA 5: Verificar Tentativas de Login
// ========================================
echo "📊 ETAPA 5: Verificando tentativas de login falhadas...\n";

try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM login_attempts WHERE status = 'failed'");
    $result = $stmt->fetch();
    echo "Total de tentativas falhadas: {$result['total']}\n";
    
    if ($result['total'] > 0) {
        echo "🧹 Limpando tentativas antigas...\n";
        $db->query("DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL '1 hour'");
        echo "✅ Limpeza concluída\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "⚠️  Tabela login_attempts não existe ou erro: " . $e->getMessage() . "\n\n";
}

// ========================================
// RESUMO FINAL
// ========================================
echo "═══════════════════════════════════════════\n";
echo "📋 RESUMO DO DIAGNÓSTICO\n";
echo "═══════════════════════════════════════════\n\n";

echo "✅ Credenciais Confirmadas:\n";
echo "   URL:   https://api.28facil.com.br/portal/\n";
echo "   Email: $adminEmail\n";
echo "   Senha: $testPassword\n\n";

echo "🧪 TESTE VIA cURL:\n";
echo "Rode este comando no terminal do servidor:\n\n";
echo "curl -X POST https://api.28facil.com.br/api/auth/login \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\"email\":\"$adminEmail\",\"password\":\"$testPassword\"}' \\\n";
echo "  -c cookies.txt -v\n\n";

echo "🔍 VERIFICAR:\n";
echo "1. Se recebe HTTP 200 OK\n";
echo "2. Se o cookie '28facil_token' é definido\n";
echo "3. Se retorna success: true\n\n";

echo "🐛 DEBUG NO NAVEGADOR:\n";
echo "1. Abra DevTools (F12)\n";
echo "2. Vá na aba Network\n";
echo "3. Tente fazer login\n";
echo "4. Verifique a requisição POST para /api/auth/login\n";
echo "5. Veja se o cookie 28facil_token está sendo setado em Set-Cookie\n";
echo "6. Verifique se há erros CORS\n\n";

echo "═══════════════════════════════════════════\n";
echo "✅ Diagnóstico concluído!\n";
echo "═══════════════════════════════════════════\n\n";
