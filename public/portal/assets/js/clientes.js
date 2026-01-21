// Clientes Management - 28Facil Portal
const API_BASE = window.location.origin;

let currentPassword = '';

// Verificar autenticação
function checkAuth() {
    const token = localStorage.getItem('token');
    if (!token) {
        window.location.href = '/portal/';
        return null;
    }
    return token;
}

// Logout
function logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = '/portal/';
}

// Carregar clientes
async function loadClients() {
    const token = checkAuth();
    if (!token) return;
    
    try {
        const response = await fetch(`${API_BASE}/users`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            renderClients(data.users);
        } else {
            showError(data.error || 'Erro ao carregar clientes');
        }
    } catch (error) {
        console.error('Error loading clients:', error);
        showError('Erro ao conectar com o servidor');
    }
}

// Renderizar tabela de clientes
function renderClients(clients) {
    const container = document.getElementById('clients-container');
    
    if (!clients || clients.length === 0) {
        container.innerHTML = `
            <div class="p-12 text-center">
                <div class="w-16 h-16 bg-slate-700 rounded-full mx-auto mb-4 flex items-center justify-center">
                    <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
                <p class="text-slate-400 text-lg">Nenhum cliente cadastrado</p>
                <p class="text-slate-500 text-sm mt-2">Clique em "Novo Cliente" para começar</p>
            </div>
        `;
        return;
    }
    
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
                ${clients.map(client => renderClientRow(client)).join('')}
            </tbody>
        </table>
    `;
    
    container.innerHTML = tableHTML;
}

function renderClientRow(client) {
    const isAdmin = client.role === 'admin';
    const statusColor = client.status === 'active' ? 'green' : 'red';
    const statusText = client.status === 'active' ? 'Ativo' : 'Inativo';
    const createdDate = new Date(client.created_at).toLocaleDateString('pt-BR');
    
    return `
        <tr class="hover:bg-slate-800 transition-colors">
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center">
                        <span class="text-white text-sm font-bold">${getInitials(client.name)}</span>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-white">${escapeHtml(client.name)}</div>
                        ${isAdmin ? '<span class="text-xs text-indigo-400 font-semibold">🔑 Admin</span>' : '<span class="text-xs text-slate-500">Cliente</span>'}
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
                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-${statusColor}-900 text-${statusColor}-300">
                    ${statusText}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">
                ${createdDate}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                ${!isAdmin ? `
                    <button onclick="resetPassword(${client.id})" class="text-indigo-400 hover:text-indigo-300 mr-3" title="Resetar Senha">
                        🔑
                    </button>
                    <button onclick="deleteClient(${client.id}, '${escapeHtml(client.name)}')" class="text-red-400 hover:text-red-300" title="Desativar">
                        ❌
                    </button>
                ` : '<span class="text-slate-600">-</span>'}
            </td>
        </tr>
    `;
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
    
    const token = checkAuth();
    if (!token) return;
    
    const name = document.getElementById('new-name').value.trim();
    const email = document.getElementById('new-email').value.trim();
    const phone = document.getElementById('new-phone').value.trim();
    const company = document.getElementById('new-company').value.trim();
    
    try {
        const response = await fetch(`${API_BASE}/users`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
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
            
            // Mostrar senha gerada
            if (data.generated_password) {
                currentPassword = data.generated_password;
                document.getElementById('generated-password').textContent = data.generated_password;
                document.getElementById('modal-password').classList.remove('hidden');
            }
            
            // Recarregar lista
            await loadClients();
            
            showSuccess('Cliente criado com sucesso!');
        } else {
            showError(data.error || 'Erro ao criar cliente');
        }
    } catch (error) {
        console.error('Error creating client:', error);
        showError('Erro ao conectar com o servidor');
    }
}

// Resetar senha
async function resetPassword(userId) {
    if (!confirm('Deseja resetar a senha deste cliente? Uma nova senha será gerada.')) {
        return;
    }
    
    const token = checkAuth();
    if (!token) return;
    
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
            showSuccess('Senha resetada com sucesso!');
        } else {
            showError(data.error || 'Erro ao resetar senha');
        }
    } catch (error) {
        console.error('Error resetting password:', error);
        showError('Erro ao conectar com o servidor');
    }
}

// Deletar (desativar) cliente
async function deleteClient(userId, clientName) {
    if (!confirm(`Deseja desativar o cliente "${clientName}"?\n\nO cliente não poderá mais acessar o sistema.`)) {
        return;
    }
    
    const token = checkAuth();
    if (!token) return;
    
    try {
        const response = await fetch(`${API_BASE}/users/${userId}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            await loadClients();
            showSuccess('Cliente desativado com sucesso!');
        } else {
            showError(data.error || 'Erro ao desativar cliente');
        }
    } catch (error) {
        console.error('Error deleting client:', error);
        showError('Erro ao conectar com o servidor');
    }
}

// Copiar senha
function copyPassword() {
    navigator.clipboard.writeText(currentPassword).then(() => {
        showSuccess('Senha copiada!');
    }).catch(err => {
        showError('Erro ao copiar senha');
    });
}

// Fechar modal de senha
function closePasswordModal() {
    document.getElementById('modal-password').classList.add('hidden');
    currentPassword = '';
}

// Notificações
function showSuccess(message) {
    // Implementar toast notification se quiser
    alert(message);
}

function showError(message) {
    alert('Erro: ' + message);
}

// Inicializar ao carregar página
window.addEventListener('DOMContentLoaded', () => {
    loadClients();
});