<?php

include('../../../inc/includes.php');

header("Content-Type: application/json; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

$action = $_GET['action'] ?? '';

function ldResponse($success, $message = '', $data = []) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

try {
    global $DB;

    // --- AÇÃO: SALVAR EDIÇÃO (User Edit) ---
    if ($action === 'update-device') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        $id      = isset($data['id']) ? (int)$data['id'] : 0;
        $alias   = trim($data['alias'] ?? '');
        
        $unit = trim($data['unit'] ?? '');
        if ($unit === 'NULL' || $unit === '') $unit = null;
        
        $dept = trim($data['department'] ?? '');
        if ($dept === 'NULL' || $dept === '') $dept = null;

        $type_id = isset($data['type_id']) ? (int)$data['type_id'] : 0;
        $glpi_id = isset($data['glpi_id']) ? (int)$data['glpi_id'] : 0;

        if ($id <= 0) ldResponse(false, 'ID inválido');

        // 1. Atualiza LabDesk (Local)
        $DB->update('glpi_labdesk_computers', [
            'alias'      => $alias,
            'unit'       => $unit,
            'department' => $dept
        ], ['id' => $id]);

        // --- ATUALIZAÇÃO REMOTA API RUSTDESK (NOVO) ---
        // Recupera o computador para pegar o rustdesk_row_id
        $currentComp = PluginLabdeskComputer::getById($id);
        if ($currentComp && !empty($currentComp['rustdesk_row_id']) && $currentComp['rustdesk_row_id'] > 0) {
            // Chama a função para atualizar o alias na API
            PluginLabdeskComputer::updateRemoteAlias($currentComp['rustdesk_row_id'], $alias);
        }
        // ------------------------------------------------

        // 2. Atualiza Categoria (Local)
        $checkType = $DB->request([
            'FROM'   => 'glpi_labdesk_computertypes_computers',
            'WHERE'  => ['computer_id' => $id]
        ])->current();

        if ($type_id > 0) {
            if ($checkType) {
                $DB->update('glpi_labdesk_computertypes_computers', 
                    ['computertypes_id' => $type_id], 
                    ['id' => $checkType['id']]
                );
            } else {
                $DB->insert('glpi_labdesk_computertypes_computers', [
                    'computer_id'      => $id,
                    'computertypes_id' => $type_id
                ]);
            }
        } else {
            if ($checkType) {
                 $DB->delete('glpi_labdesk_computertypes_computers', ['id' => $checkType['id']]);
            }
        }

        // 3. Atualiza GLPI (Se houver vínculo conhecido)
        if ($glpi_id == 0) {
            // Tenta redescobrir se não veio do front
            if ($currentComp) {
                $findGlpi = $DB->request([
                    'SELECT' => ['id'], 
                    'FROM'   => 'glpi_computers', 
                    'WHERE'  => [
                        'name'        => $currentComp['rustdesk_name'],
                        'is_deleted'  => 0,
                        'is_template' => 0
                    ], 
                    'LIMIT'  => 1
                ])->current();
                if ($findGlpi) $glpi_id = $findGlpi['id'];
            }
        }

        if ($glpi_id > 0) {
            $computer = new Computer();
            if ($computer->getFromDB($glpi_id) && !$computer->isDeleted()) {
                $updates = ['id' => $glpi_id];
                $hasUpdates = false;

                if ($type_id > 0 && $computer->fields['computertypes_id'] != $type_id) {
                    $updates['computertypes_id'] = $type_id;
                    $hasUpdates = true;
                }
                if (!is_null($unit)) {
                    $iterLoc = $DB->request(['SELECT' => ['id'], 'FROM' => 'glpi_locations', 'WHERE' => ['name' => $unit], 'LIMIT' => 1]);
                    if ($iterLoc->count() > 0) {
                        $locId = $iterLoc->current()['id'];
                        if ($computer->fields['locations_id'] != $locId) {
                            $updates['locations_id'] = $locId;
                            $hasUpdates = true;
                        }
                    }
                }
                if (!is_null($dept)) {
                    $iterGrp = $DB->request(['SELECT' => ['id'], 'FROM' => 'glpi_groups', 'WHERE' => ['name' => $dept], 'LIMIT' => 1]);
                    if ($iterGrp->count() > 0) {
                        $grpId = $iterGrp->current()['id'];
                        if ($computer->fields['groups_id'] != $grpId) {
                            $updates['groups_id'] = $grpId;
                            $hasUpdates = true;
                        }
                    }
                }

                if ($hasUpdates) {
                    $computer->update($updates);
                }
            }
        }

        ldResponse(true, 'Salvo com sucesso');
    }

    // --- AÇÃO: SINCRONIZAR (GERAL) ---
    if ($action === 'synccomputers') {
        
        // A. Sincronização básica API RustDesk
        if (class_exists('PluginLabdeskComputer') && method_exists('PluginLabdeskComputer', 'syncFromRustdesk')) {
             PluginLabdeskComputer::syncFromRustdesk();
        }

        // B. Enriquecimento GLPI -> LabDesk (Preservando dados locais)
        $labComputers = $DB->request(['FROM' => 'glpi_labdesk_computers']);

        foreach ($labComputers as $ldComp) {
            $glpiComp = $DB->request([
                'SELECT' => ['id', 'locations_id', 'groups_id', 'computertypes_id'],
                'FROM'   => 'glpi_computers',
                'WHERE'  => [
                    'name'       => $ldComp['rustdesk_name'],
                    'is_deleted' => 0, 
                    'is_template'=> 0
                ],
                'LIMIT'  => 1
            ])->current();

            if ($glpiComp) {
                $updatesLD = [];

                // 1. Unidade (Location Name)
                if (!empty($glpiComp['locations_id'])) {
                    $locRow = $DB->request([
                        'SELECT' => ['name'], 'FROM' => 'glpi_locations', 'WHERE' => ['id' => $glpiComp['locations_id']]
                    ])->current();
                    if ($locRow && !empty($locRow['name'])) {
                        $locName = $locRow['name'];
                        if ($ldComp['unit'] !== $locName) $updatesLD['unit'] = $locName;
                    }
                }

                // 2. Departamento (Group Name Level 2)
                if (!empty($glpiComp['groups_id'])) {
                    $grpRow = $DB->request([
                        'SELECT' => ['name'], 'FROM' => 'glpi_groups', 'WHERE' => ['id' => $glpiComp['groups_id']]
                    ])->current();
                    if ($grpRow && !empty($grpRow['name'])) {
                        $grpName = $grpRow['name'];
                        if ($ldComp['department'] !== $grpName) $updatesLD['department'] = $grpName;
                    }
                }

                if (!empty($updatesLD)) {
                    $DB->update('glpi_labdesk_computers', $updatesLD, ['id' => $ldComp['id']]);
                }

                // 3. Categoria
                if (!empty($glpiComp['computertypes_id'])) {
                    $checkType = $DB->request(['FROM' => 'glpi_labdesk_computertypes_computers', 'WHERE' => ['computer_id' => $ldComp['id']]])->current();
                    if ($checkType) {
                        if ($checkType['computertypes_id'] != $glpiComp['computertypes_id']) {
                            $DB->update('glpi_labdesk_computertypes_computers', ['computertypes_id' => $glpiComp['computertypes_id']], ['id' => $checkType['id']]);
                        }
                    } else {
                        $DB->insert('glpi_labdesk_computertypes_computers', ['computer_id' => $ldComp['id'], 'computertypes_id' => $glpiComp['computertypes_id']]);
                    }
                }
            }
        }

        ldResponse(true, 'Sincronização concluída');
    }

} catch (Exception $e) {
    ldResponse(false, 'Erro: ' . $e->getMessage());
}

ldResponse(false, 'Ação inválida');
?>