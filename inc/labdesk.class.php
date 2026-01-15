<?php
class PluginLabdeskLabdesk extends CommonGLPI
{
    public static $rightname = 'config';
    public static $logs_enabled = false;

    public static function getTypeName($nb = 0)
    {
        return 'LabDesk';
    }

    public static function getMenu()
    {
        return [
            'labdesk' => [
                'title' => 'LabDesk',
                'page'  => '/plugins/labdesk/front/labdesk.php',
            ],
            'config' => [
                'title'      => 'Configuração',
                'page'       => '/plugins/labdesk/front/config.php',
                'links'      => [
                    'search' => '/plugins/labdesk/front/config.php',
                ],
            ],
        ];
    }
}
?>
