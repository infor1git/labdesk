<?php
/**
 * LabDesk Plugin - Main Page (UI Melhorada + Filtros + Edição)
 * Compatível com GLPI 10.0.17
 */

include('../../../inc/includes.php');

// Permissão segura e existente
Session::checkRight("config", READ);

// Header padrão para plugin
Html::header(__('Computadores Labdesk', 'labdesk'), $_SERVER['PHP_SELF'], "tools", "PluginLabdeskLabdeskMenu", "labdesk");

// Inicialização segura
$config     = [];
$validation = ['valid' => false, 'errors' => []];
$computers  = [];
$groups     = [];

try {
   $config     = PluginLabdeskConfig::getConfig();
   $validation = PluginLabdeskConfig::validateConfig($config);
   if ($validation['valid']) {
      $computers = PluginLabdeskComputer::getAll();
      $groups    = PluginLabdeskGroup::getAll();
   }
} catch (Throwable $e) {
   error_log('[LabDesk] ' . $e->getMessage());
   $validation['valid']  = false;
   $validation['errors'] = [$e->getMessage()];
}
?>
<div class="center">
   <?php if (!$validation['valid']) { ?>
      <div class="alert alert-warning" style="max-width:700px;margin:20px auto;">
         <i class="fas fa-exclamation-triangle"></i>
         <strong><?php echo __('Configuração incompleta', 'labdesk'); ?></strong>
         <ul>
            <?php foreach ($validation['errors'] as $error) { ?>
               <li><?php echo Html::clean($error); ?></li>
            <?php } ?>
         </ul>
         <a class="btn btn-primary btn-sm"
            href="<?php echo Plugin::getWebDir('labdesk') ?>/front/config.php">
            <i class="fas fa-cog"></i>
            <?php echo __('Configurar', 'labdesk'); ?>
         </a>
      </div>
   <?php } ?>
</div>

<?php if ($validation['valid']) { ?>
<style>
   * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
   }
   body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f5f5f5;
      color: #333;
      line-height: 1.6;
   }
   .labdesk-wrapper {
      max-width: 1400px;
      margin: 0 auto 30px auto;
      padding: 20px;
   }
   .labdesk-header {
      background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
      color: #fff;
      padding: 24px 20px;
      margin: 0 -20px 24px -20px;
      border-radius: 0 0 8px 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
   }
   .labdesk-header h1 {
      font-size: 1.8em;
      margin-bottom: 6px;
      display: flex;
      align-items: center;
      gap: 10px;
   }
   .labdesk-header p {
      font-size: 0.95em;
      opacity: 0.95;
   }

   .labdesk-controls {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 18px;
      align-items: center;
   }
   .labdesk-search-box {
      flex: 1;
      min-width: 250px;
      position: relative;
   }
   .labdesk-search-box input {
      width: 100%;
      padding: 10px 42px 10px 14px;
      border: 2px solid #ddd;
      border-radius: 6px;
      font-size: 0.95em;
      transition: all 0.2s ease;
      background-color: #fff;
      color: #111827;
   }
   .labdesk-search-box input:focus {
      outline: none;
      border-color: #2563eb;
      box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
   }
   .labdesk-search-box i {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
      pointer-events: none;
   }

   .labdesk-btn-group {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
   }
   .labdesk-btn {
      padding: 9px 16px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 0.9em;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
      transition: all 0.2s ease;
      color: #fff;
   }
   .labdesk-btn-primary {
      background-color: #2563eb;
   }
   .labdesk-btn-primary:hover {
      background-color: #1d4ed8;
      box-shadow: 0 3px 8px rgba(37,99,235,0.35);
      transform: translateY(-1px);
   }
   .labdesk-btn-success {
      background-color: #16a34a;
   }
   .labdesk-btn-success:hover {
      background-color: #15803d;
      box-shadow: 0 3px 8px rgba(22,163,74,0.35);
      transform: translateY(-1px);
   }
   .labdesk-btn-secondary {
      background-color: #6b7280;
   }
   .labdesk-btn-secondary:hover {
      background-color: #4b5563;
      box-shadow: 0 3px 8px rgba(75,85,99,0.35);
      transform: translateY(-1px);
   }
   .labdesk-btn-warning {
      background-color: #f59e0b;
      color: #fff;
   }
   .labdesk-btn-warning:hover {
      background-color: #d97706;
   }
   .labdesk-btn-sm {
      padding: 7px 10px;
      font-size: 0.8em;
   }

   .labdesk-filters {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      margin-bottom: 12px;
      font-size: 0.9em;
   }
   .labdesk-filter-btn {
      padding: 6px 12px;
      background-color: #e5e7eb;
      color: #111827;
      border: 2px solid #d1d5db;
      border-radius: 6px;
      cursor: pointer;
      font-size: 0.85em;
      font-weight: 500;
      transition: all 0.2s ease;
   }
   .labdesk-filter-btn:hover {
      background-color: #d1d5db;
      border-color: #9ca3af;
   }
   .labdesk-filter-btn.active {
      background-color: #2563eb;
      border-color: #2563eb;
      color: #fff;
   }
   .labdesk-filter-select {
      padding: 6px 10px;
      border-radius: 6px;
      border: 2px solid #d1d5db;
      background-color: #fff;
      font-size: 0.85em;
      min-width: 140px;
   }

   .labdesk-stats {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 16px;
      font-size: 0.9em;
   }
   .labdesk-stat-item {
      background: #fff;
      padding: 10px 14px;
      border-radius: 6px;
      border-left: 4px solid #2563eb;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
   }
   .labdesk-stat-item strong {
      display: block;
      font-size: 1.1em;
      color: #2563eb;
   }
   .labdesk-stat-item.online { border-left-color: #16a34a; }
   .labdesk-stat-item.online strong { color: #16a34a; }
   .labdesk-stat-item.offline { border-left-color: #dc2626; }
   .labdesk-stat-item.offline strong { color: #dc2626; }

   .labdesk-view-toggle {
      margin-left: auto;
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
   }
   .labdesk-view-btn {
      padding: 5px 10px;
      border-radius: 4px;
      border: 2px solid #d1d5db;
      background-color: #e5e7eb;
      font-size: 0.8em;
      cursor: pointer;
   }
   .labdesk-view-btn.active {
      background-color: #2563eb;
      border-color: #2563eb;
      color: #fff;
   }

   .labdesk-grid-view {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px,1fr));
      gap: 14px;
      margin-bottom: 24px;
   }
   .labdesk-card {
      background-color: #fff;
      border-radius: 8px;
      padding: 12px;
      border: 1px solid #e5e7eb;
      box-shadow: 0 1px 4px rgba(0,0,0,0.08);
      display: flex;
      flex-direction: column;
      min-height: 230px;
      transition: all 0.2s ease;
   }
   .labdesk-card:hover {
      box-shadow: 0 3px 10px rgba(0,0,0,0.12);
      transform: translateY(-2px);
   }
   .labdesk-card-header {
      margin-bottom: 8px;
   }
   .labdesk-card-title {
      font-size: 0.95em;
      font-weight: 700;
      color: #111827;
      margin-bottom: 3px;
      word-break: break-word;
   }
   .labdesk-card-subtitle {
      font-size: 0.8em;
      color: #6b7280;
      word-break: break-word;
   }
   .labdesk-card-body {
      flex: 1;
      margin-bottom: 8px;
   }
   .labdesk-info-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 4px 0;
      font-size: 0.8em;
      border-bottom: 1px solid #f3f4f6;
   }
   .labdesk-info-row:last-child {
      border-bottom: none;
   }
   .labdesk-info-label {
      color: #6b7280;
      font-weight: 500;
      min-width: 70px;
   }
   .labdesk-info-value {
      color: #111827;
      font-weight: 600;
      text-align: right;
      margin-left: 6px;
      word-break: break-word;
   }

   .labdesk-status-badge {
      display: inline-block;
      padding: 3px 9px;
      border-radius: 999px;
      font-size: 0.7em;
      font-weight: 600;
      text-transform: uppercase;
   }
   .labdesk-status-online {
      background-color: #dcfce7;
      color: #15803d;
      border: 1px solid #86efac;
   }
   .labdesk-status-offline {
      background-color: #fee2e2;
      color: #991b1b;
      border: 1px solid #fca5a5;
   }

   .labdesk-groups {
      margin-top: 4px;
      display: flex;
      flex-wrap: wrap;
      gap: 4px;
   }
   .labdesk-group-badge {
      background-color: #dbeafe;
      color: #1e40af;
      padding: 3px 7px;
      border-radius: 999px;
      font-size: 0.7em;
      border: 1px solid #93c5fd;
   }

   .labdesk-card-footer {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
   }
   .labdesk-card-footer .labdesk-btn {
      flex: 1;
      min-width: 70px;
   }
   .labdesk-btn-connect {
      background-color: #16a34a;
   }
   .labdesk-btn-connect:hover {
      background-color: #15803d;
   }
   .labdesk-btn-connect-web {
      background-color: #2563eb;
   }
   .labdesk-btn-connect-web:hover {
      background-color: #1d4ed8;
   }

   .labdesk-table-view {
      display: none;
      overflow-x: auto;
      margin-bottom: 24px;
   }
   .labdesk-table-view table {
      width: 100%;
      border-collapse: collapse;
      background-color: #fff;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 1px 4px rgba(0,0,0,0.08);
   }
   .labdesk-table-view thead {
      background-color: #f3f4f6;
   }
   .labdesk-table-view th {
      padding: 10px;
      text-align: left;
      font-size: 0.85em;
      font-weight: 600;
      color: #111827;
      border-bottom: 2px solid #e5e7eb;
   }
   .labdesk-table-view td {
      padding: 9px 10px;
      font-size: 0.8em;
      border-bottom: 1px solid #e5e7eb;
   }
   .labdesk-table-view tbody tr:hover {
      background-color: #f9fafb;
   }

   .labdesk-empty {
      text-align: center;
      padding: 30px 10px;
      color: #6b7280;
   }
   .labdesk-empty i {
      font-size: 2.5em;
      margin-bottom: 8px;
      color: #d1d5db;
   }

   .labdesk-toast {
      position: fixed;
      bottom: 18px;
      right: 18px;
      padding: 12px 16px;
      border-radius: 6px;
      background-color: #16a34a;
      color: #fff;
      box-shadow: 0 3px 12px rgba(0,0,0,0.2);
      font-size: 0.85em;
      z-index: 9999;
   }
   .labdesk-toast.error { background-color: #dc2626; }
   .labdesk-toast.info  { background-color: #2563eb; }

   .labdesk-modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.45);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9000;
   }
   .labdesk-modal {
      background: #fff;
      padding: 20px 22px;
      border-radius: 8px;
      max-width: 420px;
      width: 100%;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
   }
   .labdesk-modal-header {
      font-size: 1.1em;
      font-weight: 600;
      margin-bottom: 12px;
   }
   .labdesk-modal-body .form-group {
      margin-bottom: 10px;
      font-size: 0.9em;
   }
   .labdesk-modal-body label {
      display: block;
      margin-bottom: 4px;
      font-weight: 500;
   }
   .labdesk-modal-body input,
   .labdesk-modal-body select {
      width: 100%;
      padding: 7px 10px;
      border-radius: 6px;
      border: 1px solid #d1d5db;
      font-size: 0.9em;
   }
   .labdesk-modal-footer {
      margin-top: 14px;
      display: flex;
      justify-content: flex-end;
      gap: 8px;
   }

   @media (max-width: 768px) {
      .labdesk-header h1 {
         font-size: 1.4em;
      }
      .labdesk-controls {
         flex-direction: column;
         align-items: stretch;
      }
      .labdesk-btn-group {
         width: 100%;
      }
      .labdesk-btn-group .labdesk-btn {
         flex: 1;
         justify-content: center;
      }
      .labdesk-card-footer .labdesk-btn {
         font-size: 0.75em;
      }
      .labdesk-modal {
         margin: 20px;
      }
   }
</style>

<div class="labdesk-header">
   <h1>
      <i class="fas fa-desktop"></i>
      LabDesk - RustDesk Manager
   </h1>
   <p><?php echo __('Gerenciamento de computadores integrados ao RustDesk', 'labdesk'); ?></p>
</div>

<div class="labdesk-wrapper" id="labdeskApp">
   <div class="labdesk-controls">
      <div class="labdesk-search-box">
         <input type="text" id="ldSearch" placeholder="Buscar por alias, hostname, ID ou grupo...">
         <i class="fas fa-search"></i>
      </div>

      <div class="labdesk-btn-group">
         <button type="button" class="labdesk-btn labdesk-btn-success" id="ldSyncBtn">
            <i class="fas fa-sync-alt"></i>
            Sincronizar
         </button>

         <!-- <a class="labdesk-btn labdesk-btn-secondary"
            href="<?php // echo Plugin::getWebDir('labdesk') ?>/front/config.php">
            <i class="fas fa-cog"></i>
            Configuração
         </a> -->
      </div>
   </div>

   <div class="labdesk-filters">
      <span>Status:</span>
      <button type="button" class="labdesk-filter-btn active" data-status="all">Todos</button>
      <button type="button" class="labdesk-filter-btn" data-status="online">
         <i class="fas fa-circle" style="color:#16a34a;font-size:0.6em;"></i> Online
      </button>
      <button type="button" class="labdesk-filter-btn" data-status="offline">
         <i class="fas fa-circle" style="color:#dc2626;font-size:0.6em;"></i> Offline
      </button>

      <span>Unidade:</span>
      <select id="ldFilterUnit" class="labdesk-filter-select">
         <option value="">Todas</option>
      </select>

      <span>Departamento:</span>
      <select id="ldFilterDept" class="labdesk-filter-select">
         <option value="">Todos</option>
      </select>

      <div class="labdesk-view-toggle">
         <button type="button" class="labdesk-view-btn active" data-view="grid">
            <i class="fas fa-th"></i>
         </button>
         <button type="button" class="labdesk-view-btn" data-view="table">
            <i class="fas fa-list"></i>
         </button>
      </div>
   </div>

   <div class="labdesk-stats">
      <div class="labdesk-stat-item">
         <strong id="ldTotalCount"><?php echo count($computers); ?></strong>
         Total de computadores
      </div>
      <div class="labdesk-stat-item online">
         <?php
         $online = array_filter($computers, static function ($c) {
            return ($c['status'] ?? 'offline') === 'online';
         });
         ?>
         <strong id="ldOnlineCount"><?php echo count($online); ?></strong>
         Online
      </div>
      <div class="labdesk-stat-item offline">
         <strong id="ldOfflineCount">
            <?php echo count($computers) - count($online); ?>
         </strong>
         Offline
      </div>
   </div>

   <?php if (empty($computers)) { ?>
      <div class="labdesk-empty">
         <i class="fas fa-inbox"></i>
         <p><strong><?php echo __('Nenhum computador encontrado', 'labdesk'); ?></strong></p>
         <p><?php echo __('Configure o plugin e sincronize com o RustDesk.', 'labdesk'); ?></p>
      </div>
   <?php } else { ?>
      <div id="ldGridView" class="labdesk-grid-view"></div>
      <div id="ldTableView" class="labdesk-table-view"></div>
   <?php } ?>
</div>

<!-- Modal de Edição -->
<div id="ldEditModalBackdrop" class="labdesk-modal-backdrop">
   <div class="labdesk-modal">
      <div class="labdesk-modal-header">Editar computador</div>
      <div class="labdesk-modal-body">
         <input type="hidden" id="ldEditId">
         <div class="form-group">
            <label for="ldEditAlias">Alias</label>
            <input type="text" id="ldEditAlias">
         </div>
         <div class="form-group">
            <label for="ldEditUnit">Unidade (Localização GLPI)</label>
            <select id="ldEditUnit"></select>
         </div>
         <div class="form-group">
            <label for="ldEditDept">Departamento (Grupo GLPI)</label>
            <select id="ldEditDept"></select>
         </div>
      </div>
      <div class="labdesk-modal-footer">
         <button type="button" class="labdesk-btn labdesk-btn-secondary labdesk-btn-sm" id="ldEditCancel">
            Cancelar
         </button>
         <button type="button" class="labdesk-btn labdesk-btn-primary labdesk-btn-sm" id="ldEditSave">
            Salvar
         </button>
      </div>
   </div>
</div>

<script>
   const ldComputers = <?php echo json_encode($computers, JSON_UNESCAPED_UNICODE); ?>;
   const ldRootUrl   = "<?php echo addslashes(GLPI_ROOT); ?>";
   const ldAjaxUrl   = "<?php echo Plugin::getWebDir('labdesk'); ?>/front/ajax.php";

   const ldState = {
      all: ldComputers || [],
      filtered: [],
      status: 'all',
      search: '',
      view: 'grid',
      unit: '',
      department: '',
      editing: null
   };

   function ldEscape(str) {
      if (!str) return '';
      return String(str)
         .replace(/&/g, '&amp;')
         .replace(/</g, '&lt;')
         .replace(/>/g, '&gt;')
         .replace(/"/g, '&quot;')
         .replace(/'/g, '&#039;');
   }

   function ldFormatStatusRow(status) {
      const online = (status || 'offline') === 'online';
      const cls    = online ? 'labdesk-status-online' : 'labdesk-status-offline';
      const label  = online ? 'Online' : 'Offline';
      return '<span class="labdesk-status-badge ' + cls + '">' + label + '</span>';
   }

   function ldGetInventoryUrl(c) {
      if (!c || !c.glpi_computer_id) return null;
      return ldRootUrl + '/front/computer.form.php?id=' + encodeURIComponent(c.glpi_computer_id);
   }

   function ldBuildFilterOptions() {
      const units = new Set();
      const depts = new Set();

      ldState.all.forEach(c => {
         if (c.unit) units.add(c.unit);
         if (c.department) depts.add(c.department);
      });

      const unitSelect = document.getElementById('ldFilterUnit');
      const deptSelect = document.getElementById('ldFilterDept');
      unitSelect.innerHTML = '<option value="">Todas</option>';
      deptSelect.innerHTML = '<option value="">Todos</option>';

      Array.from(units).sort().forEach(u => {
         const opt = document.createElement('option');
         opt.value = u;
         opt.textContent = u;
         unitSelect.appendChild(opt);
      });

      Array.from(depts).sort().forEach(d => {
         const opt = document.createElement('option');
         opt.value = d;
         opt.textContent = d;
         deptSelect.appendChild(opt);
      });

      // também popular selects do modal
      const editUnit = document.getElementById('ldEditUnit');
      const editDept = document.getElementById('ldEditDept');
      editUnit.innerHTML = '<option value="">(sem unidade)</option>';
      editDept.innerHTML = '<option value="">(sem departamento)</option>';

      Array.from(units).sort().forEach(u => {
         const opt = document.createElement('option');
         opt.value = u;
         opt.textContent = u;
         editUnit.appendChild(opt);
      });
      Array.from(depts).sort().forEach(d => {
         const opt = document.createElement('option');
         opt.value = d;
         opt.textContent = d;
         editDept.appendChild(opt);
      });
   }

   function ldFilter() {
      ldState.filtered = ldState.all.filter(c => {
         // Status
         const s = (ldState.status || 'all');
         const compStatus = (c.status || 'offline') === 'online' ? 'online' : 'offline';
         const matchesStatus =
            s === 'all' ||
            (s === 'online' && compStatus === 'online') ||
            (s === 'offline' && compStatus === 'offline');

         // Unidade
         const matchesUnit =
            !ldState.unit || (c.unit || '') === ldState.unit;

         // Departamento
         const matchesDept =
            !ldState.department || (c.department || '') === ldState.department;

         // Busca
         const txt = (ldState.search || '').toLowerCase();
         if (!txt) {
            return matchesStatus && matchesUnit && matchesDept;
         }

         const alias  = (c.alias         || '').toLowerCase();
         const host   = (c.rustdesk_name || c.rustdeskname || '').toLowerCase();
         const id     = (c.rustdesk_id   || '').toLowerCase();
         const groups = Array.isArray(c.groups) ? c.groups.join(' ').toLowerCase() : '';

         const matchesSearch =
            alias.includes(txt) ||
            host.includes(txt)  ||
            id.includes(txt)    ||
            groups.includes(txt);

         return matchesStatus && matchesUnit && matchesDept && matchesSearch;
      });

      ldRender();
   }

   function ldRender() {
      const list = ldState.filtered.length ? ldState.filtered : ldState.all.slice();

      const onlineCount = ldState.all.filter(c => (c.status || 'offline') === 'online').length;
      document.getElementById('ldOnlineCount').textContent  = onlineCount;
      document.getElementById('ldOfflineCount').textContent = ldState.all.length - onlineCount;
      document.getElementById('ldTotalCount').textContent   = ldState.all.length;

      if (ldState.view === 'grid') {
         ldRenderGrid(list);
         document.getElementById('ldGridView').style.display  = 'grid';
         document.getElementById('ldTableView').style.display = 'none';
      } else {
         ldRenderTable(list);
         document.getElementById('ldGridView').style.display  = 'none';
         document.getElementById('ldTableView').style.display = 'block';
      }
   }

   function ldRenderGrid(list) {
      const container = document.getElementById('ldGridView');
      if (!list.length) {
         container.innerHTML = `
            <div class="labdesk-empty" style="grid-column:1 / -1;">
               <i class="fas fa-inbox"></i>
               <p><strong>Nenhum computador encontrado</strong></p>
            </div>`;
         return;
      }

      container.innerHTML = list.map(c => {
         const alias   = ldEscape(c.alias || '');
         const rawHost = (c.rustdesk_name || c.rustdeskname || '').toString().toUpperCase();
         const hostEsc = ldEscape(rawHost);
         const unit    = ldEscape(c.unit || '');
         const dept    = ldEscape(c.department || '');
         const rid     = ldEscape(c.rustdesk_id || '');
         const groups  = Array.isArray(c.groups) ? c.groups : [];
         const invUrl  = ldGetInventoryUrl(c);
         const hostHtml = invUrl
            ? `<a href="${invUrl}" target="_blank">${hostEsc}</a>`
            : hostEsc;

         return `
            <div class="labdesk-card">
               <div class="labdesk-card-header">
                  <div class="labdesk-card-title">${alias || '(sem alias)'}</div>
                  <div class="labdesk-card-subtitle">${hostHtml || '&nbsp;'}</div>
               </div>
               <div class="labdesk-card-body">
                  <div class="labdesk-info-row">
                     <span class="labdesk-info-label">RustDesk ID</span>
                     <span class="labdesk-info-value">${rid || '-'}</span>
                  </div>
                  <div class="labdesk-info-row">
                     <span class="labdesk-info-label">Unidade</span>
                     <span class="labdesk-info-value">${unit || '-'}</span>
                  </div>
                  <div class="labdesk-info-row">
                     <span class="labdesk-info-label">Departamento</span>
                     <span class="labdesk-info-value">${dept || '-'}</span>
                  </div>
                  <div class="labdesk-info-row">
                     <span class="labdesk-info-label">Status</span>
                     <span class="labdesk-info-value">${ldFormatStatusRow(c.status)}</span>
                  </div>
                  ${groups.length ? `
                     <div class="labdesk-groups">
                        ${groups.map(g => `<span class="labdesk-group-badge">${ldEscape(g)}</span>`).join('')}
                     </div>
                  ` : ``}
               </div>
               <div class="labdesk-card-footer">
                  <button type="button"
                          class="labdesk-btn labdesk-btn-connect labdesk-btn-sm"
                          onclick="ldConnect('${rid}')">
                     <i class="fas fa-link"></i> App
                  </button>
                  <button type="button"
                          class="labdesk-btn labdesk-btn-connect-web labdesk-btn-sm"
                          onclick="ldConnectWeb('${rid}')">
                     <i class="fas fa-globe"></i> Web
                  </button>
                  <button type="button"
                          class="labdesk-btn labdesk-btn-warning labdesk-btn-sm"
                          onclick="ldOpenEdit(${c.id})">
                     <i class="fas fa-edit"></i> Editar
                  </button>
               </div>
            </div>`;
      }).join('');
   }

   function ldRenderTable(list) {
      const container = document.getElementById('ldTableView');
      if (!list.length) {
         container.innerHTML = `
            <div class="labdesk-empty">
               <i class="fas fa-inbox"></i>
               <p><strong>Nenhum computador encontrado</strong></p>
            </div>`;
         return;
      }

      const rows = list.map(c => {
         const alias   = ldEscape(c.alias || '');
         const rawHost = (c.rustdesk_name || c.rustdeskname || '').toString().toUpperCase();
         const hostEsc = ldEscape(rawHost);
         const rid     = ldEscape(c.rustdesk_id || '');
         const unit    = ldEscape(c.unit || '');
         const dept    = ldEscape(c.department || '');
         const status  = ldFormatStatusRow(c.status);
         const invUrl  = ldGetInventoryUrl(c);
         const hostHtml = invUrl
            ? `<a href="${invUrl}" target="_blank">${hostEsc}</a>`
            : hostEsc;

         return `
            <tr>
               <td>${alias}</td>
               <td>${hostHtml}</td>
               <td>${rid}</td>
               <td>${unit}</td>
               <td>${dept}</td>
               <td>${status}</td>
               <td>
                  <button type="button"
                          class="labdesk-btn labdesk-btn-connect labdesk-btn-sm"
                          onclick="ldConnect('${rid}')">
                     <i class="fas fa-link"></i>
                  </button>
                  <button type="button"
                          class="labdesk-btn labdesk-btn-connect-web labdesk-btn-sm"
                          onclick="ldConnectWeb('${rid}')">
                     <i class="fas fa-globe"></i>
                  </button>
                  <button type="button"
                          class="labdesk-btn labdesk-btn-warning labdesk-btn-sm"
                          onclick="ldOpenEdit(${c.id})">
                     <i class="fas fa-edit"></i>
                  </button>
               </td>
            </tr>`;
      }).join('');

      container.innerHTML = `
         <table>
            <thead>
               <tr>
                  <th>Alias</th>
                  <th>Hostname</th>
                  <th>RustDesk ID</th>
                  <th>Unidade</th>
                  <th>Departamento</th>
                  <th>Status</th>
                  <th>Ações</th>
               </tr>
            </thead>
            <tbody>${rows}</tbody>
         </table>`;
   }

   function ldConnect(rid) {
      if (!rid) return;
      const password = 'labRD@1983'; // ajuste conforme seu ambiente
      const url = 'rustdesk://' + rid + '?password=' + encodeURIComponent(password);
      window.location.href = url;
   }

   function ldConnectWeb(rid) {
      if (!rid) return;
      const password = 'labRD@1983'; // ajuste conforme seu ambiente
      const base = '<?php echo Html::clean($config['rustdeskurl'] ?? 'http://labdesk.labchecap.com.br:21114'); ?>';
      const url = base.replace(/\/+$/, '') + '/webclient2/#/' + encodeURIComponent(rid) +
                  '?password=' + encodeURIComponent(password);
      window.open(url, '_blank');
   }

   function ldToast(msg, type='info') {
      const div = document.createElement('div');
      div.className = 'labdesk-toast ' + type;
      div.textContent = msg;
      document.body.appendChild(div);
      setTimeout(() => div.remove(), 3500);
   }

   function ldSync() {
      if (!confirm('Sincronizar computadores agora?')) return;
      const btn = document.getElementById('ldSyncBtn');
      const old = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sincronizando...';

      const url = '<?php echo Plugin::getWebDir("labdesk"); ?>/front/config.php?action=synccomputers';
      fetch(url, { credentials: 'same-origin' })
         .then(() => {
            ldToast('Sincronização disparada. Atualize a página em alguns segundos.', 'info');
            setTimeout(() => location.reload(), 2500);
         })
         .catch(() => {
            ldToast('Erro ao iniciar a sincronização.', 'error');
         })
         .finally(() => {
            btn.disabled = false;
            btn.innerHTML = old;
         });
   }

   // Modal de edição
   function ldOpenEdit(id) {
      const c = ldState.all.find(x => x.id === id);
      if (!c) return;

      ldState.editing = id;
      document.getElementById('ldEditId').value    = c.id;
      document.getElementById('ldEditAlias').value = c.alias || '';

      const editUnit = document.getElementById('ldEditUnit');
      const editDept = document.getElementById('ldEditDept');
      if (c.unit)  editUnit.value  = c.unit;
      else         editUnit.value  = '';
      if (c.department) editDept.value = c.department;
      else              editDept.value = '';

      document.getElementById('ldEditModalBackdrop').style.display = 'flex';
   }

   function ldCloseEdit() {
      ldState.editing = null;
      document.getElementById('ldEditModalBackdrop').style.display = 'none';
   }

   function ldSaveEdit() {
      const id    = parseInt(document.getElementById('ldEditId').value, 10);
      const alias = document.getElementById('ldEditAlias').value;
      const unit  = document.getElementById('ldEditUnit').value;
      const dept  = document.getElementById('ldEditDept').value;

      if (!id) return;

      fetch(ldAjaxUrl + '?action=update-device', {
         method: 'POST',
         headers: {
            'Content-Type': 'application/json'
         },
         body: JSON.stringify({
            id: id,
            alias: alias,
            unit: unit,
            department: dept
         })
      })
      .then(r => r.json())
      .then(resp => {
         if (resp.success) {
            ldToast('Computador atualizado com sucesso', 'info');
            // Atualiza em memória para refletir no front
            const idx = ldState.all.findIndex(c => c.id === id);
            if (idx !== -1) {
               ldState.all[idx].alias      = alias;
               ldState.all[idx].unit       = unit;
               ldState.all[idx].department = dept;
            }
            ldFilter();
            ldCloseEdit();
         } else {
            ldToast('Erro ao atualizar computador', 'error');
         }
      })
      .catch(() => {
         ldToast('Erro ao atualizar computador', 'error');
      });
   }

   document.addEventListener('DOMContentLoaded', () => {
      ldState.filtered = ldState.all.slice();
      ldBuildFilterOptions();
      ldRender();

      const search = document.getElementById('ldSearch');
      search.addEventListener('input', (e) => {
         ldState.search = e.target.value;
         ldFilter();
      });

      document.getElementById('ldFilterUnit').addEventListener('change', (e) => {
         ldState.unit = e.target.value;
         ldFilter();
      });
      document.getElementById('ldFilterDept').addEventListener('change', (e) => {
         ldState.department = e.target.value;
         ldFilter();
      });

      document.querySelectorAll('.labdesk-filter-btn').forEach(btn => {
         btn.addEventListener('click', () => {
            document.querySelectorAll('.labdesk-filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            ldState.status = btn.dataset.status;
            ldFilter();
         });
      });

      document.querySelectorAll('.labdesk-view-btn').forEach(btn => {
         btn.addEventListener('click', () => {
            document.querySelectorAll('.labdesk-view-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            ldState.view = btn.dataset.view;
            ldRender();
         });
      });

      document.getElementById('ldSyncBtn').addEventListener('click', ldSync);

      document.getElementById('ldEditCancel').addEventListener('click', ldCloseEdit);
      document.getElementById('ldEditSave').addEventListener('click', ldSaveEdit);
      document.getElementById('ldEditModalBackdrop').addEventListener('click', (e) => {
         if (e.target.id === 'ldEditModalBackdrop') {
            ldCloseEdit();
         }
      });
   });
</script>
<?php } ?>

<?php
Html::footer();
