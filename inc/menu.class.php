<?php
/**
 * Classe para gerenciamento do menu
 */
class PluginLabdeskLabdeskMenu extends CommonGLPI {
    
    /**
     * Nome do menu
     */
    static function getMenuName() {
        return __('LabDesk', 'labdesk');
    }
    
    /**
     * Conteúdo do menu
     */
    static function getMenuContent() {
        $config_image = '<i class="fas fa-gears"
                                title="' . __('Configuração', 'labdesk') . '"></i>&nbsp; Configuração';
        
        $labdesk_image = '<i class="fas fa-computer"
                                title="' . __('Labdesk', 'labdesk') . '"></i>&nbsp; Equipamentos';
        
        $menu = [
            'title' => self::getMenuName(),
            'page'  => Plugin::getWebDir('labdesk') . '/front/labdesk.php',
            'icon'  => 'fas fa-computer',
            'links' => [
                'config' => Plugin::getWebDir('labdesk') . '/front/config.php',
            ]
        ];
        
        if (PluginLabdeskLabdesk::canView()) {
            $menu['options']['labdesk'] = [
                'title' => __('Equipamentos', 'labdesk'),
                'page'  => Plugin::getWebDir('labdesk') . '/front/labdesk.php',
                'links' => [
                    $config_image => Plugin::getWebDir('labdesk') . '/front/config.php',
                ]
            ];
            
            $menu['options']['config'] = [
                'title' => __('Configuração', 'labdesk'),
                'page'  => Plugin::getWebDir('labdesk') . '/front/config.php',
                'links' => [
                    $labdesk_image => Plugin::getWebDir('labdesk') . '/front/labdesk.php'
                ]
            ];
        }
        
        return $menu;
    }
}
