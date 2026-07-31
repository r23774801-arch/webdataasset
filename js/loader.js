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
