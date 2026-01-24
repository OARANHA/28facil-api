<?php
/**
 * CSRF Protection Middleware
 * Valida token CSRF em requisições POST/PUT/PATCH/DELETE
 * 
 * Issue #2 - Proteções Críticas de Segurança
 */

namespace Middleware;

class CsrfProtection
{
    /**
     * Rotas que não precisam de verificação CSRF
     * (ex: webhooks, APIs públicas, endpoints de licenciamento, autenticação inicial)
     */
    protected $except = [
        // Authentication endpoints - usuário ainda não tem token
        '/api/auth/login',
        '/api/auth/register',
        '/api/csrf-token',
        
        // Webhooks
        '/api/webhook/*',
        '/api/verify-purchase-code',
        
        // License API endpoints - external integrations
        '/api/activate_license',
        '/api/verify_license',
        '/api/check_connection_ext',
        '/api/latest_version',
        '/api/check_update',
        '/api/deactivate_license',
        
        // 28Pro Installer endpoints
        '/api/license/activate',
        '/api/license/validate',
        '/api/license/check'
    ];
    
    /**
     * Handle an incoming request.
     *
     * @param  mixed  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // Apenas verificar métodos que modificam dados
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }
        
        // Verificar se rota está na lista de exceções
        if ($this->inExceptArray($request)) {
            return $next($request);
        }
        
        // Obter token do header ou input
        $token = $request->header('X-CSRF-TOKEN') ?? $request->input('_token');
        
        // Verificar se token existe na sessão
        $sessionToken = $request->session()->token();
        
        // Validar token
        if (!$token || !hash_equals($sessionToken, $token)) {
            http_response_code(419); // 419 = Token Mismatch
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Token CSRF inválido ou expirado',
                'code' => 'CSRF_TOKEN_MISMATCH'
            ]);
            exit;
        }
        
        return $next($request);
    }
    
    /**
     * Verifica se a requisição está na lista de exceções
     *
     * @param  mixed  $request
     * @return bool
     */
    protected function inExceptArray($request)
    {
        foreach ($this->except as $except) {
            if ($except !== '/') {
                $except = trim($except, '/');
            }
            
            if ($request->fullUrlIs($except) || $request->is($except)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Gera novo token CSRF
     *
     * @param  mixed  $request
     * @return string
     */
    public static function generateToken($request)
    {
        $token = bin2hex(random_bytes(32));
        $request->session()->put('_token', $token);
        return $token;
    }
}