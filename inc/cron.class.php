<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginLabdeskCron extends CommonDBTM {

    /**
     * Define a tarefa de sincronização
     * O GLPI concatena 'cron' + NomeDaTarefa. Como o nome é 'cronSync', ele busca 'croncronSync'.
     * @param CronTask $task
     */
    static function croncronSync($task) {
        // 1. Força o carregamento das classes necessárias (Autoloader pode falhar no Cron)
        if (!class_exists('PluginLabdeskConfig')) {
            include_once(__DIR__ . '/config.class.php');
        }
        if (!class_exists('PluginLabdeskComputer')) {
            include_once(__DIR__ . '/computer.class.php');
        }

        // Log de início para debug
        $task->log("LabDesk: Iniciando execucao automatica...");

        // 2. Executa a sincronização
        if (method_exists('PluginLabdeskComputer', 'syncFromRustdesk')) {
            $result = PluginLabdeskComputer::syncFromRustdesk();
            
            if ($result['success']) {
                $count = (int)$result['synced'];
                
                // Adiciona o volume processado nas estatísticas da tarefa
                $task->addVolume($count); 
                
                $task->log("LabDesk Sucesso: " . $result['message']);
                
                // Retorna 1 (Sucesso)
                return 1; 
            } else {
                $task->log("LabDesk Erro: " . $result['message']);
                // Retorna 0 (Falha), mas registra o log
                return 0; 
            }
        } else {
            $task->log("LabDesk Fatal: Classe ou Método PluginLabdeskComputer::syncFromRustdesk não encontrado.");
            return 0;
        }
    }
}
?>