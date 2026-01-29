<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginLabdeskComputer {

    /**
     * Get all computers from database
     */
    public static function getAll() {
        global $DB;
        try {
            $result = $DB->query("SELECT * FROM `glpi_labdesk_computers` ORDER BY `rustdesk_name` ASC");
            if (!$result) return [];
            $computers = [];
            while ($row = $result->fetch_assoc()) {
                $computers[] = $row;
            }
            self::attachInventoryData($computers);
            return $computers;
        } catch (Exception $e) {
            return [];
        }
    }

    private static function attachInventoryData(array &$computers) {
        global $DB;
        if (empty($computers)) return;

        $computer_names = [];
        foreach ($computers as $c) {
            if (!empty($c['rustdesk_name'])) $computer_names[] = $c['rustdesk_name'];
        }
        $computer_names = array_unique($computer_names);
        if (empty($computer_names)) return;

        $safe_names = [];
        foreach ($computer_names as $n) $safe_names[] = $DB->escape($n);
        $in = implode("','", $safe_names);

        $query = "SELECT c.id, c.name, c.locations_id FROM glpi_computers c WHERE c.name IN ('$in') AND c.is_deleted = 0 AND c.is_template = 0 ORDER BY c.name ASC, c.id DESC";
        $inventory_map = [];
        $res = $DB->query($query);
        if ($res) {
            while ($row = $DB->fetchAssoc($res)) {
                $name = strtolower($row['name']);
                if (!isset($inventory_map[$name])) $inventory_map[$name] = $row;
            }
        }
        foreach ($computers as &$c) {
            $name = strtolower($c['rustdesk_name'] ?? '');
            if ($name && isset($inventory_map[$name])) {
                $inv = $inventory_map[$name];
                $c['glpi_computer_id'] = (int)$inv['id'];
            }
        }
        unset($c);
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
            // NOVO: Row ID
            $rustdesk_row_id = isset($data['rustdesk_row_id']) ? (int)$data['rustdesk_row_id'] : 0;
            
            $alias         = $DB->escape($data['alias']         ?? '');
            $status        = $DB->escape($data['status']        ?? 'offline');
            $last_online   = isset($data['last_online']) ? (int)$data['last_online'] : null;

            if ($last_online) {
                $last_online_sql = "FROM_UNIXTIME({$last_online})";
            } else {
                $last_online_sql = "NULL";
            }

            // Campos para UPDATE dinâmico
            $updates = [];
            $updates[] = "`rustdesk_name` = '{$rustdesk_name}'";
            $updates[] = "`rustdesk_row_id` = {$rustdesk_row_id}"; // Atualiza o ID interno se mudar
            $updates[] = "`alias` = '{$alias}'";
            $updates[] = "`status` = '{$status}'";
            $updates[] = "`last_online` = {$last_online_sql}";
            $updates[] = "`updated_at` = NOW()";

            // Só atualiza Unidade se ela foi passada no array
            if (array_key_exists('unit', $data)) {
                $unit_val = $data['unit'];
                if ($unit_val === null) $updates[] = "`unit` = NULL";
                else { $u = $DB->escape($unit_val); $updates[] = "`unit` = '{$u}'"; }
            }

            // Só atualiza Departamento se ele foi passado no array
            if (array_key_exists('department', $data)) {
                $dept_val = $data['department'];
                if ($dept_val === null) $updates[] = "`department` = NULL";
                else { $d = $DB->escape($dept_val); $updates[] = "`department` = '{$d}'"; }
            }

            $update_sql = implode(', ', $updates);

            $ins_unit = array_key_exists('unit', $data) ? "'".$DB->escape($data['unit'])."'" : "NULL";
            $ins_dept = array_key_exists('department', $data) ? "'".$DB->escape($data['department'])."'" : "NULL";

            $query = "INSERT INTO `glpi_labdesk_computers`
                 (`rustdesk_id`, `rustdesk_name`, `rustdesk_row_id`, `alias`, `unit`, `department`, `status`, `last_online`, `created_at`)
                 VALUES ('{$rustdesk_id}', '{$rustdesk_name}', {$rustdesk_row_id}, '{$alias}', {$ins_unit}, {$ins_dept}, '{$status}', {$last_online_sql}, NOW())
                 ON DUPLICATE KEY UPDATE {$update_sql}";

            $DB->queryOrDie($query, "LabDesk: Error saving computer");

            return true;
        } catch (Exception $e) {
            error_log("LabDesk Computer Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envia atualização de Alias para a API do RustDesk
     */
    public static function updateRemoteAlias($row_id, $new_alias) {
        $config = PluginLabdeskConfig::getConfig();
        $validation = PluginLabdeskConfig::validateConfig();
        
        if (!$validation['valid']) return false;
        if ($row_id <= 0) return false;

        $rustdesk_url = rtrim($config['rustdesk_url'], '/');
        $api_token    = $config['rustdesk_token'];
        $api_url      = $rustdesk_url . '/api/admin/peer/update';

        $payload = json_encode([
            "row_id" => (int)$row_id,
            "alias"  => $new_alias
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'api-token: ' . $api_token
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Sucesso se status 200
        return ($httpCode >= 200 && $httpCode < 300);
    }

    /**
     * Sync computers from RustDesk API
     */
    public static function syncFromRustdesk() {

        $config = PluginLabdeskConfig::getConfig();
        $validation = PluginLabdeskConfig::validateConfig();
        if (!$validation['valid']) {
            return ['success' => false, 'message' => 'Configuração inválida', 'synced' => 0];
        }

        try {
            $rustdesk_url = rtrim($config['rustdesk_url'], '/');
            $api_token    = $config['rustdesk_token'];
            $api_url      = $rustdesk_url . '/api/admin/peer/list?page_size=999999';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'api-token: ' . $api_token
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $error    = curl_error($ch);
            curl_close($ch);

            if ($error) return ['success' => false, 'message' => 'Erro cURL: '.$error];

            $data = json_decode($response, true);
            if (!isset($data['data']['list'])) return ['success' => false, 'message' => 'Resposta inválida'];

            $list = $data['data']['list'];
            $count = 0;

            foreach ($list as $device) {
                if (empty($device['id'])) continue;

                $hostname = $device['hostname'] ?? 'Unknown';
                
                // --- CAPTURA O ROW_ID ---
                // Verifica se veio 'row_id', senão tenta usar 'id' se for numérico e diferente do ID de conexão
                // Mas seguindo a instrução: pegar o campo 'row_id' se existir ou 'id' que geralmente é a chave primária
                $rowId = 0;
                if (isset($device['row_id'])) {
                    $rowId = (int)$device['row_id'];
                } elseif (isset($device['id']) && is_numeric($device['id'])) {
                    // Fallback comum: muitas vezes o 'id' é a PK
                    $rowId = (int)$device['id'];
                }

                // Dados básicos
                $compData = [
                    'rustdesk_id'     => $device['id'], // String ID (conexão)
                    'rustdesk_name'   => $hostname,
                    'rustdesk_row_id' => $rowId, // Salva o ID para update futuro
                    'alias'           => $device['alias'] ?? '',
                    'status'          => 'offline'
                ];

                if (isset($device['last_online_time'])) {
                    $last = (int)$device['last_online_time'];
                    if ((time() - $last) < 300) $compData['status'] = 'online';
                    $compData['last_online'] = $last;
                }

                // Vinculo com GLPI
                $inv = self::findInventoryByHostname($hostname);
                
                if ($inv) {
                    if (!empty($inv['locations_id'])) {
                        $locData = self::getLocationName($inv['locations_id']);
                        if ($locData) $compData['unit'] = $locData;
                    }
                    $groupData = self::findInventoryGroup($inv['id']);
                    if ($groupData) $compData['department'] = $groupData['name'];
                }

                if (self::save($compData)) {
                    $count++;
                }
            }
            
            PluginLabdeskConfig::setConfigValue('last_sync', date('Y-m-d H:i:s'));
            return ['success' => true, 'message' => "Sincronizado: $count", 'synced' => $count];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Auxiliares
    public static function findInventoryByHostname($name) {
        global $DB;
        if (!$name) return null;
        $escaped = $DB->escape($name);
        $res = $DB->query("SELECT id, name, locations_id FROM glpi_computers WHERE name = '$escaped' AND is_deleted=0 AND is_template=0 ORDER BY id DESC LIMIT 1");
        return ($res && $row = $DB->fetchAssoc($res)) ? $row : null;
    }
    public static function findInventoryGroup($computer_id) {
        global $DB;
        $id = (int)$computer_id;
        $query = "SELECT g.id, g.name FROM glpi_groups g INNER JOIN glpi_groups_items gi ON gi.groups_id = g.id WHERE gi.itemtype = 'Computer' AND gi.items_id = $id ORDER BY g.id DESC LIMIT 1";
        $res = $DB->query($query);
        return ($res && $row = $DB->fetchAssoc($res)) ? $row : null;
    }
    public static function getLocationName($location_id) {
        global $DB;
        $id = (int)$location_id;
        $res = $DB->query("SELECT name FROM glpi_locations WHERE id = $id");
        return ($res && $row = $DB->fetchAssoc($res)) ? $row['name'] : null;
    }
    
    // Métodos utilitários
    public static function getById($id) { global $DB; $res=$DB->query("SELECT * FROM glpi_labdesk_computers WHERE id=".$DB->escape($id)); return $res?$res->fetch_assoc():null; }
    public static function getByRustdeskId($rid) { global $DB; $res=$DB->query("SELECT * FROM glpi_labdesk_computers WHERE rustdesk_id='".$DB->escape($rid)."'"); return $res?$res->fetch_assoc():null; }
    public static function delete($id) { global $DB; $DB->query("DELETE FROM glpi_labdesk_computers WHERE id=".$DB->escape($id)); return true; }
}
?>