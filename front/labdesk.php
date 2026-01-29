<?php

include ('../../../inc/includes.php');

Session::checkLoginUser();

$config = PluginLabdeskConfig::getConfig();
$validation = PluginLabdeskConfig::validateConfig($config);

// --- PHP INICIALIZAÇÃO (Mantido igual) ---
$computers = [];
$all_locations = [];
$all_groups_root = [];
$all_types = [];
$computer_type_map = [];
$glpiNameMap = [];

if ($validation['valid']) {
    global $DB;
    $computers = PluginLabdeskComputer::getAll();

    if ($DB->tableExists('glpi_labdesk_computertypes_computers')) {
        $typeIter = $DB->request(['FROM' => 'glpi_labdesk_computertypes_computers']);
        foreach ($typeIter as $row) {
            $computer_type_map[$row['computer_id']] = $row['computertypes_id'];
        }
    }

    $names = array_column($computers, 'rustdesk_name');
    if (!empty($names)) {
        $safeNames = [];
        foreach($names as $n) $safeNames[] = $DB->escape($n);
        $nameStr = "'" . implode("','", $safeNames) . "'";
        $gIter = $DB->request("SELECT id, name, computertypes_id FROM glpi_computers WHERE name IN ($nameStr) AND is_deleted=0 AND is_template=0");
        foreach ($gIter as $gRow) {
            $key = strtolower($gRow['name']);
            if (!isset($glpiNameMap[$key])) { $glpiNameMap[$key] = $gRow; }
        }
    }

    foreach ($DB->request(['FROM' => 'glpi_locations', 'ORDER' => 'name']) as $row) { $all_locations[] = $row['name']; }
    foreach ($DB->request(['FROM' => 'glpi_groups', 'WHERE' => ['level' => 2], 'ORDER' => 'name']) as $row) { $all_groups_root[] = $row['name']; }
    foreach ($DB->request(['FROM' => 'glpi_computertypes', 'ORDER' => 'name']) as $row) { $all_types[] = ['id' => $row['id'], 'name' => $row['name']]; }
}

foreach ($computers as &$comp) {
    if (isset($computer_type_map[$comp['id']])) {
        $comp['type_id'] = $computer_type_map[$comp['id']];
    } else {
        $comp['type_id'] = 0;
    }
    $cName = strtolower($comp['rustdesk_name'] ?? '');
    if (isset($glpiNameMap[$cName])) {
        $comp['glpi_id'] = $glpiNameMap[$cName]['id'];
        if ($comp['type_id'] == 0 && !empty($glpiNameMap[$cName]['computertypes_id'])) {
            $comp['type_id'] = $glpiNameMap[$cName]['computertypes_id'];
        }
    } else {
        $comp['glpi_id'] = null;
    }
}
unset($comp);

Html::header(__('Labdesk - Equipamentos', 'labdesk'), $_SERVER['PHP_SELF'], "tools", "PluginLabdeskLabdeskMenu", "labdesk");
?>

<link rel="stylesheet" type="text/css" href="<?php echo Plugin::getWebDir('labdesk'); ?>/resources/css/labdesk.css">

<div class="labdesk-wrapper" id="ldApp">
    
    <div class="labdesk-sidebar">
        <div class="labdesk-sidebar-header">
            <div class="labdesk-sidebar-title"><i class="fas fa-computer" style="color: var(--ld-primary);"></i> LabDesk</div>
            <div style="font-size: 0.8em; color: #94a3b8; margin-top: 5px;">Gestão de Acesso</div>
        </div>
        <nav id="ldSidebarNav"></nav>
        <div style="margin-top: auto; padding: 20px;">
            <a href="<?php echo $CFG_GLPI['root_doc']; ?>/front/computertype.php" class="labdesk-btn labdesk-btn-sm" style="background: #f1f5f9; color: #64748b; justify-content: flex-start;"><i class="fas fa-plus-circle"></i> Categorias</a>
            <button id="ldSyncBtn" class="labdesk-btn labdesk-btn-sm" style="background: #f1f5f9; color: #64748b; margin-top: 10px; justify-content: flex-start;"><i class="fas fa-sync"></i> Sincronizar</button>
        </div>
    </div>

    <div class="labdesk-main">
        <div class="labdesk-toolbar">
            <div class="labdesk-search-group">
                <i class="fas fa-search labdesk-search-icon"></i>
                <input type="text" id="ldSearch" class="labdesk-search" placeholder="Buscar...">
            </div>
            
            <div class="ld-cs-wrapper" id="csFilterUnit">
                <button type="button" class="ld-cs-btn">Unidade: Todas</button>
                <div class="ld-cs-dropdown"><input type="text" class="ld-cs-search" placeholder="Buscar Unidade..."><ul class="ld-cs-list"></ul></div>
            </div>
            <div class="ld-cs-wrapper" id="csFilterDept">
                <button type="button" class="ld-cs-btn">Depto: Todos</button>
                <div class="ld-cs-dropdown"><input type="text" class="ld-cs-search" placeholder="Buscar Depto..."><ul class="ld-cs-list"></ul></div>
            </div>

            <select id="ldFilterStatus" class="labdesk-select" style="min-width:130px;">
                <option value="all">Status: Todos</option>
                <option value="online">🟢 Online</option>
                <option value="offline">⚪ Offline</option>
            </select>
            <div class="labdesk-btn-group">
                <button class="labdesk-view-btn active" data-view="grid" onclick="ldSetView('grid')"><i class="fas fa-th-large"></i></button>
                <button class="labdesk-view-btn" data-view="list" onclick="ldSetView('list')"><i class="fas fa-list"></i></button>
            </div>
        </div>

        <div class="labdesk-content-scroll">
            <?php if (!$validation['valid']): ?>
                <div style="padding: 20px; background: #fee2e2; border-radius: 8px; color: #991b1b;">Erro: <?php echo implode('<br>', $validation['errors']); ?></div>
            <?php else: ?>
                <div id="ldGridView" class="labdesk-grid"></div>
                <div id="ldTableView" style="display:none;"></div>
            <?php endif; ?>
        </div>
        
        <div style="padding: 10px 25px; border-top: 1px solid #e2e8f0; font-size: 0.85rem; color: #64748b; display: flex; gap: 20px;">
            <span><strong id="ldTotalCount">0</strong> Dispositivos</span>
            <span><strong id="ldOnlineCount" style="color: #166534;">0</strong> Online</span>
            <span><strong id="ldOfflineCount" style="color: #64748b;">0</strong> Offline</span>
        </div>
    </div>
</div>

<div id="ldEditModalBackdrop" class="labdesk-modal-backdrop">
    <div class="labdesk-modal">
        <h3 style="margin-top:0; margin-bottom: 20px;">Editar Dispositivo</h3>
        <div class="labdesk-modal-content">
            <input type="hidden" id="ldEditId">
            <input type="hidden" id="ldEditGlpiId"> 
            
            <div class="labdesk-form-group">
                <label class="labdesk-form-label">Alias</label>
                <input type="text" id="ldEditAlias" class="labdesk-input">
            </div>

            <div class="labdesk-form-group">
                <label class="labdesk-form-label">Categoria</label>
                <select id="ldEditType" class="labdesk-input"><option value="0">-- Não categorizado --</option></select>
            </div>

            <div class="labdesk-form-group">
                <label class="labdesk-form-label">Unidade</label>
                <div class="ld-cs-wrapper" id="csEditUnit" style="width:100%;">
                    <button type="button" class="ld-cs-btn">Selecione...</button>
                    <div class="ld-cs-dropdown"><input type="text" class="ld-cs-search" placeholder="Buscar..."><ul class="ld-cs-list"></ul></div>
                </div>
                <input type="hidden" id="ldEditUnitVal">
            </div>

            <div class="labdesk-form-group">
                <label class="labdesk-form-label">Departamento</label>
                <div class="ld-cs-wrapper" id="csEditDept" style="width:100%;">
                    <button type="button" class="ld-cs-btn">Selecione...</button>
                    <div class="ld-cs-dropdown"><input type="text" class="ld-cs-search" placeholder="Buscar..."><ul class="ld-cs-list"></ul></div>
                </div>
                <input type="hidden" id="ldEditDeptVal">
            </div>
        </div>
        <div class="labdesk-modal-footer">
            <button id="ldEditCancel" class="labdesk-btn" style="background:#e2e8f0; color:#475569;">Cancelar</button>
            <button id="ldEditSave" class="labdesk-btn labdesk-btn-connect">Salvar</button>
        </div>
    </div>
</div>

<script>
    // Dados vindos do PHP
    const ldComputers = <?php echo json_encode($computers, JSON_UNESCAPED_UNICODE); ?>;
    const ldLocations = <?php echo json_encode($all_locations, JSON_UNESCAPED_UNICODE); ?>;
    const ldGroups    = <?php echo json_encode($all_groups_root, JSON_UNESCAPED_UNICODE); ?>;
    const ldTypes     = <?php echo json_encode($all_types, JSON_UNESCAPED_UNICODE); ?>;
    const ldRootUrl   = "<?php echo $CFG_GLPI['root_doc']; ?>";
    const ldAjaxUrl   = "<?php echo Plugin::getWebDir('labdesk'); ?>/front/ajax.php";
    
    const ldConfig = {
        webUrl: "<?php echo Html::clean($config['webclient_url'] ?? ''); ?>",
        usePass: <?php echo ($config['use_password_default'] == '1') ? 'true' : 'false'; ?>,
        pass: "<?php echo Html::clean($config['password_default'] ?? ''); ?>"
    };

    // Estado Inicial
    const ldState = {
        all: ldComputers || [],
        filtered: [],
        filterText: '',
        filterUnit: 'all',
        filterDept: 'all',
        filterStatus: 'all',
        filterCategory: 'all',
        view: localStorage.getItem('labdesk_view') || 'grid'
    };

    document.addEventListener('DOMContentLoaded', () => {
        ldInitCustomSelects();
        ldInitEditModal();
        
        // 1. Restaura Filtros ao carregar
        ldRestoreState();

        // 2. Renderiza com os filtros restaurados
        ldRenderSidebar();
        ldFilter(); 
        ldUpdateViewButtons();
        
        // --- EVENTOS DE FILTRO COM SALVAMENTO AUTOMÁTICO ---

        // Busca Texto
        document.getElementById('ldSearch').addEventListener('input', (e) => {
            ldState.filterText = e.target.value.toLowerCase();
            ldFilter();
            ldSaveState(); // <--- Salva imediatamente ao digitar
        });

        // Filtro Status
        document.getElementById('ldFilterStatus').addEventListener('change', (e) => {
            ldState.filterStatus = e.target.value;
            ldFilter();
            ldSaveState(); // <--- Salva imediatamente ao mudar
        });

        // Sync manual
        document.getElementById('ldSyncBtn').addEventListener('click', ldSync);

        // Fecha dropdowns ao clicar fora
        document.addEventListener('click', (e) => {
            if(!e.target.closest('.ld-cs-wrapper')) document.querySelectorAll('.ld-cs-wrapper').forEach(el => el.classList.remove('open'));
        });

        // Sync Silencioso (Background)
        setTimeout(() => {
            fetch(ldAjaxUrl + '?action=synccomputers', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(d => { if(d.success) console.log('Auto-Sync OK'); })
                .catch(err => console.error('Erro Auto-Sync', err));
        }, 1500);
    });

    // --- FUNÇÕES DE ESTADO (CORRIGIDAS) ---

    function ldSaveState() {
        // Salva exatamente o que está nas variáveis agora
        const s = {
            t: ldState.filterText,
            u: ldState.filterUnit,
            d: ldState.filterDept,
            s: ldState.filterStatus,
            c: ldState.filterCategory
        };
        sessionStorage.setItem('labdesk_filter_state', JSON.stringify(s));
    }

    function ldRestoreState() {
        const stored = sessionStorage.getItem('labdesk_filter_state');
        if (stored) {
            try {
                const s = JSON.parse(stored);
                // Restaura ou usa padrão se não existir
                ldState.filterText = s.t || '';
                ldState.filterUnit = s.u || 'all';
                ldState.filterDept = s.d || 'all';
                ldState.filterStatus = s.s || 'all';
                ldState.filterCategory = s.c || 'all';

                // Aplica visualmente nos Inputs
                document.getElementById('ldSearch').value = ldState.filterText;
                document.getElementById('ldFilterStatus').value = ldState.filterStatus;
                
                // Aplica visualmente nos Selects Customizados
                const csU = document.getElementById('csFilterUnit');
                if(csU && csU.setValue) csU.setValue(ldState.filterUnit === 'all' ? null : ldState.filterUnit);
                
                const csD = document.getElementById('csFilterDept');
                if(csD && csD.setValue) csD.setValue(ldState.filterDept === 'all' ? null : ldState.filterDept);

            } catch(e) { console.error('Erro restore state', e); }
        }
    }

    // --- MANTIDO IGUAL ---
    function ldConnect(rid) {
        if (!rid) return;
        let url = 'rustdesk://' + rid;
        if (ldConfig.usePass && ldConfig.pass) url += '?password=' + encodeURIComponent(ldConfig.pass);
        window.location.href = url;
    }

    function ldConnectWeb(rid) {
        if (!rid) return;
        if (!ldConfig.webUrl) { alert('URL do WebClient não configurada.'); return; }
        const baseUrl = ldConfig.webUrl.replace(/\/+$/, '');
        let url = baseUrl + '/webclient2/#/' + encodeURIComponent(rid);
        if (ldConfig.usePass && ldConfig.pass) url += '?password=' + encodeURIComponent(ldConfig.pass);
        window.open(url, '_blank');
    }

    function ldCardTemplate(c) {
        const rid = ldEscape(c.rustdesk_id);
        const name = ldEscape(c.alias || c.rustdesk_name || 'Sem nome');
        const unit = ldEscape(c.unit || '-');
        const dept = ldEscape(c.department || '-');
        const isOnline = (c.status || 'offline') === 'online';
        let nameHtml = name;
        if (c.glpi_id) { 
            const url = ldRootUrl + '/front/computer.form.php?id=' + c.glpi_id; 
            nameHtml = `<a href="${url}" target="_blank" class="labdesk-glpi-link" title="Ver no GLPI"><i class="fas fa-link" style="font-size:0.8em;margin-right:4px;"></i>${name}</a>`; 
        }
        let btnWeb = '';
        if (ldConfig.webUrl) btnWeb = `<button class="labdesk-btn labdesk-btn-connect-web" onclick="ldConnectWeb('${rid}')"><i class="fas fa-globe"></i> Web</button>`;
        return `<div class="labdesk-card"><div class="labdesk-card-header"><div class="labdesk-card-title">${nameHtml}</div><div class="labdesk-card-subtitle">ID: ${rid}</div></div><div class="labdesk-card-body"><div class="labdesk-info-row"><span class="labdesk-info-label">Unidade</span><span class="labdesk-info-value">${unit}</span></div><div class="labdesk-info-row"><span class="labdesk-info-label">Depto</span><span class="labdesk-info-value">${dept}</span></div><div class="labdesk-info-row"><span class="labdesk-info-label">Status</span><span class="labdesk-status-badge ${isOnline?'labdesk-status-online':'labdesk-status-offline'}">${isOnline?'Online':'Offline'}</span></div></div><div class="labdesk-card-footer"><button class="labdesk-btn labdesk-btn-connect" onclick="ldConnect('${rid}')"><i class="fas fa-bolt"></i> App</button>${btnWeb}<button class="labdesk-btn labdesk-btn-warning" onclick="ldOpenEdit(${c.id})" style="flex:0;"><i class="fas fa-pen"></i></button></div></div>`;
    }

    function ldTableTemplate(list) {
        const rows = list.map(c => {
            const rid = ldEscape(c.rustdesk_id);
            const name = ldEscape(c.alias || c.rustdesk_name);
            const isOnline = (c.status || 'offline') === 'online';
            let nameHtml = name;
            if (c.glpi_id) { 
                const url = ldRootUrl + '/front/computer.form.php?id=' + c.glpi_id; 
                nameHtml = `<a href="${url}" target="_blank" class="labdesk-glpi-link"><i class="fas fa-link" style="font-size:0.8em;margin-right:4px;"></i>${name}</a>`; 
            }
            let btnWeb = '';
            if (ldConfig.webUrl) btnWeb = `<button class="labdesk-btn labdesk-btn-connect-web" style="padding:4px 8px;" onclick="ldConnectWeb('${rid}')"><i class="fas fa-globe"></i></button>`;
            return `<tr><td style="padding:12px; border-bottom:1px solid #f1f5f9;"><span class="labdesk-status-badge ${isOnline?'labdesk-status-online':'labdesk-status-offline'}">${isOnline?'Online':'Offline'}</span></td><td style="padding:12px; border-bottom:1px solid #f1f5f9; font-weight:500;">${nameHtml}</td><td style="padding:12px; border-bottom:1px solid #f1f5f9;">${rid}</td><td style="padding:12px; border-bottom:1px solid #f1f5f9;">${ldEscape(c.unit)}</td><td style="padding:12px; border-bottom:1px solid #f1f5f9;">${ldEscape(c.department)}</td><td style="padding:12px; border-bottom:1px solid #f1f5f9; text-align:right;"><div style="display:flex; justify-content:flex-end; gap:5px;"><button class="labdesk-btn labdesk-btn-connect" style="padding:4px 8px;" onclick="ldConnect('${rid}')"><i class="fas fa-bolt"></i></button>${btnWeb}<button class="labdesk-btn labdesk-btn-warning" style="padding:4px 8px;" onclick="ldOpenEdit(${c.id})"><i class="fas fa-pen"></i></button></div></td></tr>`;
        }).join('');
        return `<table style="width:100%; border-collapse:collapse; font-size:0.9rem;"><thead><tr style="background:#f8fafc; color:#64748b; text-align:left;"><th style="padding:12px;">Status</th><th style="padding:12px;">Nome</th><th style="padding:12px;">ID</th><th style="padding:12px;">Unidade</th><th style="padding:12px;">Depto</th><th style="padding:12px; text-align:right;">Ações</th></tr></thead><tbody>${rows}</tbody></table>`;
    }

    function ldInitCustomSelects() {
        // Passando ldSaveState no callback para salvar assim que selecionar
        ldSetupCustomSelect('csFilterUnit', ldLocations, 'Todas Unidades', (val) => { 
            ldState.filterUnit = val; 
            ldFilter(); 
            ldSaveState(); // <--- Salva ao mudar
        }, true);
        ldSetupCustomSelect('csFilterDept', ldGroups, 'Todos Departamentos', (val) => { 
            ldState.filterDept = val; 
            ldFilter(); 
            ldSaveState(); // <--- Salva ao mudar
        }, true);

        // Estes são do modal de edição, não precisa salvar estado
        ldSetupCustomSelect('csEditUnit', ldLocations, '(sem unidade)', (val) => { document.getElementById('ldEditUnitVal').value = val === 'NULL' ? '' : val; }, false);
        ldSetupCustomSelect('csEditDept', ldGroups, '(sem departamento)', (val) => { document.getElementById('ldEditDeptVal').value = val === 'NULL' ? '' : val; }, false);
    }

    function ldSetupCustomSelect(id, dataList, defaultLabel, onChange, isFilter) {
        const wrapper = document.getElementById(id); if(!wrapper) return;
        const btn = wrapper.querySelector('.ld-cs-btn'); const list = wrapper.querySelector('.ld-cs-list'); const search = wrapper.querySelector('.ld-cs-search');
        let itemsHtml = '';
        if (isFilter) { itemsHtml += `<li class="ld-cs-item selected" data-val="all">${defaultLabel}</li><li class="ld-cs-item empty-opt" data-val="NULL">Não Informado</li>`; } 
        else { itemsHtml += `<li class="ld-cs-item empty-opt" data-val="NULL">Não Informado</li>`; }
        dataList.forEach(item => { if(item) itemsHtml += `<li class="ld-cs-item" data-val="${ldEscape(item)}">${ldEscape(item)}</li>`; });
        list.innerHTML = itemsHtml;
        btn.addEventListener('click', () => {
            const wasOpen = wrapper.classList.contains('open'); document.querySelectorAll('.ld-cs-wrapper').forEach(el => el.classList.remove('open'));
            if(!wasOpen) { wrapper.classList.add('open'); search.value=''; search.focus(); list.querySelectorAll('.ld-cs-item').forEach(i=>i.style.display='block'); }
        });
        search.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase(); list.querySelectorAll('.ld-cs-item').forEach(item => { item.style.display = item.textContent.toLowerCase().includes(term) ? 'block' : 'none'; });
        });
        list.addEventListener('click', (e) => {
            if(e.target.classList.contains('ld-cs-item')) {
                const val = e.target.getAttribute('data-val'); const txt = e.target.textContent;
                btn.textContent = isFilter ? (val === 'all' ? defaultLabel : (val === 'NULL' ? 'Não Informado' : txt)) : (val === 'NULL' ? 'Não Informado' : txt);
                wrapper.classList.remove('open'); list.querySelectorAll('.ld-cs-item').forEach(i => i.classList.remove('selected')); e.target.classList.add('selected'); onChange(val);
            }
        });
        wrapper.setValue = (val) => {
            const targetVal = (!val || val === '') ? 'NULL' : val;
            const item = list.querySelector(`.ld-cs-item[data-val="${targetVal}"]`) || list.querySelector(`.ld-cs-item[data-val="NULL"]`);
            if(item) { 
                btn.textContent = item.textContent; 
                list.querySelectorAll('.ld-cs-item').forEach(i => i.classList.remove('selected')); 
                item.classList.add('selected'); 
                if (id === 'csEditUnit') document.getElementById('ldEditUnitVal').value = (targetVal==='NULL'?'':targetVal); 
                if (id === 'csEditDept') document.getElementById('ldEditDeptVal').value = (targetVal==='NULL'?'':targetVal); 
            }
        };
    }

    function ldInitEditModal() {
        const typeSel = document.getElementById('ldEditType'); ldTypes.forEach(t => { typeSel.add(new Option(t.name, t.id)); });
        document.getElementById('ldEditCancel').addEventListener('click', () => document.getElementById('ldEditModalBackdrop').style.display = 'none');
        document.getElementById('ldEditSave').addEventListener('click', ldSaveEdit);
        document.getElementById('ldEditModalBackdrop').addEventListener('click', (e) => { if(e.target.id === 'ldEditModalBackdrop') document.getElementById('ldEditModalBackdrop').style.display = 'none'; });
    }
    
    function ldRenderSidebar() {
        const nav = document.getElementById('ldSidebarNav'); nav.innerHTML = '';
        const countAll = ldState.all.length; const countUncat = ldState.all.filter(c => !c.type_id || c.type_id == 0).length;
        nav.appendChild(ldCreateNavItem('Todos', 'all', countAll, ldState.filterCategory === 'all'));
        nav.appendChild(ldCreateNavItem('Não Categorizados', 'uncategorized', countUncat, ldState.filterCategory === 'uncategorized'));
        const sep = document.createElement('div'); sep.style.margin = '10px 20px'; sep.style.borderBottom = '1px solid #e2e8f0'; nav.appendChild(sep);
        const title = document.createElement('div'); title.innerHTML = 'CATEGORIAS'; title.style.padding = '0 20px 5px'; title.style.fontSize = '0.75rem'; title.style.color = '#94a3b8'; title.style.fontWeight = 'bold'; nav.appendChild(title);
        if (ldTypes) ldTypes.forEach(t => { const count = ldState.all.filter(c => c.type_id == t.id).length; nav.appendChild(ldCreateNavItem(t.name, t.id, count, ldState.filterCategory == t.id)); });
    }
    
    function ldCreateNavItem(label, key, count, isActive) {
        const div = document.createElement('div'); div.className = 'labdesk-nav-item' + (isActive ? ' active' : ''); div.innerHTML = `<span>${label}</span><span class="labdesk-count-badge">${count}</span>`;
        div.onclick = () => { 
            ldState.filterCategory = key; 
            ldRenderSidebar(); 
            ldFilter(); 
            ldSaveState(); // <--- Salva ao clicar na categoria
        }; return div;
    }
    
    function ldFilter() {
        ldState.filtered = ldState.all.filter(c => {
            if (ldState.filterCategory !== 'all') { if (ldState.filterCategory === 'uncategorized') { if (c.type_id != 0) return false; } else { if (c.type_id != ldState.filterCategory) return false; } }
            if (ldState.filterText) { const term = ldState.filterText; const searchStr = ((c.alias||'')+' '+(c.rustdesk_name||'')+' '+(c.rustdesk_id||'')).toLowerCase(); if (!searchStr.includes(term)) return false; }
            if (ldState.filterStatus !== 'all') { const cStatus = (c.status || 'offline') === 'online' ? 'online' : 'offline'; if (cStatus !== ldState.filterStatus) return false; }
            if (ldState.filterUnit !== 'all') { const cUnit = c.unit || ''; if (ldState.filterUnit === 'NULL') { if (cUnit !== '') return false; } else { if (cUnit !== ldState.filterUnit) return false; } }
            if (ldState.filterDept !== 'all') { const cDept = c.department || ''; if (ldState.filterDept === 'NULL') { if (cDept !== '') return false; } else { if (cDept !== ldState.filterDept) return false; } }
            return true;
        }); ldRenderContent();
    }
    
    function ldRenderContent() {
        const grid = document.getElementById('ldGridView'); const table = document.getElementById('ldTableView'); const list = ldState.filtered;
        const onlineCount = list.filter(c => (c.status || 'offline') === 'online').length;
        document.getElementById('ldTotalCount').textContent = list.length; document.getElementById('ldOnlineCount').textContent = onlineCount; document.getElementById('ldOfflineCount').textContent = list.length - onlineCount;
        if (list.length === 0) { const emptyHtml = `<div class="labdesk-empty"><i class="fas fa-folder-open" style="font-size:3em;color:#cbd5e1;margin-bottom:15px;"></i><h3>Nenhum dispositivo encontrado</h3><p>Verifique os filtros ou selecione outra categoria.</p></div>`; grid.innerHTML = emptyHtml; table.innerHTML = emptyHtml; return; }
        if (ldState.view === 'grid') { grid.style.display = 'grid'; table.style.display = 'none'; grid.innerHTML = list.map(c => ldCardTemplate(c)).join(''); } else { grid.style.display = 'none'; table.style.display = 'block'; table.innerHTML = ldTableTemplate(list); }
    }
    
    function ldEscape(str) { if (!str) return ''; return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); }
    
    function ldSync() {
        if (!confirm('Sincronizar?')) return;
        const btn = document.getElementById('ldSyncBtn'); const oldHtml = btn.innerHTML; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ...'; btn.disabled = true;
        
        fetch(ldAjaxUrl + '?action=synccomputers', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(resp => { 
                if(resp.success) { 
                    alert('Sucesso!'); 
                    location.reload(); 
                } else { 
                    alert('Erro: ' + resp.message); 
                } 
            })
            .catch(() => alert('Erro na requisição'))
            .finally(() => { btn.innerHTML = oldHtml; btn.disabled = false; });
    }
    
    function ldOpenEdit(id) {
        const c = ldState.all.find(x => x.id === id); if(!c) return;
        document.getElementById('ldEditId').value = c.id; document.getElementById('ldEditGlpiId').value = c.glpi_id || ''; document.getElementById('ldEditAlias').value = c.alias || ''; document.getElementById('ldEditType').value = c.type_id || 0;
        document.getElementById('csEditUnit').setValue(c.unit); document.getElementById('csEditDept').setValue(c.department);
        document.getElementById('ldEditModalBackdrop').style.display = 'flex';
    }
    
    function ldSaveEdit() {
        const id = document.getElementById('ldEditId').value;
        const btnSave = document.getElementById('ldEditSave');
        const originalText = btnSave.textContent;
        btnSave.textContent = 'Salvando...';
        btnSave.disabled = true;

        fetch(ldAjaxUrl + '?action=update-device', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({
             id: id, glpi_id: document.getElementById('ldEditGlpiId').value, alias: document.getElementById('ldEditAlias').value, unit: document.getElementById('ldEditUnitVal').value, department: document.getElementById('ldEditDeptVal').value, type_id: document.getElementById('ldEditType').value
        })})
        .then(r => r.json())
        .then(resp => { 
            if(resp.success) { 
                location.reload(); 
            } else { 
                alert('Erro: ' + resp.message); 
                btnSave.textContent = originalText;
                btnSave.disabled = false;
            } 
        })
        .catch(err => {
            console.error(err);
            alert('Erro ao salvar.');
            btnSave.textContent = originalText;
            btnSave.disabled = false;
        });
    }
    
    function ldSetView(view) { ldState.view = view; localStorage.setItem('labdesk_view', view); ldUpdateViewButtons(); ldRenderContent(); }
    function ldUpdateViewButtons() { document.querySelectorAll('.labdesk-view-btn').forEach(b => { if(b.dataset.view === ldState.view) b.classList.add('active'); else b.classList.remove('active'); }); }
</script>

<?php Html::footer(); ?>