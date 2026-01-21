<?php

namespace TwentyEightFacil\Controllers;

class AuthController
{
    private $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    /**
     * POST /api/auth/register
     * Registrar novo usuário
     */
    public function register()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $name = $input['name'] ?? '';
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        
        // Validações
        if (empty($name) || empty($email) || empty($password)) {
            http_response_code(400);
            return ['error' => 'Nome, email e senha são obrigatórios'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            return ['error' => 'Email inválido'];
        }
        
        if (strlen($password) < 6) {
            http_response_code(400);
            return ['error' => 'Senha deve ter no mínimo 6 caracteres'];
        }
        
        // Verificar se email já existe
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        
        if ($stmt->fetch()) {
            http_response_code(409);
            return ['error' => 'Email já cadastrado'];
        }
        
        // Criar usuário
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password_hash, role) 
            VALUES (:name, :email, :password_hash, 'customer')
            RETURNING id
        ");
        
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash
        ]);
        
        $result = $stmt->fetch();
        $userId = $result['id'];
        $token = $this->generateToken($userId);
        
        // Retornar com cookie httpOnly (SEGURANÇA)
        $this->setSecureCookie($token);
        
        return [
            'success' => true,
            'message' => 'Usuário criado com sucesso',
            'user' => [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'role' => 'customer'
            ]
            // Token não é mais retornado no JSON (está no cookie)
        ];
    }
    
    /**
     * POST /api/auth/login
     * Login de usuário
     */
    public function login()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            http_response_code(400);
            return ['error' => 'Email e senha são obrigatórios'];
        }
        
        // Buscar usuário
        $stmt = $this->db->prepare("
            SELECT id, name, email, password_hash, role, status 
            FROM users 
            WHERE email = :email
        ");
        $stmt->execute(['email' => $email]);
        
        $user = $stmt->fetch();
        
        if ($user) {
            if ($user['status'] !== 'active') {
                http_response_code(403);
                return ['error' => 'Usuário inativo ou suspenso'];
            }
            
            if (password_verify($password, $user['password_hash'])) {
                // Atualizar last_login
                $updateStmt = $this->db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
                $updateStmt->execute(['id' => $user['id']]);
                
                $token = $this->generateToken($user['id']);
                
                // Retornar com cookie httpOnly (SEGURANÇA)
                $this->setSecureCookie($token);
                
                return [
                    'success' => true,
                    'message' => 'Login realizado com sucesso',
                    'user' => [
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'role' => $user['role']
                    ]
                    // Token não é mais retornado no JSON (está no cookie)
                ];
            }
        }
        
        http_response_code(401);
        return ['error' => 'Email ou senha incorretos'];
    }
    
    /**
     * POST /api/auth/logout
     * Logout do usuário (limpar cookie)
     */
    public function logout()
    {
        // Limpar cookie setando valor vazio e expiração no passado
        setcookie(
            '28facil_token',
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
        
        return [
            'success' => true,
            'message' => 'Logout realizado com sucesso'
        ];
    }
    
    /**
     * GET /api/auth/me
     * Obter dados do usuário logado
     */
    public function me($userId)
    {
        $stmt = $this->db->prepare("
            SELECT id, name, email, role, status, created_at, last_login_at
            FROM users 
            WHERE id = :id
        ");
        $stmt->execute(['id' => $userId]);
        
        $user = $stmt->fetch();
        
        if ($user) {
            return [
                'success' => true,
                'user' => $user
            ];
        }
        
        http_response_code(404);
        return ['error' => 'Usuário não encontrado'];
    }
    
    /**
     * Definir cookie httpOnly seguro com JWT
     */
    private function setSecureCookie($token)
    {
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        
        setcookie(
            '28facil_token',
            $token,
            [
                'expires' => time() + (86400 * 7),  // 7 dias
                'path' => '/',
                'domain' => '',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
    }
    
    /**
     * Obter token JWT do cookie
     */
    public static function getTokenFromCookie()
    {
        return $_COOKIE['28facil_token'] ?? null;
    }
    
    /**
     * Base64 URL-safe encode (sem padding)
     */
    private static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL-safe decode
     */
    private static function base64UrlDecode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    /**
     * Gerar token JWT simples
     */
    private function generateToken($userId)
    {
        $header = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::base64UrlEncode(json_encode([
            'user_id' => $userId,
            'exp' => time() + (86400 * 7) // 7 dias (igual ao cookie)
        ]));
        $secret = getenv('JWT_SECRET') ?: '28facil_secret_change_in_production';
        $signature = self::base64UrlEncode(hash_hmac('sha256', "$header.$payload", $secret, true));
        
        return "$header.$payload.$signature";
    }
    
    /**
     * Validar token JWT
     */
    public static function validateToken($token)
    {
        if (!$token) return null;
        
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        
        list($header, $payload, $signature) = $parts;
        
        $secret = getenv('JWT_SECRET') ?: '28facil_secret_change_in_production';
        $expectedSignature = self::base64UrlEncode(hash_hmac('sha256', "$header.$payload", $secret, true));
        
        if ($signature !== $expectedSignature) return null;
        
        $data = json_decode(self::base64UrlDecode($payload), true);
        
        if (!$data || $data['exp'] < time()) return null;
        
        return $data['user_id'];
    }
}