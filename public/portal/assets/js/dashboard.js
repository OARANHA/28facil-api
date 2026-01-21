// Check authentication
const token = localStorage.getItem('token');
const user = JSON.parse(localStorage.getItem('user') || '{}');

if (!token) {
    window.location.href = '/portal/index.html';
}

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
    
    // Set name
    const nameElement = document.getElementById('user-name');
    if (nameElement) {
        nameElement.textContent = userName;
    }
    
    // Set email
    const emailElement = document.getElementById('user-email');
    if (emailElement) {
        emailElement.textContent = userEmail;
    }
    
    // Set initials
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

// Call on page load
displayUserInfo();

// Show new license button for admins
if (user.role === 'admin') {
    document.getElementById('btn-new-license').classList.remove('hidden');
}

// Logout
function logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    localStorage.removeItem('theme');
    window.location.href = '/portal/index.html';
}

// Load licenses
async function loadLicenses() {
    const container = document.getElementById('licenses-container');
    
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
        const licenses = data.licenses || [];
        
        // Update stats
        updateStats(licenses);
        
        // Render licenses
        if (licenses.length === 0) {
            container.innerHTML = `
                <div class="glass-effect rounded-lg shadow-xl p-12 text-center">
                    <svg class="mx-auto h-16 w-16 text-slate-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p class="text-slate-300 text-lg mb-4">Nenhuma licença encontrada</p>
                    ${user.role === 'admin' ? '<button onclick="showNewLicenseModal()" class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold rounded-lg transition-all transform hover:scale-105">Criar Primeira Licença</button>' : '<p class="text-slate-400 text-sm">Entre em contato com o administrador para obter uma licença</p>'}
                </div>
            `;
        } else {
            container.innerHTML = licenses.map(license => renderLicense(license)).join('');
        }
    } catch (error) {
        container.innerHTML = `
            <div class="glass-effect rounded-lg shadow-xl p-12 text-center">
                <svg class="mx-auto h-16 w-16 text-red-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-red-300">${error.message}</p>
            </div>
        `;
    }
}

// Update stats with animation
function updateStats(licenses) {
    const total = licenses.length;
    const active = licenses.filter(l => l.status === 'active').length;
    const activations = licenses.reduce((sum, l) => sum + parseInt(l.active_activations || 0), 0);
    
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

// Render license card
function renderLicense(license) {
    const statusColors = {
        active: 'bg-green-500/20 text-green-300 border-green-500',
        expired: 'bg-red-500/20 text-red-300 border-red-500',
        suspended: 'bg-yellow-500/20 text-yellow-300 border-yellow-500',
        revoked: 'bg-gray-500/20 text-gray-300 border-gray-500'
    };
    
    const typeLabels = {
        lifetime: 'Vitalícia',
        annual: 'Anual',
        monthly: 'Mensal',
        trial: 'Trial'
    };
    
    return `
        <div class="glass-effect rounded-lg shadow-xl p-6 hover:scale-105 transition-all duration-300 animate-slide-in">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-white">${license.product_name}</h3>
                    <p class="text-sm text-slate-400">Criada em ${new Date(license.created_at).toLocaleDateString('pt-BR')}</p>
                </div>
                <span class="px-3 py-1 text-xs font-semibold rounded-lg border ${statusColors[license.status]}">
                    ${license.status.toUpperCase()}
                </span>
            </div>
            
            <div class="space-y-3 mb-4">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Purchase Code:</span>
                    <code class="font-mono text-indigo-400 font-semibold">${license.purchase_code}</code>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Tipo:</span>
                    <span class="font-medium text-white">${typeLabels[license.license_type]}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Ativações:</span>
                    <span class="font-medium text-white">${license.active_activations || 0} / ${license.max_activations}</span>
                </div>
            </div>
            
            <button onclick="viewLicenseDetails(${license.id})" 
                    class="w-full py-3 px-4 bg-indigo-600/50 hover:bg-indigo-600 text-white font-medium rounded-lg transition-all">
                Ver Detalhes
            </button>
        </div>
    `;
}

// Modal functions
function showNewLicenseModal() {
    document.getElementById('modal-new-license').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('modal-new-license').classList.add('hidden');
}

// Create new license
async function createLicense(e) {
    e.preventDefault();
    
    const productName = document.getElementById('new-product').value;
    const licenseType = document.getElementById('new-type').value;
    const maxActivations = document.getElementById('new-activations').value;
    const submitBtn = e.target.querySelector('button[type="submit"]');
    
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
                product_name: productName,
                license_type: licenseType,
                max_activations: parseInt(maxActivations)
            })
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            closeModal();
            loadLicenses();
            
            // Show success notification
            showNotification('Licença criada com sucesso!', 'success');
        } else {
            showNotification(data.error || 'Erro ao criar licença', 'error');
        }
    } catch (error) {
        showNotification('Erro de conexão. Tente novamente.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Criar';
    }
}

// Show notification
function showNotification(message, type = 'info') {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500'
    };
    
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-fade-in`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// View license details
async function viewLicenseDetails(licenseId) {
    const modal = document.getElementById('modal-license-details');
    const content = document.getElementById('license-details-content');
    
    modal.classList.remove('hidden');
    content.innerHTML = '<p class="text-center text-slate-300">Carregando...</p>';
    
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
            
            content.innerHTML = `
                <div class="bg-slate-800 rounded-lg p-4 space-y-3">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-slate-400 mb-1">Purchase Code</p>
                            <p class="font-mono text-sm font-semibold text-indigo-400">${license.purchase_code}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 mb-1">UUID</p>
                            <p class="font-mono text-xs text-slate-300">${license.uuid}</p>
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
                            <p class="font-medium text-white">${license.status}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 mb-1">Ativações</p>
                            <p class="font-medium text-white">${activations.filter(a => a.status === 'active').length} / ${license.max_activations}</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <h4 class="font-semibold text-white mb-3">Ativações (${activations.length})</h4>
                    ${activations.length === 0 ? 
                        '<p class="text-slate-400 text-sm">Nenhuma ativação ainda</p>' :
                        activations.map(a => `
                            <div class="bg-slate-800 border border-slate-700 rounded-lg p-4 mb-3">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <p class="font-medium text-white">${a.domain}</p>
                                        <p class="text-xs text-slate-400">${a.installation_name || 'N/A'}</p>
                                    </div>
                                    <span class="px-2 py-1 text-xs font-semibold rounded ${a.status === 'active' ? 'bg-green-500/20 text-green-300' : 'bg-gray-500/20 text-gray-300'}">
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
                                    <div class="col-span-2">
                                        <span class="font-medium">License Key:</span> <code class="font-mono text-indigo-400">${a.license_key.substring(0, 20)}...</code>
                                    </div>
                                </div>
                            </div>
                        `).join('')
                    }
                </div>
            `;
        } else {
            content.innerHTML = `<p class="text-red-300">${data.error || 'Erro ao carregar detalhes'}</p>`;
        }
    } catch (error) {
        content.innerHTML = `<p class="text-red-300">Erro de conexão</p>`;
    }
}

function closeLicenseDetails() {
    document.getElementById('modal-license-details').classList.add('hidden');
}

// Load licenses on page load
loadLicenses();