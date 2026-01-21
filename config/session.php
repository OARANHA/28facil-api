<?php
/**
 * Configuração de Sessões Seguras PHP
 * Issue #2 - Proteções Críticas de Segurança
 */

// Apenas iniciar sessão se ainda não iniciada
if (session_status() === PHP_SESSION_NONE) {
    
    // Configurações de segurança da sessão
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    // Usar cookies seguros apenas em HTTPS (produção)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    // Regenerar ID da sessão periodicamente
    ini_set('session.gc_maxlifetime', 3600); // 1 hora
    ini_set('session.cookie_lifetime', 0);   // Expira ao fechar navegador
    
    // Nome da sessão customizado
    session_name('28FACIL_SESSION');
    
    // Iniciar sessão
    session_start();
    
    // Gerar token CSRF se não existir
    if (!isset($_SESSION['_token'])) {
        $_SESSION['_token'] = bin2hex(random_bytes(32));
    }
    
    // Regenerar ID da sessão periodicamente para prevenir session fixation
    if (!isset($_SESSION['_last_regenerate'])) {
        $_SESSION['_last_regenerate'] = time();
    } elseif (time() - $_SESSION['_last_regenerate'] > 300) { // 5 minutos
        session_regenerate_id(true);
        $_SESSION['_last_regenerate'] = time();
    }
}
