/**
 * 28Facil Portal - App Logic (Secure Version)
 * Versão com Security Module integrado
 * Issue #2 - Proteções Críticas de Segurança
 */

// API Base URL
const API_URL = '/api';

// Carregar CSRF token ao iniciar
let csrfToken = null;

async function loadCsrfToken() {
    try {
        const response = await fetch(`${API_URL}/csrf-token`, { credentials: 'include' });
        const data = await response.json();
        if (data.success && data.csrf_token) {
            csrfToken = data.csrf_token;
            // Criar meta tag para o Security module
            let metaTag = document.querySelector('meta[name="csrf-token"]');
            if (!metaTag) {
                metaTag = document.createElement('meta');
                metaTag.name = 'csrf-token';
                document.head.appendChild(metaTag);
            }
            metaTag.content = csrfToken;
        }
    } catch (error) {
        console.error('Erro ao carregar CSRF token:', error);
    }
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

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    loadCsrfToken();
});

// Tab switching
function showTab(tab) {
    const loginForm = document.getElementById('form-login');
    const registerForm = document.getElementById('form-register');
    const loginTab = document.getElementById('tab-login');
    const registerTab = document.getElementById('tab-register');
    
    if (tab === 'login') {
        registerForm.classList.add('hidden');
        setTimeout(() => {
            loginForm.classList.remove('hidden');
            loginForm.classList.add('animate-fade-in');
        }, 50);
        
        loginTab.classList.remove('bg-slate-700', 'text-slate-300');
        loginTab.classList.add('bg-indigo-600', 'text-white');
        registerTab.classList.remove('bg-indigo-600', 'text-white');
        registerTab.classList.add('bg-slate-700', 'text-slate-300');
    } else {
        loginForm.classList.add('hidden');
        setTimeout(() => {
            registerForm.classList.remove('hidden');
            registerForm.classList.add('animate-fade-in');
        }, 50);
        
        registerTab.classList.remove('bg-slate-700', 'text-slate-300');
        registerTab.classList.add('bg-indigo-600', 'text-white');
        loginTab.classList.remove('bg-indigo-600', 'text-white');
        loginTab.classList.add('bg-slate-700', 'text-slate-300');
    }
}

// ========================================
// SECURE LOGIN HANDLER
// ========================================

async function handleSecureLogin(e) {
    e.preventDefault();
    
    // Verificar rate limiting
    const rateLimitCheck = Security.checkRateLimit();
    if (rateLimitCheck.isLocked) {
        Security.showSecureError('login-error', rateLimitCheck.message);
        return;
    }
    
    const email = Security.sanitizeInput(document.getElementById('login-email').value.trim());
    const password = document.getElementById('login-password').value;
    const submitBtn = e.target.querySelector('button[type="submit"]');
    
    // Hide previous errors
    document.getElementById('login-error').classList.add('hidden');
    
    // Disable button
    submitBtn.disabled = true;
    submitBtn.textContent = 'Entrando...';
    
    try {
        const response = await Security.secureFetch(`${API_URL}/auth/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            // Reset rate limit após sucesso
            Security.resetRateLimit();
            
            // Token agora está em httpOnly cookie, não mais no localStorage
            localStorage.setItem('user', JSON.stringify(data.user));
            
            submitBtn.textContent = '✓ Login realizado!';
            submitBtn.classList.add('bg-green-600');
            
            Security.showToast('Login realizado com sucesso!', 'success');
            
            setTimeout(() => {
                window.location.href = '/portal/dashboard.html';
            }, 500);
        } else {
            // Registrar tentativa falha
            const result = Security.recordFailedAttempt();
            Security.showSecureError('login-error', result.message || data.error);
            
            submitBtn.disabled = false;
            submitBtn.textContent = 'Entrar';
        }
    } catch (error) {
        console.error('Login error:', error);
        Security.showSecureError('login-error', 'Erro de conexão. Verifique sua internet.');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Entrar';
    }
}

// ========================================
// SECURE REGISTER HANDLER
// ========================================

async function handleSecureRegister(e) {
    e.preventDefault();
    
    const name = Security.sanitizeInput(document.getElementById('register-name').value.trim());
    const email = Security.sanitizeInput(document.getElementById('register-email').value.trim());
    const password = document.getElementById('register-password').value;
    const submitBtn = e.target.querySelector('button[type="submit"]');
    
    // Hide previous messages
    document.getElementById('register-error').classList.add('hidden');
    
    // Disable button
    submitBtn.disabled = true;
    submitBtn.textContent = 'Criando conta...';
    
    try {
        const response = await Security.secureFetch(`${API_URL}/auth/register`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, email, password })
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            localStorage.setItem('user', JSON.stringify(data.user));
            
            submitBtn.textContent = '✓ Conta criada!';
            submitBtn.classList.add('bg-green-600');
            
            Security.showToast('Conta criada com sucesso!', 'success');
            
            setTimeout(() => {
                window.location.href = '/portal/dashboard.html';
            }, 1000);
        } else {
            Security.showSecureError('register-error', data.error || 'Erro ao criar conta.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Criar Conta';
        }
    } catch (error) {
        console.error('Register error:', error);
        Security.showSecureError('register-error', 'Erro de conexão. Verifique sua internet.');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Criar Conta';
    }
}