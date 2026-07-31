// 1. Inisialisasi Database Dummy di LocalStorage
if (!localStorage.getItem('usersDB')) {
    const dummyUsers = [
        { nrp: '90123401', username: 'admin_ut', password: '123', role: 'admin' },
        { nrp: '90123402', username: 'user_it1', password: '123', role: 'it' },
        { nrp: '90123403', username: 'user_ga1', password: '123', role: 'ga' }
    ];
    localStorage.setItem('usersDB', JSON.stringify(dummyUsers));
}

// --- Fungsi untuk memunculkan form yang dipilih ---
function hideAllForms() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const resetForm = document.getElementById('resetForm');
    
    if (loginForm) loginForm.classList.remove('active');
    if (registerForm) registerForm.classList.remove('active');
    if (resetForm) resetForm.classList.remove('active');
}

function showLogin() {
    hideAllForms();
    const loginForm = document.getElementById('loginForm');
    if (loginForm) loginForm.classList.add('active');
}

function showRegister() {
    hideAllForms();
    const registerForm = document.getElementById('registerForm');
    if (registerForm) registerForm.classList.add('active');
    updatePasswordValidation();
}

function showReset() {
    hideAllForms();
    const resetForm = document.getElementById('resetForm');
    if (resetForm) resetForm.classList.add('active');
}

const registerPasswordRules = {
    uppercase: value => /[A-Z]/.test(value),
    lowercase: value => /[a-z]/.test(value),
    number: value => /\d/.test(value),
    symbol: value => /[^A-Za-z0-9]/.test(value),
    length: value => value.length >= 8
};

function getRegisterPasswordValidation(password) {
    const checks = Object.fromEntries(
        Object.entries(registerPasswordRules).map(([key, test]) => [key, test(password)])
    );
    const score = Object.values(checks).filter(Boolean).length;

    return {
        checks,
        score,
        valid: score === Object.keys(registerPasswordRules).length
    };
}

function updatePasswordValidation() {
    const passwordInput = document.getElementById('regPassword');
    const strengthBox = document.querySelector('.password-strength');
    const strengthText = document.getElementById('passwordStrengthText');

    if (!passwordInput || !strengthBox) {
        return { valid: true, checks: {}, score: 0 };
    }

    const password = passwordInput.value;
    const validation = getRegisterPasswordValidation(password);
    let strength = 'empty';
    let label = 'Belum diisi';

    if (password) {
        if (validation.score <= 2) {
            strength = 'weak';
            label = 'Lemah';
        } else if (validation.score === 3) {
            strength = 'fair';
            label = 'Cukup';
        } else if (validation.score === 4) {
            strength = 'good';
            label = 'Baik';
        } else {
            strength = 'strong';
            label = 'Kuat';
        }
    }

    strengthBox.dataset.strength = strength;
    if (strengthText) strengthText.textContent = label;

    Object.entries(validation.checks).forEach(([rule, isValid]) => {
        const ruleElement = document.querySelector(`.password-rules [data-rule="${rule}"]`);
        if (ruleElement) ruleElement.classList.toggle('is-valid', isValid);
    });

    return validation;
}

function normalizeNrpOptions(options) {
    if (!Array.isArray(options)) return [];

    return options
        .map(option => {
            if (typeof option === 'string' || typeof option === 'number') {
                const value = String(option).trim();
                return value ? { value, text: value } : null;
            }

            if (!option || typeof option !== 'object') return null;

            const value = String(option.value || option.nrp || option.id || '').trim();
            const label = String(option.text || option.label || option.nama || option.name || value).trim();
            if (!value) return null;

            return {
                value,
                text: label && label !== value ? `${value} - ${label}` : value
            };
        })
        .filter(Boolean);
}

function loadNrpOptions(selectElement) {
    const nrpOptions = normalizeNrpOptions(window.UT_NRP_OPTIONS || []);
    const existingValues = new Set(
        Array.from(selectElement.options).map(option => option.value)
    );

    nrpOptions.forEach(option => {
        if (existingValues.has(option.value)) return;
        selectElement.add(new Option(option.text, option.value));
        existingValues.add(option.value);
    });
}

function replaceNrpSelectWithInput(selectElement) {
    const input = document.createElement('input');
    input.type = 'text';
    input.id = selectElement.id;
    input.placeholder = selectElement.getAttribute('placeholder') || 'Masukkan NRP pegawai';
    input.autocomplete = 'off';
    selectElement.replaceWith(input);
    return input;
}

function initializeNrpDropdown() {
    const nrpSelect = document.getElementById('regNrp');
    if (!nrpSelect || nrpSelect.dataset.enhanced === 'true') return;

    nrpSelect.dataset.enhanced = 'true';
    loadNrpOptions(nrpSelect);

    if (typeof TomSelect === 'undefined') {
        replaceNrpSelectWithInput(nrpSelect);
        return;
    }

    try {
        const tomSelect = new TomSelect(nrpSelect, {
            maxItems: 1,
            create: input => {
                const value = input.trim();
                return value ? { value, text: value } : false;
            },
            persist: false,
            allowEmptyOption: true,
            placeholder: 'Pilih atau ketik NRP pegawai',
            searchField: ['text', 'value'],
            sortField: { field: 'text', direction: 'asc' }
        });

        tomSelect.wrapper.classList.add('auth-nrp-select');
    } catch (error) {
        console.warn('Tom Select initialization failed:', error);
        replaceNrpSelectWithInput(nrpSelect);
    }
}

function getRegisterNrpValue() {
    const nrpField = document.getElementById('regNrp');
    if (!nrpField) return '';

    if (nrpField.tomselect) {
        const selectedValue = nrpField.tomselect.getValue().trim();
        const typedValue = nrpField.tomselect.control_input?.value.trim() || '';

        if (selectedValue) return selectedValue;
        if (typedValue) {
            nrpField.tomselect.addOption({ value: typedValue, text: typedValue });
            nrpField.tomselect.addItem(typedValue, true);
            return typedValue;
        }
    }

    return nrpField.value.trim();
}

function clearRegisterNrpValue() {
    const nrpField = document.getElementById('regNrp');
    if (!nrpField) return;

    if (nrpField.tomselect) {
        nrpField.tomselect.clear(true);
        return;
    }

    nrpField.value = '';
}

function initializeRegisterValidation() {
    const passwordInput = document.getElementById('regPassword');
    if (!passwordInput) return;

    passwordInput.addEventListener('input', updatePasswordValidation);
    updatePasswordValidation();
}

function initializeAuthEnhancements() {
    initializeNrpDropdown();
    initializeRegisterValidation();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAuthEnhancements);
} else {
    initializeAuthEnhancements();
}

// --- Logika Lupa Password (Mengirim ke PHP) ---
async function handleReset() {
    const nrp = document.getElementById('resetNrp').value.trim();
    const username = document.getElementById('resetUsername').value.trim();
    const passwordBaru = document.getElementById('resetPasswordBaru').value.trim();
    
    if (!nrp || !username || !passwordBaru) {
        showToast("Semua kolom (NRP, Username, Password Baru) wajib diisi!", "info");
        return;
    }

    toggleLoader(true, "Memperbarui password...");
    try {
        const response = await fetch('reset_password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                nrp: nrp,
                username: username,
                password_baru: passwordBaru
            })
        });

        const result = await response.json();
        toggleLoader(false);
        showToast(result.message, result.status === "success" ? "success" : "error");

        // Jika sukses di-reset, kosongkan form dan kembalikan ke halaman login
        if (result.status === "success") {
            document.getElementById('resetNrp').value = '';
            document.getElementById('resetUsername').value = '';
            document.getElementById('resetPasswordBaru').value = '';
            setTimeout(() => { showLogin(); }, 1200);
        }
        
    } catch (error) {
        toggleLoader(false);
        console.error("Error:", error);
        showToast("Terjadi kesalahan saat menghubungi server backend.", "error");
    }
}

// --- Logika Registrasi Menggunakan PHP & MySQL ---
async function handleRegister() {
    const nrp = getRegisterNrpValue();
    const username = document.getElementById('regUsername').value.trim();
    const password = document.getElementById('regPassword').value.trim();
    const role = document.getElementById('regRole').value;
    
    if (!nrp || !username || !password) {
        showToast("Semua field (NRP, Nama, Password) wajib diisi!", "info");
        return;
    }

    if (!updatePasswordValidation().valid) {
        showToast("Password harus memenuhi semua persyaratan keamanan.", "info");
        return;
    }

    toggleLoader(true, "Mendaftarkan akun...");
    try {
        // Mengirim data ke register.php
        const response = await fetch('register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                nrp: nrp, 
                username: username, 
                password: password, 
                role: role 
            })
        });

        const result = await response.json();
        toggleLoader(false);
        
        showToast(result.message, result.status === "success" ? "success" : "error");

        if (result.status === "success") {
            // Reset Form & pindah ke tampilan login setelah 1.5 detik
            clearRegisterNrpValue();
            document.getElementById('regUsername').value = '';
            document.getElementById('regPassword').value = '';
            updatePasswordValidation();
            setTimeout(() => { showLogin(); }, 1500);
        }
    } catch (error) {
        toggleLoader(false);
        console.error("Error:", error);
        showToast("Terjadi kesalahan saat menghubungi server backend.", "error");
    }
}

/// --- Logika Login Menggunakan PHP & MySQL ---
async function handleLogin() {
    const nrp = document.getElementById('loginNrp').value.trim();
    const password = document.getElementById('loginPassword').value.trim();
    const selectedRoleElement = document.getElementById('loginRole');
    const selectedRole = selectedRoleElement ? selectedRoleElement.value : '';
    
    if (!nrp || !password) {
        showToast("Masukkan NRP dan Password!", "info");
        return;
    }

    toggleLoader(true, "Memproses login...");
    try {
        // ==========================================
        // TAMBAHKAN BARIS INI (Delay 1 detik untuk pamer loader)
        await new Promise(resolve => setTimeout(resolve, 1000));
        // ==========================================

        // Mengirim data ke login.php
        const response = await fetch('login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                nrp: nrp,
                password: password,
                role: selectedRole
            })
        });

        const result = await response.json();
        toggleLoader(false);
        
        if (result.status === "success") {
            localStorage.setItem('currentNrp', result.data.nrp);
            localStorage.setItem('currentUser', result.data.username);
            localStorage.setItem('userRole', result.data.role);
            
            // Set session-level auth flag (cleared when browser/tab closes)
            sessionStorage.setItem('isLoggedIn', 'true');
            
            // Simpan pesan sambutan untuk dashboard
            localStorage.setItem('welcomeToast', `Login Berhasil! Selamat datang, ${result.data.username}.`);
            
            // Tampilkan toast sukses warna hijau ('success')
            showToast(result.message + ` Selamat datang, ${result.data.username}.`, 'success');
            
            // Jeda 1 detik agar notifikasi sempat terbaca sebelum pindah halaman
            setTimeout(() => {
                window.location.replace('dashboard.html');
            }, 1000);
        } else {
            // Tampilkan toast error warna merah ('error')
            showToast(result.message, 'error');
        }
        
    } catch (error) {
        toggleLoader(false);
        console.error("Error:", error);
        showToast("Terjadi kesalahan saat menghubungi server backend.", "error");
    }
}
