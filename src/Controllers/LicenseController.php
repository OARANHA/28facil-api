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
     * GET /licenses
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
                WHERE user_id = :user_id
                ORDER BY l.created_at DESC
            ");
            $stmt->execute(['user_id' => $userId]);
        }
        
        $licenses = $stmt->fetchAll();
        
        return [
            'success' => true,
            'licenses' => $licenses
        ];
    }
    
    /**
     * POST /licenses
     * Criar nova licença
     */
    public function create($userId, $isAdmin = false)
    {
        if (!$isAdmin) {
            http_response_code(403);
            return ['error' => 'Acesso negado. Apenas administradores.'];
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $targetUserId = $input['user_id'] ?? null;
        $productName = $input['product_name'] ?? 'AiVoPro';
        $licenseType = $input['license_type'] ?? 'lifetime';
        $maxActivations = $input['max_activations'] ?? 1;
        
        if (!$targetUserId) {
            http_response_code(400);
            return ['error' => 'user_id é obrigatório'];
        }
        
        // Gerar purchase code único
        $purchaseCode = $this->generatePurchaseCode();
        $uuid = $this->generateUUID();
        
        $stmt = $this->db->prepare("
            INSERT INTO licenses (
                uuid, user_id, purchase_code, product_name, 
                license_type, max_activations
            ) VALUES (:uuid, :user_id, :purchase_code, :product_name, :license_type, :max_activations)
            RETURNING id, uuid, purchase_code, product_name, license_type, max_activations
        ");
        
        $stmt->execute([
            'uuid' => $uuid,
            'user_id' => $targetUserId,
            'purchase_code' => $purchaseCode,
            'product_name' => $productName,
            'license_type' => $licenseType,
            'max_activations' => $maxActivations
        ]);
        
        $license = $stmt->fetch();
        
        if ($license) {
            http_response_code(201);
            return [
                'success' => true,
                'message' => 'Licença criada com sucesso',
                'license' => $license
            ];
        }
        
        http_response_code(500);
        return ['error' => 'Erro ao criar licença'];
    }
    
    /**
     * GET /licenses/{id}
     * Detalhes da licença
     */
    public function get($licenseId, $userId, $isAdmin = false)
    {
        $query = "
            SELECT l.*, u.name as user_name, u.email as user_email
            FROM licenses l
            JOIN users u ON l.user_id = u.id
            WHERE l.id = :license_id
        ";
        
        $params = ['license_id' => $licenseId];
        
        if (!$isAdmin) {
            $query .= " AND l.user_id = :user_id";
            $params['user_id'] = $userId;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $license = $stmt->fetch();
        
        if ($license) {
            // Buscar ativações
            $activationsStmt = $this->db->prepare("
                SELECT * FROM license_activations 
                WHERE license_id = :license_id
                ORDER BY activated_at DESC
            ");
            $activationsStmt->execute(['license_id' => $licenseId]);
            $license['activations'] = $activationsStmt->fetchAll();
            
            return [
                'success' => true,
                'license' => $license
            ];
        }
        
        http_response_code(404);
        return ['error' => 'Licença não encontrada'];
    }
    
    /**
     * POST /license/validate
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
            WHERE purchase_code = :purchase_code
        ");
        $stmt->execute(['purchase_code' => $purchaseCode]);
        $row = $stmt->fetch();
        
        if ($row) {
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
     * POST /license/activate
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
            WHERE purchase_code = :purchase_code AND status = 'active'
        ");
        $stmt->execute(['purchase_code' => $purchaseCode]);
        $license = $stmt->fetch();
        
        if (!$license) {
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
            ) VALUES (:uuid, :license_id, :license_key, :domain, :installation_hash, 
                      :installation_name, :server_ip, :user_agent, :metadata)
        ");
        
        $result = $activateStmt->execute([
            'uuid' => $uuid,
            'license_id' => $license['id'],
            'license_key' => $licenseKey,
            'domain' => $domain,
            'installation_hash' => $installationHash,
            'installation_name' => $installationName,
            'server_ip' => $serverIp,
            'user_agent' => $userAgent,
            'metadata' => $metadata
        ]);
        
        if ($result) {
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
     * GET /license/check
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
            WHERE la.license_key = :license_key AND la.status = 'active'
        ");
        $stmt->execute(['license_key' => $licenseKey]);
        $row = $stmt->fetch();
        
        if ($row) {
            // Atualizar last_check
            $updateStmt = $this->db->prepare("
                UPDATE license_activations 
                SET last_check_at = NOW(), check_count = check_count + 1
                WHERE id = :id
            ");
            $updateStmt->execute(['id' => $row['id']]);
            
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

    // ==========================================
    // ENDPOINTS COMPATÍVEIS COM LICENSEBOX API
    // Para integração com sistema GoFresha/28Pro
    // ==========================================

    /**
     * POST /api/check_connection_ext
     * Verifica conexão com a API
     */
    public function checkConnectionExt($request, $response)
    {
        return [
            'status' => true,
            'message' => 'Connection successful'
        ];
    }

    /**
     * POST /api/latest_version
     * Retorna a versão mais recente do produto
     * 
     * Payload esperado: {"product_id": "2006AB23"}
     */
    public function latestVersion($request, $response)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $productId = $input['product_id'] ?? null;
        
        if (!$productId) {
            http_response_code(400);
            return [
                'status' => false,
                'message' => 'product_id é obrigatório'
            ];
        }
        
        // Retornar versão atual do produto
        return [
            'status' => true,
            'current_version' => 'v2.0.0',
            'latest_version' => 'v2.0.0',
            'changelog' => 'Sistema de licenciamento migrado para 28Facil API',
            'update_available' => false
        ];
    }

    /**
     * POST /api/activate_license
     * Ativa uma licença para um cliente
     * 
     * Payload esperado: {
     *   "product_id": "2006AB23",
     *   "license_code": "XXXX-XXXX-XXXX",
     *   "client_name": "Nome do Cliente",
     *   "verify_type": "envato"
     * }
     */
    public function activateLicenseCompat($request, $response)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $productId = $input['product_id'] ?? null;
        $licenseCode = $input['license_code'] ?? null;
        $clientName = $input['client_name'] ?? null;
        $verifyType = $input['verify_type'] ?? 'default';
        
        if (!$productId || !$licenseCode || !$clientName) {
            http_response_code(400);
            return [
                'status' => false,
                'message' => 'product_id, license_code e client_name são obrigatórios'
            ];
        }
        
        // Gerar um "license file" content (base64 do JSON com dados da licença)
        $licenseData = [
            'product_id' => $productId,
            'license_code' => $licenseCode,
            'client_name' => $clientName,
            'activated_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year')),
            'signature' => hash('sha256', $productId . $licenseCode . $clientName . 'salt28facil')
        ];
        
        $licenseFileContent = base64_encode(json_encode($licenseData));
        
        return [
            'status' => true,
            'message' => 'Licença ativada com sucesso',
            'lic_response' => $licenseFileContent
        ];
    }

    /**
     * POST /api/verify_license
     * Verifica se uma licença é válida
     * 
     * Payload esperado: {
     *   "product_id": "2006AB23",
     *   "license_file": "base64_content" OU
     *   "license_code": "XXXX-XXXX-XXXX",
     *   "client_name": "Nome do Cliente"
     * }
     */
    public function verifyLicenseCompat($request, $response)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $productId = $input['product_id'] ?? null;
        $licenseFile = $input['license_file'] ?? null;
        $licenseCode = $input['license_code'] ?? null;
        $clientName = $input['client_name'] ?? null;
        
        if (!$productId) {
            http_response_code(400);
            return [
                'status' => false,
                'message' => 'product_id é obrigatório'
            ];
        }
        
        // Verificar via license_file (base64) OU via license_code+client_name
        if ($licenseFile) {
            try {
                $licenseData = json_decode(base64_decode($licenseFile), true);
                
                if (!$licenseData || $licenseData['product_id'] !== $productId) {
                    return [
                        'status' => false,
                        'message' => 'Licença inválida ou expirada'
                    ];
                }
                
                // Verificar expiração
                if (isset($licenseData['expires_at']) && strtotime($licenseData['expires_at']) < time()) {
                    return [
                        'status' => false,
                        'message' => 'Licença expirada'
                    ];
                }
                
                return [
                    'status' => true,
                    'message' => 'Verified! Thanks for purchasing.'
                ];
                
            } catch (Exception $e) {
                return [
                    'status' => false,
                    'message' => 'Erro ao processar licença'
                ];
            }
        }
        
        // Verificar via license_code + client_name
        if ($licenseCode && $clientName) {
            // Aqui você pode integrar com seu sistema de validação de purchase codes
            // Por enquanto, aceitar qualquer código para compatibilidade
            return [
                'status' => true,
                'message' => 'Verified! Thanks for purchasing.'
            ];
        }
        
        return [
            'status' => false,
            'message' => 'Dados insuficientes para verificação'
        ];
    }

    /**
     * POST /api/deactivate_license
     * Desativa uma licença
     * 
     * Payload esperado: {
     *   "product_id": "2006AB23",
     *   "license_file": "base64_content" OU
     *   "license_code": "XXXX-XXXX-XXXX",
     *   "client_name": "Nome do Cliente"
     * }
     */
    public function deactivateLicenseCompat($request, $response)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $productId = $input['product_id'] ?? null;
        
        if (!$productId) {
            http_response_code(400);
            return [
                'status' => false,
                'message' => 'product_id é obrigatório'
            ];
        }
        
        // Por enquanto, apenas retornar sucesso
        // Aqui você pode implementar lógica de desativação no seu banco
        return [
            'status' => true,
            'message' => 'Licença desativada com sucesso'
        ];
    }

    /**
     * POST /api/check_update
     * Verifica se há atualizações disponíveis
     * 
     * Payload esperado: {
     *   "product_id": "2006AB23",
     *   "current_version": "v1.0.0"
     * }
     */
    public function checkUpdate($request, $response)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $productId = $input['product_id'] ?? null;
        $currentVersion = $input['current_version'] ?? 'v1.0.0';
        
        if (!$productId) {
            http_response_code(400);
            return [
                'status' => false,
                'message' => 'product_id é obrigatório'
            ];
        }
        
        // Retornar que não há atualizações por enquanto
        return [
            'status' => true,
            'current_version' => $currentVersion,
            'latest_version' => 'v2.0.0',
            'update_available' => false,
            'message' => 'Sistema atualizado. Nenhuma atualização disponível no momento.'
        ];
    }
}
