/**
 * 28Facil Portal - UI Components Module
 * Sistema modular de componentes reutilizáveis
 * Issue #3 - Melhorias de UX/UI
 */

const UIComponents = {
    // ========================================
    // PAGINAÇÃO
    // ========================================
    
    pagination: {
        currentPage: 1,
        itemsPerPage: 10,
        totalPages: 1,
        
        /**
         * Renderizar controles de paginação
         */
        render(containerId, totalItems, onPageChange) {
            this.totalPages = Math.ceil(totalItems / this.itemsPerPage);
            const container = document.getElementById(containerId);
            
            if (!container || this.totalPages <= 1) {
                if (container) container.innerHTML = '';
                return;
            }
            
            const prevDisabled = this.currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-slate-700 cursor-pointer';
            const nextDisabled = this.currentPage === this.totalPages ? 'opacity-50 cursor-not-allowed' : 'hover:bg-slate-700 cursor-pointer';
            
            container.innerHTML = `
                <div class="flex items-center justify-between mt-6 px-4 py-3 bg-slate-800 rounded-lg">
                    <button 
                        onclick="UIComponents.pagination.goToPage(${this.currentPage - 1}, ${totalItems}, '${containerId}', arguments[0])" 
                        class="px-4 py-2 bg-slate-700 text-white rounded-lg transition-all ${prevDisabled}"
                        ${this.currentPage === 1 ? 'disabled' : ''}>
                        ← Anterior
                    </button>
                    
                    <div class="flex items-center space-x-2">
                        <span class="text-slate-300">Página</span>
                        <span class="px-3 py-1 bg-indigo-600 text-white rounded-lg font-semibold">${this.currentPage}</span>
                        <span class="text-slate-300">de ${this.totalPages}</span>
                    </div>
                    
                    <button 
                        onclick="UIComponents.pagination.goToPage(${this.currentPage + 1}, ${totalItems}, '${containerId}', arguments[0])" 
                        class="px-4 py-2 bg-slate-700 text-white rounded-lg transition-all ${nextDisabled}"
                        ${this.currentPage === this.totalPages ? 'disabled' : ''}>
                        Próxima →
                    </button>
                </div>
            `;
        },
        
        /**
         * Ir para página específica
         */
        goToPage(page, totalItems, containerId, callback) {
            if (page < 1 || page > this.totalPages) return;
            this.currentPage = page;
            if (callback && typeof callback === 'function') {
                callback(page);
            }
            this.render(containerId, totalItems, callback);
        },
        
        /**
         * Paginar array de dados
         */
        paginate(data) {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;
            return data.slice(start, end);
        },
        
        /**
         * Reset paginação
         */
        reset() {
            this.currentPage = 1;
        }
    },
    
    // ========================================
    // SISTEMA DE TOASTS
    // ========================================
    
    toast: {
        /**
         * Mostrar toast de notificação
         */
        show(message, type = 'info', duration = 3000) {
            const toast = document.createElement('div');
            const colors = {
                success: 'bg-green-600',
                error: 'bg-red-600',
                warning: 'bg-yellow-600',
                info: 'bg-indigo-600'
            };
            
            const icons = {
                success: '✓',
                error: '✕',
                warning: '⚠',
                info: 'ℹ'
            };
            
            toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-2xl ${colors[type]} text-white transform transition-all duration-300 z-50 flex items-center space-x-2 animate-slide-in`;
            toast.innerHTML = `
                <span class="text-xl font-bold">${icons[type]}</span>
                <span>${message}</span>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.transform = 'translateX(400px)';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, duration);
        },
        
        success(message, duration) {
            this.show(message, 'success', duration);
        },
        
        error(message, duration) {
            this.show(message, 'error', duration);
        },
        
        warning(message, duration) {
            this.show(message, 'warning', duration);
        },
        
        info(message, duration) {
            this.show(message, 'info', duration);
        }
    },
    
    // ========================================
    // LOADING STATES
    // ========================================
    
    loading: {
        /**
         * Criar skeleton para cards
         */
        cardSkeleton() {
            return `
                <div class="bg-slate-800 rounded-lg p-6 animate-pulse">
                    <div class="h-4 bg-slate-700 rounded w-1/4 mb-4"></div>
                    <div class="h-8 bg-slate-700 rounded w-1/2 mb-2"></div>
                    <div class="h-3 bg-slate-700 rounded w-3/4"></div>
                </div>
            `;
        },
        
        /**
         * Criar skeleton para linhas de tabela
         */
        tableSkeleton(cols = 4) {
            let cells = '';
            for (let i = 0; i < cols; i++) {
                cells += '<td class="px-6 py-4"><div class="h-4 bg-slate-700 rounded animate-pulse"></div></td>';
            }
            return `<tr class="border-b border-slate-700">${cells}</tr>`;
        },
        
        /**
         * Mostrar loading em container
         */
        show(containerId, type = 'card', count = 3) {
            const container = document.getElementById(containerId);
            if (!container) return;
            
            container.innerHTML = '';
            
            if (type === 'card') {
                for (let i = 0; i < count; i++) {
                    container.innerHTML += this.cardSkeleton();
                }
            } else if (type === 'table') {
                const cols = container.closest('table')?.querySelectorAll('thead th').length || 4;
                for (let i = 0; i < count; i++) {
                    container.innerHTML += this.tableSkeleton(cols);
                }
            }
        }
    },
    
    // ========================================
    // ESTADOS VAZIOS
    // ========================================
    
    emptyState: {
        /**
         * Renderizar estado vazio
         */
        render(containerId, config = {}) {
            const container = document.getElementById(containerId);
            if (!container) return;
            
            const {
                icon = '📋',
                title = 'Nenhum item encontrado',
                description = 'Comece criando seu primeiro item',
                buttonText = '+ Criar Novo',
                buttonAction = null,
                buttonId = null
            } = config;
            
            const button = buttonText && buttonAction ? `
                <button 
                    ${buttonId ? `id="${buttonId}"` : ''}
                    onclick="${buttonAction}" 
                    class="mt-4 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-all transform hover:scale-105">
                    ${buttonText}
                </button>
            ` : '';
            
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <div class="text-6xl mb-4 opacity-50">${icon}</div>
                    <h3 class="text-xl font-semibold text-white mb-2">${title}</h3>
                    <p class="text-slate-400 mb-4">${description}</p>
                    ${button}
                </div>
            `;
        }
    },
    
    // ========================================
    // BUSCA/FILTRO
    // ========================================
    
    search: {
        /**
         * Criar campo de busca
         */
        render(containerId, placeholder = 'Buscar...', onSearch) {
            const container = document.getElementById(containerId);
            if (!container) return;
            
            const searchId = `search-${Date.now()}`;
            
            container.innerHTML = `
                <div class="relative">
                    <input 
                        type="text" 
                        id="${searchId}"
                        placeholder="${placeholder}" 
                        class="w-full pl-10 pr-4 py-3 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    <svg class="absolute left-3 top-3.5 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            `;
            
            // Adicionar listener de busca
            const input = document.getElementById(searchId);
            if (input && onSearch) {
                input.addEventListener('input', (e) => onSearch(e.target.value));
            }
        },
        
        /**
         * Filtrar dados baseado em termo de busca
         */
        filter(data, searchTerm, fields = []) {
            if (!searchTerm || !searchTerm.trim()) return data;
            
            const term = searchTerm.toLowerCase().trim();
            
            return data.filter(item => {
                // Se nenhum campo especificado, buscar em todos
                if (fields.length === 0) {
                    return JSON.stringify(item).toLowerCase().includes(term);
                }
                
                // Buscar apenas nos campos especificados
                return fields.some(field => {
                    const value = item[field];
                    return value && String(value).toLowerCase().includes(term);
                });
            });
        }
    },
    
    // ========================================
    // CLIPBOARD
    // ========================================
    
    clipboard: {
        /**
         * Copiar texto para clipboard
         */
        async copy(text, successMessage = '✅ Copiado!') {
            try {
                await navigator.clipboard.writeText(text);
                UIComponents.toast.success(successMessage);
                return true;
            } catch (err) {
                console.error('Erro ao copiar:', err);
                UIComponents.toast.error('❌ Erro ao copiar');
                return false;
            }
        },
        
        /**
         * Criar botão de copiar
         */
        button(text, label = '') {
            const btnId = `copy-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
            
            setTimeout(() => {
                const btn = document.getElementById(btnId);
                if (btn) {
                    btn.addEventListener('click', () => this.copy(text));
                }
            }, 0);
            
            return `
                <button 
                    id="${btnId}"
                    class="inline-flex items-center px-3 py-1 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition-all text-sm"
                    title="Copiar ${label || text}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                    ${label ? `<span class="ml-1">${label}</span>` : ''}
                </button>
            `;
        }
    },
    
    // ========================================
    // MODALS
    // ========================================
    
    modal: {
        /**
         * Criar e mostrar modal
         */
        show(config = {}) {
            const {
                title = 'Modal',
                body = '',
                confirmText = 'Confirmar',
                cancelText = 'Cancelar',
                onConfirm = null,
                onCancel = null,
                size = 'md' // sm, md, lg, xl
            } = config;
            
            const sizeClasses = {
                sm: 'max-w-md',
                md: 'max-w-lg',
                lg: 'max-w-2xl',
                xl: 'max-w-4xl'
            };
            
            const modalId = `modal-${Date.now()}`;
            
            const modalHTML = `
                <div id="${modalId}" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 animate-fade-in">
                    <div class="bg-slate-800 rounded-lg shadow-2xl ${sizeClasses[size]} w-full mx-4 animate-slide-up">
                        <div class="flex items-center justify-between p-6 border-b border-slate-700">
                            <h3 class="text-xl font-semibold text-white">${title}</h3>
                            <button onclick="UIComponents.modal.close('${modalId}')" class="text-slate-400 hover:text-white transition-colors">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="p-6 text-slate-300">
                            ${body}
                        </div>
                        <div class="flex justify-end space-x-3 p-6 border-t border-slate-700">
                            <button 
                                onclick="UIComponents.modal.close('${modalId}')" 
                                class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition-all">
                                ${cancelText}
                            </button>
                            <button 
                                id="${modalId}-confirm"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-all">
                                ${confirmText}
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Adicionar event listeners
            if (onConfirm) {
                document.getElementById(`${modalId}-confirm`).addEventListener('click', () => {
                    onConfirm();
                    this.close(modalId);
                });
            }
            
            if (onCancel) {
                document.getElementById(modalId).addEventListener('click', (e) => {
                    if (e.target.id === modalId) {
                        onCancel();
                        this.close(modalId);
                    }
                });
            }
            
            return modalId;
        },
        
        /**
         * Fechar modal
         */
        close(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.opacity = '0';
                setTimeout(() => modal.remove(), 300);
            }
        }
    }
};

// Exportar globalmente
window.UIComponents = UIComponents;
