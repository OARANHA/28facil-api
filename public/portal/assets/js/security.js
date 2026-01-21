/**
 * 28Facil Security Module
 * Implementa proteções contra XSS, CSRF e Rate Limiting
 * Issue #2 - Proteções Críticas de Segurança
 */

// ========================================
// CSRF PROTECTION
// ========================================

/**
 * Obtém o token CSRF da meta tag
 */
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta) {
        console.warn('⚠️ CSRF token meta tag não encontrada!');
        return null;
    }
    return meta.getAttribute('content');
}

/**
 * Adiciona token CSRF aos headers da requisição
 */
function addCsrfHeaders(headers = {}) {
    const csrfToken = getCsrfToken();
    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }
    return headers;
}

/**
 * Fetch seguro com CSRF token automático
 */
async function secureFetch(url, options = {}) {
    options.headers = addCsrfHeaders(options.headers || {});
    
    // Adicionar credentials para cookies httpOnly
    options.credentials = 'include';
    
    return fetch(url, options);
}

// ========================================
// XSS PROTECTION - INPUT SANITIZATION
// ========================================

/**
 * Sanitiza string removendo HTML/script tags
 * Usa DOMPurify se disponível, senão fallback básico
 */
function sanitizeInput(input) {
    if (typeof input !== 'string') return input;
    
    // Se DOMPurify estiver disponível, usar
    if (typeof DOMPurify !== 'undefined') {
        return DOMPurify.sanitize(input, { 
            ALLOWED_TAGS: [],
            ALLOWED_ATTR: [] 
        });
    }
    
    // Fallback: sanitização básica
    const div = document.createElement('div');
    div.textContent = input;
    return div.innerHTML;
}

/**
 * Sanitiza objeto recursivamente
 */
function sanitizeObject(obj) {
    if (typeof obj !== 'object' || obj === null) {
        return sanitizeInput(obj);
    }
    
    const sanitized = {};
    for (const [key, value] of Object.entries(obj)) {
        sanitized[key] = typeof value === 'object' 
            ? sanitizeObject(value)
            : sanitizeInput(value);
    }
    return sanitized;
}

/**
 * Exibe mensagem de erro de forma segura (sanitizada)
 */
function showSecureError(elementId, message) {
    const errorDiv = document.getElementById(elementId);
    if (!errorDiv) return;
    
    // Sanitizar mensagem antes de exibir
    errorDiv.textContent = sanitizeInput(message);
    errorDiv.classList.remove('hidden');
    
    // Auto-hide após 5 segundos
    setTimeout(() => {
        errorDiv.classList.add('hidden');
    }, 5000);
}

// ========================================
// RATE LIMITING - BRUTE FORCE PROTECTION
// ========================================

const RATE_LIMIT_CONFIG = {
    MAX_ATTEMPTS: 5,
    LOCKOUT_TIME: 5 * 60 * 1000, // 5 minutos em ms
    STORAGE_KEY: 'loginAttempts',
    LOCKOUT_KEY: 'lockoutUntil'
};

/**
 * Verifica se usuário está bloqueado por rate limiting
 * @returns {Object} { isLocked: boolean, remainingTime: number }
 */
function checkRateLimit() {
    const lockoutUntil = parseInt(localStorage.getItem(RATE_LIMIT_CONFIG.LOCKOUT_KEY) || '0');
    const now = Date.now();
    
    if (now < lockoutUntil) {
        const remainingMs = lockoutUntil - now;
        const remainingMinutes = Math.ceil(remainingMs / 60000);
        return {
            isLocked: true,
            remainingTime: remainingMinutes,
            message: `🔒 Muitas tentativas falhas. Aguarde ${remainingMinutes} minuto(s).`
        };
    }
    
    // Se passou o tempo de lockout, resetar tentativas
    if (lockoutUntil > 0 && now >= lockoutUntil) {
        resetRateLimit();
    }
    
    return { isLocked: false };
}

/**
 * Registra tentativa de login falha
 */
function recordFailedAttempt() {
    let attempts = parseInt(localStorage.getItem(RATE_LIMIT_CONFIG.STORAGE_KEY) || '0');
    attempts++;
    
    localStorage.setItem(RATE_LIMIT_CONFIG.STORAGE_KEY, attempts.toString());
    
    // Se atingiu o máximo, bloquear
    if (attempts >= RATE_LIMIT_CONFIG.MAX_ATTEMPTS) {
        const lockoutUntil = Date.now() + RATE_LIMIT_CONFIG.LOCKOUT_TIME;
        localStorage.setItem(RATE_LIMIT_CONFIG.LOCKOUT_KEY, lockoutUntil.toString());
        
        return {
            locked: true,
            message: `🔒 Muitas tentativas falhas. Conta bloqueada por ${RATE_LIMIT_CONFIG.LOCKOUT_TIME / 60000} minutos.`
        };
    }
    
    const remaining = RATE_LIMIT_CONFIG.MAX_ATTEMPTS - attempts;
    return {
        locked: false,
        attemptsRemaining: remaining,
        message: `⚠️ Login falhou. ${remaining} tentativa(s) restante(s).`
    };
}

/**
 * Reseta contador de tentativas (após login bem-sucedido)
 */
function resetRateLimit() {
    localStorage.removeItem(RATE_LIMIT_CONFIG.STORAGE_KEY);
    localStorage.removeItem(RATE_LIMIT_CONFIG.LOCKOUT_KEY);
}

/**
 * Obtém número de tentativas restantes
 */
function getRemainingAttempts() {
    const attempts = parseInt(localStorage.getItem(RATE_LIMIT_CONFIG.STORAGE_KEY) || '0');
    return Math.max(0, RATE_LIMIT_CONFIG.MAX_ATTEMPTS - attempts);
}

// ========================================
// PURCHASE CODE MASKING
// ========================================

/**
 * Mascara Purchase Code para exibição segura
 * Exemplo: XXXX-XXXX-XXXX-1234 -> XXXX-****-****-1234
 */
function maskPurchaseCode(code) {
    if (!code || typeof code !== 'string') return code;
    
    // Se código tiver menos de 12 caracteres, não mascarar
    if (code.length < 12) return code;
    
    // Mostrar apenas primeiros 8 e últimos 4 caracteres
    const start = code.slice(0, 8);
    const end = code.slice(-4);
    
    return `${start}-****-****-****-${end}`;
}

/**
 * Copia código completo para clipboard (sem mascaramento)
 */
async function copyPurchaseCode(code, buttonElement) {
    try {
        await navigator.clipboard.writeText(code);
        
        // Feedback visual
        const originalText = buttonElement.innerHTML;
        buttonElement.innerHTML = '✓ Copiado!';
        buttonElement.classList.add('bg-green-600');
        
        setTimeout(() => {
            buttonElement.innerHTML = originalText;
            buttonElement.classList.remove('bg-green-600');
        }, 2000);
        
    } catch (err) {
        console.error('Erro ao copiar:', err);
        showSecureError('copy-error', '❌ Erro ao copiar código');
    }
}

// ========================================
// TOAST NOTIFICATIONS
// ========================================

/**
 * Exibe notificação toast
 * @param {string} message - Mensagem a exibir
 * @param {string} type - Tipo: 'success', 'error', 'warning', 'info'
 */
function showToast(message, type = 'info') {
    // Sanitizar mensagem
    const safeMessage = sanitizeInput(message);
    
    const toast = document.createElement('div');
    const colors = {
        success: 'bg-green-600',
        error: 'bg-red-600',
        warning: 'bg-yellow-600',
        info: 'bg-indigo-600'
    };
    
    toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50
        ${colors[type] || colors.info} text-white transform transition-all duration-300`;
    toast.textContent = safeMessage;
    
    document.body.appendChild(toast);
    
    // Animar entrada
    setTimeout(() => toast.style.opacity = '1', 10);
    
    // Remover após 3 segundos
    setTimeout(() => {
        toast.style.transform = 'translateY(100px)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ========================================
// UTILITY FUNCTIONS
// ========================================

/**
 * Valida força da senha
 */
function validatePasswordStrength(password) {
    const checks = {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
    };
    
    const score = Object.values(checks).filter(Boolean).length;
    
    return {
        isValid: checks.length && score >= 3,
        score,
        checks,
        strength: score <= 2 ? 'fraca' : score === 3 ? 'média' : score === 4 ? 'forte' : 'muito forte'
    };
}

/**
 * Remove dados sensíveis do localStorage
 */
function clearSensitiveData() {
    // Não remover mais o token do localStorage (será cookie httpOnly)
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    sessionStorage.clear();
}

// ========================================
// EXPORTS (para uso global)
// ========================================

if (typeof window !== 'undefined') {
    window.Security = {
        // CSRF
        getCsrfToken,
        addCsrfHeaders,
        secureFetch,
        
        // Sanitização
        sanitizeInput,
        sanitizeObject,
        showSecureError,
        
        // Rate Limiting
        checkRateLimit,
        recordFailedAttempt,
        resetRateLimit,
        getRemainingAttempts,
        
        // Purchase Code
        maskPurchaseCode,
        copyPurchaseCode,
        
        // Toast
        showToast,
        
        // Utilities
        validatePasswordStrength,
        clearSensitiveData
    };
    
    console.log('🔒 Security Module carregado com sucesso!');
}