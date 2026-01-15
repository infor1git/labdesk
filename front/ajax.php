<?php
/**
 * LabDesk - AJAX Handler
 * Handles all AJAX requests from the frontend
 */

include('../../../inc/includes.php');

// Verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::validateCSRF($_POST);
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'sync-devices':
            if (PluginLabdeskComputer::syncFromRustDesk()) {
                echo json_encode(['success' => true, 'message' => 'Sincronização concluída']);
            } else {
                throw new Exception('Erro na sincronização');
            }
            break;

        case 'get-devices':
            $computers = PluginLabdeskComputer::getRustDeskComputers();
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
                
                if ($stmt->execute([$data['alias'] ?? '', $data['unit'] ?? '', $data['department'] ?? '', $data['id']])) {
                    echo json_encode(['success' => true]);
                } else {
                    throw new Exception('Erro ao atualizar');
                }
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
                    
                    // Add computers to group
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
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

?>
