<?php

include ('../../../inc/includes.php');

// 1. Verifica permissão de escrita
Session::checkRight("config", UPDATE);

// 2. Verificação de Perfil (Compatibilidade com nomes variados)
$activeProfile = $_SESSION['glpiactiveprofile']['name'] ?? '';
$allowedProfiles = [
    'super-admin', 'super admin', 'admin', 'administrator', 'administrador', 
    'tecnico', 'technician', 'root'
];

if (!in_array(strtolower($activeProfile), $allowedProfiles)) {
    Html::displayRightError();
}

// Processa salvamento
if (isset($_POST['update'])) {
    
    // 1. Salva configurações na tabela do plugin
    PluginLabdeskConfig::setConfigValue('rustdesk_url', $_POST['rustdesk_url']);
    PluginLabdeskConfig::setConfigValue('rustdesk_token', $_POST['rustdesk_token']);
    
    $sync_interval = (int)$_POST['sync_interval'];
    PluginLabdeskConfig::setConfigValue('sync_interval', $sync_interval);
    
    PluginLabdeskConfig::setConfigValue('password_default', $_POST['password_default']);
    PluginLabdeskConfig::setConfigValue('webclient_url', $_POST['webclient_url']);
    
    $use_pass = isset($_POST['use_password_default']) ? '1' : '0';
    PluginLabdeskConfig::setConfigValue('use_password_default', $use_pass);

    // 2. ATUALIZA A FREQUÊNCIA DA TAREFA CRON DO GLPI (NOVO)
    // Isso garante que o GLPI execute a tarefa no intervalo definido
    $cron = new CronTask();
    if ($cron->getFromDBbyName('PluginLabdeskCron', 'cronSync')) {
        $cron->update([
            'id'        => $cron->fields['id'],
            'frequency' => $sync_interval
        ]);
    }

    Html::back();
}

Html::header(__('Labdesk - Configuração', 'labdesk'), $_SERVER['PHP_SELF'], "tools", "PluginLabdeskLabdeskMenu", "config");

$config = PluginLabdeskConfig::getConfig();
?>

<link rel="stylesheet" type="text/css" href="<?php echo Plugin::getWebDir('labdesk'); ?>/resources/css/labdesk.css">

<style>
    .labdesk-config-wrapper { max-width: 900px; margin: 30px auto; }
    .labdesk-section-header { color: var(--ld-primary); font-size: 0.95rem; font-weight: 600; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 20px; margin-top: 10px; display: flex; align-items: center; gap: 8px; }
    .labdesk-footer-bar { background: #f8fafc; padding: 20px; border-radius: 0 0 8px 8px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; margin-top: 20px; margin-left: -24px; margin-right: -24px; margin-bottom: -24px; }
</style>

<div class="labdesk-config-wrapper">
    <div class="labdesk-card">
        <div class="labdesk-card-header">
            <div class="labdesk-card-title" style="font-size: 1.3rem;">
                <i class="fas fa-cogs" style="color: var(--ld-primary);"></i> Configurações LabDesk
            </div>
            <div class="labdesk-card-subtitle">Gerencie a conexão com o servidor RustDesk e preferências de acesso</div>
        </div>

        <div class="labdesk-card-body">
            <form action="config.php" method="post">
                <input type="hidden" name="_glpi_csrf_token" value="<?php echo Session::getNewCSRFToken(); ?>" />
                
                <div class="labdesk-section-header">
                    <i class="fas fa-server"></i> Conexão API RustDesk
                </div>
                
                <div class="labdesk-form-group">
                    <label class="labdesk-form-label">URL da API</label>
                    <input type="text" class="labdesk-input" name="rustdesk_url" value="<?php echo Html::clean($config['rustdesk_url'] ?? ''); ?>" placeholder="http://seu-servidor:21114">
                </div>

                <div class="labdesk-form-group">
                    <label class="labdesk-form-label">Token (Opcional)</label>
                    <input type="password" class="labdesk-input" name="rustdesk_token" value="<?php echo Html::clean($config['rustdesk_token'] ?? ''); ?>">
                </div>

                <div class="labdesk-section-header" style="margin-top: 30px;">
                    <i class="fas fa-desktop"></i> Cliente & Acesso
                </div>

                <div class="labdesk-form-group">
                    <label class="labdesk-form-label">URL do WebClient</label>
                    <input type="text" class="labdesk-input" name="webclient_url" value="<?php echo Html::clean($config['webclient_url'] ?? ''); ?>" placeholder="http://seu-servidor:21114">
                </div>

                <div class="labdesk-form-group" style="margin-top: 20px;">
                     <div style="display: flex; align-items: center; gap: 10px;">
                         <input type="checkbox" id="use_password_default" name="use_password_default" value="1" <?php echo (($config['use_password_default'] ?? '0') == '1' ? 'checked' : ''); ?> style="cursor: pointer; width: 18px; height: 18px;">
                         <label for="use_password_default" style="margin: 0; font-weight: 500; cursor: pointer; color: var(--ld-text-main);">Ativar Senha Padrão nos Links</label>
                     </div>
                </div>

                <div class="labdesk-form-group" id="password_container" style="<?php echo (($config['use_password_default'] ?? '0') == '1' ? '' : 'display: none;'); ?>; margin-top: 15px; padding-left: 28px;">
                    <label class="labdesk-form-label">Senha Padrão</label>
                    <div style="display: flex; gap: 5px; max-width: 400px;">
                        <input type="password" class="labdesk-input" id="password_default" name="password_default" value="<?php echo Html::clean($config['password_default'] ?? ''); ?>">
                        <button type="button" class="labdesk-btn" id="togglePassword" style="width: auto; background: #f1f5f9; color: #64748b; padding: 0 15px;">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="labdesk-section-header" style="margin-top: 30px;">
                    <i class="fas fa-sync"></i> Automação
                </div>
                
                <div class="labdesk-form-group">
                    <label class="labdesk-form-label">Intervalo de Sincronização (segundos)</label>
                    <input type="number" class="labdesk-input" name="sync_interval" value="<?php echo $config['sync_interval'] ?? '300'; ?>" style="max-width: 150px;">
                    <small style="color: #64748b; font-size: 0.85rem; display:block; margin-top:5px;">
                        Ao salvar, a frequência da tarefa automática será atualizada no GLPI.
                    </small>
                </div>

                <div class="labdesk-footer-bar">
                    <div style="font-size: 0.85rem; color: #64748b;">
                        Última sincronização: <strong><?php echo $config['last_sync'] ?: 'Nunca'; ?></strong>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="button" id="btnSync" class="labdesk-btn" style="background: #fff; border: 1px solid #e2e8f0; color: var(--ld-primary);">
                            <i class="fas fa-sync"></i> Sincronizar Agora
                        </button>
                        <button type="submit" name="update" class="labdesk-btn labdesk-btn-connect">
                            <i class="fas fa-save"></i> Salvar Configurações
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Toggle Senha
    const toggle = document.getElementById('togglePassword');
    const pass = document.getElementById('password_default');
    if(toggle && pass) {
        toggle.addEventListener('click', () => {
            const type = pass.getAttribute('type') === 'password' ? 'text' : 'password';
            pass.setAttribute('type', type);
            toggle.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
    }

    // Toggle Visibilidade
    const check = document.getElementById('use_password_default');
    const container = document.getElementById('password_container');
    if(check && container) {
        check.addEventListener('change', () => {
            container.style.display = check.checked ? 'block' : 'none';
        });
    }

    // Ação Sincronizar via AJAX
    const btnSync = document.getElementById('btnSync');
    if(btnSync) {
        btnSync.addEventListener('click', () => {
            if(!confirm('Deseja iniciar a sincronização com o RustDesk e importar dados do GLPI?')) return;
            const oldHtml = btnSync.innerHTML;
            btnSync.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            btnSync.disabled = true;
            fetch('ajax.php?action=synccomputers', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(resp => {
                    alert(resp.success ? 'Sincronização realizada com sucesso!' : 'Erro: ' + resp.message);
                    if(resp.success) location.reload();
                })
                .catch(() => alert('Erro de comunicação com o servidor.'))
                .finally(() => {
                    btnSync.innerHTML = oldHtml;
                    btnSync.disabled = false;
                });
        });
    }
});
</script>

<?php Html::footer(); ?>