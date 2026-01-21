/**
 * Clientes Management - 28Facil Portal
 * Versão com UIComponents integrado
 * Issue #3 - Melhorias de UX/UI
 * Issue #2 - Autenticação via cookie httpOnly
 */

const API_BASE = window.location.origin;

let currentPassword = '';
let allClients = []; // Cache de todos os clientes
let filteredClients = []; // Clientes filtrados pela busca
let user = {}; // Usuário autenticado

// Verificar autenticação via cookie (httpOnly)
async function checkAuth() {
    try {
        const response = await fetch('/api/auth/me', {
            credentials: 'include' // Importante: enviar cookies
        });
        
        if (!response.ok) {
            // Não autenticado
            window.location.href = '/portal/index.html';
            return false;
        }
        
        const data = await response.json();
        if (data.success && data.user) {
            user = data.user;
            localStorage.setItem('user', JSON.stringify(user));
            
            // Verificar se é admin
            if (user.role !== 'admin') {
                UIComponents.toast.error('❌ Acesso negado! Apenas administradores.');
                setTimeout(() => {
                    window.location.href = '/portal/dashboard.html';
                }, 1500);
                return false;
            }
            
            return true;
        } else {
            window.location.href = '/portal/index.html';
            return false;
        }
    } catch (error) {
        console.error('Auth error:', error);
        window.location.href = '/portal/index.html';
        return false;
    }
}

// Logout
async function logout() {
    try {
        await fetch('/api/auth/logout', {
            method: 'POST',
            credentials: 'include'
        });
    } catch (error) {
        console.error('Logout error:', error);
    }
    
    localStorage.removeItem('user');
    localStorage.removeItem('theme');
    window.location.href = '/portal/index.html';
}

// Carregar clientes
async function loadClients() {
    // Mostrar loading skeleton
    UIComponents.loading.show('clients-container', 'table', 5);
    
    try {
        const response = await fetch(`${API_BASE}/api/users`, {
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            allClients = data.users;
            filteredClients = [...allClients];
            
            // Resetar paginação
            UIComponents.pagination.reset();
            
            renderClients();
            
            // Renderizar busca
            renderSearch();
            
            UIComponents.toast.success('✅ Clientes carregados!');
        } else {
            if (response.status === 401) {
                logout();
                return;
            }
            UIComponents.toast.error(data.error || '❌ Erro ao carregar clientes');
            renderEmptyState();
        }
    } catch (error) {
        console.error('Error loading clients:', error);
        UIComponents.toast.error('❌ Erro ao conectar com o servidor');
        renderEmptyState();
    }
}

// Renderizar campo de busca
function renderSearch() {
    UIComponents.search.render('search-container', 'Buscar por nome, email ou empresa...', (searchTerm) => {
        // Filtrar clientes
        filteredClients = UIComponents.search.filter(allClients, searchTerm, ['name', 'email', 'company']);
        
        // Resetar paginação ao buscar
        UIComponents.pagination.reset();
        
        // Renderizar resultados
        renderClients();
    });
}

// Renderizar tabela de clientes
function renderClients() {
    const container = document.getElementById('clients-container');
    
    if (!filteredClients || filteredClients.length === 0) {
        renderEmptyState();
        // Limpar paginação
        document.getElementById('pagination-container').innerHTML = '';
        return;
    }
    
    // Paginar dados
    const paginatedData = UIComponents.pagination.paginate(filteredClients);
    
    const tableHTML = `
        <table class="w-full">
            <thead class="bg-slate-800 border-b border-slate-700">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Cliente</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Contato</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Empresa</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Cadastro</th>
                    <th class="px-6 py-4 text-right text-xs font-medium text-slate-400 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                ${paginatedData.map(client => renderClientRow(client)).join('')}
            </tbody>
        </table>
    `;
    
    container.innerHTML = tableHTML;
    
    // Renderizar paginação
    UIComponents.pagination.render('pagination-container', filteredClients.length, () => {
        renderClients();
    });
}

function renderClientRow(client) {
    const isAdmin = client.role === 'admin';
    const statusColor = client.status === 'active' ? 'green' : 'red';
    const statusText = client.status === 'active' ? 'Ativo' : 'Inativo';
    const createdDate = new Date(client.created_at).toLocaleDateString('pt-BR');
    
    return `
        <tr class="table-row-hover">
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center">
                        <span class="text-white text-sm font-bold">${getInitials(client.name)}</span>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-white">${escapeHtml(client.name)}</div>
                        ${isAdmin ? '<span class="badge badge-info">🔑 Admin</span>' : '<span class="text-xs text-slate-500">Cliente</span>'}
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <div class="text-sm text-slate-300">${escapeHtml(client.email)}</div>
                ${client.phone ? `<div class="text-xs text-slate-500 mt-1">${escapeHtml(client.phone)}</div>` : ''}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-slate-300">${client.company ? escapeHtml(client.company) : '<span class="text-slate-600">-</span>'}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="badge badge-${client.status === 'active' ? 'success' : 'error'}">
                    ${statusText}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">
                ${createdDate}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                ${!isAdmin ? `
                    <button onclick="resetPassword(${client.id})" class="text-indigo-400 hover:text-indigo-300 mr-3 transition-colors" title="Resetar Senha">
                        🔑
                    </button>
                    <button onclick="confirmDeleteClient(${client.id}, '${escapeHtml(client.name).replace(/'/g, "\\'")}'')" class="text-red-400 hover:text-red-300 transition-colors" title="Desativar">
                        ❌
                    </button>
                ` : '<span class="text-slate-600">-</span>'}
            </td>
        </tr>
    `;
}

function renderEmptyState() {
    const container = document.getElementById('clients-container');
    
    UIComponents.emptyState.render('clients-container', {
        icon: '👥',
        title: 'Nenhum cliente encontrado',
        description: filteredClients.length === 0 && allClients.length > 0 
            ? 'Tente uma busca diferente' 
            : 'Comece cadastrando seu primeiro cliente',
        buttonText: allClients.length === 0 ? '+ Novo Cliente' : null,
        buttonAction: allClients.length === 0 ? 'showNewClientModal()' : null
    });
}

function getInitials(name) {
    return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Modal: Novo Cliente
function showNewClientModal() {
    document.getElementById('modal-new-client').classList.remove('hidden');
    document.getElementById('new-name').value = '';
    document.getElementById('new-email').value = '';
    document.getElementById('new-phone').value = '';
    document.getElementById('new-company').value = '';
}

function closeModal() {
    document.getElementById('modal-new-client').classList.add('hidden');
}

// Criar cliente
async function createClient(event) {
    event.preventDefault();
    
    const name = document.getElementById('new-name').value.trim();
    const email = document.getElementById('new-email').value.trim();
    const phone = document.getElementById('new-phone').value.trim();
    const company = document.getElementById('new-company').value.trim();
    
    const submitBtn = event.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Criando...';
    
    try {
        const response = await fetch(`${API_BASE}/api/users`, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                name,
                email,
                phone,
                company,
                generate_password: true
            })
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            closeModal();
            
            // Mostrar senha gerada com modal do UIComponents
            if (data.generated_password) {
                showPasswordModal(data.generated_password, name);
            }
            
            // Recarregar lista
            await loadClients();
            
            UIComponents.toast.success('✅ Cliente criado com sucesso!');
        } else {
            if (response.status === 401) {
                logout();
                return;
            }
            UIComponents.toast.error(data.error || '❌ Erro ao criar cliente');
        }
    } catch (error) {
        console.error('Error creating client:', error);
        UIComponents.toast.error('❌ Erro ao conectar com o servidor');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Criar Cliente';
    }
}

// Mostrar modal de senha gerada
function showPasswordModal(password, clientName) {
    currentPassword = password;
    
    UIComponents.modal.show({
        title: '🔑 Senha Gerada',
        body: `
            <div class="text-center">
                <p class="text-slate-300 mb-4">Senha gerada para <strong class="text-white">${escapeHtml(clientName)}</strong>:</p>
                <div class="bg-slate-900 border border-slate-700 rounded-lg p-4 mb-4">
                    <code class="text-2xl text-indigo-400 font-mono font-bold">${password}</code>
                </div>
                <p class="text-sm text-yellow-400 mb-4">⚠ Guarde esta senha! Ela não será mostrada novamente.</p>
                ${UIComponents.clipboard.button(password, 'Copiar Senha')}
            </div>
        `,
        confirmText: 'Fechar',
        cancelText: '',
        size: 'md'
    });
}

// Resetar senha
async function resetPassword(userId) {
    // Confirmar com modal
    UIComponents.modal.show({
        title: '⚠ Resetar Senha',
        body: '<p class="text-slate-300">Deseja resetar a senha deste cliente?<br>Uma nova senha será gerada.</p>',
        confirmText: 'Sim, Resetar',
        cancelText: 'Cancelar',
        onConfirm: async () => {
            try {
                const response = await fetch(`${API_BASE}/api/users/${userId}/reset-password`, {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ generate: true })
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    // Buscar nome do cliente
                    const client = allClients.find(c => c.id === userId);
                    showPasswordModal(data.new_password, client ? client.name : 'Cliente');
                    UIComponents.toast.success('✅ Senha resetada com sucesso!');
                } else {
                    if (response.status === 401) {
                        logout();
                        return;
                    }
                    UIComponents.toast.error(data.error || '❌ Erro ao resetar senha');
                }
            } catch (error) {
                console.error('Error resetting password:', error);
                UIComponents.toast.error('❌ Erro ao conectar com o servidor');
            }
        }
    });
}

// Confirmar e deletar (desativar) cliente
function confirmDeleteClient(userId, clientName) {
    UIComponents.modal.show({
        title: '⚠ Desativar Cliente',
        body: `
            <p class="text-slate-300 mb-2">Deseja desativar o cliente <strong class="text-white">${clientName}</strong>?</p>
            <p class="text-sm text-red-400">O cliente não poderá mais acessar o sistema.</p>
        `,
        confirmText: 'Sim, Desativar',
        cancelText: 'Cancelar',
        onConfirm: () => deleteClient(userId)
    });
}

// Deletar (desativar) cliente
async function deleteClient(userId) {
    try {
        const response = await fetch(`${API_BASE}/api/users/${userId}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            await loadClients();
            UIComponents.toast.success('✅ Cliente desativado com sucesso!');
        } else {
            if (response.status === 401) {
                logout();
                return;
            }
            UIComponents.toast.error(data.error || '❌ Erro ao desativar cliente');
        }
    } catch (error) {
        console.error('Error deleting client:', error);
        UIComponents.toast.error('❌ Erro ao conectar com o servidor');
    }
}

// Inicializar ao carregar página
window.addEventListener('DOMContentLoaded', async () => {
    const isAuth = await checkAuth();
    if (isAuth) {
        loadClients();
    }
});