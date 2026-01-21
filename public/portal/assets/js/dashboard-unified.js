// ====================================
// 28Facil - Dashboard Unificado (SPA)
// Tudo em uma página, sem recarregar
// ====================================

const API_BASE = window.location.origin;
const token = localStorage.getItem('token');
const user = JSON.parse(localStorage.getItem('user') || '{}');

let usersCache = [];
let currentPassword = '';

// ====================================
// AUTH & INIT
// ====================================

if (!token) {
    window.location.href = '/portal/index.html';
}

function logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = '/portal/index.html';
}

// Display user info
function displayUserInfo() {
    const userName = user.name || 'Usuário';
    document.getElementById('user-name').textContent = userName;
    document.getElementById('user-email').textContent = user.email || '';
    
    const initials = userName.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
    document.getElementById('user-initials').textContent = initials;
}

// Show admin features
function initAdminFeatures() {
    if (user.role === 'admin') {
        document.getElementById('tab-clients').classList.remove('hidden');
        document.getElementById('btn-new-license').classList.remove('hidden');
    }
}

// ====================================
// TAB SYSTEM
// ====================================

function switchTab(tabName) {
    // Hide all contents
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    
    // Remove active class from all tabs
    document.querySelectorAll('[id^="tab-"]').forEach(el => {
        el.classList.remove('tab-active');
        el.classList.add('tab-inactive');
    });
    
    // Show selected content
    document.getElementById('content-' + tabName).classList.remove('hidden');
    
    // Add active class to selected tab
    document.getElementById('tab-' + tabName).classList.remove('tab-inactive');
    document.getElementById('tab-' + tabName).classList.add('tab-active');
    
    // Load data based on tab
    if (tabName === 'clients') {
        loadClients();
    } else if (tabName === 'licenses') {
        loadLicenses();
    }
}

// ====================================
// CLIENTS TAB
// ====================================

async function loadClients() {
    const container = document.getElementById('clients-container');
    container.innerHTML = '<div class="p-12 text-center"><p class="text-slate-300">Carregando...</p></div>';
    
    try {
        const response = await fetch(`${API_BASE}/users`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            usersCache = data.users || [];
            renderClients(data.users);
        } else {
            container.innerHTML = `<div class="p-12 text-center"><p class="text-red-300">${data.error}</p></div>`;
        }
    } catch (error) {
        container.innerHTML = '<div class="p-12 text-center"><p class="text-red-300">Erro ao carregar</p></div>';
    }
}

function renderClients(clients) {
    const container = document.getElementById('clients-container');
    
    if (!clients || clients.length === 0) {
        container.innerHTML = `
            <div class="p-12 text-center">
                <p class="text-slate-400 text-lg">Nenhum cliente cadastrado</p>
                <button onclick="showNewClientModal()" class="mt-4 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg">
                    Criar Primeiro Cliente
                </button>
            </div>
        `;
        return;
    }
    
    container.innerHTML = `
        <table class="w-full">
            <thead class="bg-slate-800 border-b border-slate-700">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-slate-400 uppercase">Cliente</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-slate-400 uppercase">Contato</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-slate-400 uppercase">Empresa</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-slate-400 uppercase">Status</th>
                    <th class="px-6 py-4 text-right text-xs font-medium text-slate-400 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                ${clients.map(c => renderClientRow(c)).join('')}
            </tbody>
        </table>
    `;
}

function renderClientRow(client) {
    const isAdmin = client.role === 'admin';
    const statusColor = client.status === 'active' ? 'green' : 'red';
    
    return `
        <tr class="hover:bg-slate-800 transition-colors">
            <td class="px-6 py-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center">
                        <span class="text-white text-sm font-bold">${client.name.split(' ').map(w => w[0]).join('').substring(0,2).toUpperCase()}</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-white">${escapeHtml(client.name)}</p>
                        ${isAdmin ? '<span class="text-xs text-indigo-400">🔑 Admin</span>' : '<span class="text-xs text-slate-500">Cliente</span>'}
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <p class="text-sm text-slate-300">${escapeHtml(client.email)}</p>
                ${client.phone ? `<p class="text-xs text-slate-500">${escapeHtml(client.phone)}</p>` : ''}
            </td>
            <td class="px-6 py-4">
                <p class="text-sm text-slate-300">${client.company ? escapeHtml(client.company) : '-'}</p>
            </td>
            <td class="px-6 py-4">
                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-${statusColor}-900 text-${statusColor}-300">
                    ${client.status === 'active' ? 'Ativo' : 'Inativo'}
                </span>
            </td>
            <td class="px-6 py-4 text-right">
                ${!isAdmin ? `
                    <button onclick="resetPassword(${client.id})" class="text-indigo-400 hover:text-indigo-300 mr-3" title="Resetar Senha">🔑</button>
                ` : ''}
            </td>
        </tr>
    `;
}

function showNewClientModal() {
    document.getElementById('modal-new-client').classList.remove('hidden');
    document.getElementById('new-client-name').value = '';
    document.getElementById('new-client-email').value = '';
    document.getElementById('new-client-phone').value = '';
    document.getElementById('new-client-company').value = '';
}

function closeClientModal() {
    document.getElementById('modal-new-client').classList.add('hidden');
}

async function createClient(e) {
    e.preventDefault();
    
    const data = {
        name: document.getElementById('new-client-name').value.trim(),
        email: document.getElementById('new-client-email').value.trim(),
        phone: document.getElementById('new-client-phone').value.trim(),
        company: document.getElementById('new-client-company').value.trim(),
        generate_password: true
    };
    
    try {
        const response = await fetch(`${API_BASE}/users`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            closeClientModal();
            
            // Show password
            if (result.generated_password) {
                currentPassword = result.generated_password;
                document.getElementById('generated-password').textContent = result.generated_password;
                document.getElementById('modal-password').classList.remove('hidden');
            }
            
            loadClients();
            showNotification('Cliente criado com sucesso!', 'success');
        } else {
            showNotification(result.error || 'Erro ao criar cliente', 'error');
        }
    } catch (error) {
        showNotification('Erro de conexão', 'error');
    }
}

async function resetPassword(userId) {
    if (!confirm('Resetar senha deste cliente?')) return;
    
    try {
        const response = await fetch(`${API_BASE}/users/${userId}/reset-password`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({ generate: true })
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            currentPassword = data.new_password;
            document.getElementById('generated-password').textContent = data.new_password;
            document.getElementById('modal-password').classList.remove('hidden');
        } else {
            showNotification(data.error || 'Erro', 'error');
        }
    } catch (error) {
        showNotification('Erro de conexão', 'error');
    }
}

function copyPassword() {
    navigator.clipboard.writeText(currentPassword).then(() => {
        showNotification('Senha copiada!', 'success');
    });
}

function closePasswordModal() {
    document.getElementById('modal-password').classList.add('hidden');
    currentPassword = '';
}

// ====================================
// LICENSES TAB
// ====================================

async function loadLicenses() {
    const container = document.getElementById('licenses-container');
    container.innerHTML = '<div class="glass-effect rounded-lg p-12 text-center"><p class="text-slate-300">Carregando...</p></div>';
    
    // Load users first (for admin)
    if (user.role === 'admin' && usersCache.length === 0) {
        await loadUsers();
    }
    
    try {
        const response = await fetch(`${API_BASE}/api/licenses`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            updateStats(data.licenses);
            renderLicenses(data.licenses);
        } else {
            container.innerHTML = `<div class="glass-effect rounded-lg p-12 text-center"><p class="text-red-300">${data.error}</p></div>`;
        }
    } catch (error) {
        container.innerHTML = '<div class="glass-effect rounded-lg p-12 text-center"><p class="text-red-300">Erro ao carregar</p></div>';
    }
}

async function loadUsers() {
    try {
        const response = await fetch(`${API_BASE}/users`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await response.json();
        if (response.ok) usersCache = data.users || [];
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

function updateStats(licenses) {
    const total = licenses.length;
    const active = licenses.filter(l => l.status === 'active').length;
    const activations = licenses.reduce((sum, l) => sum + parseInt(l.active_activations || 0), 0);
    
    document.getElementById('stat-total').textContent = total;
    document.getElementById('stat-active').textContent = active;
    document.getElementById('stat-activations').textContent = activations;
}

function renderLicenses(licenses) {
    const container = document.getElementById('licenses-container');
    
    if (!licenses || licenses.length === 0) {
        container.innerHTML = `
            <div class="glass-effect rounded-lg p-12 text-center">
                <p class="text-slate-400 text-lg mb-4">Nenhuma licença encontrada</p>
                ${user.role === 'admin' ? '<button onclick="showNewLicenseModal()" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg">Criar Primeira Licença</button>' : ''}
            </div>
        `;
        return;
    }
    
    container.innerHTML = licenses.map(l => renderLicense(l)).join('');
}

function renderLicense(license) {
    const statusColors = {
        active: 'bg-green-500/20 text-green-300 border-green-500',
        expired: 'bg-red-500/20 text-red-300 border-red-500'
    };
    
    const ownerName = getUserNameById(license.user_id);
    
    return `
        <div class="glass-effect rounded-lg shadow-xl p-6 hover:scale-105 transition-all animate-slide-in">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-white">${license.product_name}</h3>
                    ${user.role === 'admin' ? `<p class="text-sm text-indigo-400 mt-1">${ownerName}</p>` : ''}
                </div>
                <span class="px-3 py-1 text-xs font-semibold rounded-lg border ${statusColors[license.status]}">
                    ${license.status.toUpperCase()}
                </span>
            </div>
            <div class="space-y-2 mb-4">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Purchase Code:</span>
                    <code class="font-mono text-indigo-400 font-semibold">${license.purchase_code}</code>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Ativações:</span>
                    <span class="text-white font-medium">${license.active_activations || 0} / ${license.max_activations}</span>
                </div>
            </div>
            <button onclick="viewLicenseDetails(${license.id})" class="w-full py-3 bg-indigo-600/50 hover:bg-indigo-600 text-white rounded-lg transition-all">
                Ver Detalhes
            </button>
        </div>
    `;
}

function getUserNameById(userId) {
    const u = usersCache.find(u => u.id == userId);
    return u ? u.name : 'Desconhecido';
}

async function showNewLicenseModal() {
    document.getElementById('modal-new-license').classList.remove('hidden');
    
    if (usersCache.length === 0) await loadUsers();
    
    const select = document.getElementById('new-user-id');
    select.innerHTML = '<option value="">Selecione um cliente...</option>' +
        usersCache.filter(u => u.role === 'customer')
            .map(u => `<option value="${u.id}">${u.name} (${u.email})</option>`)
            .join('');
}

function closeLicenseModal() {
    document.getElementById('modal-new-license').classList.add('hidden');
}

async function createLicense(e) {
    e.preventDefault();
    
    const data = {
        user_id: parseInt(document.getElementById('new-user-id').value),
        product_name: document.getElementById('new-product').value,
        license_type: document.getElementById('new-type').value,
        max_activations: parseInt(document.getElementById('new-activations').value)
    };
    
    if (!data.user_id) {
        showNotification('Selecione um cliente!', 'error');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/api/licenses`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            closeLicenseModal();
            loadLicenses();
            showNotification('Licença criada com sucesso!', 'success');
        } else {
            showNotification(result.error || 'Erro ao criar licença', 'error');
        }
    } catch (error) {
        showNotification('Erro de conexão', 'error');
    }
}

async function viewLicenseDetails(licenseId) {
    document.getElementById('modal-license-details').classList.remove('hidden');
    document.getElementById('license-details-content').innerHTML = '<p class="text-center text-slate-300">Carregando...</p>';
    
    try {
        const response = await fetch(`${API_BASE}/api/licenses/${licenseId}`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            const license = data.license;
            const activations = license.activations || [];
            
            document.getElementById('license-details-content').innerHTML = `
                <div class="bg-slate-800 rounded-lg p-4 space-y-3">
                    <div class="grid grid-cols-2 gap-4">
                        <div><p class="text-xs text-slate-400">Purchase Code</p><p class="font-mono text-sm text-indigo-400">${license.purchase_code}</p></div>
                        <div><p class="text-xs text-slate-400">UUID</p><p class="font-mono text-xs text-slate-300">${license.uuid}</p></div>
                        <div><p class="text-xs text-slate-400">Produto</p><p class="text-white">${license.product_name}</p></div>
                        <div><p class="text-xs text-slate-400">Status</p><p class="text-white">${license.status}</p></div>
                        <div><p class="text-xs text-slate-400">Ativações</p><p class="text-white">${activations.filter(a => a.status === 'active').length} / ${license.max_activations}</p></div>
                    </div>
                </div>
                <h4 class="font-semibold text-white mt-6 mb-3">Ativações (${activations.length})</h4>
                ${activations.length === 0 ? '<p class="text-slate-400 text-sm">Nenhuma ativação</p>' :
                    activations.map(a => `
                        <div class="bg-slate-800 rounded-lg p-4 mb-3">
                            <div class="flex justify-between mb-2">
                                <p class="font-medium text-white">${a.domain}</p>
                                <span class="px-2 py-1 text-xs rounded ${a.status === 'active' ? 'bg-green-500/20 text-green-300' : 'bg-gray-500/20 text-gray-300'}">${a.status}</span>
                            </div>
                            <p class="text-xs text-slate-400">Ativada: ${new Date(a.activated_at).toLocaleDateString('pt-BR')}</p>
                        </div>
                    `).join('')
                }
            `;
        }
    } catch (error) {
        document.getElementById('license-details-content').innerHTML = '<p class="text-red-300">Erro ao carregar</p>';
    }
}

function closeLicenseDetails() {
    document.getElementById('modal-license-details').classList.add('hidden');
}

// ====================================
// UTILS
// ====================================

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNotification(message, type = 'info') {
    const colors = { success: 'bg-green-500', error: 'bg-red-500', info: 'bg-blue-500' };
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50`;
    notification.textContent = message;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}

// ====================================
// INIT
// ====================================

displayUserInfo();
initAdminFeatures();
loadLicenses();
