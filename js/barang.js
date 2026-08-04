// ==========================================
// BARANG PAGES — shared controller for the 4
// separated pages (Masuk/Keluar × IT/GA).
//
// Each page sets window.BARANG_CONFIG:
//   {
//     module: 'masuk' | 'keluar',
//     type: 'it' | 'ga',
//     title: 'Barang Masuk IT',
//     hasSupplier: true | false,
//     tbodyId, infoId, buttonsId,
//     modalId, addBtnId, modalTitleId, modalSaveId
//   }
// ==========================================
(function () {
    const cfg = window.BARANG_CONFIG || {};
    const MODULE = cfg.module || 'masuk';
    const TYPE = cfg.type || 'it';
    const API = `barang_${TYPE}`;
    const HAS_SUPPLIER = !!cfg.hasSupplier;

    const itemsPerPage = 10;
    let currentPage = 1;
    let allData = [];
    let sourceData = [];

    // ---------- RBAC ----------
    function userRole() {
        return (localStorage.getItem('userRole') || '').toLowerCase();
    }

    // Can this role create records of TYPE? (Add flow only — Action column removed)
    // Delegates to the shared rule in js/loader.js (admin manages everything).
    function canManage() {
        if (typeof window.canManageData === 'function') {
            return window.canManageData(TYPE);
        }
        const role = userRole();
        if (role === 'admin') return true;
        if (role === 'it') return TYPE === 'it';
        if (role === 'ga') return TYPE === 'ga';
        return false;
    }

    // ---------- UI helpers ----------
    function $(id) { return document.getElementById(id); }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function openModal(id) { const m = $(id); if (m) m.style.display = 'flex'; }
    function closeModal(id) { const m = $(id); if (m) m.style.display = 'none'; }

    function renderEmptyState(title, message) {
        return `<div class="table-empty-state">
            <div class="empty-state-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <path d="M7.5 9.5 12 12l4.5-2.5"></path>
                    <path d="M12 12v5"></path>
                </svg>
            </div>
            <strong>${title}</strong>
            <span>${message}</span>
        </div>`;
    }

    function loadUserInfo() {
        const nrp = localStorage.getItem('currentNrp') || 'User';
        const username = localStorage.getItem('currentUser') || 'User';
        const role = localStorage.getItem('userRole') || 'admin';
        const initial = username.charAt(0).toUpperCase();
        const set = (id, val) => { const el = $(id); if (el) el.textContent = val; };
        set('sidebarNrp', nrp + ' (' + role.toUpperCase() + ')');
        set('sidebarRole', role.toUpperCase());
        set('sidebarAvatar', initial);
        set('topAvatar', initial);
        set('topUserName', username);
        set('topUserRole', role.toUpperCase());
    }

    function logout() {
        sessionStorage.removeItem('isLoggedIn');
        localStorage.clear();
        window.location.replace('login.html');
    }

    // ---------- Summary cards (by area) ----------
    const AREAS = ['Main Office', 'Part BKJ', 'Part BIU', 'Part BIU 3', 'BIU Service', 'Kel.', 'PTK'];

    function updateSummaryCards(data) {
        const totals = {};
        AREAS.forEach(a => { totals[a] = 0; });
        data.forEach(item => {
            if (item.area && Object.prototype.hasOwnProperty.call(totals, item.area)) {
                totals[item.area] += parseInt(item.jumlah, 10) || 1;
            }
        });
        AREAS.forEach(area => {
            const el = $(`total-${area}`);
            if (el) el.textContent = totals[area];
        });
    }

    // ---------- Table rendering + pagination ----------
    function colSpan() { return HAS_SUPPLIER ? 8 : 7; }

    function renderPage() {
        const tbody = $(cfg.tbodyId);
        if (!tbody) return;
        tbody.innerHTML = '';
        const start = (currentPage - 1) * itemsPerPage;
        const pageData = allData.slice(start, start + itemsPerPage);

        if (pageData.length > 0) {
            pageData.forEach((item, i) => {
                const rowNum = start + i + 1;
                tbody.innerHTML += `<tr>
                    <td>${rowNum}</td>
                    <td>${esc(item.asset_number) || '-'}</td>
                    <td class="td-wrap">${esc(item.asset_name) || '-'}</td>
                    <td>${esc(item.jumlah)}</td>
                    ${HAS_SUPPLIER ? `<td class="td-wrap">${esc(item.supplier) || '-'}</td>` : ''}
                    <td>${esc(item.tanggal) || '-'}</td>
                    <td>${esc(item.pic) || '-'}</td>
                    <td>${esc(item.area) || '-'}</td>
                </tr>`;
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="${colSpan()}">${renderEmptyState('Tidak ada data barang.', 'Gunakan tombol Tambah untuk menambahkan data.')}</td></tr>`;
        }
        updateSummaryCards(allData);
        renderPagination();
    }

    function renderPagination() {
        const info = $(cfg.infoId);
        const btns = $(cfg.buttonsId);
        if (!info || !btns) return;
        const total = allData.length;
        const totalPages = Math.ceil(total / itemsPerPage);
        if (total === 0) {
            info.textContent = 'Menampilkan 0-0 dari 0 data';
            btns.innerHTML = '';
            return;
        }
        const start = (currentPage - 1) * itemsPerPage + 1;
        const end = Math.min(currentPage * itemsPerPage, total);
        info.textContent = `Menampilkan ${start}-${end} dari ${total} data`;
        let html = '';
        html += `<button class="pagination-btn" onclick="goToPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>&laquo; Prev</button>`;
        for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage) {
                html += `<button class="pagination-btn pagination-active">${i}</button>`;
            } else if (i === 1 || i === totalPages || Math.abs(i - currentPage) <= 2) {
                html += `<button class="pagination-btn" onclick="goToPage(${i})">${i}</button>`;
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                html += `<span class="pagination-ellipsis">...</span>`;
            }
        }
        html += `<button class="pagination-btn" onclick="goToPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>Next &raquo;</button>`;
        btns.innerHTML = html;
    }

    // ---------- Column filters (shared table-filters.js) ----------
    function applyColumnFilters() {
        const formatters = {
            tanggal: item => {
                if (!item.tanggal) return '';
                const d = new Date(item.tanggal);
                if (Number.isNaN(d.getTime())) return item.tanggal;
                const day = String(d.getDate()).padStart(2, '0');
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const year = d.getFullYear();
                return `${item.tanggal} ${day}/${month}/${year}`;
            }
        };
        sourceData = sourceData || [];
        allData = sourceData.filter(item => {
            if (typeof window.matchesSharedPopupFilters === 'function') {
                return window.matchesSharedPopupFilters(item, formatters);
            }
            return true;
        });
        currentPage = 1;
        renderPage();
    }

    // ---------- Data loading ----------
    async function loadData() {
        if (typeof toggleLoader === 'function') toggleLoader(true, 'Memuat data...');
        try {
            const res = await fetch(`get_${API}.php?module=${MODULE}`);
            const result = await res.json();
            if (typeof toggleLoader === 'function') toggleLoader(false);
            if (result.status === 'success') {
                sourceData = result.data || [];
                applyColumnFilters();
            } else {
                sourceData = [];
                allData = [];
                currentPage = 1;
                renderPage();
            }
        } catch (err) {
            if (typeof toggleLoader === 'function') toggleLoader(false);
            console.error(err);
            if (typeof showToast === 'function') showToast('Gagal memuat data dari server.', 'error');
        }
    }

    // ---------- Add ----------
    function collectForm() {
        const g = id => { const el = $(id); return el ? el.value : ''; };
        return {
            module: MODULE,
            asset_number: g('assetNumber').trim(),
            asset_name: g('assetName').trim(),
            jumlah: g('jumlah').trim(),
            supplier: g('supplier').trim(),
            tanggal: g('tanggal'),
            pic: g('pic').trim(),
            area: g('area')
        };
    }

    function clearForm() {
        ['assetNumber', 'assetName', 'jumlah', 'supplier', 'tanggal', 'pic'].forEach(id => {
            const el = $(id);
            if (el) el.value = '';
        });
        const area = $('area');
        if (area) area.value = 'Main Office';
    }

    function setModalTitle(text) {
        const t = $(cfg.modalTitleId || 'modalTitle');
        if (t) t.textContent = text;
        const btn = $(cfg.modalSaveId || 'modalSave');
        if (btn) btn.textContent = 'Simpan Data';
    }

    window.bukaModal = function () {
        clearForm();
        setModalTitle(cfg.addLabel || 'Tambah Barang');
        openModal(cfg.modalId);
    };

    window.tutupModal = function () { closeModal(cfg.modalId); };

    window.simpanBarang = async function () {
        if (!canManage()) {
            if (typeof showToast === 'function') showToast('Anda tidak memiliki izin untuk mengelola data ini.', 'error');
            return;
        }
        const data = collectForm();
        if (!data.asset_name || !data.jumlah || !data.tanggal) {
            if (typeof showToast === 'function') showToast('Asset Name, Jumlah, dan Tanggal wajib diisi!', 'info');
            return;
        }
        if (typeof toggleLoader === 'function') toggleLoader(true, 'Menyimpan data...');
        try {
            const res = await fetch(`tambah_${API}.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (typeof toggleLoader === 'function') toggleLoader(false);
            if (typeof showToast === 'function') showToast(result.message, result.status === 'success' ? 'success' : 'error');
            if (result.status === 'success') {
                closeModal(cfg.modalId);
                clearForm();
                loadData();
            }
        } catch (err) {
            if (typeof toggleLoader === 'function') toggleLoader(false);
            if (typeof showToast === 'function') showToast('Terjadi kesalahan saat menghubungi server.', 'error');
        }
    };

    // ---------- RBAC UI gating ----------
    function applyRbacUi() {
        const can = canManage();
        const addBtn = $(cfg.addBtnId || 'btnTambah');
        if (addBtn) addBtn.style.display = can ? 'inline-flex' : 'none';
    }

    // ---------- Init ----------
    function toggleSidebarDropdown() {
        const dropdown = document.querySelector('.sidebar-dropdown');
        if (dropdown) dropdown.classList.toggle('open');
    }

    window.toggleSidebarDropdown = toggleSidebarDropdown;
    window.logout = logout;
    window.goToPage = function (page) {
        const totalPages = Math.ceil(allData.length / itemsPerPage);
        if (page < 1 || page > totalPages) return;
        currentPage = page;
        renderPage();
    };

    document.addEventListener('DOMContentLoaded', () => {
        loadUserInfo();
        applyRbacUi();
        loadData();
    });
})();
