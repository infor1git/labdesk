<?php
/**
 * LabDesk Plugin - Configuration Page
 * Padrão GLPI 10.0.17 - Funcionando corretamente
 */

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

Html::header(__('Configuração Labdesk', 'labdesk'), $_SERVER['PHP_SELF'], "tools", "PluginLabdeskLabdeskMenu", "config");

$config = PluginLabdeskConfig::getConfig();

// Save configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    Session::checkRight('config', UPDATE);

    $data = [
        'rustdesk_url'   => $_POST['rustdesk_url'] ?? '',
        'rustdesk_token' => $_POST['rustdesk_token'] ?? '',
        'units'          => isset($_POST['units'])
            ? array_filter(array_map('trim', explode(',', $_POST['units'])))
            : [],
        'departments'    => isset($_POST['departments'])
            ? array_filter(array_map('trim', explode(',', $_POST['departments'])))
            : [],
    ];

    // Salvar usando API existente
    foreach ($data as $key => $value) {
        PluginLabdeskConfig::setConfigValue($key, $value);
    }

    Session::addMessageAfterRedirect(__('Configuração salva com sucesso', 'labdesk'), true, INFO);
    Html::back();
}

// Sync computers from RustDesk
if (isset($_GET['action']) && $_GET['action'] === 'sync_computers') {
    Session::checkRight('config', UPDATE);

    if (PluginLabdeskComputer::syncFromRustdesk()) {
        Session::addMessageAfterRedirect(__('Sincronização de computadores concluída', 'labdesk'), true, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Erro na sincronização de computadores', 'labdesk'), false, ERROR);
    }

    Html::back();
}

// Sync groups from RustDesk
if (isset($_GET['action']) && $_GET['action'] === 'sync_groups') {
    Session::checkRight('config', UPDATE);

    $result = PluginLabdeskGroup::syncFromRustdesk();
    
    if ($result['success']) {
        Session::addMessageAfterRedirect($result['message'], true, INFO);
    } else {
        Session::addMessageAfterRedirect($result['message'], false, ERROR);
    }

    Html::back();
}

?>

<div class="container" style="max-width: 600px; margin: 20px auto;">
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2><?php echo __('Configuração do LabDesk', 'labdesk'); ?></h2>

        <form method="POST" style="margin: 20px 0;">
            <?php
            echo Html::hidden('_glpi_csrf_token', [
                'value' => Session::getNewCSRFToken()
            ]);
            ?>
            <input type="hidden" name="save_config" value="1">

            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold;">
                    <?php echo __('URL do RustDesk API', 'labdesk'); ?>
                </label>
                <input type="text" name="rustdesk_url"
                       value="<?php echo Html::cleanInputText($config['rustdesk_url'] ?? ''); ?>"
                       placeholder="http://seu-servidor:21114"
                       style="width: 100%; padding: 8px;">
                <small style="color: #666;"><?php echo __('Exemplo: http://labdesk.labchecap.com.br:8001', 'labdesk'); ?></small>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold;">
                    <?php echo __('Token da API RustDesk', 'labdesk'); ?>
                </label>
                <input type="password" name="rustdesk_token"
                       value="<?php echo Html::cleanInputText($config['rustdesk_token'] ?? ''); ?>"
                       placeholder="Cole o token aqui"
                       style="width: 100%; padding: 8px;">
                <small style="color: #666;"><?php echo __('Obtido em RustDesk Web Console → Perfil → API Token', 'labdesk'); ?></small>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold;">
                    <?php echo __('Unidades (separadas por vírgula)', 'labdesk'); ?>
                </label>
                <input type="text" name="units"
                       value="<?php echo implode(', ', $config['units'] ?? []); ?>"
                       placeholder="Salvador, São Paulo, Brasília"
                       style="width: 100%; padding: 8px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold;">
                    <?php echo __('Setores (separados por vírgula)', 'labdesk'); ?>
                </label>
                <input type="text" name="departments"
                       value="<?php echo implode(', ', $config['departments'] ?? []); ?>"
                       placeholder="TI, Administrativo, Financeiro, RH"
                       style="width: 100%; padding: 8px;">
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo __('Salvar Configuração', 'labdesk'); ?>
                </button>

                <?php if (!empty($config['rustdesk_url']) && !empty($config['rustdesk_token'])): ?>
                    <a href="?action=sync_computers" class="btn btn-success" onclick="return confirm('<?php echo __('Sincronizar computadores agora?', 'labdesk'); ?>');">
                        <i class="fas fa-sync"></i> <?php echo __('Sincronizar Computadores', 'labdesk'); ?>
                    </a>

                    <a href="?action=sync_groups" class="btn btn-info" onclick="return confirm('<?php echo __('Sincronizar grupos/tags agora?', 'labdesk'); ?>');">
                        <i class="fas fa-tags"></i> <?php echo __('Sincronizar Grupos', 'labdesk'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <hr style="margin: 30px 0;">

        <h3><?php echo __('Status da Configuração', 'labdesk'); ?></h3>
        <table class="table" style="width: 100%;">
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 10px; font-weight: bold; width: 200px;">URL RustDesk:</td>
                <td style="padding: 10px;">
                    <?php 
                    if (!empty($config['rustdesk_url'])) {
                        echo '<span style="color: green;">✅</span> ' . Html::cleanInputText($config['rustdesk_url']);
                    } else {
                        echo '<span style="color: red;">❌</span> ' . __('Não configurada', 'labdesk');
                    }
                    ?>
                </td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 10px; font-weight: bold;">Token API:</td>
                <td style="padding: 10px;">
                    <?php 
                    if (!empty($config['rustdesk_token'])) {
                        $token = $config['rustdesk_token'];
                        $display = substr($token, 0, 10) . '****' . substr($token, -5);
                        echo '<span style="color: green;">✅</span> ' . Html::cleanInputText($display) . ' (' . __('oculto', 'labdesk') . ')';
                    } else {
                        echo '<span style="color: red;">❌</span> ' . __('Não configurado', 'labdesk');
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; font-weight: bold;">Última sincronização:</td>
                <td style="padding: 10px;">
                    <?php 
                    if (!empty($config['last_sync'])) {
                        echo Html::cleanInputText($config['last_sync']);
                    } else {
                        echo __('Nunca sincronizado', 'labdesk');
                    }
                    ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<?php Html::footer(); ?>
