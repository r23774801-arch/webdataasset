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
    
    if (loginForm) loginForm.classList.remove('active');
    if (registerForm) registerForm.classList.remove('active');
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

function replaceNrpSelectWithInput(selectElement) {
    const input = document.createElement('input');
    input.type = 'text';
    input.id = selectElement.id;
    input.placeholder = selectElement.getAttribute('placeholder') || 'Masukkan NRP pegawai';
    input.autocomplete = 'off';
    selectElement.replaceWith(input);
    return input;
}

function renderNrpOption(data, escape) {
    const value = escape(String(data.value || ''));
    const name = data.nama ? escape(String(data.nama)) : '';
    const department = data.department ? escape(String(data.department)) : '';
    const hasRich = Boolean(name || department);

    // Empty placeholder option (value ''): show its label as muted hint text.
    if (!value && data.text) {
        return `<div class="nrp-option nrp-option-placeholder">${escape(String(data.text))}</div>`;
    }

    if (!hasRich) {
        return `<div class="nrp-option"><span class="nrp-option-nrp">${value}</span></div>`;
    }

    return `<div class="nrp-option">
        <span class="nrp-option-nrp">${value}</span>
        ${name ? `<span class="nrp-option-name">${name}</span>` : ''}
        ${department ? `<span class="nrp-option-dept">${department}</span>` : ''}
    </div>`;
}

function renderNrpItem(data, escape) {
    const value = escape(String(data.value || ''));
    const name = data.nama ? escape(String(data.nama)) : '';
    return `<span class="nrp-selected">${name ? `${value} — ${name}` : value}</span>`;
}

function initializeNrpDropdown() {
    const nrpSelect = document.getElementById('regNrp');
    if (!nrpSelect || nrpSelect.dataset.enhanced === 'true') return;

    nrpSelect.dataset.enhanced = 'true';

    if (typeof TomSelect === 'undefined') {
        replaceNrpSelectWithInput(nrpSelect);
        return;
    }

    try {
        let tomSelect = null;
        let nrpCloseTimer = null;
        let nrpReopened = false;
        tomSelect = new TomSelect(nrpSelect, {
            maxItems: 1,
            // Phase 4.22 — NRP must be picked from the master employee directory.
            create: false,
            persist: false,
            allowEmptyOption: true,
            placeholder: 'Search Employee NRP or Name...',
            searchField: ['text', 'value'],
            sortField: { field: 'text', direction: 'asc' },
            // Only query the server once the user typed at least 2 characters.
            shouldLoad: query => query.trim().length >= 2,
            load: function (query, callback) {
                const q = query.trim();
                if (q.length < 2) {
                    callback();
                    return;
                }
                fetch(`search_employee.php?q=${encodeURIComponent(q)}`)
                    .then(r => r.json())
                    .then(res => {
                        const list = (res && res.data) || [];
                        callback(list.map(emp => ({
                            value: emp.nrp,
                            text: `${emp.nrp} - ${emp.employee_name}`,
                            nama: emp.employee_name,
                            email: emp.email || ''
                        })));
                    })
                    .catch(() => callback());
            },
            render: {
                option: renderNrpOption,
                item: renderNrpItem,
                loading: () => '<div class="nrp-empty">Searching employees...</div>',
                no_results: function () {
                    const typed = tomSelect.control_input ? tomSelect.control_input.value.trim() : '';
                    return typed.length < 2
                        ? '<div class="nrp-empty">Type at least 2 characters...</div>'
                        : '<div class="nrp-empty">No employee found.</div>';
                }
            },
            onChange: function (value) {
                if (!value) return;
                const option = tomSelect.options[value];
                if (!option) return;
                // Auto-populate: Username = NRP, Email = employee email (if any),
                // and keep the employee name in a hidden field.
                const usernameInput = document.getElementById('regUsername');
                const emailInput = document.getElementById('regEmail');
                const nameInput = document.getElementById('regEmployeeName');
                if (usernameInput) usernameInput.value = value;
                if (emailInput && option.email) emailInput.value = option.email;
                if (nameInput) nameInput.value = option.nama || '';
            },
            onDropdownOpen: () => {
                if (nrpCloseTimer) nrpReopened = true; // re-opened during the fade-out
                if (tomSelect.dropdown) tomSelect.dropdown.classList.remove('nrp-closing');
            },
            onDropdownClose: () => {}
        });

        tomSelect.wrapper.classList.add('auth-nrp-select');

        // Gentle fade-out when the dropdown closes (Tom Select hides it instantly by default).
        // A pending-close timer plus a reopen flag keeps the fade safe against fast re-opens
        // while still closing normally for Escape / selection / click-outside.
        const originalClose = tomSelect.close.bind(tomSelect);
        tomSelect.close = function () {
            const dropdown = tomSelect.dropdown;
            if (!tomSelect.isOpen || !dropdown) {
                originalClose();
                return;
            }
            // A fresh close intent supersedes any pending fade.
            if (nrpCloseTimer) {
                window.clearTimeout(nrpCloseTimer);
                nrpCloseTimer = null;
            }
            nrpReopened = false;
            dropdown.classList.add('nrp-closing');
            nrpCloseTimer = window.setTimeout(() => {
                nrpCloseTimer = null;
                dropdown.classList.remove('nrp-closing');
                if (nrpReopened) {
                    nrpReopened = false;
                    return;
                }
                originalClose();
            }, 150);
        };
    } catch (error) {
        console.warn('Tom Select initialization failed:', error);
        replaceNrpSelectWithInput(nrpSelect);
    }
}

function getRegisterNrpValue() {
    const nrpField = document.getElementById('regNrp');
    if (!nrpField) return '';

    // Phase 4.22 — the NRP is only valid when picked from the employee directory.
    // Free-typed text can never be submitted (backend validates against master_employee too).
    if (nrpField.tomselect) {
        return (nrpField.tomselect.getValue() || '').trim();
    }

    return nrpField.value.trim();
}

function clearRegisterNrpValue() {
    const nrpField = document.getElementById('regNrp');
    if (!nrpField) return;

    if (nrpField.tomselect) {
        nrpField.tomselect.clear(true);
    } else {
        nrpField.value = '';
    }

    const nameInput = document.getElementById('regEmployeeName');
    if (nameInput) nameInput.value = '';
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

// --- Logika Registrasi Menggunakan PHP & MySQL ---
async function handleRegister() {
    const nrp = getRegisterNrpValue();
    const username = document.getElementById('regUsername').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const password = document.getElementById('regPassword').value.trim();
    const role = document.getElementById('regRole').value;
    const department = document.getElementById('regDepartment').value.trim();
    
    if (!nrp || !username || !email || !password) {
        showToast("Semua field (NRP, Nama, Email, Password) wajib diisi!", "info");
        return;
    }

    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(email)) {
        showToast("Format email tidak valid.", "error");
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
                email: email, 
                password: password, 
                role: role,
                department: department
            })
        });

        const result = await response.json();
        toggleLoader(false);
        
        showToast(result.message, result.status === "success" ? "success" : "error");

        if (result.status === "success") {
            // Reset Form & pindah ke tampilan login setelah 1.5 detik
            clearRegisterNrpValue();
            document.getElementById('regUsername').value = '';
            document.getElementById('regEmail').value = '';
            document.getElementById('regPassword').value = '';
            document.getElementById('regDepartment').value = '';
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
    