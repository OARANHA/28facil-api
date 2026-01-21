// Check authentication
const token = localStorage.getItem('token');
const user = JSON.parse(localStorage.getItem('user') || '{}');

if (!token) {
    window.location.href = '/portal/index.html';
}

// Display user name
document.getElementById('user-name').textContent = user.name || 'Usuário';

// Show new license button for admins
if (user.role === 'admin') {
    document.getElementById('btn-new-license').classList.remove('hidden');
}

// Logout
function logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
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
                <div class="text-center py-12 bg-white rounded-lg shadow">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p class="mt-4 text-gray-500">Nenhuma licença encontrada</p>
                    ${user.role === 'admin' ? '<button onclick="showNewLicenseModal()" class="mt-4 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md">Criar Primeira Licença</button>' : ''}
                </div>
            `;
        } else {
            container.innerHTML = licenses.map(license => renderLicense(license)).join('');
        }
    } catch (error) {
        container.innerHTML = `
            <div class="text-center py-12 bg-white rounded-lg shadow">
                <p class="text-red-500">${error.message}</p>
            </div>
        `;
    }
}

// Update stats
function updateStats(licenses) {
    const total = licenses.length;
    const active = licenses.filter(l => l.status === 'active').length;
    const activations = licenses.reduce((sum, l) => sum + parseInt(l.active_activations || 0), 0);
    
    document.getElementById('stat-total').textContent = total;
    document.getElementById('stat-active').textContent = active;
    document.getElementById('stat-activations').textContent = activations;
}

// Render license card
function renderLicense(license) {
    const statusColors = {
        active: 'bg-green-100 text-green-800',
        expired: 'bg-red-100 text-red-800',
        suspended: 'bg-yellow-100 text-yellow-800',
        revoked: 'bg-gray-100 text-gray-800'
    };
    
    const typeLabels = {
        lifetime: 'Vitalícia',
        annual: 'Anual',
        monthly: 'Mensal',
        trial: 'Trial'
    };
    
    return `
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">${license.product_name}</h3>
                    <p class="text-sm text-gray-500">Criada em ${new Date(license.created_at).toLocaleDateString('pt-BR')}</p>
                </div>
                <span class="px-3 py-1 text-xs font-semibold rounded-full ${statusColors[license.status]}">
                    ${license.status.toUpperCase()}
                </span>
            </div>
            
            <div class="space-y-2 mb-4">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Purchase Code:</span>
                    <code class="font-mono text-indigo-600 font-semibold">${license.purchase_code}</code>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Tipo:</span>
                    <span class="font-medium">${typeLabels[license.license_type]}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Ativações:</span>
                    <span class="font-medium">${license.active_activations || 0} / ${license.max_activations}</span>
                </div>
            </div>
            
            <button onclick="viewLicenseDetails(${license.id})" class="w-full py-2 px-4 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-medium rounded-md transition-colors">
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
            alert('Licença criada com sucesso!');
        } else {
            alert(data.error || 'Erro ao criar licença');
        }
    } catch (error) {
        alert('Erro de conexão. Tente novamente.');
    }
}

// View license details
async function viewLicenseDetails(licenseId) {
    const modal = document.getElementById('modal-license-details');
    const content = document.getElementById('license-details-content');
    
    modal.classList.remove('hidden');
    content.innerHTML = '<p class="text-center text-gray-500">Carregando...</p>';
    
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
                <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Purchase Code</p>
                            <p class="font-mono text-sm font-semibold text-indigo-600">${license.purchase_code}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">UUID</p>
                            <p class="font-mono text-xs text-gray-700">${license.uuid}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Produto</p>
                            <p class="font-medium">${license.product_name}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Tipo</p>
                            <p class="font-medium">${license.license_type}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Status</p>
                            <p class="font-medium">${license.status}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Ativações</p>
                            <p class="font-medium">${activations.filter(a => a.status === 'active').length} / ${license.max_activations}</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <h4 class="font-semibold text-gray-900 mb-3">Ativações (${activations.length})</h4>
                    ${activations.length === 0 ? 
                        '<p class="text-gray-500 text-sm">Nenhuma ativação ainda</p>' :
                        activations.map(a => `
                            <div class="bg-white border rounded-lg p-4 mb-3">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <p class="font-medium text-gray-900">${a.domain}</p>
                                        <p class="text-xs text-gray-500">${a.installation_name || 'N/A'}</p>
                                    </div>
                                    <span class="px-2 py-1 text-xs font-semibold rounded ${a.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                                        ${a.status}
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs text-gray-600">
                                    <div>
                                        <span class="font-medium">Ativada:</span> ${new Date(a.activated_at).toLocaleDateString('pt-BR')}
                                    </div>
                                    <div>
                                        <span class="font-medium">Último check:</span> ${a.last_check_at ? new Date(a.last_check_at).toLocaleDateString('pt-BR') : 'Nunca'}
                                    </div>
                                    <div class="col-span-2">
                                        <span class="font-medium">License Key:</span> <code class="font-mono">${a.license_key.substring(0, 20)}...</code>
                                    </div>
                                </div>
                            </div>
                        `).join('')
                    }
                </div>
            `;
        } else {
            content.innerHTML = `<p class="text-red-500">${data.error || 'Erro ao carregar detalhes'}</p>`;
        }
    } catch (error) {
        content.innerHTML = `<p class="text-red-500">Erro de conexão</p>`;
    }
}

function closeLicenseDetails() {
    document.getElementById('modal-license-details').classList.add('hidden');
}

// Load licenses on page load
loadLicenses();