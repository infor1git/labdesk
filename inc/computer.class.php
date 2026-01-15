<?php
/**
 * LabDesk Plugin - Computer Class
 * Gerencia computadores sincronizados do RustDesk
 * VERSÃO CORRIGIDA - Métodos estáticos para sincronização
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginLabdeskComputer
{
    /**
     * Get all computers from database
     *
     * @return array
     */
    public static function getAll()
    {
        global $DB;

        try {
            $result = $DB->query(
                "SELECT * FROM `glpi_labdesk_computers` ORDER BY `rustdesk_name` ASC"
            );

            if (!$result) {
                return [];
            }

            $computers = [];
            while ($row = $result->fetch_assoc()) {
                $row['groups'] = self::getComputerGroups($row['id']);
                $computers[] = $row;
            }

            return $computers;

        } catch (Exception $e) {
            error_log("LabDesk Computer Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get computer by ID
     *
     * @param int $computer_id
     * @return array|null
     */
    public static function getById($computer_id)
    {
        global $DB;

        try {
            $escaped_id = $DB->escape($computer_id);
            $result = $DB->query(
                "SELECT * FROM `glpi_labdesk_computers` WHERE `id` = '{$escaped_id}'"
            );

            if (!$result) {
                return null;
            }

            $row = $result->fetch_assoc();
            if ($row) {
                $row['groups'] = self::getComputerGroups($row['id']);
            }

            return $row;

        } catch (Exception $e) {
            error_log("LabDesk Computer Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get computer by RustDesk ID
     *
     * @param string $rustdesk_id
     * @return array|null
     */
    public static function getByRustdeskId($rustdesk_id)
    {
        global $DB;

        try {
            $escaped_id = $DB->escape($rustdesk_id);
            $result = $DB->query(
                "SELECT * FROM `glpi_labdesk_computers` WHERE `rustdesk_id` = '{$escaped_id}'"
            );

            if (!$result) {
                return null;
            }

            $row = $result->fetch_assoc();
            if ($row) {
                $row['groups'] = self::getComputerGroups($row['id']);
            }

            return $row;

        } catch (Exception $e) {
            error_log("LabDesk Computer Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get groups for a computer
     *
     * @param int $computer_id
     * @return array
     */
    public static function getComputerGroups($computer_id)
    {
        global $DB;

        try {
            $escaped_id = $DB->escape($computer_id);
            $result = $DB->query(
                "SELECT g.`name` FROM `glpi_labdesk_groups` g
                 INNER JOIN `glpi_labdesk_group_computers` gc ON g.`id` = gc.`group_id`
                 WHERE gc.`computer_id` = '{$escaped_id}'
                 ORDER BY g.`name` ASC"
            );

            if (!$result) {
                return [];
            }

            $groups = [];
            while ($row = $result->fetch_assoc()) {
                $groups[] = $row['name'];
            }

            return $groups;

        } catch (Exception $e) {
            error_log("LabDesk Computer Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create or update computer
     *
     * @param array $data
     * @return boolean
     */
    public static function save($data)
    {
        global $DB;

        try {
            $rustdesk_id = $DB->escape($data['rustdesk_id'] ?? '');
            $rustdesk_name = $DB->escape($data['rustdesk_name'] ?? 'Unknown');
            $alias = $DB->escape($data['alias'] ?? '');
            $unit = $DB->escape($data['unit'] ?? '');
            $department = $DB->escape($data['department'] ?? '');
            $status = $DB->escape($data['status'] ?? 'offline');
            $last_online = isset($data['last_online']) ? (int)$data['last_online'] : null;

            if ($last_online) {
                $last_online_sql = "FROM_UNIXTIME({$last_online})";
            } else {
                $last_online_sql = "NULL";
            }

            $DB->queryOrDie(
                "INSERT INTO `glpi_labdesk_computers` 
                 (`rustdesk_id`, `rustdesk_name`, `alias`, `unit`, `department`, `status`, `last_online`, `created_at`)
                 VALUES ('{$rustdesk_id}', '{$rustdesk_name}', '{$alias}', '{$unit}', '{$department}', '{$status}', {$last_online_sql}, NOW())
                 ON DUPLICATE KEY UPDATE 
                 `rustdesk_name` = '{$rustdesk_name}',
                 `alias` = '{$alias}',
                 `unit` = '{$unit}',
                 `department` = '{$department}',
                 `status` = '{$status}',
                 `last_online` = {$last_online_sql},
                 `updated_at` = NOW()",
                "LabDesk: Error saving computer"
            );

            return true;

        } catch (Exception $e) {
            error_log("LabDesk Computer Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete computer
     *
     * @param int $computer_id
     * @return boolean
     */
    public static function delete($computer_id)
    {
        global $DB;

        try {
            $escaped_id = $DB->escape($computer_id);

            // Deletar relacionamentos com grupos
            $DB->queryOrDie(
                "DELETE FROM `glpi_labdesk_group_computers` WHERE `computer_id` = '{$escaped_id}'",
                "LabDesk: Error deleting computer groups"
            );

            // Deletar computador
            $DB->queryOrDie(
                "DELETE FROM `glpi_labdesk_computers` WHERE `id` = '{$escaped_id}'",
                "LabDesk: Error deleting computer"
            );

            return true;

        } catch (Exception $e) {
            error_log("LabDesk Computer Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync computers from RustDesk API
     * Usa o endpoint correto: /api/admin/peer/list
     * MÉTODO ESTÁTICO para uso em AJAX
     *
     * @return array
     */
    public static function syncFromRustdesk()
    {
        $config = PluginLabdeskConfig::getConfig();

        // Validar configuração
        $validation = PluginLabdeskConfig::validateConfig();
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Configuração incompleta: ' . implode(', ', $validation['errors']),
                'synced' => 0
            ];
        }

        try {
            $rustdesk_url = rtrim($config['rustdesk_url'], '/');
            $api_token = $config['rustdesk_token'];

            // URL do endpoint correto
            $api_url = $rustdesk_url . '/api/admin/peer/list';

            // Headers da requisição
            $headers = [
                'Content-Type: application/json',
                'Accept: application/json',
                'api-token: ' . $api_token
            ];

            // Fazer requisição cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error) {
                return [
                    'success' => false,
                    'message' => 'Erro cURL: ' . $curl_error,
                    'synced' => 0
                ];
            }

            if ($http_code !== 200) {
                return [
                    'success' => false,
                    'message' => 'Erro HTTP ' . $http_code . ' ao conectar com RustDesk',
                    'synced' => 0
                ];
            }

            $response_data = json_decode($response, true);
            
            if (!$response_data) {
                return [
                    'success' => false,
                    'message' => 'Resposta inválida do RustDesk',
                    'synced' => 0
                ];
            }

            // Verificar se a resposta tem o formato esperado
            if ($response_data['code'] !== 0) {
                return [
                    'success' => false,
                    'message' => 'Erro RustDesk: ' . ($response_data['message'] ?? 'Unknown error'),
                    'synced' => 0
                ];
            }

            // Processar lista de computadores
            $computers = isset($response_data['data']['list']) ? $response_data['data']['list'] : [];
            $synced_count = 0;

            foreach ($computers as $device) {
                $rustdesk_id = $device['id'] ?? null;
                $hostname = $device['hostname'] ?? 'Unknown';
                $host_alias = $device['alias'] ?? null;
                $status = 'offline';
                $last_online = null;

                if (!$rustdesk_id) {
                    continue;
                }

                // Calcular status baseado em last_online_time
                if (isset($device['last_online_time'])) {
                    $last_online_ts = (int)$device['last_online_time'];
                    $now = time();
                    $diff = $now - $last_online_ts;
                    
                    // Considerar online se foi visto nos últimos 5 minutos
                    if ($diff < 300) {
                        $status = 'online';
                    }
                    
                    $last_online = $last_online_ts;
                }

                // Montar dados do computador
                $computer_data = [
                    'rustdesk_id' => $rustdesk_id,
                    'rustdesk_name' => $hostname,
                    'status' => $status,
                    'last_online' => $last_online,
                    'alias' => $host_alias
                ];

                // Salvar no banco
                if (self::save($computer_data)) {
                    $synced_count++;
                }
            }

            // Atualizar last_sync
            PluginLabdeskConfig::setConfigValue('last_sync', date('Y-m-d H:i:s'));

            return [
                'success' => true,
                'message' => "Sincronização concluída: {$synced_count} dispositivos",
                'synced' => $synced_count,
                'total' => count($computers)
            ];

        } catch (Exception $e) {
            error_log("LabDesk Sync Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao sincronizar: ' . $e->getMessage(),
                'synced' => 0
            ];
        }
    }

    /**
     * Update computer status
     *
     * @param int $computer_id
     * @param string $status
     * @return boolean
     */
    public static function updateStatus($computer_id, $status)
    {
        global $DB;

        try {
            $escaped_id = $DB->escape($computer_id);
            $escaped_status = $DB->escape($status);

            $DB->queryOrDie(
                "UPDATE `glpi_labdesk_computers` SET `status` = '{$escaped_status}', `updated_at` = NOW() WHERE `id` = '{$escaped_id}'",
                "LabDesk: Error updating computer status"
            );

            return true;

        } catch (Exception $e) {
            error_log("LabDesk Computer Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update computer alias
     *
     * @param int $computer_id
     * @param string $alias
     * @return boolean
     */
    public static function updateAlias($computer_id, $alias)
    {
        global $DB;

        try {
            $escaped_id = $DB->escape($computer_id);
            $escaped_alias = $DB->escape($alias);

            $DB->queryOrDie(
                "UPDATE `glpi_labdesk_computers` SET `alias` = '{$escaped_alias}', `updated_at` = NOW() WHERE `id` = '{$escaped_id}'",
                "LabDesk: Error updating computer alias"
            );

            return true;

        } catch (Exception $e) {
            error_log("LabDesk Computer Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update computer unit and department
     *
     * @param int $computer_id
     * @param string $unit
     * @param string $department
     * @return boolean
     */
    public static function updateUnitDept($computer_id, $unit, $department)
    {
        global $DB;

        try {
            $escaped_id = $DB->escape($computer_id);
            $escaped_unit = $DB->escape($unit);
            $escaped_dept = $DB->escape($department);

            $DB->queryOrDie(
                "UPDATE `glpi_labdesk_computers` SET `unit` = '{$escaped_unit}', `department` = '{$escaped_dept}', `updated_at` = NOW() WHERE `id` = '{$escaped_id}'",
                "LabDesk: Error updating computer unit/department"
            );

            return true;

        } catch (Exception $e) {
            error_log("LabDesk Computer Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Count computers by status
     *
     * @param string $status
     * @return int
     */
    public static function countByStatus($status = null)
    {
        global $DB;

        try {
            $query = "SELECT COUNT(*) as count FROM `glpi_labdesk_computers`";
            
            if ($status) {
                $escaped_status = $DB->escape($status);
                $query .= " WHERE `status` = '{$escaped_status}'";
            }

            $result = $DB->query($query);
            
            if (!$result) {
                return 0;
            }

            $row = $result->fetch_assoc();
            return (int)$row['count'];

        } catch (Exception $e) {
            error_log("LabDesk Computer Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Search computers
     *
     * @param string $query
     * @return array
     */
    public static function search($query)
    {
        global $DB;

        try {
            $escaped_query = $DB->escape("%{$query}%");
            
            $result = $DB->query(
                "SELECT * FROM `glpi_labdesk_computers` 
                 WHERE `rustdesk_name` LIKE '{$escaped_query}' 
                 OR `alias` LIKE '{$escaped_query}'
                 ORDER BY `rustdesk_name` ASC"
            );

            if (!$result) {
                return [];
            }

            $computers = [];
            while ($row = $result->fetch_assoc()) {
                $row['groups'] = self::getComputerGroups($row['id']);
                $computers[] = $row;
            }

            return $computers;

        } catch (Exception $e) {
            error_log("LabDesk Computer Error: " . $e->getMessage());
            return [];
        }
    }
}
?>
