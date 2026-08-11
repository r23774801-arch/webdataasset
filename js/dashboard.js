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
    // All non-ADMIN roles manage both IT and GA assets — no lock icons needed.
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
                if (details.pending_users > 0) pendingItems.push(`Registrasi User (${details.pending_users})`);
                
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
