<?php
/**
 * LabDesk Plugin for GLPI
 * Setup file with proper GLPI 10.0.16+ structure - VERSÃO MELHORADA
 */

define('PLUGIN_LABDESK_VERSION', '1.0.0');
define('PLUGIN_LABDESK_MIN_GLPI_VERSION', '10.0.16');
define('PLUGIN_LABDESK_MAX_GLPI_VERSION', '10.0.99');

/**
 * Returns the array with metadata for the plugin - Needed
 *
 * @return array
 */
function plugin_version_labdesk()
{
    return [
        'name' => 'LabDesk',
        'version' => PLUGIN_LABDESK_VERSION,
        'author' => 'Rodrigo Cruz',
        'license' => 'GPL v3+',
        'homepage' => 'https://infor1.com.br',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_LABDESK_MIN_GLPI_VERSION,
                'max' => PLUGIN_LABDESK_MAX_GLPI_VERSION,
            ],
            'php' => [
                'exts' => [
                    'curl' => [
                        'required' => true,
                    ],
                ],
            ],
        ],
        'state' => 'stable',
        'description' => 'Gerenciamento de computadores RustDesk no GLPI',
    ];
}

/**
 * Returns the array with compatibility info
 * REQUIRED - else the plugin won't work
 *
 * @return array|bool
 */
function plugin_labdesk_check_prerequisites()
{
    // Verificar versão do GLPI
    if (version_compare(GLPI_VERSION, PLUGIN_LABDESK_MIN_GLPI_VERSION, 'lt')) {
        echo 'This plugin requires GLPI >= ' . PLUGIN_LABDESK_MIN_GLPI_VERSION;
        return false;
    }

    // Verificar cURL
    if (!function_exists('curl_init') && !extension_loaded('curl')) {
        echo 'This plugin requires cURL extension to be installed and enabled';
        return false;
    }

    return true;
}

/**
 * Check configuration process for plugin
 *
 * @param bool $verbose Enable verbose output
 * @return bool
 */
function plugin_labdesk_check_config($verbose = false)
{
    return true;
}

/**
 * Init hooks of the plugin.
 * REQUIRED
 *
 * @return void
 */
function plugin_init_labdesk()
{
    global $PLUGIN_HOOKS;

    // CSRF Protection
    $PLUGIN_HOOKS['csrf_compliant']['labdesk'] = true;

    // Only if connected
    if (Session::getLoginUserID()) {
        // Menu

         $PLUGIN_HOOKS['menu_toadd']['labdesk'] = ['tools' => 'PluginLabdeskLabdeskMenu'];

        // Inclur arquivos das classes
        $plugin_path = __DIR__;
        
        if (file_exists($plugin_path . '/inc/labdesk.class.php')) {
            include_once($plugin_path . '/inc/labdesk.class.php');
        }
        if (file_exists($plugin_path . '/inc/computer.class.php')) {
            include_once($plugin_path . '/inc/computer.class.php');
        }
        if (file_exists($plugin_path . '/inc/group.class.php')) {
            include_once($plugin_path . '/inc/group.class.php');
        }
        if (file_exists($plugin_path . '/inc/config.class.php')) {
            include_once($plugin_path . '/inc/config.class.php');
        }        
        if (file_exists($plugin_path . '/inc/menu.class.php')) {
            include_once($plugin_path . '/inc/menu.class.php');
        }

        // Register classes
        Plugin::registerClass('PluginLabdeskLabdesk', [
            'addtabon' => ['Computer']
        ]);
        Plugin::registerClass('PluginLabdeskComputer');
        Plugin::registerClass('PluginLabdeskGroup');
        Plugin::registerClass('PluginLabdeskConfig');        
        Plugin::registerClass('PluginLabdeskLabdeskMenu');

        // Add CSS and JS
        $PLUGIN_HOOKS['add_css']['labdesk'] = [
            'resources/css/labdesk.css',
        ];

        $PLUGIN_HOOKS['add_javascript']['labdesk'] = [
            'resources/js/labdesk.js',
        ];

        // Config page
        if (Session::haveRight('config', UPDATE)) {
            $PLUGIN_HOOKS['config_page']['labdesk'] = 'front/config.php';
        }
    }
}
?>
