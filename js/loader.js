// ==========================================
// GLOBAL AUTH GUARD — Runs BEFORE anything else
// ==========================================
(function() {
    // If sessionStorage 'isLoggedIn' flag is missing, user is not authenticated
    if (!sessionStorage.getItem('isLoggedIn')) {
        // Get current page filename
        const path = window.location.pathname;
        const currentPage = path.substring(path.lastIndexOf('/') + 1);
        // Only redirect if not already on login page (to avoid redirect loop)
        if (currentPage !== 'login.html') {
            window.location.replace('login.html');
        }
    }
})();

// ==========================================
// FUNGSI UNTUK MENYIAPKAN TOAST DAN LOADER GLOBAL
// ==========================================
function initializeSharedUI() {
    const body = document.body;

    if (!document.getElementById('toast-container')) {
        const toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        body.appendChild(toastContainer);
    }

    if (!document.getElementById('loader')) {
        const loader = document.createElement('div');
        loader.id = 'loader';
        loader.innerHTML = `
            <div class="loader-panel">
                <div class="loader-icon" aria-hidden="true"></div>
                <div class="loader-text" id="loaderText">Memuat...</div>
            </div>
        `;
        body.appendChild(loader);
    }
}

function isInternalLink(link) {
    if (!link || !link.href) return false;
    if (link.protocol.startsWith('http') && link.host !== window.location.host) return false;
    if (link.href.startsWith('mailto:') || link.href.startsWith('tel:') || link.href.startsWith('javascript:')) return false;
    if (link.getAttribute('target') === '_blank') return false;
    return true;
}

function attachTransitionHandlers() {
    const body = document.body;
    const links = document.querySelectorAll('a[href]');

    links.forEach(link => {
        if (!isInternalLink(link)) return;
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#')) return;

        link.addEventListener('click', event => {
            event.preventDefault();
            const target = link.getAttribute('href');
            if (!target) return;

            body.classList.add('page-transition-exit');
            toggleLoader(true, 'Memuat...');
            setTimeout(() => {
                window.location.href = target;
            }, 320);
        });
    });

    window.addEventListener('beforeunload', () => {
        body.classList.add('page-transition-exit');
    });
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.innerHTML = `
        <span class="toast-message">${message}</span>
        <span class="toast-progress"></span>
    `;

    container.appendChild(toast);

    const removeToast = () => {
        if (!toast.classList.contains('toast-removing')) {
            toast.classList.add('toast-removing');
            setTimeout(() => toast.remove(), 280);
        }
    };

    toast.addEventListener('click', removeToast);
    setTimeout(removeToast, 3400);
}

// ==========================================
// FUNGSI UNTUK MENAMPILKAN/MENYEMBUNYIKAN LOADER
// ==========================================
function toggleLoader(show, text = 'Memuat...') {
    const loader = document.getElementById('loader');
    const loaderText = document.getElementById('loaderText');

    if (loader) {
        if (show) {
            if (loaderText) loaderText.innerText = text;
            loader.classList.add('active');
        } else {
            loader.classList.remove('active');
        }
    }
}

window.addEventListener('DOMContentLoaded', () => {
    initializeSharedUI();
    toggleLoader(true, 'Memuat...');
    document.body.classList.add('page-transition-enter');
    requestAnimationFrame(() => {
        document.body.classList.remove('page-transition-enter');
    });
    attachTransitionHandlers();
    setTimeout(() => {
        if (document.getElementById('loader')?.classList.contains('active')) {
            toggleLoader(false);
        }
    }, 900);
});
// ==========================================
// ADMIN-ONLY NAV - SHOW APPROVAL LINK FOR ADMIN ONLY
// ==========================================
function applyRoleNav() {
    const navApproval = document.getElementById('navApproval');
    if (navApproval) {
        const role = (localStorage.getItem('userRole') || '').toLowerCase();
        navApproval.style.display = role === 'admin' ? 'flex' : 'none';
    }
}

// Run on DOM ready to show/hide role-gated nav items
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyRoleNav);
} else {
    applyRoleNav();
}

// ==========================================
// SIDEBAR ACTIVE STATE - HIGHLIGHT CURRENT PAGE
// ==========================================
function setActiveSidebar() {
    // Get current page filename from URL (strip query/hash)
    const path = window.location.pathname;
    const currentPage = path.substring(path.lastIndexOf('/') + 1).split('?')[0].split('#')[0];
    if (!currentPage) return;

    // Collect all sidebar links
    const sidebarLinks = document.querySelectorAll('.sidebar-nav-item, .sidebar-dropdown-item');
    
    // Remove existing active class from all sidebar links
    sidebarLinks.forEach(link => link.classList.remove('active'));

    // Match: for each unique filename, keep the simplest href (no hash fragment)
    const matchMap = {};
    sidebarLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (!href) return;
        
        const linkPage = href.split('?')[0].split('#')[0];
        if (linkPage === currentPage) {
            const hasHash = href.includes('#');
            // Prefer link without hash (e.g., "dashboard.html" over "dashboard.html#profil-ut")
            if (!matchMap[linkPage] || !hasHash) {
                matchMap[linkPage] = link;
            }
        }
    });

    // Apply active class to matched links
    Object.values(matchMap).forEach(link => link.classList.add('active'));

    // Auto-expand parent dropdown if active link is a dropdown sub-item
    const activeDropdownItem = document.querySelector('.sidebar-dropdown-item.active');
    if (activeDropdownItem) {
        const parentDropdown = activeDropdownItem.closest('.sidebar-dropdown');
        if (parentDropdown) {
            parentDropdown.classList.add('open');
        }
    }
}

// Run on DOM ready to highlight the correct nav item
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setActiveSidebar);
} else {
    setActiveSidebar();
}

// ==========================================
// SHARED RBAC — ADD/MANAGE PERMISSION (Phase 4.5.3)
// One rule used by every asset-management page:
//   admin → false (monitoring/approval only — no add/edit/delete)
//   it    → manage IT data only
//   ga    → manage GA data only
// ==========================================
function canManageData(type) {
    const role = (localStorage.getItem('userRole') || '').toLowerCase();
    if (role === 'admin') return false;
    if (role === 'it') return String(type || '').toLowerCase() === 'it';
    if (role === 'ga') return String(type || '').toLowerCase() === 'ga';
    return false;
}
window.canManageData = canManageData;

// ==========================================
// SHARED RBAC — FINISH STOCKTAKING PERMISSION
// Fail-closed: only the owning role may finish a stocktaking session.
//   admin → false (monitoring/approval/reporting only)
//   it    → true for 'it' assets only
//   ga    → true for 'ga' assets only
//   unknown/missing role → false (button hidden, never shown)
// ==========================================
function canFinishStocktaking(type) {
    const role = (localStorage.getItem('userRole') || '').toLowerCase();
    if (role === 'it') return String(type || '').toLowerCase() === 'it';
    if (role === 'ga') return String(type || '').toLowerCase() === 'ga';
    return false;
}
window.canFinishStocktaking = canFinishStocktaking;

// ==========================================
// USER MENU DROPDOWN (top-right avatar -> Profile / Logout)
// ==========================================
function closeUserMenu(menu) {
    if (!menu) return;
    menu.classList.remove('open');
    const badge = menu.querySelector('.top-user-badge');
    if (badge) badge.setAttribute('aria-expanded', 'false');
}

function toggleUserMenu(event) {
    if (event) event.stopPropagation();
    const menu = document.querySelector('.top-user-menu');
    if (!menu) return;
    const isOpen = menu.classList.toggle('open');
    const badge = menu.querySelector('.top-user-badge');
    if (badge) badge.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

function initUserMenu() {
    const menu = document.querySelector('.top-user-menu');
    if (!menu) return;

    // Close when clicking outside the menu
    document.addEventListener('click', (event) => {
        if (menu.classList.contains('open') && !menu.contains(event.target)) {
            closeUserMenu(menu);
        }
    });

    // Close on Escape
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeUserMenu(menu);
    });

    // Close after selecting a menu item (Profile / Logout)
    const dropdown = menu.querySelector('.user-menu-dropdown');
    if (dropdown) {
        dropdown.addEventListener('click', (event) => {
            if (event.target.closest('.user-menu-item')) closeUserMenu(menu);
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initUserMenu);
} else {
    initUserMenu();
}

// ==========================================
// MASTER AREA — single source of truth for Area lists (Phase 4.24)
// Area dropdowns, summary cards and charts read from master_area via
// get_areas.php, so a new Area appears everywhere without code changes.
// ==========================================
window.UTAreas = { list: [], loaded: false };

window.loadMasterAreas = async function () {
    if (window.UTAreas.loaded) return window.UTAreas.list;
    try {
        const res = await fetch('get_areas.php');
        const result = await res.json();
        if (result.status === 'success' && Array.isArray(result.data)) {
            window.UTAreas.list = result.data
                .filter(a => String(a.is_active) !== '0' && a.area_name)
                .map(a => String(a.area_name));
            window.UTAreas.loaded = true;
        }
    } catch (err) {
        console.error('Gagal memuat master area:', err);
    }
    return window.UTAreas.list;
};

function _utAreaEsc(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Fill a <select> with every active master Area. Keeps the current value when
// it is still valid, otherwise selects the first Area.
window.populateAreaSelect = function (selectEl) {
    if (!selectEl) return;
    const current = selectEl.value;
    selectEl.innerHTML = window.UTAreas.list
        .map(a => `<option value="${_utAreaEsc(a)}">${_utAreaEsc(a)}</option>`)
        .join('');
    if (current && window.UTAreas.list.includes(current)) {
        selectEl.value = current;
    } else if (selectEl.options.length) {
        selectEl.value = selectEl.options[0].value;
    }
};

// Build the clickable per-Area summary cards plus the "All" card into a
// container. Cards are fully driven by master_area (no hardcoded Areas).
// opts: { container, title(area), subtitle, iconSvg }
window.buildAreaCards = function (opts) {
    const container = opts && opts.container;
    if (!container) return;
    const icon = String(opts.iconSvg || '');
    const subtitle = String(opts.subtitle || 'Total Asset');
    const titleFn = typeof opts.title === 'function' ? opts.title : (a => 'TOTAL ASSET ' + String(a).toUpperCase());

    const cards = window.UTAreas.list.map(area => {
        const safe = _utAreaEsc(area);
        return `<div class="summary-card clickable" data-area="${safe}" onclick="filterByArea('${safe}')">
            <div class="card-icon">${icon}</div>
            <div class="card-title">${_utAreaEsc(titleFn(area))}</div>
            <div class="card-value" id="total-${safe}">0</div>
            <div class="card-subtitle">${subtitle}</div>
        </div>`;
    });
    cards.push(`<div class="summary-card clickable active-card" data-area="" onclick="filterByArea('')" style="border: 2px dashed #ffc107;">
        <div class="card-icon">${icon}</div>
        <div class="card-title">TAMPILKAN SEMUA</div>
        <div class="card-value" id="total-All">0</div>
        <div class="card-subtitle">${subtitle}</div>
    </div>`);
    container.innerHTML = cards.join('');
};
