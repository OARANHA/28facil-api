<?php

namespace TwentyEightFacil\Controllers;

class UserController
{
    private $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    /**
     * GET /users
     * Listar todos os usuários (apenas admin)
     */
    public function list($isAdmin)
    {
        if (!$isAdmin) {
            http_response_code(403);
            return ['error' => 'Acesso negado. Apenas administradores.'];
        }
        
        $stmt = $this->db->prepare("
            SELECT id, name, email, phone, company, role, status, 
                   created_at, last_login_at
            FROM users 
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        return [
            'success' => true,
            'users' => $users,
            'count' => count($users)
        ];
    }
    
    /**
     * GET /users/{id}
     * Obter detalhes de um usuário específico
     */
    public function get($userId, $currentUserId, $isAdmin)
    {
        // Usuários podem ver seus próprios dados, admin pode ver todos
        if (!$isAdmin && $userId != $currentUserId) {
            http_response_code(403);
            return ['error' => 'Acesso negado'];
        }
        
        $stmt = $this->db->prepare("
            SELECT id, name, email, phone, company, role, status, 
                   email_verified_at, created_at, updated_at, last_login_at
            FROM users 
            WHERE id = :id
        ");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            return ['error' => 'Usuário não encontrado'];
        }
        
        return [
            'success' => true,
            'user' => $user
        ];
    }
    
    /**
     * POST /users
     * Criar novo cliente (apenas admin)
     */
    public function create($isAdmin)
    {
        if (!$isAdmin) {
            http_response_code(403);
            return ['error' => 'Acesso negado. Apenas administradores.'];
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $company = trim($input['company'] ?? '');
        $generatePassword = $input['generate_password'] ?? true;
        $password = $input['password'] ?? null;
        
        // Validações
        if (empty($name)) {
            http_response_code(400);
            return ['error' => 'Nome é obrigatório'];
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            return ['error' => 'Email válido é obrigatório'];
        }
        
        // Verificar se email já existe
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            http_response_code(409);
            return ['error' => 'Email já cadastrado'];
        }
        
        // Gerar senha se necessário
        if ($generatePassword || empty($password)) {
            $password = $this->generateRandomPassword();
            $passwordGenerated = true;
        } else {
            $passwordGenerated = false;
        }
        
        if (strlen($password) < 6) {
            http_response_code(400);
            return ['error' => 'Senha deve ter no mínimo 6 caracteres'];
        }
        
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        
        // Criar usuário
        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password_hash, phone, company, role) 
            VALUES (:name, :email, :password_hash, :phone, :company, 'customer')
            RETURNING id, name, email, phone, company, role, status, created_at
        ");
        
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
            'phone' => $phone ?: null,
            'company' => $company ?: null
        ]);
        
        $user = $stmt->fetch();
        
        $response = [
            'success' => true,
            'message' => 'Cliente criado com sucesso',
            'user' => $user
        ];
        
        // Se a senha foi gerada, retornar para mostrar ao admin
        if ($passwordGenerated) {
            $response['generated_password'] = $password;
            $response['message'] .= '. Senha gerada automaticamente.';
        }
        
        http_response_code(201);
        return $response;
    }
    
    /**
     * PUT /users/{id}
     * Atualizar dados do usuário
     */
    public function update($userId, $currentUserId, $isAdmin)
    {
        // Usuários podem editar seus próprios dados, admin pode editar todos
        if (!$isAdmin && $userId != $currentUserId) {
            http_response_code(403);
            return ['error' => 'Acesso negado'];
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $fields = [];
        $params = ['id' => $userId];
        
        if (isset($input['name']) && !empty(trim($input['name']))) {
            $fields[] = "name = :name";
            $params['name'] = trim($input['name']);
        }
        
        if (isset($input['phone'])) {
            $fields[] = "phone = :phone";
            $params['phone'] = trim($input['phone']) ?: null;
        }
        
        if (isset($input['company'])) {
            $fields[] = "company = :company";
            $params['company'] = trim($input['company']) ?: null;
        }
        
        // Email pode ser alterado (verificar duplicação)
        if (isset($input['email']) && !empty(trim($input['email']))) {
            $email = trim($input['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                return ['error' => 'Email inválido'];
            }
            
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
            $stmt->execute(['email' => $email, 'id' => $userId]);
            if ($stmt->fetch()) {
                http_response_code(409);
                return ['error' => 'Email já está em uso'];
            }
            
            $fields[] = "email = :email";
            $params['email'] = $email;
        }
        
        // Apenas admin pode alterar status e role
        if ($isAdmin) {
            if (isset($input['status'])) {
                $fields[] = "status = :status";
                $params['status'] = $input['status'];
            }
            if (isset($input['role'])) {
                $fields[] = "role = :role";
                $params['role'] = $input['role'];
            }
        }
        
        if (empty($fields)) {
            http_response_code(400);
            return ['error' => 'Nenhum campo para atualizar'];
        }
        
        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        // Retornar dados atualizados
        $stmt = $this->db->prepare("
            SELECT id, name, email, phone, company, role, status, 
                   created_at, updated_at, last_login_at
            FROM users WHERE id = :id
        ");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        
        return [
            'success' => true,
            'message' => 'Usuário atualizado com sucesso',
            'user' => $user
        ];
    }
    
    /**
     * DELETE /users/{id}
     * Desativar usuário (soft delete)
     */
    public function delete($userId, $isAdmin)
    {
        if (!$isAdmin) {
            http_response_code(403);
            return ['error' => 'Acesso negado. Apenas administradores.'];
        }
        
        // Não permitir deletar o próprio admin
        $stmt = $this->db->prepare("SELECT role FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            return ['error' => 'Usuário não encontrado'];
        }
        
        if ($user['role'] === 'admin') {
            http_response_code(403);
            return ['error' => 'Não é possível desativar administradores'];
        }
        
        // Soft delete - apenas mudar status
        $stmt = $this->db->prepare("
            UPDATE users 
            SET status = 'inactive', updated_at = NOW() 
            WHERE id = :id
        ");
        $stmt->execute(['id' => $userId]);
        
        return [
            'success' => true,
            'message' => 'Usuário desativado com sucesso'
        ];
    }
    
    /**
     * POST /users/{id}/reset-password
     * Resetar senha do usuário (admin only)
     */
    public function resetPassword($userId, $isAdmin)
    {
        if (!$isAdmin) {
            http_response_code(403);
            return ['error' => 'Acesso negado. Apenas administradores.'];
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $generatePassword = $input['generate'] ?? true;
        
        if ($generatePassword) {
            $newPassword = $this->generateRandomPassword();
        } else {
            $newPassword = $input['password'] ?? '';
            if (strlen($newPassword) < 6) {
                http_response_code(400);
                return ['error' => 'Senha deve ter no mínimo 6 caracteres'];
            }
        }
        
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        
        $stmt = $this->db->prepare("
            UPDATE users 
            SET password_hash = :password_hash, updated_at = NOW() 
            WHERE id = :id
        ");
        $stmt->execute([
            'password_hash' => $passwordHash,
            'id' => $userId
        ]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            return ['error' => 'Usuário não encontrado'];
        }
        
        return [
            'success' => true,
            'message' => 'Senha resetada com sucesso',
            'new_password' => $newPassword
        ];
    }
    
    /**
     * Gerar senha aleatória segura
     */
    private function generateRandomPassword($length = 12)
    {
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$%';
        $password = '';
        $max = strlen($chars) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }
        
        return $password;
    }
}