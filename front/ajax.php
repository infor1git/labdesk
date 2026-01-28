<?php

include ('../../../inc/includes.php');

header("Content-Type: application/json; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

$action = $_GET['action'] ?? '';

if ($action === 'update-device') {
    // Lê JSON do body
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $id    = isset($data['id']) ? (int)$data['id'] : 0;
    $alias = $data['alias'] ?? '';
    $unit  = $data['unit'] ?? '';
    $dept  = $data['department'] ?? '';
    
    // Novos campos
    $type_id = isset($data['type_id']) ? (int)$data['type_id'] : 0;
    $glpi_id = isset($data['glpi_id']) ? (int)$data['glpi_id'] : 0;

    if ($id > 0) {
        global $DB;
        
        // 1. Atualiza dados internos do plugin (LabDesk)
        $result = $DB->update(
            'glpi_plugin_labdesk_computers',
            [
                'alias'      => $alias,
                'unit'       => $unit,
                'department' => $dept
                // Note: Se sua tabela do plugin não tem 'type_id', não tentamos salvar lá
                // O foco é atualizar no GLPI Core se houver vínculo
            ],
            ['id' => $id]
        );

        // 2. Atualiza Categoria no GLPI Core (se houver vínculo)
        if ($glpi_id > 0 && $type_id >= 0) {
            $computer = new Computer();
            // Verifica se o computador existe antes de tentar atualizar
            if ($computer->getFromDB($glpi_id)) {
                $computer->update([
                    'id'               => $glpi_id,
                    'computertypes_id' => $type_id
                ]);
            }
        }

        echo json_encode(['success' => $result]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ação desconhecida']);