<?php

include ('../../../inc/includes.php');

Session::checkLoginUser();

// --- LÓGICA DE BACKEND ---

$config = PluginLabdeskConfig::getConfig();
$validation = PluginLabdeskConfig::validateConfig($config);

$computers = [];
$all_locations = [];
$all_groups_root = [];
$all_types = [];

if ($validation['valid']) {
    global $DB;

    // 1. Buscar Computadores do Plugin (LabDesk)
    $computers = PluginLabdeskComputer::getAll();

    // 2. Buscar Localizações
    $iterator_loc = $DB->request(['FROM' => 'glpi_locations', 'ORDER' => 'name']);
    foreach ($iterator_loc as $row) {
        $all_locations[] = $row['name'];
    }

    // 3. Buscar Grupos (Apenas Pai - Level 2 conforme solicitado)
    $iterator_grp = $DB->request([
       'FROM'   => 'glpi_groups',
       'WHERE'  => ['level' => 2], // Sua correção aplicada
       'FIELDS' => ['id', 'name'],
       'ORDER'  => 'name ASC',
    ]);
    foreach ($iterator_grp as $row) {
        $all_groups_root[] = $row['name'];
    }

    // 4. Buscar Categorias (Tipos de Computadores)
    $iterator_types = $DB->request(['FROM' => 'glpi_computertypes', 'ORDER' => 'name']);
    foreach ($iterator_types as $row) {
        $all_types[] = [
            'id'   => $row['id'],
            'name' => $row['name']
        ];
    }
}

// 5. ENRIQUECIMENTO DE DADOS (CRUZAMENTO COM GLPI)
// Aqui fazemos a mágica: procuramos se o computador do plugin existe no GLPI pelo nome.
// Se existir, pegamos o ID e o Tipo.
foreach ($computers as &$comp) {
    // Valores padrão
    $comp['glpi_id'] = null;
    $comp['type_id'] = 0;
    
    // Tenta encontrar pelo nome do host
    $searchName = $comp['rustdesk_name'] ?? '';
    if (!empty($searchName)) {
        // Busca o PRIMEIRO registro ativo e não deletado
        $iterator = $DB->request([
            'SELECT' => ['id', 'computertypes_id'],
            'FROM'   => 'glpi_computers',
            'WHERE'  => [
                'name'        => $searchName,
                'is_deleted'  => 0,
                'is_template' => 0
            ],
            'START'  => 0,
            'LIMIT'  => 1
        ]);
        
        if ($iterator->count() > 0) {
            $data = $iterator->current();
            $comp['glpi_id'] = $data['id']; // ID do computador no GLPI
            $comp['type_id'] = $data['computertypes_id']; // Categoria
        }
    }
}
unset($comp); // Limpa referência

// --- FRONTEND ---
Html::header('LabDesk', $_SERVER['PHP_SELF'], "assets", "labdesk");
?>

<link rel="stylesheet" type="text/css" href="<?php echo Plugin::getWebDir('labdesk'); ?>/resources/css/labdesk.css">

<div class="labdesk-wrapper">
    
    <div class="labdesk-sidebar">
        <div class="labdesk-sidebar-header">
            <div class="labdesk-sidebar-title">
                <i class="fas fa-network-wired" style="color: var(--ld-primary);"></i> LabDesk
            </div>
            <div style="font-size: 0.8em; color: #94a3b8; margin-top: 5px;">Gestão de Acesso</div>
        </div>

        <nav id="ldSidebarNav">
            </nav>

        <div style="margin-top: auto; padding: 20px;">
            <a href="<?php echo $CFG_GLPI['root_doc']; ?>/front/computertype.php" class="labdesk-btn labdesk-btn-sm" style="background: #f1f5f9; color: #64748b; justify-content: flex-start;">
                <i class="fas fa-plus-circle"></i> Categorias
            </a>
            <button id="ldSyncBtn" class="labdesk-btn labdesk-btn-sm" style="background: #f1f5f9; color: #64748b; margin-top: 10px; justify-content: flex-start;">
                <i class="fas fa-sync"></i> Sincronizar
            </button>
        </div>
    </div>

    <div class="labdesk-main">
        
        <div class="labdesk-toolbar">
            <div class="labdesk-search-group">
                <i class="fas fa-search labdesk-search-icon"></i>
                <input type="text" id="ldSearch" class="labdesk-input" placeholder="Buscar por nome, ID ou alias...">
            </div>

            <select id="ldFilterUnit" class="labdesk-select">
                <option value="">Todas Unidades</option>
            </select>

            <select id="ldFilterDept" class="labdesk-select">
                <option value="">Todos Departamentos</option>
            </select>

            <select id="ldFilterStatus" class="labdesk-select">
                <option value="all">Todos Status</option>
                <option value="online">🟢 Online</option>
                <option value="offline">⚪ Offline</option>
            </select>

            <div class="labdesk-btn-group">
                <button class="labdesk-view-btn active" data-view="grid" onclick="ldSetView('grid')">
                    <i class="fas fa-th-large"></i>
                </button>
                <button class="labdesk-view-btn" data-view="list" onclick="ldSetView('list')">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>

        <div class="labdesk-content-scroll">
            <?php if (!$validation['valid']): ?>
                <div style="padding: 20px; background: #fee2e2; border-radius: 8px; color: #991b1b;">
                    <strong>Erro:</strong> <?php echo implode('<br>', $validation['errors']); ?>
                </div>
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
        <input type="hidden" id="ldEditId">
        <input type="hidden" id="ldEditGlpiId"> 
        
        <div class="labdesk-form-group">
            <label class="labdesk-form-label">Alias (Apelido)</label>
            <input type="text" id="ldEditAlias" class="labdesk-input">
        </div>

        <div class="labdesk-form-group">
            <label class="labdesk-form-label">Categoria (Tipo de Computador)</label>
            <select id="ldEditType" class="labdesk-input">
                <option value="0">-- Não categorizado --</option>
            </select>
            <small style="color:#64748b; font-size:0.8em; display:block; margin-top:4px;">
                Alterar isso atualizará o computador no inventário do GLPI se vinculado.
            </small>
        </div>

        <div class="labdesk-form-group">
            <label class="labdesk-form-label">Unidade</label>
            <select id="ldEditUnit" class="labdesk-input">
                <option value="">(sem unidade)</option>
            </select>
        </div>

        <div class="labdesk-form-group">
            <label class="labdesk-form-label">Departamento</label>
            <select id="ldEditDept" class="labdesk-input">
                <option value="">(sem departamento)</option>
            </select>
        </div>

        <div class="labdesk-modal-footer">
            <button id="ldEditCancel" class="labdesk-btn" style="background:#e2e8f0; color:#475569;">Cancelar</button>
            <button id="ldEditSave" class="labdesk-btn labdesk-btn-connect">Salvar</button>
        </div>
    </div>
</div>

<script>
    // DADOS
    const ldComputers = <?php echo json_encode($computers, JSON_UNESCAPED_UNICODE); ?>;
    const ldLocations = <?php echo json_encode($all_locations, JSON_UNESCAPED_UNICODE); ?>;
    const ldGroups    = <?php echo json_encode($all_groups_root, JSON_UNESCAPED_UNICODE); ?>;
    const ldTypes     = <?php echo json_encode($all_types, JSON_UNESCAPED_UNICODE); ?>;
    const ldRootUrl   = "<?php $CFG_GLPI["root_doc"]; ?>";
    const ldAjaxUrl   = "<?php echo Plugin::getWebDir('labdesk'); ?>/front/ajax.php";
    const ldBaseUrl   = "<?php echo Html::clean($config['rustdeskurl'] ?? 'http://labdesk.labchecap.com.br:21114'); ?>";

    const ldState = {
        all: ldComputers || [],
        filtered: [],
        filterText: '',
        filterUnit: '',
        filterDept: '',
        filterStatus: 'all',
        filterCategory: 'all',
        view: localStorage.getItem('labdesk_view') || 'grid'
    };

    document.addEventListener('DOMContentLoaded', () => {
        ldInitFilters();
        ldRenderSidebar();
        ldFilter(); 
        ldUpdateViewButtons();
        
        document.getElementById('ldSearch').addEventListener('input', (e) => {
            ldState.filterText = e.target.value.toLowerCase();
            ldFilter();
        });
        document.getElementById('ldFilterUnit').addEventListener('change', (e) => {
            ldState.filterUnit = e.target.value;
            ldFilter();
        });
        document.getElementById('ldFilterDept').addEventListener('change', (e) => {
            ldState.filterDept = e.target.value;
            ldFilter();
        });
        document.getElementById('ldFilterStatus').addEventListener('change', (e) => {
            ldState.filterStatus = e.target.value;
            ldFilter();
        });

        document.getElementById('ldSyncBtn').addEventListener('click', ldSync);
        document.getElementById('ldEditCancel').addEventListener('click', ldCloseEdit);
        document.getElementById('ldEditSave').addEventListener('click', ldSaveEdit);
        document.getElementById('ldEditModalBackdrop').addEventListener('click', (e) => {
           if (e.target.id === 'ldEditModalBackdrop') ldCloseEdit();
        });
    });

    function ldInitFilters() {
        const unitSel = document.getElementById('ldFilterUnit');
        const deptSel = document.getElementById('ldFilterDept');
        const editUnit = document.getElementById('ldEditUnit');
        const editDept = document.getElementById('ldEditDept');
        const editType = document.getElementById('ldEditType');

        if(ldLocations) ldLocations.forEach(l => {
            unitSel.add(new Option(l, l));
            editUnit.add(new Option(l, l));
        });
        if(ldGroups) ldGroups.forEach(g => {
            deptSel.add(new Option(g, g));
            editDept.add(new Option(g, g));
        });
        if(ldTypes) ldTypes.forEach(t => {
            editType.add(new Option(t.name, t.id));
        });
    }

    function ldRenderSidebar() {
        const nav = document.getElementById('ldSidebarNav');
        nav.innerHTML = '';
        const countAll = ldState.all.length;
        const countUncat = ldState.all.filter(c => !c.type_id || c.type_id == 0).length;

        nav.appendChild(ldCreateNavItem('Todos', 'all', countAll, ldState.filterCategory === 'all'));
        nav.appendChild(ldCreateNavItem('Não Categorizados', 'uncategorized', countUncat, ldState.filterCategory === 'uncategorized'));

        const sep = document.createElement('div');
        sep.style.margin = '10px 20px';
        sep.style.borderBottom = '1px solid #e2e8f0';
        nav.appendChild(sep);
        
        const title = document.createElement('div');
        title.innerHTML = 'CATEGORIAS';
        title.style.padding = '0 20px 5px';
        title.style.fontSize = '0.75rem';
        title.style.color = '#94a3b8';
        title.style.fontWeight = 'bold';
        nav.appendChild(title);

        if (ldTypes) {
            ldTypes.forEach(t => {
                const count = ldState.all.filter(c => c.type_id == t.id).length;
                nav.appendChild(ldCreateNavItem(t.name, t.id, count, ldState.filterCategory == t.id));
            });
        }
    }

    function ldCreateNavItem(label, key, count, isActive) {
        const div = document.createElement('div');
        div.className = 'labdesk-nav-item' + (isActive ? ' active' : '');
        div.innerHTML = `<span>${label}</span><span class="labdesk-count-badge">${count}</span>`;
        div.onclick = () => {
            ldState.filterCategory = key;
            ldRenderSidebar(); // Re-render para atualizar active
            ldFilter();
        };
        return div;
    }

    function ldFilter() {
        ldState.filtered = ldState.all.filter(c => {
            if (ldState.filterCategory !== 'all') {
                if (ldState.filterCategory === 'uncategorized') {
                    if (c.type_id != 0) return false;
                } else {
                    if (c.type_id != ldState.filterCategory) return false;
                }
            }
            if (ldState.filterText) {
                const term = ldState.filterText;
                const searchStr = ((c.alias||'')+' '+(c.rustdesk_name||'')+' '+(c.rustdesk_id||'')).toLowerCase();
                if (!searchStr.includes(term)) return false;
            }
            if (ldState.filterStatus !== 'all') {
                const cStatus = (c.status || 'offline') === 'online' ? 'online' : 'offline';
                if (cStatus !== ldState.filterStatus) return false;
            }
            if (ldState.filterUnit && c.unit !== ldState.filterUnit) return false;
            if (ldState.filterDept && c.department !== ldState.filterDept) return false;
            return true;
        });
        ldRenderContent();
    }

    function ldRenderContent() {
        const grid = document.getElementById('ldGridView');
        const table = document.getElementById('ldTableView');
        const list = ldState.filtered;

        const onlineCount = list.filter(c => (c.status || 'offline') === 'online').length;
        document.getElementById('ldTotalCount').textContent = list.length;
        document.getElementById('ldOnlineCount').textContent = onlineCount;
        document.getElementById('ldOfflineCount').textContent = list.length - onlineCount;

        if (list.length === 0) {
            const emptyHtml = `<div class="labdesk-empty"><i class="fas fa-folder-open" style="font-size:3em;color:#cbd5e1;margin-bottom:15px;"></i><h3>Nenhum dispositivo encontrado</h3><p>Verifique os filtros ou selecione outra categoria.</p></div>`;
            grid.innerHTML = emptyHtml;
            table.innerHTML = emptyHtml;
            return;
        }

        if (ldState.view === 'grid') {
            grid.style.display = 'grid';
            table.style.display = 'none';
            grid.innerHTML = list.map(c => ldCardTemplate(c)).join('');
        } else {
            grid.style.display = 'none';
            table.style.display = 'block';
            table.innerHTML = ldTableTemplate(list);
        }
    }

    function ldEscape(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function ldCardTemplate(c) {
        const rid = ldEscape(c.rustdesk_id);
        const name = ldEscape(c.alias || c.rustdesk_name || 'Sem nome');
        const unit = ldEscape(c.unit || '-');
        const dept = ldEscape(c.department || '-');
        const isOnline = (c.status || 'offline') === 'online';
        
        // Link para GLPI se existir ID
        let nameHtml = name;
        if (c.glpi_id) {
            const url = ldRootUrl + '/front/computer.form.php?id=' + c.glpi_id;
            nameHtml = `<a href="${url}" target="_blank" class="labdesk-glpi-link" title="Ver no GLPI"><i class="fas fa-link" style="font-size:0.8em;margin-right:4px;"></i>${name}</a>`;
        }
        
        return `
        <div class="labdesk-card">
            <div class="labdesk-card-header">
                <div class="labdesk-card-title">${nameHtml}</div>
                <div class="labdesk-card-subtitle">ID: ${rid}</div>
            </div>
            <div class="labdesk-card-body">
                <div class="labdesk-info-row"><span class="labdesk-info-label">Unidade</span><span class="labdesk-info-value">${unit}</span></div>
                <div class="labdesk-info-row"><span class="labdesk-info-label">Depto</span><span class="labdesk-info-value">${dept}</span></div>
                <div class="labdesk-info-row"><span class="labdesk-info-label">Status</span><span class="labdesk-status-badge ${isOnline?'labdesk-status-online':'labdesk-status-offline'}">${isOnline?'Online':'Offline'}</span></div>
            </div>
            <div class="labdesk-card-footer">
                <button class="labdesk-btn labdesk-btn-connect" onclick="ldConnect('${rid}')"><i class="fas fa-bolt"></i> App</button>
                <button class="labdesk-btn labdesk-btn-connect-web" onclick="ldConnectWeb('${rid}')"><i class="fas fa-globe"></i> Web</button>
                <button class="labdesk-btn labdesk-btn-warning" onclick="ldOpenEdit(${c.id})" style="flex:0;"><i class="fas fa-pen"></i></button>
            </div>
        </div>`;
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

            return `
            <tr>
                <td style="padding:12px; border-bottom:1px solid #f1f5f9;"><span class="labdesk-status-badge ${isOnline?'labdesk-status-online':'labdesk-status-offline'}">${isOnline?'Online':'Offline'}</span></td>
                <td style="padding:12px; border-bottom:1px solid #f1f5f9; font-weight:500;">${nameHtml}</td>
                <td style="padding:12px; border-bottom:1px solid #f1f5f9;">${rid}</td>
                <td style="padding:12px; border-bottom:1px solid #f1f5f9;">${ldEscape(c.unit)}</td>
                <td style="padding:12px; border-bottom:1px solid #f1f5f9;">${ldEscape(c.department)}</td>
                <td style="padding:12px; border-bottom:1px solid #f1f5f9; text-align:right;">
                    <div style="display:flex; justify-content:flex-end; gap:5px;">
                        <button class="labdesk-btn labdesk-btn-connect" style="padding:4px 8px;" onclick="ldConnect('${rid}')"><i class="fas fa-bolt"></i></button>
                        <button class="labdesk-btn labdesk-btn-connect-web" style="padding:4px 8px;" onclick="ldConnectWeb('${rid}')"><i class="fas fa-globe"></i></button>
                        <button class="labdesk-btn labdesk-btn-warning" style="padding:4px 8px;" onclick="ldOpenEdit(${c.id})"><i class="fas fa-pen"></i></button>
                    </div>
                </td>
            </tr>`;
        }).join('');

        return `<table style="width:100%; border-collapse:collapse; font-size:0.9rem;"><thead><tr style="background:#f8fafc; color:#64748b; text-align:left;"><th style="padding:12px;">Status</th><th style="padding:12px;">Nome</th><th style="padding:12px;">ID</th><th style="padding:12px;">Unidade</th><th style="padding:12px;">Depto</th><th style="padding:12px; text-align:right;">Ações</th></tr></thead><tbody>${rows}</tbody></table>`;
    }

    function ldSetView(view) {
        ldState.view = view;
        localStorage.setItem('labdesk_view', view);
        ldUpdateViewButtons();
        ldRenderContent();
    }
    function ldUpdateViewButtons() {
        document.querySelectorAll('.labdesk-view-btn').forEach(b => {
            if(b.dataset.view === ldState.view) b.classList.add('active');
            else b.classList.remove('active');
        });
    }
    function ldConnect(rid) {
        if (!rid) return;
        window.location.href = 'rustdesk://' + rid + '?password=' + encodeURIComponent('labRD@1983');
    }
    function ldConnectWeb(rid) {
        if (!rid) return;
        const url = ldBaseUrl.replace(/\/+$/, '') + '/webclient2/#/' + encodeURIComponent(rid) + '?password=' + encodeURIComponent('labRD@1983');
        window.open(url, '_blank');
    }
    function ldSync() {
        if (!confirm('Iniciar sincronização?')) return;
        const btn = document.getElementById('ldSyncBtn');
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ...';
        btn.disabled = true;
        fetch('<?php echo Plugin::getWebDir("labdesk"); ?>/front/config.php?action=synccomputers', { credentials: 'same-origin' }).then(() => { alert('Sincronização iniciada. A página será recarregada.'); location.reload(); }).catch(() => alert('Erro na sincronização')).finally(() => { btn.innerHTML = oldHtml; btn.disabled = false; });
    }

    // EDIT FUNCS
    function ldOpenEdit(id) {
        const c = ldState.all.find(x => x.id === id);
        if(!c) return;
        
        document.getElementById('ldEditId').value = c.id;
        document.getElementById('ldEditGlpiId').value = c.glpi_id || ''; // Guarda o ID do GLPI se houver
        document.getElementById('ldEditAlias').value = c.alias || '';
        document.getElementById('ldEditUnit').value = c.unit || '';
        document.getElementById('ldEditDept').value = c.department || '';
        document.getElementById('ldEditType').value = c.type_id || 0; // Preenche a categoria
        
        document.getElementById('ldEditModalBackdrop').style.display = 'flex';
    }

    function ldCloseEdit() {
        document.getElementById('ldEditModalBackdrop').style.display = 'none';
    }

    function ldSaveEdit() {
        const id = document.getElementById('ldEditId').value;
        const glpiId = document.getElementById('ldEditGlpiId').value;
        const alias = document.getElementById('ldEditAlias').value;
        const unit = document.getElementById('ldEditUnit').value;
        const dept = document.getElementById('ldEditDept').value;
        const typeId = document.getElementById('ldEditType').value;

        fetch(ldAjaxUrl + '?action=update-device', {
             method: 'POST',
             headers: {'Content-Type': 'application/json'},
             body: JSON.stringify({
                 id: id, 
                 alias: alias, 
                 unit: unit, 
                 department: dept,
                 type_id: typeId, // Novo campo
                 glpi_id: glpiId  // Novo campo
             })
        })
        .then(r => r.json())
        .then(resp => {
            if(resp.success) {
                const ix = ldState.all.findIndex(x => x.id == id);
                if(ix >= 0) {
                    ldState.all[ix].alias = alias;
                    ldState.all[ix].unit = unit;
                    ldState.all[ix].department = dept;
                    ldState.all[ix].type_id = typeId; // Atualiza local
                }
                ldRenderSidebar(); // Atualiza contadores
                ldFilter();
                ldCloseEdit();
            } else {
                alert('Erro ao salvar');
            }
        });
    }
</script>

<?php Html::footer(); ?>