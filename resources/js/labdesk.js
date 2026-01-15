/**
 * LabDesk - Main JavaScript
 */

// API Configuration
const API_URL = '/plugins/labdesk/front/ajax.php';

// Application State
const app = {
    computers: [],
    groups: [],
    units: [],
    departments: [],
    selectedView: 'grid',
    activeFilters: {
        status: 'all',
        unit: 'all-units',
        department: 'all-depts',
        group: null,
        search: ''
    },
    selectedComputers: new Set(),
    editingComputer: null
};

/**
 * Utility Functions
 */

function formatLastOnline(dateString) {
    if (!dateString) return 'Desconhecido';
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);

    if (minutes < 1) return 'Agora';
    if (minutes < 60) return `${minutes}m atrás`;
    if (hours < 24) return `${hours}h atrás`;
    return `${days}d atrás`;
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

function editComputer(computerId) {
    const computer = app.computers.find(c => c.id === computerId);
    if (!computer) return;

    app.editingComputer = computer;
    
    const idInput = document.getElementById('editCompId');
    const nameInput = document.getElementById('editCompName');
    const aliasInput = document.getElementById('editCompAlias');
    const unitSelect = document.getElementById('editCompUnit');
    const deptSelect = document.getElementById('editCompDept');

    if (idInput) idInput.value = computer.id;
    if (nameInput) nameInput.value = computer.rustdesk_name;
    if (aliasInput) aliasInput.value = computer.alias || '';
    if (unitSelect) unitSelect.value = computer.unit || '';
    if (deptSelect) deptSelect.value = computer.department || '';

    openModal('editComputerModal');
}

function deleteComputer(computerId) {
    if (!confirm('Tem certeza que deseja deletar este computador?')) return;
    
    app.computers = app.computers.filter(c => c.id !== computerId);
    app.groups.forEach(group => {
        group.computers = (group.computers || []).filter(cid => cid !== computerId);
    });
    render();
}

async function removeComputerFromGroup(computerId, groupName) {
    const group = app.groups.find(g => g.name === groupName);
    if (!group) return;

    try {
        const response = await fetch(`${API_URL}?action=remove-computer-from-group`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-GLPI-CSRF-Token': getCsrfToken()
            },
            body: JSON.stringify({
                group_id: group.id,
                computer_id: computerId
            })
        });

        if (response.ok) {
            const computer = app.computers.find(c => c.id === computerId);
            if (computer) {
                computer.groups = (computer.groups || []).filter(g => g !== groupName);
            }
            render();
        }
    } catch (error) {
        console.error('Erro ao remover computador do grupo:', error);
    }
}

function filterComputers() {
    return app.computers.filter(comp => {
        const statusMatch = app.activeFilters.status === 'all' || comp.status === app.activeFilters.status;
        const unitMatch = app.activeFilters.unit === 'all-units' || comp.unit === app.activeFilters.unit;
        const deptMatch = app.activeFilters.department === 'all-depts' || comp.department === app.activeFilters.department;
        const groupMatch = !app.activeFilters.group || (comp.groups || []).includes(app.activeFilters.group);
        const searchMatch = app.activeFilters.search === '' ||
            comp.rustdesk_name.toLowerCase().includes(app.activeFilters.search.toLowerCase()) ||
            (comp.alias || '').toLowerCase().includes(app.activeFilters.search.toLowerCase());

        return statusMatch && unitMatch && deptMatch && groupMatch && searchMatch;
    });
}

function getCsrfToken() {
    const meta = document.querySelector('meta[name="glpi_csrf_token"]');
    return meta ? meta.content : '';
}

function render() {
    const gridView = document.getElementById('gridView');
    const tableView = document.getElementById('tableView');

    if (app.selectedView === 'grid') {
        if (gridView) gridView.style.display = 'grid';
        if (tableView) tableView.style.display = 'none';
    } else {
        if (gridView) gridView.style.display = 'none';
        if (tableView) tableView.style.display = 'block';
    }

    // Update selected count
    const selectedCountEl = document.getElementById('selectedCount');
    if (selectedCountEl) {
        selectedCountEl.textContent = `${app.selectedComputers.size} selecionados`;
    }
}

/**
 * Event Listeners
 */

document.addEventListener('DOMContentLoaded', function() {
    // Search input
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            app.activeFilters.search = e.target.value;
            render();
        });
    }

    // View toggle
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            app.selectedView = e.target.dataset.view;
            render();
        });
    });

    // Filter items
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('filter-item')) {
            const filter = e.target.dataset.filter;
            
            if (filter && filter.startsWith('group-')) {
                const groupId = filter.replace('group-', '');
                const group = app.groups.find(g => g.id === parseInt(groupId));
                app.activeFilters.group = group ? group.name : null;
            } else if (filter === 'all') {
                app.activeFilters.status = 'all';
            } else if (filter === 'all-units') {
                app.activeFilters.unit = 'all-units';
            } else if (filter === 'all-depts') {
                app.activeFilters.department = 'all-depts';
            } else if (filter && ['online', 'offline'].includes(filter)) {
                app.activeFilters.status = filter;
            } else if (filter && app.units && app.units.includes(filter)) {
                app.activeFilters.unit = filter;
            } else if (filter && app.departments && app.departments.includes(filter)) {
                app.activeFilters.department = filter;
            }
            
            render();
        }
    });

    // New Group button
    const btnNewGroup = document.getElementById('btnNewGroup');
    if (btnNewGroup) {
        btnNewGroup.addEventListener('click', () => {
            openModal('newGroupModal');
        });
    }

    // Refresh button
    const btnRefresh = document.getElementById('btnRefresh');
    if (btnRefresh) {
        btnRefresh.addEventListener('click', async () => {
            btnRefresh.style.animation = 'spin 1s linear';
            
            try {
                const response = await fetch(`${API_URL}?action=sync-devices`, {
                    method: 'GET'
                });
                if (response.ok) {
                    location.reload();
                }
            } catch (error) {
                console.error('Erro ao sincronizar:', error);
                alert('Erro ao sincronizar com RustDesk');
            }
            
            setTimeout(() => {
                btnRefresh.style.animation = '';
            }, 1000);
        });
    }

    // Select All button
    const btnSelectAll = document.getElementById('btnSelectAll');
    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', () => {
            filterComputers().forEach(comp => {
                app.selectedComputers.add(comp.id);
            });
            render();
        });
    }

    // Edit Computer Form
    const editForm = document.getElementById('editComputerForm');
    if (editForm) {
        editForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const data = {
                id: app.editingComputer.id,
                alias: document.getElementById('editCompAlias').value,
                unit: document.getElementById('editCompUnit').value,
                department: document.getElementById('editCompDept').value
            };

            try {
                const response = await fetch(`${API_URL}?action=update-device`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-GLPI-CSRF-Token': getCsrfToken()
                    },
                    body: JSON.stringify(data)
                });

                if (response.ok) {
                    closeModal('editComputerModal');
                    const result = await response.json();
                    if (result.success) {
                        alert('Computador atualizado com sucesso!');
                        location.reload();
                    }
                }
            } catch (error) {
                console.error('Erro ao atualizar computador:', error);
                alert('Erro ao salvar alterações');
            }
        });
    }

    // New Group Form
    const newGroupForm = document.getElementById('newGroupForm');
    if (newGroupForm) {
        newGroupForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const selectedComputers = Array.from(document.querySelectorAll('#groupComputersCheckboxes input:checked'))
                .map(cb => parseInt(cb.value));

            const data = {
                name: document.getElementById('groupName').value,
                description: document.getElementById('groupDesc').value,
                computers: selectedComputers
            };

            try {
                const response = await fetch(`${API_URL}?action=create-group`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-GLPI-CSRF-Token': getCsrfToken()
                    },
                    body: JSON.stringify(data)
                });

                if (response.ok) {
                    const result = await response.json();
                    if (result.success) {
                        closeModal('newGroupModal');
                        alert('Grupo criado com sucesso!');
                        location.reload();
                    }
                }
            } catch (error) {
                console.error('Erro ao criar grupo:', error);
                alert('Erro ao criar grupo');
            }
        });
    }

    // Close modals on background click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
    });

    render();
});

// Add spin animation
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);
