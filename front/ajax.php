<?php

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

header('Content-Type: application/json; charset=UTF-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {

    switch ($action) {

        case 'sync-from-rustdesk':
            $result = PluginLabdeskComputer::syncFromRustdesk();
            if ($result['success']) {
                echo json_encode(['success' => true, 'message' => 'Sincronização concluída', 'data' => $result]);
            } else {
                throw new Exception($result['message'] ?? 'Erro na sincronização');
            }
            break;

        case 'get-devices':
            $computers = PluginLabdeskComputer::getAll();
            echo json_encode($computers);
            break;

        case 'update-device':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                global $DB;

                $stmt = $DB->prepare("
                    UPDATE glpi_labdesk_computers
                    SET alias = ?, unit = ?, department = ?
                    WHERE id = ?
                ");
                if ($stmt->execute([
                    $data['alias'] ?? '',
                    $data['unit'] ?? '',
                    $data['department'] ?? '',
                    $data['id']
                ])) {
                    echo json_encode(['success' => true]);
                } else {
                    throw new Exception('Erro ao atualizar');
                }
            }
            break;

        case 'update-unit-dept':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $id   = (int)($data['id'] ?? 0);
                $unit = trim($data['unit'] ?? '');
                $dept = trim($data['department'] ?? '');

                if (!$id) {
                    throw new Exception('ID inválido');
                }

                // Atualiza LabDesk
                PluginLabdeskComputer::updateUnitDept($id, $unit, $dept);

                // Reflete no inventário GLPI (Computer + Location + Group)
                $comp = PluginLabdeskComputer::getById($id);
                if ($comp && !empty($comp['rustdesk_name'])) {
                    global $DB;

                    $name = $DB->escape($comp['rustdesk_name']);
                    $res  = $DB->query("
                        SELECT id, locations_id
                        FROM glpi_computers
                        WHERE name = '{$name}'
                        ORDER BY id DESC
                        LIMIT 1
                    ");
                    if ($res && $row = $DB->fetch_assoc($res)) {
                        $compId = (int)$row['id'];

                        // Localização pelo nome → locations_id
                        if ($unit !== '') {
                            $loc = new Location();
                            if ($loc->getFromDBByCrit(['name' => $unit])) {
                                $locId   = (int)$loc->fields['id'];
                                $compObj = new Computer();
                                if ($compObj->getFromDB($compId)) {
                                    $input                 = $compObj->fields;
                                    $input['id']           = $compId;
                                    $input['locations_id'] = $locId;
                                    $compObj->update($input);
                                }
                            }
                        }

                        // Grupo pelo nome → glpi_groups_items
                        if ($dept !== '') {
                            $group = new Group();
                            if ($group->getFromDBByCrit(['name' => $dept])) {
                                $gid = (int)$group->fields['id'];
                                $DB->queryOrDie("
                                    INSERT IGNORE INTO glpi_groups_items (groups_id, itemtype, items_id)
                                    VALUES ({$gid}, 'Computer', {$compId})
                                ", "LabDesk Error linking group");
                            }
                        }
                    }
                }

                echo json_encode(['success' => true]);
            }
            break;

        case 'get-groups':
            $groups = PluginLabdeskGroup::getGroups();
            echo json_encode($groups);
            break;

        case 'create-group':
            if ($method === 'POST') {
                global $DB;
                $data = json_decode(file_get_contents('php://input'), true);

                $stmt = $DB->prepare("
                    INSERT INTO glpi_labdesk_groups (name, description)
                    VALUES (?, ?)
                ");
                if ($stmt->execute([$data['name'], $data['description'] ?? ''])) {
                    $groupId = $DB->lastInsertId();
                    foreach ($data['computers'] ?? [] as $computerId) {
                        PluginLabdeskGroup::addComputerToGroup($computerId, $groupId);
                    }
                    echo json_encode(['success' => true, 'id' => $groupId]);
                } else {
                    throw new Exception('Erro ao criar grupo');
                }
            }
            break;

        case 'delete-group':
            if ($method === 'POST') {
                global $DB;
                $data = json_decode(file_get_contents('php://input'), true);

                $stmt = $DB->prepare("DELETE FROM glpi_labdesk_group_computers WHERE group_id = ?");
                $stmt->execute([$data['id']]);

                $stmt = $DB->prepare("DELETE FROM glpi_labdesk_groups WHERE id = ?");
                if ($stmt->execute([$data['id']])) {
                    echo json_encode(['success' => true]);
                } else {
                    throw new Exception('Erro ao deletar grupo');
                }
            }
            break;

        case 'add-computer-to-group':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                if (PluginLabdeskGroup::addComputerToGroup($data['computer_id'], $data['group_id'])) {
                    echo json_encode(['success' => true]);
                } else {
                    throw new Exception('Erro ao adicionar computador ao grupo');
                }
            }
            break;

        case 'remove-computer-from-group':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                if (PluginLabdeskGroup::removeComputerFromGroup($data['computer_id'], $data['group_id'])) {
                    echo json_encode(['success' => true]);
                } else {
                    throw new Exception('Erro ao remover computador do grupo');
                }
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Ação não encontrada']);
            break;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
