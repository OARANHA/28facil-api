<?php
/**
 * Security Headers Middleware
 * Adiciona headers de segurança em todas as respostas da API
 * 
 * Issue #2 - Proteções Críticas de Segurança
 */

namespace Middleware;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        $response = $next($request);
        
        // Prevenir clickjacking
        $response->header('X-Frame-Options', 'DENY');
        
        // Prevenir MIME type sniffing
        $response->header('X-Content-Type-Options', 'nosniff');
        
        // Referrer Policy
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Content Security Policy
        $csp = [
            "default-src 'self'",
            "script-src 'self' https://cdn.tailwindcss.com https://cdn.jsdelivr.net 'unsafe-inline'",
            "style-src 'self' https://cdn.tailwindcss.com 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        ];
        $response->header('Content-Security-Policy', implode('; ', $csp));
        
        // HSTS (HTTP Strict Transport Security)
        // Apenas em produção com HTTPS
        if ($request->secure()) {
            $response->header(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }
        
        // Permissions Policy (antiga Feature Policy)
        $permissions = [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=()',
            'usb=()',
            'magnetometer=()'
        ];
        $response->header('Permissions-Policy', implode(', ', $permissions));
        
        // X-XSS-Protection (legacy, mas ainda útil para navegadores antigos)
        $response->header('X-XSS-Protection', '1; mode=block');
        
        return $response;
    }
}
