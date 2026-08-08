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
    const role = (localStorage.getItem('userRole') || '').toLowerCase();
    const isAdmin = role === 'admin';

    const navApproval = document.getElementById('navApproval');
    if (navApproval) {
        navApproval.style.display = isAdmin ? 'flex' : 'none';
    }

    const navDataAkun = document.getElementById('navDataAkun');
    if (navDataAkun) {
        navDataAkun.style.display = isAdmin ? 'flex' : 'none';
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
// MOBILE SIDEBAR TOGGLE (off-canvas drawer)
// Shared for every page that renders .sidebar.
// Uses the existing .sidebar / .open classes and the
// .sidebar-overlay + .sidebar-close-btn elements.
// All listeners are delegated once => no duplicates,
// no conflicts with any pre-existing sidebar script.
// ==========================================
let sidebarOverlayEl = null;
let sidebarCloseBtnEl = null;

function getSidebarElement() {
    return document.querySelector('#sidebar, .sidebar');
}

function isMobileBreakpoint() {
    return window.matchMedia('(max-width: 768px)').matches;
}

function closeSidebar() {
    const sidebar = getSidebarElement();
    if (sidebar) sidebar.classList.remove('open');
    if (sidebarOverlayEl) sidebarOverlayEl.classList.remove('active');
    if (sidebarCloseBtnEl) sidebarCloseBtnEl.setAttribute('aria-expanded', 'false');
}

function openSidebar() {
    const sidebar = getSidebarElement();
    if (!sidebar) return;
    sidebar.classList.add('open');
    if (isMobileBreakpoint()) {
        ensureSidebarOverlay();
        ensureSidebarCloseButton();
        if (sidebarOverlayEl) sidebarOverlayEl.classList.add('active');
    }
    if (sidebarCloseBtnEl) sidebarCloseBtnEl.setAttribute('aria-expanded', 'true');
}

function toggleSidebar() {
    const sidebar = getSidebarElement();
    if (!sidebar) return;
    if (sidebar.classList.contains('open')) {
        closeSidebar();
    } else {
        openSidebar();
    }
}

function ensureSidebarOverlay() {
    if (sidebarOverlayEl) return;
    sidebarOverlayEl = document.createElement('div');
    sidebarOverlayEl.className = 'sidebar-overlay';
    sidebarOverlayEl.setAttribute('aria-hidden', 'true');
    document.body.appendChild(sidebarOverlayEl);
}

function ensureSidebarCloseButton() {
    const sidebar = getSidebarElement();
    if (!sidebar || sidebarCloseBtnEl || document.getElementById('sidebarCloseBtn')) return;
    const brand = sidebar.querySelector('.sidebar-brand');
    if (!brand) return;
    sidebarCloseBtnEl = document.createElement('button');
    sidebarCloseBtnEl.type = 'button';
    sidebarCloseBtnEl.id = 'sidebarCloseBtn';
    sidebarCloseBtnEl.className = 'sidebar-close-btn';
    sidebarCloseBtnEl.setAttribute('aria-label', 'Tutup menu');
    sidebarCloseBtnEl.setAttribute('aria-expanded', 'false');
    sidebarCloseBtnEl.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
    sidebarCloseBtnEl.addEventListener('click', closeSidebar);
    brand.appendChild(sidebarCloseBtnEl);
}

function initSidebarToggle() {
    if (!getSidebarElement()) return; // pages without a sidebar (e.g. login.html)

    ensureSidebarCloseButton(); // CSS hides it on desktop

    // Single delegated listener: open/close on hamburger click (no duplicates).
    document.addEventListener('click', function (event) {
        const toggle = event.target.closest('.sidebar-toggle-btn');
        if (toggle) {
            event.preventDefault();
            toggleSidebar();
        }
    });

    // Close when tapping the overlay.
    document.addEventListener('click', function (event) {
        if (event.target.closest('.sidebar-overlay')) {
            closeSidebar();
        }
    });

    // Close the drawer before navigating after picking a sidebar link (mobile).
    document.addEventListener('click', function (event) {
        const link = event.target.closest('.sidebar a[href]');
        if (link && !link.href.startsWith('javascript:') && isMobileBreakpoint()) {
            closeSidebar();
        }
    });

    // Close on Escape.
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeSidebar();
    });

    // Leaving the mobile breakpoint resets the drawer to closed.
    const mq = window.matchMedia('(max-width: 768px)');
    const handleMqChange = function () {
        if (!mq.matches) closeSidebar();
    };
    if (mq.addEventListener) mq.addEventListener('change', handleMqChange);
    else if (mq.addListener) mq.addListener(handleMqChange);

    // A fresh page load always starts with the drawer closed.
    closeSidebar();
}

window.toggleSidebar = toggleSidebar;
window.closeSidebar = closeSidebar;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebarToggle);
} else {
    initSidebarToggle();
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
        return `<div class="summary-card clickable" data-area="${safe}" onclick="filterByArea(this.dataset.area)">
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

// ==========================================
// SHARED ATTACHMENT PREVIEW — images get a
// thumbnail, PDF documents get an icon that
// opens an embedded preview modal.
// ==========================================
function isPdfPath(path) {
    return String(path || '').toLowerCase().endsWith('.pdf');
}

function _escAttr(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Renders the small table-cell preview for an attachment path.
//  - images -> 40x40 thumbnail opening the page's image preview modal
//  - PDF    -> a PDF icon button opening the shared PDF preview modal
function renderAttachmentPreview(path) {
    const safe = _escAttr(path || '');
    if (!safe) {
        return '<span class="text-muted" style="font-size:12px;">No Attachment</span>';
    }
    if (isPdfPath(safe)) {
        return `<button type="button" class="btn-attachment-pdf" onclick="previewPdfAttachment(this.dataset.path)" data-path="${safe}" title="Preview PDF" style="display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border:1px solid #dc3545; color:#dc3545; background:#fff; border-radius:6px; cursor:pointer; font-size:12px;">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
            PDF</button>`;
    }
    return `<img src="${safe}" alt="Attachment" width="40" height="40" style="object-fit: cover; border-radius: 4px; cursor: pointer;" onclick="previewImage(this.dataset.src)" data-src="${safe}">`;
}

// Opens the shared PDF preview modal (created lazily on first use).
function previewPdfAttachment(path) {
    let modal = document.getElementById('pdfPreviewModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'pdfPreviewModal';
        modal.className = 'image-preview-modal';
        modal.style.cssText = 'position:fixed; inset:0; z-index:10000; background:rgba(0,0,0,0.75); display:flex; align-items:center; justify-content:center; padding:24px;';
        modal.innerHTML = `<span class="image-preview-close" onclick="document.getElementById('pdfPreviewModal').style.display='none'" style="position:absolute; top:16px; right:24px; font-size:2rem; color:#fff; cursor:pointer; z-index:2;">&times;</span>
            <iframe class="image-preview-content" title="PDF Preview" style="width:90%; height:90%; border:0; border-radius:8px; background:#fff;" onclick="event.stopPropagation()"></iframe>`;
        document.body.appendChild(modal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) modal.style.display = 'none';
        });
    }
    const frame = modal.querySelector('iframe');
    if (frame) frame.src = path;
    modal.style.display = 'flex';
}

// ==========================================
// SHARED STOCKTAKING ATTACHMENT UPLOAD —
// single entry point used by aset-it.html and
// aset-ga.html. Reuses upload_attachment.php
// (JPG/JPEG/PNG/WEBP images and PDF documents).
// ==========================================
window.uploadAttachmentFile = async function (file) {
    const formData = new FormData();
    formData.append('attachment', file);
    const response = await fetch('upload_attachment.php', { method: 'POST', body: formData });
    return response.json();
};

// Renders the modal "current asset photo" — preview only, never replaced.
window.renderAssetPhotoPreview = function (path) {
    const safe = _escAttr(path || '');
    if (!safe) {
        return '<span class="text-muted" style="font-size:12px;">Tidak ada foto aset.</span>';
    }
    if (isPdfPath(safe)) {
        return '<span class="attachment-file-note">Lampiran: PDF</span>';
    }
    return `<img src="${safe}" alt="Foto Aset" class="stocktaking-asset-photo" onclick="previewImage(this.dataset.src)" data-src="${safe}">`;
};

// Renders the modal "Attachment (stocktaking evidence)" state — shows the
// existing attachment, is kept when no new file is chosen.
window.renderAttachmentNote = function (path) {
    const safe = _escAttr(path || '');
    if (!safe) {
        return '<span class="text-muted" style="font-size:12px;">Belum ada lampiran. Upload gambar (JPG/JPEG/PNG/WEBP) atau PDF.</span>';
    }
    if (isPdfPath(safe)) {
        return '<span class="attachment-file-note"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg> Lampiran saat ini: PDF (tetap dipertahankan jika tidak memilih file baru).</span>';
    }
    return `<div style="display:inline-flex; align-items:center; gap:8px; margin-top:10px; flex-wrap:wrap;">
        <img src="${safe}" alt="Lampiran" class="stocktaking-asset-photo" onclick="previewImage(this.dataset.src)" data-src="${safe}">
        <span class="text-muted" style="font-size:12px;">Lampiran saat ini (tetap dipertahankan jika tidak memilih file baru).</span>
    </div>`;
};
