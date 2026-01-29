<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginLabdeskConfig extends CommonDBTM {

    static function getConfig() {
        global $DB;
        $config = [];
        
        // Valores Padrão
        $defaults = [
            'rustdesk_url'         => '',
            'rustdesk_token'       => '',
            'sync_interval'        => '300',
            'last_sync'            => '',
            'password_default'     => '',
            'use_password_default' => '0',
            'webclient_url'        => ''
        ];

        if ($DB->tableExists('glpi_labdesk_settings')) {
            $iterator = $DB->request(['FROM' => 'glpi_labdesk_settings']);
            foreach ($iterator as $row) {
                $config[$row['key']] = $row['value'];
            }
        }

        return array_merge($defaults, $config);
    }

    static function setConfigValue($key, $value) {
        global $DB;
        // Sanitização básica
        $value = $DB->escape($value);
        
        $DB->queryOrDie(
            "INSERT INTO `glpi_labdesk_settings` (`key`, `value`) 
             VALUES ('$key', '$value') 
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
            "LabDesk: Erro ao salvar configuração $key"
        );
    }

    static function validateConfig($data = null) {
        if (!$data) $data = self::getConfig();
        
        $errors = [];
        if (empty($data['rustdesk_url'])) {
            $errors[] = "URL da API do RustDesk não configurada.";
        }
        
        return [
            'valid'  => empty($errors),
            'errors' => $errors
        ];
    }
}
?>