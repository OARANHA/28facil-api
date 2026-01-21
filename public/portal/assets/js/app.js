// API Base URL
const API_URL = '/api';

// Tab switching
function showTab(tab) {
    const loginForm = document.getElementById('form-login');
    const registerForm = document.getElementById('form-register');
    const loginTab = document.getElementById('tab-login');
    const registerTab = document.getElementById('tab-register');
    
    if (tab === 'login') {
        loginForm.classList.remove('hidden');
        registerForm.classList.add('hidden');
        loginTab.classList.remove('bg-gray-200', 'text-gray-700');
        loginTab.classList.add('bg-indigo-600', 'text-white');
        registerTab.classList.remove('bg-indigo-600', 'text-white');
        registerTab.classList.add('bg-gray-200', 'text-gray-700');
    } else {
        loginForm.classList.add('hidden');
        registerForm.classList.remove('hidden');
        registerTab.classList.remove('bg-gray-200', 'text-gray-700');
        registerTab.classList.add('bg-indigo-600', 'text-white');
        loginTab.classList.remove('bg-indigo-600', 'text-white');
        loginTab.classList.add('bg-gray-200', 'text-gray-700');
    }
}

// Handle Login
async function handleLogin(e) {
    e.preventDefault();
    
    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;
    const errorDiv = document.getElementById('login-error');
    
    errorDiv.classList.add('hidden');
    
    try {
        const response = await fetch(`${API_URL}/auth/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            // Save token
            localStorage.setItem('token', data.token);
            localStorage.setItem('user', JSON.stringify(data.user));
            
            // Redirect to dashboard
            window.location.href = '/portal/dashboard.html';
        } else {
            errorDiv.textContent = data.error || 'Erro ao fazer login';
            errorDiv.classList.remove('hidden');
        }
    } catch (error) {
        errorDiv.textContent = 'Erro de conexão. Tente novamente.';
        errorDiv.classList.remove('hidden');
    }
}

// Handle Register
async function handleRegister(e) {
    e.preventDefault();
    
    const name = document.getElementById('register-name').value;
    const email = document.getElementById('register-email').value;
    const password = document.getElementById('register-password').value;
    const errorDiv = document.getElementById('register-error');
    
    errorDiv.classList.add('hidden');
    
    try {
        const response = await fetch(`${API_URL}/auth/register`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, email, password })
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            // Save token
            localStorage.setItem('token', data.token);
            localStorage.setItem('user', JSON.stringify(data.user));
            
            // Redirect to dashboard
            window.location.href = '/portal/dashboard.html';
        } else {
            errorDiv.textContent = data.error || 'Erro ao criar conta';
            errorDiv.classList.remove('hidden');
        }
    } catch (error) {
        errorDiv.textContent = 'Erro de conexão. Tente novamente.';
        errorDiv.classList.remove('hidden');
    }
}