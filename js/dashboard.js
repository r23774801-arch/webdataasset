// ==========================================
// 1. Cek Sesi Login (Authentication)
// ==========================================
const currentUser = localStorage.getItem('currentUser');
const currentNrp = localStorage.getItem('currentNrp');
const userRole = localStorage.getItem('userRole');

// Tendang kembali ke halaman login jika belum login
if (!currentUser || !currentNrp || !userRole) {
    window.location.replace('login.html'); 
}

// ==========================================
// 2. Eksekusi Setelah Halaman Dimuat
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
    
    // --- Munculkan Toast Sambutan dari Login ---
    const welcomeMsg = localStorage.getItem('welcomeToast');
    if (welcomeMsg) {
        showToast(welcomeMsg, 'success');
        localStorage.removeItem('welcomeToast');
    }

    // --- Tampilkan Nama di Navbar ---
    const navName = document.getElementById('navName');
    if (navName) {
        navName.innerText = `${currentUser} (${userRole.toUpperCase()})`;
    }

    // --- Tampilkan Foto Kecil di Navbar ---
    const navAvatar = document.getElementById('navAvatar');
    const savedPic = localStorage.getItem(`profilePic_${currentNrp}`);
    
    if (navAvatar) {
        navAvatar.style.display = 'inline-block'; 
        if (savedPic) {
            navAvatar.src = savedPic; 
        } else {
            navAvatar.src = `https://ui-avatars.com/api/?name=${currentUser}&background=FFCC00&color=fff&size=50`;
        }
    }

    // ==========================================
    // 3. ADMIN NOTIFICATION: Unverified Data Check
    // ==========================================
    if (userRole === 'admin') {
        cekDataPending();
    }

    // ==========================================
    // 3. LOGIKA PROTEKSI AKSES (Gembok Role)
    // ==========================================
    // --- LOGIKA PROTEKSI AKSES (Gembok Vektor Bersih) ---
    const navAsetIt = document.getElementById('nav-aset-it');
    const navAsetGa = document.getElementById('nav-aset-ga');

    // Desain ikon gembok SVG datar (Flat Icon) dengan class CSS
    const svgLock = `<svg class="padlock-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/></svg>`;

    if (userRole === 'it') {
        if (navAsetGa) {
            navAsetGa.innerHTML = `Data Aset GA ${svgLock}`;
            navAsetGa.classList.add('locked');
        }
    } else if (userRole === 'ga') {
        if (navAsetIt) {
            navAsetIt.innerHTML = `Data Aset IT ${svgLock}`;
            navAsetIt.classList.add('locked');
        }
    }
});

// ==========================================
// 4. Fungsi Cek Data Pending (Admin Only)
// ==========================================
async function cekDataPending() {
    try {
        const response = await fetch('get_pending_count.php');
        const result = await response.json();
        
        if (result.status === 'success' && result.total > 0) {
            const notification = document.getElementById('admin-notification');
            const badge = document.getElementById('notification-badge');
            const message = document.getElementById('notification-message');
            
            if (notification && badge && message) {
                badge.textContent = result.total;
                
                // Customize message based on what's pending
                const details = result.details;
                const pendingItems = [];
                if (details.aset_it > 0) pendingItems.push(`Aset IT (${details.aset_it})`);
                if (details.aset_ga > 0) pendingItems.push(`Aset GA (${details.aset_ga})`);
                if (details.barang_masuk > 0) pendingItems.push(`Barang Masuk (${details.barang_masuk})`);
                if (details.barang_keluar > 0) pendingItems.push(`Barang Keluar (${details.barang_keluar})`);
                
                message.textContent = `Ada ${result.total} data aset yang belum diverifikasi: ${pendingItems.join(', ')}`;
                notification.style.display = 'flex';
            }
        }
    } catch (error) {
        console.error('Gagal memuat data pending:', error);
    }
}

// ==========================================
// 5. Fungsi Logout
// ==========================================
function logout() {
    toggleLoader(true, "Keluar dari sesi...");
    setTimeout(() => {
        sessionStorage.removeItem('isLoggedIn');
        localStorage.clear();
        window.location.replace('login.html'); 
    }, 800);
}
