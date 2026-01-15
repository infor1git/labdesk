<?php
/**
 * LabDesk Plugin - Group Class
 * Sincroniza grupos/tags do RustDesk
 */

class PluginLabdeskGroup extends CommonDBTM
{
    public static $rightname = 'config';
    public $dohistory = true;
    public $table = 'glpi_labdesk_groups';
    public $type = 'PluginLabdeskGroup';

    public static function getTypeName($nb = 0)
    {
        return 'Grupo';
    }

    /**
     * Obter todos os grupos do banco de dados
     * 
     * @return array
     */
    public static function getAll()
    {
        global $DB;
        
        try {
            $result = $DB->query("SELECT * FROM `glpi_labdesk_groups` ORDER BY `name` ASC");
            
            if (!$result) {
                return [];
            }
            
            $groups = [];
            while ($row = $result->fetch_assoc()) {
                $groups[] = $row;
            }
            return $groups;
        } catch (Exception $e) {
            error_log('[LabDesk Group] Erro ao obter grupos: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obter grupos com contagem de computadores
     * 
     * @return array
     */
    public static function getGroups()
    {
        global $DB;
        
        try {
            $result = $DB->query("
                SELECT g.*, COUNT(gc.computer_id) as computer_count
                FROM `glpi_labdesk_groups` g
                LEFT JOIN `glpi_labdesk_group_computers` gc ON g.id = gc.group_id
                GROUP BY g.id
                ORDER BY g.name ASC
            ");
            
            if (!$result) {
                return [];
            }
            
            $groups = [];
            while ($row = $result->fetch_assoc()) {
                $groups[] = $row;
            }
            return $groups;
        } catch (Exception $e) {
            error_log('[LabDesk Group] Erro ao obter grupos: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Sincronizar tags do RustDesk com grupos
     * Busca as tags da API e as sincroniza no banco
     * 
     * @return array ['success' => bool, 'message' => string, 'synced' => int]
     */
    public static function syncFromRustdesk()
    {
        try {
            // Obter configuração
            $config = PluginLabdeskConfig::getConfig();
            
            if (empty($config['rustdesk_url']) || empty($config['rustdesk_token'])) {
                return [
                    'success' => false,
                    'message' => 'URL ou Token do RustDesk não configurados'
                ];
            }
            
            // Fazer requisição para obter tags
            $url = rtrim($config['rustdesk_url'], '/') . '/api/admin/tag/list';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'api-token: ' . $config['rustdesk_token']
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            // Verificar erro de cURL
            if ($curl_error) {
                return [
                    'success' => false,
                    'message' => 'Erro cURL: ' . $curl_error
                ];
            }
            
            // Verificar código HTTP
            if ($http_code !== 200) {
                return [
                    'success' => false,
                    'message' => 'Erro HTTP ' . $http_code . ' ao conectar com RustDesk'
                ];
            }
            
            // Decodificar resposta
            $data = json_decode($response, true);
            
            if (!$data || !isset($data['data']['list'])) {
                return [
                    'success' => false,
                    'message' => 'Resposta inválida do RustDesk'
                ];
            }
            
            // Processar tags
            global $DB;
            $tags = $data['data']['list'];
            $synced_count = 0;
            
            foreach ($tags as $tag) {
                $rustdesk_id = $tag['id'] ?? null;
                $tag_name = $tag['name'] ?? null;
                $collection_name = $tag['collection']['name'] ?? 'Sem Coleção';
                
                if (!$rustdesk_id || !$tag_name) {
                    continue;
                }
                
                // Combinar coleção + tag para nome único
                $full_name = $collection_name . ' / ' . $tag_name;
                
                try {
                    $escaped_id = $DB->escape($rustdesk_id);
                    $escaped_name = $DB->escape($tag_name);
                    $escaped_full_name = $DB->escape($full_name);
                    $escaped_collection = $DB->escape($collection_name);
                    
                    // Inserir ou atualizar grupo
                    $DB->query("
                        INSERT INTO `glpi_labdesk_groups` 
                        (`id`, `name`, `description`, `updated_at`)
                        VALUES ('{$escaped_id}', '{$escaped_name}', '{$escaped_full_name}', NOW())
                        ON DUPLICATE KEY UPDATE 
                            `name` = '{$escaped_name}',
                            `description` = '{$escaped_full_name}',
                            `updated_at` = NOW()
                    ");
                    
                    $synced_count++;
                } catch (Exception $e) {
                    error_log('[LabDesk Group] Erro ao sincronizar tag ' . $tag_name . ': ' . $e->getMessage());
                    continue;
                }
            }
            
            return [
                'success' => true,
                'message' => "Sincronização de grupos concluída: {$synced_count} tags",
                'synced' => $synced_count
            ];
            
        } catch (Exception $e) {
            error_log('[LabDesk Group] Erro na sincronização: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao sincronizar: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Adicionar computador a um grupo
     * 
     * @param int $computerId
     * @param int $groupId
     * @return bool
     */
    public static function addComputerToGroup($computerId, $groupId)
    {
        global $DB;
        
        try {
            $escaped_group_id = $DB->escape($groupId);
            $escaped_computer_id = $DB->escape($computerId);
            
            $DB->query("
                INSERT IGNORE INTO `glpi_labdesk_group_computers` 
                (`group_id`, `computer_id`)
                VALUES ('{$escaped_group_id}', '{$escaped_computer_id}')
            ");
            
            return true;
        } catch (Exception $e) {
            error_log('[LabDesk Group] Erro ao adicionar computador ao grupo: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Remover computador de um grupo
     * 
     * @param int $computerId
     * @param int $groupId
     * @return bool
     */
    public static function removeComputerFromGroup($computerId, $groupId)
    {
        global $DB;
        
        try {
            $escaped_group_id = $DB->escape($groupId);
            $escaped_computer_id = $DB->escape($computerId);
            
            $DB->query("
                DELETE FROM `glpi_labdesk_group_computers` 
                WHERE `group_id` = '{$escaped_group_id}' AND `computer_id` = '{$escaped_computer_id}'
            ");
            
            return true;
        } catch (Exception $e) {
            error_log('[LabDesk Group] Erro ao remover computador do grupo: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obter computadores de um grupo
     * 
     * @param int $groupId
     * @return array
     */
    public static function getComputersByGroup($groupId)
    {
        global $DB;
        
        try {
            $escaped_group_id = $DB->escape($groupId);
            
            $result = $DB->query("
                SELECT c.* FROM `glpi_labdesk_computers` c
                INNER JOIN `glpi_labdesk_group_computers` gc ON c.id = gc.computer_id
                WHERE gc.group_id = '{$escaped_group_id}'
                ORDER BY c.rustdesk_name ASC
            ");
            
            if (!$result) {
                return [];
            }
            
            $computers = [];
            while ($row = $result->fetch_assoc()) {
                $computers[] = $row;
            }
            return $computers;
        } catch (Exception $e) {
            error_log('[LabDesk Group] Erro ao obter computadores do grupo: ' . $e->getMessage());
            return [];
        }
    }
}
?>
