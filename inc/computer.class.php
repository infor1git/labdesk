<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginLabdeskComputer {

    /**
     * Get all computers from database
     * @return array
     */
    public static function getAll() {
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
                $row['groups']   = self::getComputerGroups($row['id']);
                $computers[]     = $row;
            }

            // Enriquecer com inventário GLPI (computador, localização e grupo)
            self::attachInventoryData($computers);

            return $computers;

        } catch (Exception $e) {
            error_log("LabDesk Computer Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Anexa dados do inventário GLPI (maior ID por hostname)
     */
    private static function attachInventoryData(array &$computers) {
        global $DB;

        if (empty($computers)) {
            return;
        }

        // nomes de host que temos em LabDesk
        $computer_names = [];
        foreach ($computers as $c) {
            if (!empty($c['rustdesk_name'])) {
                $computer_names[] = $c['rustdesk_name'];
            }
        }
        $computer_names = array_unique($computer_names);
        if (empty($computer_names)) {
            return;
        }

        $in = implode("','", array_map([$DB, 'escape'], $computer_names));

        // para cada name, traz o maior id (ordenando por name, id DESC e pegando o primeiro)
        $query = "
            SELECT c.id, c.name, c.locations_id
            FROM glpi_computers c
            WHERE c.name IN ('$in')
            ORDER BY c.name ASC, c.id DESC
        ";

        $inventory_map = [];
        $res = $DB->query($query);
        if ($res) {
            while ($row = $DB->fetchAssoc($res)) {
                $name = $row['name'];
                if (!isset($inventory_map[$name])) {
                    $inventory_map[$name] = $row;
                }
            }
        }

        // para cada computador do LabDesk, anexa info de inventário se existir
        foreach ($computers as &$c) {
            $name = $c['rustdesk_name'] ?? '';
            if ($name && isset($inventory_map[$name])) {
                $inv = $inventory_map[$name];
                $c['glpi_computer_id'] = (int)$inv['id'];

                // Localização do GLPI → unit (nome)
                if (!empty($inv['locations_id'])) {
                    $loc = new Location();
                    if ($loc->getFromDB($inv['locations_id'])) {
                        $c['unit'] = $loc->fields['name'];
                    }
                }

                // Grupo do GLPI → department (nome)
                $groupName = null;
                $gid       = null;
                $sqlg = "
                    SELECT g.id, g.name
                    FROM glpi_groups g
                    INNER JOIN glpi_groups_items gi
                        ON gi.groups_id = g.id
                    WHERE gi.itemtype = 'Computer'
                      AND gi.items_id = ".(int)$inv['id']."
                    ORDER BY g.id DESC
                    LIMIT 1
                ";
                $rg = $DB->query($sqlg);
                if ($rg && $gr = $DB->fetchAssoc($rg)) {
                    $groupName = $gr['name'];
                    $gid       = (int)$gr['id'];
                }

                if ($groupName) {
                    $c['department']   = $groupName;
                    $c['glpi_group_id'] = $gid;
                }
            }
        }
        unset($c);
    }

    /**
     * Get computer by ID
     * @param int $computer_id
     * @return array|null
     */
    public static function getById($computer_id) {
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
                return $row;
            }
            return null;
        } catch (Exception $e) {
            error_log("LabDesk Computer Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get computer by RustDesk ID
     * @param string $rustdesk_id
     * @return array|null
     */
    public static function getByRustdeskId($rustdesk_id) {
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
                return $row;
            }
            return null;
        } catch (Exception $e) {
            error_log("LabDesk Computer Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get groups for a computer
     * @param int $computer_id
     * @return array
     */
    public static function getComputerGroups($computer_id) {
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
     * @param array $data
     * @return boolean
     */
    public static function save($data) {
        global $DB;

        try {
            $rustdesk_id   = $DB->escape($data['rustdesk_id']   ?? '');
            $rustdesk_name = $DB->escape($data['rustdesk_name'] ?? 'Unknown');
            $alias         = $DB->escape($data['alias']         ?? '');
            $unit          = $DB->escape($data['unit']          ?? '');
            $department    = $DB->escape($data['department']    ?? '');
            $status        = $DB->escape($data['status']        ?? 'offline');
            $last_online   = isset($data['last_online']) ? (int)$data['last_online'] : null;

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
                 `alias`         = '{$alias}',
                 `unit`          = '{$unit}',
                 `department`    = '{$department}',
                 `status`        = '{$status}',
                 `last_online`   = {$last_online_sql},
                 `updated_at`    = NOW()",
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
     * @param int $computer_id
     * @return boolean
     */
    public static function delete($computer_id) {
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
     * @return array
     */
    public static function syncFromRustdesk() {

        $config = PluginLabdeskConfig::getConfig();

        // Validar configuração
        $validation = PluginLabdeskConfig::validateConfig();
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Configuração incompleta: ' . implode(', ', $validation['errors']),
                'synced'  => 0
            ];
        }

        try {
            $rustdesk_url = rtrim($config['rustdesk_url'], '/');
            $api_token    = $config['rustdesk_token'];

            // URL do endpoint correto
            $api_url = $rustdesk_url . '/api/admin/peer/list?page_size=999999';

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

            $response   = curl_exec($ch);
            $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error) {
                return [
                    'success' => false,
                    'message' => 'Erro cURL: ' . $curl_error,
                    'synced'  => 0
                ];
            }

            if ($http_code !== 200) {
                return [
                    'success' => false,
                    'message' => 'Erro HTTP ' . $http_code . ' ao conectar com RustDesk',
                    'synced'  => 0
                ];
            }

            $response_data = json_decode($response, true);
            if (!$response_data) {
                return [
                    'success' => false,
                    'message' => 'Resposta inválida do RustDesk',
                    'synced'  => 0
                ];
            }

            // Verificar se a resposta tem o formato esperado
            if ($response_data['code'] !== 0) {
                return [
                    'success' => false,
                    'message' => 'Erro RustDesk: ' . ($response_data['message'] ?? 'Unknown error'),
                    'synced'  => 0
                ];
            }

            // Processar lista de computadores
            $computers    = isset($response_data['data']['list']) ? $response_data['data']['list'] : [];
            $synced_count = 0;

            foreach ($computers as $device) {
                $rustdesk_id = $device['id']       ?? null;
                $hostname    = $device['hostname'] ?? 'Unknown';
                $host_alias  = $device['alias']    ?? null;
                $status      = 'offline';
                $last_online = null;

                if (!$rustdesk_id) {
                    continue;
                }

                // Calcular status baseado em last_online_time
                if (isset($device['last_online_time'])) {
                    $last_online_ts = (int)$device['last_online_time'];
                    $now            = time();
                    $diff           = $now - $last_online_ts;
                    // Considerar online se foi visto nos últimos 5 minutos
                    if ($diff < 300) {
                        $status = 'online';
                    }
                    $last_online = $last_online_ts;
                }

                // Montar dados do computador
                $computer_data = [
                    'rustdesk_id'   => $rustdesk_id,
                    'rustdesk_name' => $hostname,
                    'status'        => $status,
                    'last_online'   => $last_online,
                    'alias'         => $host_alias
                ];

                // Buscar dados do inventário GLPI e preencher unit/department
                $inv = self::findInventoryByHostname($hostname);
                if ($inv) {
                    // localização → unit (nome)
                    if (!empty($inv['locations_id'])) {
                        $loc = new Location();
                        if ($loc->getFromDB($inv['locations_id'])) {
                            $computer_data['unit'] = $loc->fields['name'];
                        }
                    }
                    // grupo → department (nome)
                    $group = self::findInventoryGroup($inv['id']);
                    if ($group) {
                        $computer_data['department'] = $group['name'];
                    }
                }

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
                'synced'  => $synced_count,
                'total'   => count($computers)
            ];

        } catch (Exception $e) {
            error_log("LabDesk Sync Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao sincronizar: ' . $e->getMessage(),
                'synced'  => 0
            ];
        }
    }

    public static function findInventoryByHostname($name) {
        global $DB;
        if (!$name) {
            return null;
        }
        $escaped = $DB->escape($name);
        $res = $DB->query(
            "SELECT id, name, locations_id
             FROM glpi_computers
             WHERE name = '{$escaped}'
             ORDER BY id DESC
             LIMIT 1"
        );
        if ($res && $row = $DB->fetchAssoc($res)) {
            return $row;
        }
        return null;
    }

    public static function findInventoryGroup($computer_id) {
        global $DB;
        $id = (int)$computer_id;
        $res = $DB->query(
            "SELECT g.id, g.name
             FROM glpi_groups g
             INNER JOIN glpi_groups_items gi
                ON gi.groups_id = g.id
             WHERE gi.itemtype = 'Computer'
               AND gi.items_id = {$id}
             ORDER BY g.id DESC
             LIMIT 1"
        );
        if ($res && $row = $DB->fetchAssoc($res)) {
            return $row;
        }
        return null;
    }

    /**
     * Update computer unit and department
     */
    public static function updateUnitDept($computer_id, $unit, $department) {
        global $DB;
        try {
            $escaped_id   = $DB->escape($computer_id);
            $escaped_unit = $DB->escape($unit);
            $escaped_dept = $DB->escape($department);
            $DB->queryOrDie(
                "UPDATE `glpi_labdesk_computers`
                 SET `unit` = '{$escaped_unit}',
                     `department` = '{$escaped_dept}',
                     `updated_at` = NOW()
                 WHERE `id` = '{$escaped_id}'",
                "LabDesk: Error updating computer unit/department"
            );
            return true;
        } catch (Exception $e) {
            error_log("LabDesk Computer Error: " . $e->getMessage());
            return false;
        }
    }

    // (restante dos métodos: updateStatus, updateAlias, countByStatus, search) 
    // mantenha exatamente como já estão no seu arquivo atual
}
