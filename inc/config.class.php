<?php
/**
 * LabDesk Plugin - Configuration Class
 * Gerencia as configurações do plugin
 * VERSÃO CORRIGIDA - Compatível com PHP 7.4+
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginLabdeskConfig
{
    /**
     * Check if string is valid JSON
     * Compatível com PHP 7.4+ (não usa json_validate que é PHP 8.3+)
     *
     * @param string $json
     * @return boolean
     */
    private static function isValidJson($json)
    {
        if (!is_string($json)) {
            return false;
        }
        
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Get all configurations
     *
     * @return array
     */
    public static function getConfig()
    {
        global $DB;

        try {
            // Query para obter todas as settings
            $result = $DB->query(
                "SELECT `key`, `value` FROM `glpi_labdesk_settings` ORDER BY `key` ASC"
            );

            if (!$result) {
                return self::getDefaultConfig();
            }

            $config = [];
            
            // Usar fetch_assoc() - compatível com todas as versões
            while ($row = $result->fetch_assoc()) {
                $key = $row['key'];
                $value = $row['value'];
                
                // Tentar fazer parse de JSON - versão compatível
                if (self::isValidJson($value)) {
                    $value = json_decode($value, true);
                }
                
                $config[$key] = $value;
            }

            return $config;

        } catch (Exception $e) {
            error_log("LabDesk Config Error: " . $e->getMessage());
            return self::getDefaultConfig();
        }
    }

    /**
     * Get single configuration value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getConfigValue($key, $default = null)
    {
        global $DB;

        try {
            $result = $DB->query(
                "SELECT `value` FROM `glpi_labdesk_settings` WHERE `key` = '" . $DB->escape($key) . "'"
            );

            if (!$result) {
                return $default;
            }

            $row = $result->fetch_assoc();
            
            if (!$row) {
                return $default;
            }

            $value = $row['value'];
            
            // Tentar fazer parse de JSON - versão compatível
            if (self::isValidJson($value)) {
                return json_decode($value, true);
            }

            return $value;

        } catch (Exception $e) {
            error_log("LabDesk Config Error: " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Set configuration value
     *
     * @param string $key
     * @param mixed $value
     * @return boolean
     */
    public static function setConfigValue($key, $value)
    {
        global $DB;

        try {
            // Se for array ou objeto, converter para JSON
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            $escaped_key = $DB->escape($key);
            $escaped_value = $DB->escape($value);

            $result = $DB->queryOrDie(
                "INSERT INTO `glpi_labdesk_settings` (`key`, `value`) 
                 VALUES ('{$escaped_key}', '{$escaped_value}') 
                 ON DUPLICATE KEY UPDATE `value` = '{$escaped_value}'",
                "LabDesk: Error updating config"
            );

            return true;

        } catch (Exception $e) {
            error_log("LabDesk Config Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get default configuration
     *
     * @return array
     */
    public static function getDefaultConfig()
    {
        return [
            'rustdesk_url' => 'http://seu-servidor:21114',
            'rustdesk_token' => '',
            'units' => ['Salvador', 'São Paulo', 'Brasília'],
            'departments' => ['TI', 'Administrativo', 'Financeiro', 'RH', 'Operacional'],
            'sync_interval' => 300,
            'last_sync' => '2024-01-01 00:00:00'
        ];
    }

    /**
     * Validate RustDesk configuration
     *
     * @return array
     */
    public static function validateConfig()
    {
        $config = self::getConfig();
        $errors = [];

        if (empty($config['rustdesk_url'])) {
            $errors[] = 'URL do RustDesk não configurada';
        }

        if (empty($config['rustdesk_token'])) {
            $errors[] = 'Token do RustDesk não configurado';
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors
        ];
    }

    /**
     * Sync devices from RustDesk
     *
     * @return array
     */
    public static function syncDevices()
    {
        $config = self::getConfig();

        // Validar configuração
        $validation = self::validateConfig();
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Configuração incompleta: ' . implode(', ', $validation['errors'])
            ];
        }

        try {
            $rustdesk_url = rtrim($config['rustdesk_url'], '/');
            $token = $config['rustdesk_token'];

            // URL da API RustDesk
            $api_url = $rustdesk_url . '/api/v1/peers';

            // Headers da requisição
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ];

            // Fazer requisição cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error) {
                return [
                    'success' => false,
                    'message' => 'Erro cURL: ' . $curl_error
                ];
            }

            if ($http_code !== 200) {
                return [
                    'success' => false,
                    'message' => 'Erro HTTP ' . $http_code . ' ao conectar com RustDesk'
                ];
            }

            $data = json_decode($response, true);
            if (!$data) {
                return [
                    'success' => false,
                    'message' => 'Resposta inválida do RustDesk'
                ];
            }

            // Processar dispositivos
            global $DB;
            $devices = is_array($data) ? $data : (isset($data['data']) ? $data['data'] : []);
            $synced_count = 0;

            foreach ($devices as $device) {
                $rustdesk_id = $device['id'] ?? null;
                $rustdesk_name = $device['alias'] ?? $device['name'] ?? 'Unknown';

                if (!$rustdesk_id) continue;

                $escaped_id = $DB->escape($rustdesk_id);
                $escaped_name = $DB->escape($rustdesk_name);

                $DB->queryOrDie(
                    "INSERT INTO `glpi_labdesk_computers` (`rustdesk_id`, `rustdesk_name`, `status`, `created_at`) 
                     VALUES ('{$escaped_id}', '{$escaped_name}', 'offline', NOW())
                     ON DUPLICATE KEY UPDATE `rustdesk_name` = '{$escaped_name}', `last_sync` = NOW()",
                    "LabDesk: Error syncing device"
                );

                $synced_count++;
            }

            // Atualizar last_sync
            self::setConfigValue('last_sync', date('Y-m-d H:i:s'));

            return [
                'success' => true,
                'message' => "Sincronização concluída: {$synced_count} dispositivos",
                'synced' => $synced_count
            ];

        } catch (Exception $e) {
            error_log("LabDesk Sync Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao sincronizar: ' . $e->getMessage()
            ];
        }
    }
}
?>
