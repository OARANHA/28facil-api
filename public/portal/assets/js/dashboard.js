/**
 * Dashboard - 28Facil Portal
 * Versão com UIComponents integrado
 * Issue #3 - Melhorias de UX/UI
 */

// Check authentication
const token = localStorage.getItem('token');
const user = JSON.parse(localStorage.getItem('user') || '{}');

if (!token) {
    window.location.href = '/portal/index.html';
}

// Cache
let usersCache = [];
let allLicenses = [];
let filteredLicenses = [];

// Theme Management
function initTheme() {
    const theme = localStorage.getItem('theme') || 'dark';
    document.documentElement.classList.toggle('dark', theme === 'dark');
    updateThemeIcon(theme);
}

function toggleTheme() {
    const isDark = document.documentElement.classList.contains('dark');
    const newTheme = isDark ? 'light' : 'dark';
    
    document.documentElement.classList.toggle('dark');
    localStorage.setItem('theme', newTheme);
    updateThemeIcon(newTheme);
}

function updateThemeIcon(theme) {
    const iconDark = document.getElementById('icon-dark');
    const iconLight = document.getElementById('icon-light');
    
    if (theme === 'dark') {
        iconDark?.classList.remove('hidden');
        iconLight?.classList.add('hidden');
    } else {
        iconDark?.classList.add('hidden');
        iconLight?.classList.remove('hidden');
    }
}

// Initialize theme
document.addEventListener('DOMContentLoaded', initTheme);

// Display user information
function displayUserInfo() {
    const userName = user.name || 'Usuário';
    const userEmail = user.email || '';
    
    const nameElement = document.getElementById('user-name');
    if (nameElement) nameElement.textContent = userName;
    
    const emailElement = document.getElementById('user-email');
    if (emailElement) emailElement.textContent = userEmail;
    
    const initialsElement = document.getElementById('user-initials');
    if (initialsElement) {
        const initials = userName
            .split(' ')
            .map(word => word[0])
            .join('')
            .substring(0, 2)
            .toUpperCase();
        initialsElement.textContent = initials;
    }
}

displayUserInfo();

// Show new license button for admins
if (user.role === 'admin') {
    document.getElementById('btn-new-license')?.classList.remove('hidden');
}

// Logout
function logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    localStorage.removeItem('theme');
    window.location.href = '/portal/index.html';
}

// Load users (for admin)
async function loadUsers() {
    if (user.role !== 'admin') return [];
    
    try {
        const response = await fetch('/api/users', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            usersCache = data.users || [];
            return usersCache;
        }
    } catch (error) {
        console.error('Error loading users:', error);
    }
    
    return [];
}

// Get user info by ID
function getUserNameById(userId) {
    const foundUser = usersCache.find(u => u.id == userId);
    return foundUser ? foundUser.name : 'Desconhecido';
}

function getUserEmailById(userId) {
    const foundUser = usersCache.find(u => u.id == userId);
    return foundUser ? foundUser.email : '';
}

// Load licenses
async function loadLicenses() {
    const statsContainer = document.getElementById('stats-container');
    const licensesContainer = document.getElementById('licenses-container');
    
    // Mostrar loading skeletons
    if (statsContainer) {
        statsContainer.innerHTML = `
            ${UIComponents.loading.cardSkeleton()}
            ${UIComponents.loading.cardSkeleton()}
            ${UIComponents.loading.cardSkeleton()}
        `;
    }
    
    UIComponents.loading.show('licenses-container', 'card', 6);
    
    // Load users first (for admin)
    if (user.role === 'admin') {
        await loadUsers();
    }
    
    try {
        const response = await fetch('/api/licenses', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            if (response.status === 401) {
                logout();
                return;
            }
            throw new Error('Erro ao carregar licenças');
        }
        
        const data = await response.json();
        allLicenses = data.licenses || [];
        filteredLicenses = [...allLicenses];
        
        // Update stats
        updateStats(allLicenses);
        
        // Resetar paginação
        UIComponents.pagination.reset();
        
        // Render licenses
        renderLicenses();
        
        // Render busca
        renderSearch();
        
        UIComponents.toast.success('✅ Dashboard carregado!');
    } catch (error) {
        console.error('Error loading licenses:', error);
        UIComponents.toast.error('❌ Erro ao carregar licenças');
        renderEmptyState(true);
    }
}

// Renderizar campo de busca
function renderSearch() {
    UIComponents.search.render('search-container', 'Buscar por produto ou purchase code...', (searchTerm) => {
        filteredLicenses = UIComponents.search.filter(allLicenses, searchTerm, ['product_name', 'purchase_code']);
        UIComponents.pagination.reset();
        renderLicenses();
    });
}

// Update stats with animation
function updateStats(licenses) {
    const total = licenses.length;
    const active = licenses.filter(l => l.status === 'active').length;
    const activations = licenses.reduce((sum, l) => sum + parseInt(l.active_activations || 0), 0);
    
    const statsContainer = document.getElementById('stats-container');
    if (statsContainer) {
        statsContainer.innerHTML = `
            <div class="card-hover bg-slate-800 rounded-lg p-6">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-slate-400 text-sm font-medium">Total de Licenças</p>
                    <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <p id="stat-total" class="text-3xl font-bold text-white mb-1">0</p>
                <p class="text-xs text-slate-500">Licenças cadastradas</p>
            </div>
            
            <div class="card-hover bg-slate-800 rounded-lg p-6">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-slate-400 text-sm font-medium">Licenças Ativas</p>
                    <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <p id="stat-active" class="text-3xl font-bold text-white mb-1">0</p>
                <p class="text-xs text-slate-500">Em uso no momento</p>
            </div>
            
            <div class="card-hover bg-slate-800 rounded-lg p-6">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-slate-400 text-sm font-medium">Ativações</p>
                    <svg class="w-8 h-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"></path>
                    </svg>
                </div>
                <p id="stat-activations" class="text-3xl font-bold text-white mb-1">0</p>
                <p class="text-xs text-slate-500">Total de domínios ativos</p>
            </div>
        `;
    }
    
    animateValue('stat-total', 0, total, 1000);
    animateValue('stat-active', 0, active, 1000);
    animateValue('stat-activations', 0, activations, 1000);
}

function animateValue(elementId, start, end, duration) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const startTime = performance.now();
    
    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const current = Math.floor(progress * (end - start) + start);
        
        element.textContent = current;
        
        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }
    
    requestAnimationFrame(update);
}

// Renderizar licenças
function renderLicenses() {
    const container = document.getElementById('licenses-container');
    
    if (!filteredLicenses || filteredLicenses.length === 0) {
        renderEmptyState(false);
        document.getElementById('pagination-container').innerHTML = '';
        return;
    }
    
    // Paginar dados (9 por página = 3x3 grid)
    UIComponents.pagination.itemsPerPage = 9;
    const paginatedData = UIComponents.pagination.paginate(filteredLicenses);
    
    container.innerHTML = paginatedData.map(license => renderLicense(license)).join('');
    
    // Renderizar paginação
    UIComponents.pagination.render('pagination-container', filteredLicenses.length, () => {
        renderLicenses();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

function renderEmptyState(isError) {
    const container = document.getElementById('licenses-container');
    
    if (isError) {
        UIComponents.emptyState.render('licenses-container', {
            icon: '❌',
            title: 'Erro ao carregar licenças',
            description: 'Tente recarregar a página',
            buttonText: '🔄 Recarregar',
            buttonAction: 'location.reload()'
        });
    } else {
        const isSearchResult = filteredLicenses.length === 0 && allLicenses.length > 0;
        
        UIComponents.emptyState.render('licenses-container', {
            icon: isSearchResult ? '🔍' : '📄',
            title: isSearchResult ? 'Nenhum resultado encontrado' : 'Nenhuma licença encontrada',
            description: isSearchResult 
                ? 'Tente uma busca diferente' 
                : (user.role === 'admin' 
                    ? 'Crie sua primeira licença para começar' 
                    : 'Entre em contato com o administrador'),
            buttonText: (user.role === 'admin' && !isSearchResult) ? '+ Nova Licença' : null,
            buttonAction: (user.role === 'admin' && !isSearchResult) ? 'showNewLicenseModal()' : null
        });
    }
}

// Render license card
function renderLicense(license) {
    const statusColors = {
        active: 'badge-success',
        expired: 'badge-error',
        suspended: 'badge-warning',
        revoked: 'badge-error'
    };
    
    const typeLabels = {
        lifetime: 'Vitalícia',
        annual: 'Anual',
        monthly: 'Mensal',
        trial: 'Trial'
    };
    
    const ownerName = getUserNameById(license.user_id);
    const ownerEmail = getUserEmailById(license.user_id);
    
    return `
        <div class="card-hover bg-slate-800 rounded-lg p-6 animate-fade-in">
            <div class="flex justify-between items-start mb-4">
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-white mb-1">${license.product_name}</h3>
                    ${user.role === 'admin' && ownerName !== 'Desconhecido' ? `
                        <div class="flex items-center space-x-2 mt-2">
                            <div class="w-6 h-6 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center">
                                <span class="text-white text-xs font-bold">${ownerName.split(' ').map(w => w[0]).join('').substring(0,2).toUpperCase()}</span>
                            </div>
                            <div>
                                <p class="text-xs text-indigo-400 font-medium">${ownerName}</p>
                            </div>
                        </div>
                    ` : ''}
                </div>
                <span class="badge ${statusColors[license.status]}">
                    ${license.status.toUpperCase()}
                </span>
            </div>
            
            <div class="space-y-2 mb-4 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Purchase Code:</span>
                    <div class="flex items-center space-x-2">
                        <code class="font-mono text-indigo-400 text-xs">${license.purchase_code.substring(0, 12)}...</code>
                        ${UIComponents.clipboard.button(license.purchase_code, '')}
                    </div>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Tipo:</span>
                    <span class="font-medium text-white">${typeLabels[license.license_type]}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Ativações:</span>
                    <span class="font-medium text-white">${license.active_activations || 0}/${license.max_activations}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Criada:</span>
                    <span class="text-slate-300 text-xs">${new Date(license.created_at).toLocaleDateString('pt-BR')}</span>
                </div>
            </div>
            
            <button onclick="viewLicenseDetails(${license.id})" 
                    class="w-full py-2 px-4 bg-indigo-600/50 hover:bg-indigo-600 text-white font-medium rounded-lg transition-all">
                Ver Detalhes
            </button>
        </div>
    `;
}

// Modal: Nova Licença
async function showNewLicenseModal() {
    const modal = document.getElementById('modal-new-license');
    const select = document.getElementById('new-user-id');
    
    modal.classList.remove('hidden');
    
    if (usersCache.length === 0) {
        await loadUsers();
    }
    
    select.innerHTML = '<option value="">Selecione um cliente...</option>' +
        usersCache
            .filter(u => u.role === 'customer')
            .map(u => `<option value="${u.id}">${u.name} (${u.email})</option>`)
            .join('');
}

function closeModal() {
    document.getElementById('modal-new-license').classList.add('hidden');
}

// Criar licença
async function createLicense(e) {
    e.preventDefault();
    
    const userId = document.getElementById('new-user-id').value;
    const productName = document.getElementById('new-product').value;
    const licenseType = document.getElementById('new-type').value;
    const maxActivations = document.getElementById('new-activations').value;
    const submitBtn = e.target.querySelector('button[type="submit"]');
    
    if (!userId) {
        UIComponents.toast.warning('⚠ Selecione um cliente!');
        return;
    }
    
    submitBtn.disabled = true;
    submitBtn.textContent = 'Criando...';
    
    try {
        const response = await fetch('/api/licenses', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                user_id: parseInt(userId),
                product_name: productName,
                license_type: licenseType,
                max_activations: parseInt(maxActivations)
            })
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            closeModal();
            await loadLicenses();
            
            const userName = getUserNameById(userId);
            UIComponents.toast.success(`✅ Licença criada para ${userName}!`);
        } else {
            UIComponents.toast.error(data.error || '❌ Erro ao criar licença');
        }
    } catch (error) {
        UIComponents.toast.error('❌ Erro de conexão. Tente novamente.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Criar';
    }
}

// Ver detalhes da licença
async function viewLicenseDetails(licenseId) {
    UIComponents.modal.show({
        title: '📄 Detalhes da Licença',
        body: '<p class="text-center text-slate-300">Carregando...</p>',
        confirmText: 'Fechar',
        cancelText: '',
        size: 'lg'
    });
    
    try {
        const response = await fetch(`/api/licenses/${licenseId}`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            const license = data.license;
            const activations = license.activations || [];
            const ownerName = getUserNameById(license.user_id);
            const ownerEmail = getUserEmailById(license.user_id);
            
            const modalBody = `
                <div class="bg-slate-900 rounded-lg p-4 space-y-3 mb-4">
                    <div class="grid grid-cols-2 gap-4">
                        ${user.role === 'admin' ? `
                        <div class="col-span-2 pb-3 border-b border-slate-700">
                            <p class="text-xs text-slate-400 mb-2">Cliente</p>
                            <div class="flex items-center space-x-2">
                                <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center">
                                    <span class="text-white text-xs font-bold">${ownerName.split(' ').map(w => w[0]).join('').substring(0,2).toUpperCase()}</span>
                                </div>
                                <div>
                                    <p class="font-medium text-white">${ownerName}</p>
                                    <p class="text-xs text-slate-400">${ownerEmail}</p>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        <div class="col-span-2">
                            <p class="text-xs text-slate-400 mb-1">Purchase Code</p>
                            <div class="flex items-center justify-between bg-slate-800 rounded px-3 py-2">
                                <code class="font-mono text-sm font-semibold text-indigo-400">${license.purchase_code}</code>
                                ${UIComponents.clipboard.button(license.purchase_code, 'Copiar')}
                            </div>
                        </div>
                        <div class="col-span-2">
                            <p class="text-xs text-slate-400 mb-1">UUID</p>
                            <div class="flex items-center justify-between bg-slate-800 rounded px-3 py-2">
                                <code class="font-mono text-xs text-slate-300">${license.uuid}</code>
                                ${UIComponents.clipboard.button(license.uuid, 'Copiar')}
                            </div>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 mb-1">Produto</p>
                            <p class="font-medium text-white">${license.product_name}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 mb-1">Tipo</p>
                            <p class="font-medium text-white">${license.license_type}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 mb-1">Status</p>
                            <span class="badge badge-${license.status === 'active' ? 'success' : 'error'}">${license.status}</span>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 mb-1">Ativações</p>
                            <p class="font-medium text-white">${activations.filter(a => a.status === 'active').length}/${license.max_activations}</p>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h4 class="font-semibold text-white mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                        </svg>
                        Ativações (${activations.length})
                    </h4>
                    ${activations.length === 0 ? 
                        '<p class="text-slate-400 text-sm text-center py-4">Nenhuma ativação ainda</p>' :
                        '<div class="space-y-2 max-h-64 overflow-y-auto custom-scrollbar">' +
                        activations.map(a => `
                            <div class="bg-slate-800 border border-slate-700 rounded-lg p-3">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1">
                                        <p class="font-medium text-white text-sm">${a.domain}</p>
                                        <p class="text-xs text-slate-400">${a.installation_name || 'N/A'}</p>
                                    </div>
                                    <span class="badge ${a.status === 'active' ? 'badge-success' : 'badge-error'} text-xs">
                                        ${a.status}
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs text-slate-400">
                                    <div>
                                        <span class="font-medium">Ativada:</span> ${new Date(a.activated_at).toLocaleDateString('pt-BR')}
                                    </div>
                                    <div>
                                        <span class="font-medium">Último check:</span> ${a.last_check_at ? new Date(a.last_check_at).toLocaleDateString('pt-BR') : 'Nunca'}
                                    </div>
                                </div>
                            </div>
                        `).join('') +
                        '</div>'
                    }
                </div>
            `;
            
            // Atualizar modal (fechar e reabrir com conteúdo)
            const modals = document.querySelectorAll('[id^="modal-"]');
            modals.forEach(m => m.remove());
            
            UIComponents.modal.show({
                title: '📄 Detalhes da Licença',
                body: modalBody,
                confirmText: 'Fechar',
                cancelText: '',
                size: 'lg'
            });
        } else {
            UIComponents.toast.error(data.error || '❌ Erro ao carregar detalhes');
        }
    } catch (error) {
        UIComponents.toast.error('❌ Erro de conexão');
    }
}

// Load licenses on page load
loadLicenses();