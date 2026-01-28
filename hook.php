<?php
/**
 * LabDesk Plugin - Hook file for GLPI 10.0.16+
 * Handles installation, uninstallation and migration
 * VERSÃO CORRIGIDA - Sem Migration::addLog()
 */

/**
 * Install hook - Creates tables and initial configuration
 *
 * @return boolean
 */
function plugin_labdesk_install()
{
    global $DB;

    // Create computers table
    if (!$DB->tableExists('glpi_labdesk_computers')) {
        $DB->queryOrDie(
            "CREATE TABLE `glpi_labdesk_computers` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `rustdesk_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
                `rustdesk_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
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

    // Create computertypes-computers junction table
    if (!$DB->tableExists('glpi_labdesk_computertypes_computers')) {
        $DB->queryOrDie(
            "CREATE TABLE `glpi_labdesk_computertypes_computers` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `computertypes_id` INT UNSIGNED NOT NULL,
                `computer_id` INT UNSIGNED NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_computertypes_computer` (`computertypes_id`,`computer_id`),
                KEY `computertypes_id` (`computertypes_id`),
                KEY `computer_id` (`computer_id`),
                CONSTRAINT `fk_labdesk_computertypes_id` FOREIGN KEY (`computertypes_id`) REFERENCES `glpi_computertypes` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_labdesk_computer_id` FOREIGN KEY (`computer_id`) REFERENCES `glpi_labdesk_computers` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC",
            "LabDesk: fail to create glpi_labdesk_computertypes_computers table"
        );
    }

    // Create settings table
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

        // Insert default settings
        $DB->queryOrDie(
            "INSERT INTO `glpi_labdesk_settings` (`key`, `value`) VALUES
            ('rustdesk_url', 'http://seu-servidor:21114'),
            ('rustdesk_token', ''),
            ('sync_interval', '300'),
            ('last_sync', '1900-01-01 00:00:00')
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
            "LabDesk: fail to insert default settings"
        );
    }

    return true;
}

/**
 * Uninstall hook - Removes tables and data
 *
 * @return boolean
 */
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
            $DB->queryOrDie(
                "DROP TABLE IF EXISTS `$table`",
                "LabDesk: fail to drop table $table"
            );
        }
    }

    return true;
}

/**
 * Pre-installation check for migration version
 * 
 * @return boolean
 */
function plugin_labdesk_pre_install()
{
    return true;
}

/**
 * Post-installation hook
 *
 * @return boolean
 */
function plugin_labdesk_post_install()
{
    return true;
}

/**
 * Pre-uninstallation hook
 *
 * @return boolean
 */
function plugin_labdesk_pre_uninstall()
{
    return true;
}

/**
 * Post-uninstallation hook
 *
 * @return boolean
 */
function plugin_labdesk_post_uninstall()
{
    return true;
}

/**
 * Migration upgrade/update hook
 *
 * @param string $version
 * @return boolean
 */
function plugin_labdesk_upgrade($version)
{
    return true;
}
?>
