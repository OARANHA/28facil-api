<?php

namespace TwentyEightFacil\Controllers;

class LicenseController
{
    private $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    /**
     * GET /api/licenses
     * Listar licenças do usuário
     */
    public function list($userId, $isAdmin = false)
    {
        if ($isAdmin) {
            // Admin vê todas
            $stmt = $this->db->prepare("
                SELECT l.*, u.name as user_name, u.email as user_email,
                       (SELECT COUNT(*) FROM license_activations WHERE license_id = l.id AND status = 'active') as active_activations
                FROM licenses l
                JOIN users u ON l.user_id = u.id
                ORDER BY l.created_at DESC
            ");
            $stmt->execute();
        } else {
            // Cliente vê só suas licenças
            $stmt = $this->db->prepare("
                SELECT l.*,
                       (SELECT COUNT(*) FROM license_activations WHERE license_id = l.id AND status = 'active') as active_activations
                FROM licenses l
                WHERE user_id = ?
                ORDER BY l.created_at DESC
            ");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
        }
        
        $result = $stmt->get_result();
        $licenses = [];
        
        while ($row = $result->fetch_assoc()) {
            $licenses[] = $row;
        }
        
        return [
            'success' => true,
            'licenses' => $licenses
        ];
    }
    
    /**
     * POST /api/licenses
     * Criar nova licença
     */
    public function create($userId, $isAdmin = false)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $targetUserId = $isAdmin && isset($input['user_id']) ? $input['user_id'] : $userId;
        $productName = $input['product_name'] ?? 'AiVoPro';
        $licenseType = $input['license_type'] ?? 'lifetime';
        $maxActivations = $input['max_activations'] ?? 1;
        
        // Gerar purchase code único
        $purchaseCode = $this->generatePurchaseCode();
        $uuid = $this->generateUUID();
        
        $stmt = $this->db->prepare("
            INSERT INTO licenses (
                uuid, user_id, purchase_code, product_name, 
                license_type, max_activations
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sisssi', $uuid, $targetUserId, $purchaseCode, $productName, $licenseType, $maxActivations);
        
        if ($stmt->execute()) {
            $licenseId = $stmt->insert_id;
            
            return [
                'success' => true,
                'message' => 'Licença criada com sucesso',
                'license' => [
                    'id' => $licenseId,
                    'uuid' => $uuid,
                    'purchase_code' => $purchaseCode,
                    'product_name' => $productName,
                    'license_type' => $licenseType,
                    'max_activations' => $maxActivations
                ]
            ];
        }
        
        http_response_code(500);
        return ['error' => 'Erro ao criar licença'];
    }
    
    /**
     * GET /api/licenses/{id}
     * Detalhes da licença
     */
    public function get($licenseId, $userId, $isAdmin = false)
    {
        $query = "
            SELECT l.*, u.name as user_name, u.email as user_email
            FROM licenses l
            JOIN users u ON l.user_id = u.id
            WHERE l.id = ?
        ";
        
        if (!$isAdmin) {
            $query .= " AND l.user_id = ?";
        }
        
        $stmt = $this->db->prepare($query);
        
        if ($isAdmin) {
            $stmt->bind_param('i', $licenseId);
        } else {
            $stmt->bind_param('ii', $licenseId, $userId);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Buscar ativações
            $activationsStmt = $this->db->prepare("
                SELECT * FROM license_activations 
                WHERE license_id = ?
                ORDER BY activated_at DESC
            ");
            $activationsStmt->bind_param('i', $licenseId);
            $activationsStmt->execute();
            $activationsResult = $activationsStmt->get_result();
            
            $activations = [];
            while ($activation = $activationsResult->fetch_assoc()) {
                $activations[] = $activation;
            }
            
            $row['activations'] = $activations;
            
            return [
                'success' => true,
                'license' => $row
            ];
        }
        
        http_response_code(404);
        return ['error' => 'Licença não encontrada'];
    }
    
    /**
     * POST /api/license/validate
     * Validar purchase code (usado pela aplicação)
     */
    public function validate()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $purchaseCode = $input['purchase_code'] ?? '';
        
        if (empty($purchaseCode)) {
            http_response_code(400);
            return ['error' => 'Purchase code é obrigatório'];
        }
        
        $stmt = $this->db->prepare("
            SELECT id, uuid, product_name, license_type, status, max_activations, expires_at,
                   (SELECT COUNT(*) FROM license_activations WHERE license_id = id AND status = 'active') as active_activations
            FROM licenses
            WHERE purchase_code = ?
        ");
        $stmt->bind_param('s', $purchaseCode);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $valid = $row['status'] === 'active' && 
                     ($row['expires_at'] === null || strtotime($row['expires_at']) > time());
            
            return [
                'valid' => $valid,
                'license' => [
                    'id' => $row['id'],
                    'uuid' => $row['uuid'],
                    'product' => $row['product_name'],
                    'type' => $row['license_type'],
                    'status' => $row['status'],
                    'max_activations' => (int)$row['max_activations'],
                    'active_activations' => (int)$row['active_activations'],
                    'can_activate' => (int)$row['active_activations'] < (int)$row['max_activations'],
                    'expires_at' => $row['expires_at']
                ]
            ];
        }
        
        http_response_code(404);
        return [
            'valid' => false,
            'error' => 'Purchase code inválido'
        ];
    }
    
    /**
     * POST /api/license/activate
     * Ativar licença em domínio
     */
    public function activate()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $purchaseCode = $input['purchase_code'] ?? '';
        $domain = $input['domain'] ?? '';
        $installationHash = $input['installation_hash'] ?? '';
        $installationName = $input['installation_name'] ?? $domain;
        
        if (empty($purchaseCode) || empty($domain) || empty($installationHash)) {
            http_response_code(400);
            return ['error' => 'Purchase code, domain e installation_hash são obrigatórios'];
        }
        
        // Buscar licença
        $stmt = $this->db->prepare("
            SELECT id, max_activations,
                   (SELECT COUNT(*) FROM license_activations WHERE license_id = id AND status = 'active') as active_activations
            FROM licenses
            WHERE purchase_code = ? AND status = 'active'
        ");
        $stmt->bind_param('s', $purchaseCode);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!($license = $result->fetch_assoc())) {
            http_response_code(404);
            return ['error' => 'Licença não encontrada ou inativa'];
        }
        
        // Verificar limite de ativações
        if ($license['active_activations'] >= $license['max_activations']) {
            http_response_code(403);
            return ['error' => 'Limite de ativações atingido'];
        }
        
        // Criar ativação
        $uuid = $this->generateUUID();
        $licenseKey = $this->generateLicenseKey();
        $serverIp = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $metadata = json_encode($input['metadata'] ?? []);
        
        $activateStmt = $this->db->prepare("
            INSERT INTO license_activations (
                uuid, license_id, license_key, domain, installation_hash, 
                installation_name, server_ip, user_agent, metadata
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $activateStmt->bind_param(
            'sisssssss',
            $uuid, $license['id'], $licenseKey, $domain, $installationHash,
            $installationName, $serverIp, $userAgent, $metadata
        );
        
        if ($activateStmt->execute()) {
            return [
                'success' => true,
                'activated' => true,
                'license_key' => $licenseKey,
                'message' => 'Licença ativada com sucesso'
            ];
        }
        
        http_response_code(500);
        return ['error' => 'Erro ao ativar licença'];
    }
    
    /**
     * GET /api/license/check
     * Verificar status da licença (health check)
     */
    public function check()
    {
        $licenseKey = $_SERVER['HTTP_X_LICENSE_KEY'] ?? null;
        
        if (!$licenseKey) {
            http_response_code(401);
            return ['error' => 'License key não fornecida'];
        }
        
        $stmt = $this->db->prepare("
            SELECT la.*, l.status as license_status, l.expires_at
            FROM license_activations la
            JOIN licenses l ON la.license_id = l.id
            WHERE la.license_key = ? AND la.status = 'active'
        ");
        $stmt->bind_param('s', $licenseKey);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Atualizar last_check
            $updateStmt = $this->db->prepare("
                UPDATE license_activations 
                SET last_check_at = NOW(), check_count = check_count + 1
                WHERE id = ?
            ");
            $updateStmt->bind_param('i', $row['id']);
            $updateStmt->execute();
            
            $active = $row['license_status'] === 'active' && 
                      ($row['expires_at'] === null || strtotime($row['expires_at']) > time());
            
            return [
                'active' => $active,
                'status' => $row['license_status'],
                'domain' => $row['domain'],
                'activated_at' => $row['activated_at'],
                'expires_at' => $row['expires_at'],
                'last_check_at' => date('c')
            ];
        }
        
        http_response_code(404);
        return [
            'active' => false,
            'error' => 'Licença não encontrada ou inativa'
        ];
    }
    
    // Helpers
    private function generatePurchaseCode()
    {
        return strtoupper(sprintf(
            '%s-%s-%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(4))
        ));
    }
    
    private function generateLicenseKey()
    {
        return '28fc_' . bin2hex(random_bytes(32));
    }
    
    private function generateUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}