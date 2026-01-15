<?php
include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

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

    // Salvar usando API existente (correção crítica)
    foreach ($data as $key => $value) {
        PluginLabdeskConfig::setConfigValue($key, $value);
    }

    Session::addMessageAfterRedirect(__('Configuração salva com sucesso', 'labdesk'), true, INFO);
    Html::back();
}

// Sync from RustDesk
if (isset($_GET['action']) && $_GET['action'] === 'sync') {
    Session::checkRight('config', UPDATE);

    if (PluginLabdeskComputer::syncFromRustdesk()) {
        Session::addMessageAfterRedirect(__('Sincronização concluída', 'labdesk'), true, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Erro na sincronização', 'labdesk'), false, ERROR);
    }

    Html::back();
}

Html::header(
    __('Configuração LabDesk', 'labdesk'),
    $_SERVER['REQUEST_URI'],
    'config',
    'plugins'
);
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
            </div>

            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold;">
                    <?php echo __('Token da API RustDesk', 'labdesk'); ?>
                </label>
                <input type="password" name="rustdesk_token"
                       value="<?php echo Html::cleanInputText($config['rustdesk_token'] ?? ''); ?>"
                       style="width: 100%; padding: 8px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold;">
                    <?php echo __('Unidades (separadas por vírgula)', 'labdesk'); ?>
                </label>
                <input type="text" name="units"
                       value="<?php echo implode(', ', $config['units'] ?? []); ?>"
                       style="width: 100%; padding: 8px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold;">
                    <?php echo __('Setores (separados por vírgula)', 'labdesk'); ?>
                </label>
                <input type="text" name="departments"
                       value="<?php echo implode(', ', $config['departments'] ?? []); ?>"
                       style="width: 100%; padding: 8px;">
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">
                    <?php echo __('Salvar', 'labdesk'); ?>
                </button>

                <a href="?action=sync" class="btn btn-secondary">
                    <?php echo __('Sincronizar Agora', 'labdesk'); ?>
                </a>
            </div>
        </form>
    </div>
</div>

<?php Html::footer(); ?>
