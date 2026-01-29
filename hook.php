<?php
/**
 * LabDesk Plugin - Hook file
 */

function plugin_labdesk_install()
{
    global $DB;

    // 1. Tabela de Computadores
    if (!$DB->tableExists('glpi_labdesk_computers')) {
        $DB->queryOrDie(
            "CREATE TABLE `glpi_labdesk_computers` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `rustdesk_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
                `rustdesk_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `rustdesk_row_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `alias` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `unit` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `status` enum('online','offline') COLLATE utf8mb4_unicode_ci DEFAULT 'offline',
                `last_online` TIMESTAMP NULL DEFAULT NULL,
                `last_sync` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `status` (`status`),
                KEY `unit` (`unit`),
                KEY `department` (`department`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC",
            "LabDesk: fail to create glpi_labdesk_computers table"
        );
    }

    // 2. Tabela de Relacionamento de Tipos
    if (!$DB->tableExists('glpi_labdesk_computertypes_computers')) {
        $DB->queryOrDie(
            "CREATE TABLE `glpi_labdesk_computertypes_computers` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `computer_id` INT UNSIGNED NOT NULL,
                `computertypes_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_comp_type` (`computer_id`),
                KEY `computertypes_id` (`computertypes_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC",
            "LabDesk: fail to create glpi_labdesk_computertypes_computers table"
        );
    }

    // 3. Tabela de Configurações
    if (!$DB->tableExists('glpi_labdesk_settings')) {
        $DB->queryOrDie(
            "CREATE TABLE `glpi_labdesk_settings` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
                `value` longtext COLLATE utf8mb4_unicode_ci,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC",
            "LabDesk: fail to create glpi_labdesk_settings table"
        );

        $DB->queryOrDie(
            "INSERT INTO `glpi_labdesk_settings` (`key`, `value`) VALUES
            ('rustdesk_url', 'http://seu-servidor:21114'),
            ('rustdesk_token', ''),
            ('sync_interval', '300'),
            ('last_sync', '1900-01-01 00:00:00'),
            ('password_default', ''),
            ('webclient_url', 'http://seu-servidor:21114'),
            ('use_password_default', '0')
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
            "LabDesk: fail to insert default settings"
        );
    }

    // 4. Registrar CRON TASK
    $cron = new CronTask();
    if (!$cron->getFromDBbyName('PluginLabdeskCron', 'cronSync')) {
        $cron->add([
            'itemtype' => 'PluginLabdeskCron',
            'name'     => 'cronLabdeskSync',
            'frequency'=> 300,
            'param'    => 1,
            'state'    => 1,
            'mode'     => 1
        ]);
    }

    return true;
}

function plugin_labdesk_uninstall()
{
    global $DB;
    $tables = [
        'glpi_labdesk_computertypes_computers',
        'glpi_labdesk_computers',
        'glpi_labdesk_settings',
    ];
    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->queryOrDie("DROP TABLE IF EXISTS `$table`", "LabDesk: fail to drop $table");
        }
    }
    
    // Remove Cron
    $cron = new CronTask();
    if ($cron->getFromDBbyName('PluginLabdeskCron', 'cronSync')) {
        $cron->delete(['id' => $cron->fields['id']]);
    }

    return true;
}

function plugin_labdesk_pre_install() { return true; }
function plugin_labdesk_post_install() { return true; }
function plugin_labdesk_pre_uninstall() { return true; }
function plugin_labdesk_post_uninstall() { return true; }
function plugin_labdesk_upgrade($version) { return true; }
?>