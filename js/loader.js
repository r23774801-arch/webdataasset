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
//   admin → manage everything
//   it    → manage IT data only
//   ga    → manage GA data only
// ==========================================
function canManageData(type) {
    const role = (localStorage.getItem('userRole') || '').toLowerCase();
    if (role === 'admin') return true;
    if (role === 'it') return String(type || '').toLowerCase() === 'it';
    if (role === 'ga') return String(type || '').toLowerCase() === 'ga';
    return false;
}
window.canManageData = canManageData;

// ==========================================
// GLOBAL SIDEBAR HAMBURGER CONTROLLER
// Handles off-canvas drawer on Tablet & Mobile (<=1024px)
// ==========================================
function initializeSidebar() {
    const sidebar = document.getElementById('sidebar') || document.querySelector('.sidebar');
    if (!sidebar) return;

    // Ensure sidebar overlay element exists
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        document.body.classList.add('sidebar-open');
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.classList.remove('sidebar-open');
    }

    function toggleSidebar(e) {
        if (e) e.stopPropagation();
        if (sidebar.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    const toggleBtns = document.querySelectorAll('.sidebar-toggle-btn');
    toggleBtns.forEach(btn => {
        btn.removeEventListener('click', toggleSidebar);
        btn.addEventListener('click', toggleSidebar);
    });

    overlay.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });

    const sidebarLinks = sidebar.querySelectorAll('.sidebar-nav-item, .sidebar-dropdown-item');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 1024 && !link.classList.contains('sidebar-dropdown-toggle')) {
                closeSidebar();
            }
        });
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 1024 && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeSidebar);
} else {
    initializeSidebar();
}

