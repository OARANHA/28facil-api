// API Base URL
const API_URL = '/api';

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

// Initialize theme on page load
document.addEventListener('DOMContentLoaded', initTheme);

// Tab switching
function showTab(tab) {
    const loginForm = document.getElementById('form-login');
    const registerForm = document.getElementById('form-register');
    const loginTab = document.getElementById('tab-login');
    const registerTab = document.getElementById('tab-register');
    
    // Add animation
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

// Input validation helpers
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validateName(name) {
    return name.trim().length >= 3;
}

function validatePassword(password) {
    return password.length >= 6;
}

// Show error message
function showError(elementId, message) {
    const errorDiv = document.getElementById(elementId);
    errorDiv.textContent = message;
    errorDiv.classList.remove('hidden');
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        errorDiv.classList.add('hidden');
    }, 5000);
}

// Show success message
function showSuccess(elementId, message) {
    const successDiv = document.getElementById(elementId);
    successDiv.textContent = message;
    successDiv.classList.remove('hidden');
    
    setTimeout(() => {
        successDiv.classList.add('hidden');
    }, 3000);
}

// Handle Login
async function handleLogin(e) {
    e.preventDefault();
    
    const email = document.getElementById('login-email').value.trim();
    const password = document.getElementById('login-password').value;
    const errorDiv = document.getElementById('login-error');
    const submitBtn = e.target.querySelector('button[type="submit"]');
    
    // Hide previous errors
    errorDiv.classList.add('hidden');
    
    // Client-side validation
    if (!validateEmail(email)) {
        showError('login-error', 'Por favor, insira um email válido');
        return;
    }
    
    if (!password) {
        showError('login-error', 'Por favor, insira sua senha');
        return;
    }
    
    // Disable button and show loading
    submitBtn.disabled = true;
    submitBtn.textContent = 'Entrando...';
    
    try {
        const response = await fetch(`${API_URL}/auth/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            // Save token and user data
            localStorage.setItem('token', data.token);
            localStorage.setItem('user', JSON.stringify(data.user));
            
            // Show success and redirect
            submitBtn.textContent = '✓ Login realizado!';
            submitBtn.classList.add('bg-green-600');
            
            setTimeout(() => {
                window.location.href = '/portal/dashboard.html';
            }, 500);
        } else {
            showError('login-error', data.error || 'Email ou senha incorretos');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Entrar';
        }
    } catch (error) {
        console.error('Login error:', error);
        showError('login-error', 'Erro de conexão. Verifique sua internet.');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Entrar';
    }
}

// Handle Register
async function handleRegister(e) {
    e.preventDefault();
    
    const name = document.getElementById('register-name').value.trim();
    const email = document.getElementById('register-email').value.trim();
    const password = document.getElementById('register-password').value;
    const errorDiv = document.getElementById('register-error');
    const submitBtn = e.target.querySelector('button[type="submit"]');
    
    // Hide previous errors
    errorDiv.classList.add('hidden');
    
    // Client-side validation
    if (!validateName(name)) {
        showError('register-error', 'Nome deve ter pelo menos 3 caracteres');
        return;
    }
    
    if (!validateEmail(email)) {
        showError('register-error', 'Por favor, insira um email válido');
        return;
    }
    
    if (!validatePassword(password)) {
        showError('register-error', 'Senha deve ter pelo menos 6 caracteres');
        return;
    }
    
    // Disable button and show loading
    submitBtn.disabled = true;
    submitBtn.textContent = 'Criando conta...';
    
    try {
        const response = await fetch(`${API_URL}/auth/register`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, email, password })
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            // Save token and user data
            localStorage.setItem('token', data.token);
            localStorage.setItem('user', JSON.stringify(data.user));
            
            // Show success
            submitBtn.textContent = '✓ Conta criada!';
            submitBtn.classList.add('bg-green-600');
            
            showSuccess('register-success', 'Conta criada com sucesso! Redirecionando...');
            
            setTimeout(() => {
                window.location.href = '/portal/dashboard.html';
            }, 1000);
        } else {
            showError('register-error', data.error || 'Erro ao criar conta. Tente novamente.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Criar Conta';
        }
    } catch (error) {
        console.error('Register error:', error);
        showError('register-error', 'Erro de conexão. Verifique sua internet.');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Criar Conta';
    }
}

// Add input event listeners for real-time validation feedback
document.addEventListener('DOMContentLoaded', () => {
    const registerEmail = document.getElementById('register-email');
    const registerName = document.getElementById('register-name');
    const registerPassword = document.getElementById('register-password');
    
    if (registerEmail) {
        registerEmail.addEventListener('blur', function() {
            if (this.value && !validateEmail(this.value)) {
                this.classList.add('border-red-500');
            } else {
                this.classList.remove('border-red-500');
                this.classList.add('border-green-500');
            }
        });
    }
    
    if (registerName) {
        registerName.addEventListener('blur', function() {
            if (this.value && !validateName(this.value)) {
                this.classList.add('border-red-500');
            } else {
                this.classList.remove('border-red-500');
                this.classList.add('border-green-500');
            }
        });
    }
    
    if (registerPassword) {
        registerPassword.addEventListener('input', function() {
            if (this.value && !validatePassword(this.value)) {
                this.classList.add('border-red-500');
            } else {
                this.classList.remove('border-red-500');
                this.classList.add('border-green-500');
            }
        });
    }
});