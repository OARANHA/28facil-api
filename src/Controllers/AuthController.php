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
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            http_response_code(409);
            return ['error' => 'Email já cadastrado'];
        }
        
        // Criar usuário
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password_hash, role) 
            VALUES (?, ?, ?, 'customer')
        ");
        $stmt->bind_param('sss', $name, $email, $passwordHash);
        
        if ($stmt->execute()) {
            $userId = $stmt->insert_id;
            $token = $this->generateToken($userId);
            
            return [
                'success' => true,
                'message' => 'Usuário criado com sucesso',
                'user' => [
                    'id' => $userId,
                    'name' => $name,
                    'email' => $email,
                    'role' => 'customer'
                ],
                'token' => $token
            ];
        }
        
        http_response_code(500);
        return ['error' => 'Erro ao criar usuário'];
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
            WHERE email = ?
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if ($row['status'] !== 'active') {
                http_response_code(403);
                return ['error' => 'Usuário inativo ou suspenso'];
            }
            
            if (password_verify($password, $row['password_hash'])) {
                // Atualizar last_login
                $updateStmt = $this->db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
                $updateStmt->bind_param('i', $row['id']);
                $updateStmt->execute();
                
                $token = $this->generateToken($row['id']);
                
                return [
                    'success' => true,
                    'message' => 'Login realizado com sucesso',
                    'user' => [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'email' => $row['email'],
                        'role' => $row['role']
                    ],
                    'token' => $token
                ];
            }
        }
        
        http_response_code(401);
        return ['error' => 'Email ou senha incorretos'];
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
            WHERE id = ?
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return [
                'success' => true,
                'user' => $row
            ];
        }
        
        http_response_code(404);
        return ['error' => 'Usuário não encontrado'];
    }
    
    /**
     * Gerar token JWT simples
     */
    private function generateToken($userId)
    {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'user_id' => $userId,
            'exp' => time() + (86400 * 30) // 30 dias
        ]));
        $secret = getenv('JWT_SECRET') ?: '28facil_secret_change_in_production';
        $signature = base64_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
        
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
        $expectedSignature = base64_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
        
        if ($signature !== $expectedSignature) return null;
        
        $data = json_decode(base64_decode($payload), true);
        
        if (!$data || $data['exp'] < time()) return null;
        
        return $data['user_id'];
    }
}