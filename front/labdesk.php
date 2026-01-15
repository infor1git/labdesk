<?php
/**
 * LabDesk Plugin - Main Page
 * Compatible with GLPI 10.0.17
 */

include ('../../../inc/includes.php');

// Permissão segura e existente
Session::checkRight('config', READ);

// Header padrão para plugin
Html::header(
    __('LabDesk', 'labdesk'),
    $_SERVER['REQUEST_URI'],
    'plugins',
    'labdesk'
);

// Inicialização segura
$config      = [];
$validation  = ['valid' => false, 'errors' => []];
$computers   = [];
$groups      = [];

try {
    $config     = PluginLabdeskConfig::getConfig();
    $validation = PluginLabdeskConfig::validateConfig();

    if ($validation['valid']) {
        $computers = PluginLabdeskComputer::getAll();
        $groups    = PluginLabdeskGroup::getAll();
    }
} catch (Throwable $e) {
    error_log('[LabDesk] ' . $e->getMessage());
    $validation = [
        'valid'  => false,
        'errors' => [$e->getMessage()]
    ];
}
?>

<div class="center">

    <?php if (!$validation['valid']) : ?>
        <div class="alert alert-warning" style="max-width:700px;margin:20px auto;">
            <i class="fas fa-exclamation-triangle"></i>
            <strong><?php echo __('Configuração incompleta', 'labdesk'); ?></strong>
            <ul>
                <?php foreach ($validation['errors'] as $error) : ?>
                    <li><?php echo Html::clean($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <a class="btn btn-primary btn-sm"
               href="<?php echo Plugin::getWebDir('labdesk'); ?>/front/config.php">
                <i class="fas fa-cog"></i>
                <?php echo __('Configurar', 'labdesk'); ?>
            </a>
        </div>
    <?php endif; ?>

    <div style="max-width:1200px;margin:20px auto;">

        <h2><?php echo __('LabDesk', 'labdesk'); ?></h2>
        <p><?php echo __('Gerenciamento de computadores integrados ao RustDesk', 'labdesk'); ?></p>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin:20px 0;">
            <div class="card">
                <div class="card-body text-center">
                    <h3><?php echo count($computers); ?></h3>
                    <p><?php echo __('Computadores', 'labdesk'); ?></p>
                </div>
            </div>

            <div class="card">
                <div class="card-body text-center">
                    <h3>
                        <?php
                        $online = array_filter($computers, static function ($c) {
                            return ($c['status'] ?? '') === 'online';
                        });
                        echo count($online);
                        ?>
                    </h3>
                    <p><?php echo __('Online', 'labdesk'); ?></p>
                </div>
            </div>

            <div class="card">
                <div class="card-body text-center">
                    <h3><?php echo count($groups); ?></h3>
                    <p><?php echo __('Grupos', 'labdesk'); ?></p>
                </div>
            </div>
        </div>

        <div style="text-align:center;margin-bottom:20px;">
            <a class="btn btn-primary"
               href="<?php echo Plugin::getWebDir('labdesk'); ?>/front/config.php">
                <i class="fas fa-cog"></i>
                <?php echo __('Configuração', 'labdesk'); ?>
            </a>
        </div>

        <?php if (empty($computers)) : ?>
            <div class="card" style="padding:40px;text-align:center;">
                <div style="font-size:48px;">📭</div>
                <p><strong><?php echo __('Nenhum computador encontrado', 'labdesk'); ?></strong></p>
                <p><?php echo __('Configure o plugin e sincronize com o RustDesk', 'labdesk'); ?></p>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><?php echo __('Nome', 'labdesk'); ?></th>
                            <th><?php echo __('Apelido', 'labdesk'); ?></th>
                            <th><?php echo __('Status', 'labdesk'); ?></th>
                            <th><?php echo __('Unidade', 'labdesk'); ?></th>
                            <th><?php echo __('Setor', 'labdesk'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($computers as $comp) : ?>
                            <tr>
                                <td><strong><?php echo Html::clean($comp['rustdesk_name'] ?? '—'); ?></strong></td>
                                <td><?php echo Html::clean($comp['alias'] ?? '—'); ?></td>
                                <td>
                                    <?php
                                    $status = $comp['status'] ?? 'offline';
                                    $class  = ($status === 'online') ? 'badge-success' : 'badge-danger';
                                    ?>
                                    <span class="badge <?php echo $class; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td><?php echo Html::clean($comp['unit'] ?? '—'); ?></td>
                                <td><?php echo Html::clean($comp['department'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php
Html::footer();
