```php
<?php
session_start();
// Anti-cache agar status email_verified selalu fresh
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
date_default_timezone_set('Asia/Jakarta');

include 'koneksi.php';
include 'email_functions.php';
$nim = $_SESSION['user'];

// Ambil nama mahasiswa
$q = $conn->prepare("SELECT nama FROM Mahasiswa WHERE nim=?");
$q->bind_param("s", $nim);
$q->execute();
$q->bind_result($nama);
if (!$q->fetch()) {
    $nama = $nim;
}
$q->close();

// Ambil data mahasiswa untuk profil
$profil = [
    'nama' => $nama,
    'nim' => $nim,
    'email' => '',
    'program_studi' => '',
    'status_aktif' => '',
    'foto' => '',
    'email_verified' => 0,
];
$q = $conn->prepare("SELECT email, program_studi, status_aktif, foto, email_verified FROM Mahasiswa WHERE nim=?");
$q->bind_param("s", $nim);
$q->execute();
$q->bind_result($profil['email'], $profil['program_studi'], $profil['status_aktif'], $profil['foto'], $profil['email_verified']);
$q->fetch();
$q->close();
$profil['email_verified'] = (int)$profil['email_verified']; // Pastikan integer

// --- HANDLE EDIT PROFIL MAHASISWA ---
$edit_feedback = '';
if (isset($_POST['simpan_edit_profil'])) {
    $new_email = trim($_POST['edit_email']);
    if ($new_email === '') {
        $edit_feedback = '<div class="alert alert-danger">Email wajib diisi.</div>';
    } else {
        // Cek duplikat email jika berubah
        $cek = $conn->prepare("SELECT nim FROM Mahasiswa WHERE email=? AND nim!=?");
        $cek->bind_param("ss", $new_email, $nim);
        $cek->execute();
        $cek->store_result();
        if ($cek->num_rows > 0) {
            $edit_feedback = '<div class="alert alert-danger">Email sudah terdaftar.</div>';
        } else {
            $stmt = $conn->prepare("UPDATE Mahasiswa SET email=?, email_verified=0 WHERE nim=?");
            $stmt->bind_param("ss", $new_email, $nim);
            if ($stmt->execute()) {
                // Reset session profil untuk memastikan data fresh
                unset($_SESSION['profil']);
                header('Location: dashboardmahasiswa.php?page=profil&edit=success');
                exit();
            } else {
                $edit_feedback = '<div class="alert alert-danger">Gagal update data.</div>';
            }
            $stmt->close();
        }
        $cek->close();
    }
}

// --- QUERY TAGIHAN AKTIF MAHASISWA ---
$tagihan_aktif = [];
$get_id = $conn->prepare("SELECT id_mahasiswa FROM Mahasiswa WHERE nim=?");
$get_id->bind_param("s", $nim);
$get_id->execute();
$get_id->bind_result($id_mahasiswa);
if ($get_id->fetch()) {
    $get_id->close();
    $q = $conn->prepare("SELECT * FROM Tagihan WHERE id_mahasiswa=? AND status_tagihan='Belum Bayar' ORDER BY batas_waktu ASC");
    $q->bind_param("i", $id_mahasiswa);
    $q->execute();
    $result = $q->get_result();
    while ($row = $result->fetch_assoc()) {
        $tagihan_aktif[] = $row;
    }
    $q->close();
} else {
    $get_id->close();
}

// --- QUERY RIWAYAT PEMBAYARAN ---
$riwayat = [];
$q = $conn->prepare("SELECT p.tanggal_bayar, p.jumlah_dibayar, p.status_verifikasi, t.semester, t.tahun_ajaran FROM Pembayaran p JOIN Tagihan t ON p.id_tagihan = t.id_tagihan WHERE t.id_mahasiswa=? ORDER BY p.tanggal_bayar DESC");
$q->bind_param("i", $id_mahasiswa);
$q->execute();
$result = $q->get_result();
while ($row = $result->fetch_assoc()) {
    $riwayat[] = $row;
}
$q->close();

// --- HANDLE SUBMIT PEMBAYARAN ---
$pembayaran_feedback = '';
if (isset($_POST['ajukan_verifikasi'])) {
    if (empty($profil['email_verified']) || !$profil['email_verified']) {
        $pembayaran_feedback = '<div class="alert alert-danger">Anda harus memverifikasi email terlebih dahulu sebelum dapat melakukan pembayaran.</div>';
    } else {
        $id_tagihan = intval($_POST['id_tagihan']);
        $metode = $_POST['metode_pembayaran'];
        $va = $_POST['va_number'];
        $jumlah = intval($_POST['jumlah_dibayar']);
        $catatan = $_POST['catatan'] ?? '';
        $file = $_FILES['bukti_bayar'];
        $allowed = ['image/jpeg', 'image/png', 'image/jpg'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $pembayaran_feedback = '<div class="alert alert-danger">Gagal upload file.</div>';
        } elseif (!in_array($file['type'], $allowed)) {
            $pembayaran_feedback = '<div class="alert alert-danger">File harus JPG/PNG.</div>';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newname = 'bukti_' . $nim . '_' . $id_tagihan . '_' . time() . '.' . $ext;
            $target = __DIR__ . '/bukti_bayar/';
            if (!is_dir($target)) mkdir($target, 0777, true);
            $filepath = $target . $newname;
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $stmt = $conn->prepare("INSERT INTO Pembayaran (id_tagihan, tanggal_bayar, metode_pembayaran, jumlah_dibayar, bukti_bayar, status_verifikasi, catatan_admin) VALUES (?, NOW(), ?, ?, ?, 'Menunggu', ?)");
                $stmt->bind_param("isiss", $id_tagihan, $metode, $jumlah, $newname, $catatan);
                if ($stmt->execute()) {
                    $pembayaran_feedback = '<div class="alert alert-success">Pengajuan verifikasi berhasil dikirim.</div>';
                    if (!empty($profil['email'])) {
                        sendPaymentNotification($profil['email'], $jumlah, $metode, date('Y-m-d H:i:s'), $catatan);
                    }
                } else {
                    $pembayaran_feedback = '<div class="alert alert-danger">Gagal menyimpan pembayaran.</div>';
                }
                $stmt->close();
            } else {
                $pembayaran_feedback = '<div class="alert alert-danger">Gagal upload file ke server.</div>';
            }
        }
    }
}

// --- HANDLE UPLOAD FOTO PROFIL MAHASISWA ---
$foto_feedback = '';
if (isset($_POST['upload_foto']) && isset($_FILES['foto_profil'])) {
    $foto = $_FILES['foto_profil'];
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $maxSize = 8 * 1024 * 1024; // 8MB
    $ext = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        $foto_feedback = '<div class="alert alert-danger mt-2">Format file tidak didukung. Hanya jpg, jpeg, png, gif, webp.</div>';
    } else if ($foto['size'] > $maxSize) {
        $foto_feedback = '<div class="alert alert-danger mt-2">Ukuran file maksimal 8MB.</div>';
    } else if ($foto['error'] === UPLOAD_ERR_OK) {
        $targetDir = __DIR__ . '/foto_profile/';
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        if (!empty($profil['foto']) && file_exists(__DIR__ . '/' . $profil['foto'])) {
            @unlink(__DIR__ . '/' . $profil['foto']);
        }
        $newName = 'foto_profile/mahasiswa_' . $nim . '.' . $ext;
        $fullPath = __DIR__ . '/' . $newName;
        if (move_uploaded_file($foto['tmp_name'], $fullPath)) {
            $stmt = $conn->prepare("UPDATE Mahasiswa SET foto=? WHERE nim=?");
            $stmt->bind_param("ss", $newName, $nim);
            if ($stmt->execute()) {
                unset($_SESSION['profil']); // Reset session profil
                header('Location: dashboardmahasiswa.php?page=profil&upload=success');
                exit();
            } else {
                $foto_feedback = '<div class="alert alert-danger mt-2">Gagal menyimpan foto ke database.</div>';
            }
            $stmt->close();
        } else {
            $foto_feedback = '<div class="alert alert-danger mt-2">Gagal mengupload foto.</div>';
        }
    } else {
        $foto_feedback = '<div class="alert alert-danger mt-2">Terjadi kesalahan saat mengupload foto.</div>';
    }
}

// --- NOTIFIKASI EMAIL: Tagihan sisa 10 hari ---
if (!empty($tagihan_aktif)) {
    foreach ($tagihan_aktif as $t) {
        $batas = strtotime($t['batas_waktu']);
        $now = time();
        $selisih = floor(($batas - $now) / 86400);
        if ($selisih === 10) {
            $cek = $conn->prepare("SELECT COUNT(*) FROM Notifikasi WHERE id_mahasiswa=? AND id_tagihan=? AND tipe_notifikasi='tenggat_10_hari'");
            $cek->bind_param("ii", $id_mahasiswa, $t['id_tagihan']);
            $cek->execute();
            $cek->bind_result($sudah);
            $cek->fetch();
            $cek->close();
            if ($sudah == 0 && !empty($profil['email'])) {
                $subject = 'Peringatan Tenggat Pembayaran UKT';
                $message = 'Tenggat pembayaran UKT Anda untuk semester ' . htmlspecialchars($t['semester']) . ' tahun ajaran ' . htmlspecialchars($t['tahun_ajaran']) . ' sisa 10 hari lagi. Mohon segera lakukan pembayaran sebelum ' . date('d M Y', strtotime($t['batas_waktu'])) . '.';
                sendGeneralNotification($profil['email'], $subject, $message, $id_mahasiswa, $t['id_tagihan'], 'tenggat_10_hari', $conn);
            }
        }
    }
}

// --- NOTIFIKASI EMAIL: Pembayaran diverifikasi (Lunas/Terverifikasi) ---
$q = $conn->prepare("SELECT p.id_pembayaran, p.status_verifikasi, p.jumlah_dibayar, p.metode_pembayaran, p.tanggal_bayar, t.id_tagihan FROM Pembayaran p JOIN Tagihan t ON p.id_tagihan = t.id_tagihan WHERE t.id_mahasiswa=? AND (p.status_verifikasi='Terverifikasi' OR p.status_verifikasi='Lunas') ORDER BY p.tanggal_bayar DESC LIMIT 1");
$q->bind_param("i", $id_mahasiswa);
$q->execute();
$q->bind_result($id_pembayaran, $status_verifikasi, $jumlah_dibayar, $metode_pembayaran, $tanggal_bayar, $id_tagihan_cek);
if ($q->fetch()) {
    $q->close();
    $cek = $conn->prepare("SELECT COUNT(*) FROM Notifikasi WHERE id_mahasiswa=? AND id_tagihan=? AND tipe_notifikasi='pembayaran_sukses'");
    $cek->bind_param("ii", $id_mahasiswa, $id_tagihan_cek);
    $cek->execute();
    $cek->bind_result($sudah);
    $cek->fetch();
    $cek->close();
    if ($sudah == 0 && !empty($profil['email'])) {
        $subject = 'Pembayaran UKT Berhasil';
        $message = 'Pembayaran UKT Anda telah diverifikasi dan dinyatakan LUNAS. Terima kasih telah melakukan pembayaran.';
        sendGeneralNotification($profil['email'], $subject, $message, $id_mahasiswa, $id_tagihan_cek, 'pembayaran_sukses', $conn);
    }
} else {
    $q->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard MyUKT Mahasiswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #e6f6f3; }
        .sidebar {
            min-height: 100vh;
            background: #2bb6a8;
            color: #fff;
            padding: 0;
        }
        .sidebar .nav-link {
            color: #fff;
            font-size: 1.1rem;
            padding: 1rem 1.5rem;
        }
        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            background: #1e7c72;
            color: #fff;
        }
        .sidebar .logo {
            font-size: 2rem;
            font-weight: bold;
            font-family: 'Segoe Script', cursive;
            color: #b2e5df;
            padding: 1.5rem 1.5rem 1rem 1.5rem;
        }
        .sidebar .user-section {
            padding: 1rem 1.5rem 0.5rem 1.5rem;
            display: flex;
            align-items: center;
        }
        .sidebar .user-section i {
            font-size: 1.7rem;
            margin-right: 0.7rem;
        }
        .sidebar .user-section span {
            font-size: 1.1rem;
        }
        .main-content {
            padding: 2.5rem 2rem 2rem 2rem;
        }
        .status-box {
            background: #fff7f7;
            border: 1.5px solid #f5c6cb;
            border-radius: 1.2rem;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px #e6f6f3;
        }
        .status-title {
            font-size: 1.1rem;
            color: #444;
            font-weight: 500;
        }
        .status-badge {
            color: #d32f2f;
            font-size: 2rem;
            margin-right: 0.5rem;
        }
        .status-badge.lunas {
            color: #388e3c;
        }
        .btn-primary, .btn-outline-primary {
            border-radius: 2rem;
            min-width: 140px;
        }
        .btn-primary {
            background: #3b9cff;
            border: 1.5px solid #3b9cff;
        }
        .btn-outline-primary {
            color: #3b9cff;
            border: 1.5px solid #3b9cff;
            background: #fff;
        }
        .riwayat-box {
            background: #e6f6f3;
            border-radius: 1rem;
            padding: 1.2rem 1.5rem;
            margin-top: 1.5rem;
        }
        .riwayat-title {
            font-weight: 500;
            margin-bottom: 0.7rem;
        }
        @media (max-width: 900px) {
            .main-content { padding: 1rem; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar position-fixed h-100" id="sidebarNav" style="transition: all 0.5s cubic-bezier(.4,2,.6,1); z-index: 1040; left: 0; top: 0; background: #2bb6a8;">
            <div class="d-flex align-items-center justify-content-between logo" style="padding-right:0.5rem;">
                <span>MyUKT</span>
                <button id="toggleSidebarBtn" class="btn btn-link p-0 ms-2" style="font-size:2rem;color:#fff;outline:none;border:none;"><i class="bi bi-arrow-left"></i></button>
            </div>
            <div class="user-section mb-3">
                <a href="dashboardmahasiswa.php?page=profil" class="d-flex align-items-center text-decoration-none<?= (!isset($_GET['page']) || $_GET['page'] === 'profil') ? ' bg-info bg-opacity-50' : '' ?>" style="border-radius:1.2rem; padding:0.3rem 1.2rem;">
                    <?php if (!empty($profil['foto']) && file_exists(__DIR__ . '/' . $profil['foto'])): ?>
                        <img src="<?= htmlspecialchars($profil['foto']) ?>" alt="Foto Profil" class="rounded-circle me-2" style="width:42px; height:42px; object-fit:cover;">
                    <?php else: ?>
                        <i class="bi bi-person-circle me-2" style="font-size:2rem; color:#fff;"></i>
                    <?php endif; ?>
                    <span class="fw-bold text-white" style="font-size:1.15rem;"><?= htmlspecialchars($nama) ?></span>
                </a>
            </div>
            <ul class="nav flex-column mb-4">
                <li class="nav-item">
                    <a class="nav-link<?= !isset($_GET['page']) ? ' active' : '' ?>" href="dashboardmahasiswa.php"><i class="bi bi-house-door me-2"></i>Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= (isset($_GET['page']) && $_GET['page'] === 'pembayaran') ? ' active' : '' ?>" href="dashboardmahasiswa.php?page=pembayaran"><i class="bi bi-cash-coin me-2"></i>Pembayaran</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= (isset($_GET['page']) && $_GET['page'] === 'riwayat') ? ' active' : '' ?>" href="dashboardmahasiswa.php?page=riwayat"><i class="bi bi-clock-history me-2"></i>Riwayat Pembayaran</a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link" href="tampilan.php"><i class="bi bi-box-arrow-left me-2"></i>Keluar</a>
                </li>
            </ul>
        </nav>
        <!-- Main Content -->
        <main class="main-content" id="mainContent" style="transition: all 0.5s cubic-bezier(.4,2,.6,1); margin-left: 16.5%; width: 83.5%;">
            <div class="header-bar mb-4 d-flex align-items-center justify-content-between" style="background:#fff;border:1.5px solid #c6ebe6;border-radius:0.2rem;min-height:56px;padding:1.2rem 2.2rem 1.2rem 2.2rem;">
                <div>
                    <button class="menu-btn d-md-none"><i class="bi bi-list"></i></button>
                    <span class="fw-bold fs-5 ms-2">
                        <?php
                        if (!isset($_GET['page'])) {
                            echo 'Home';
                        } elseif ($_GET['page'] === 'pembayaran') {
                            echo 'Pembayaran';
                        } elseif ($_GET['page'] === 'riwayat') {
                            echo 'Riwayat Pembayaran';
                        } elseif ($_GET['page'] === 'profil') {
                            echo 'Profil';
                        }
                        ?>
                    </span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-headset support-center-icon ms-3" style="font-size:1.7rem;cursor:pointer;color:#2bb6a8;"></i>
                    <button id="notifBellBtn" class="btn p-0 border-0 position-relative" style="background:transparent;outline:none;box-shadow:none;">
                        <i class="bi bi-bell" style="font-size:1.7rem;color:#2bb6a8;"></i>
                    </button>
                </div>
            </div>
            <!-- Modal Notifikasi -->
            <div id="notifModal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.18);align-items:center;justify-content:center;">
                <div style="background:#fff;padding:2.2rem 2.5rem;border-radius:1.2rem;box-shadow:0 4px 24px rgba(0,0,0,0.13);min-width:340px;max-width:95vw;position:relative;">
                    <button onclick="document.getElementById('notifModal').style.display='none'" style="position:absolute;top:12px;right:18px;background:none;border:none;font-size:1.5rem;color:#888;cursor:pointer;">×</button>
                    <div class="mb-3" style="font-size:1.5rem;font-weight:700;text-align:center;">Notifikasi</div>
                    <div id="notifList">
                        <?php
                        $adaNotif = false;
                        $adaBelumAjukan = false;
                        $adaMenunggu = false;
                        if (!empty($tagihan_aktif)) {
                            $t = $tagihan_aktif[0];
                            $q = $conn->prepare("SELECT status_verifikasi, jumlah_dibayar FROM Pembayaran WHERE id_tagihan=? ORDER BY id_pembayaran DESC LIMIT 1");
                            $q->bind_param("i", $t['id_tagihan']);
                            $q->execute();
                            $q->bind_result($status_bayar_terakhir, $jumlah_bayar_terakhir);
                            if ($q->fetch()) {
                                if ($status_bayar_terakhir === 'Menunggu' || $status_bayar_terakhir === 'Menunggu..') {
                                    $adaNotif = true;
                                    $adaMenunggu = true;
                                    echo '<div class="p-3 mb-3 rounded" style="background:#fff7c2;box-shadow:0 2px 8px #e6f6f3;display:flex;align-items:center;justify-content:space-between;gap:10px;">';
                                    echo '<div><b>Pembayaran Menunggu Verifikasi</b><br><span style="font-size:0.98rem;">Pembayaran UKT sebesar Rp '.number_format($jumlah_bayar_terakhir,0,',','.').' sedang diverifikasi oleh admin.</span></div>';
                                    echo '</div>';
                                } elseif ($status_bayar_terakhir === 'Ditolak') {
                                    $adaNotif = true;
                                    echo '<div class="p-3 mb-3 rounded" style="background:#f7bdbd;box-shadow:0 2px 8px #e6f6f3;display:flex;align-items:center;justify-content:space-between;gap:10px;">';
                                    echo '<div><b>Pembayaran Ditolak</b><br><span style="font-size:0.98rem;">Bukti tidak sah. Mohon Unggah ulang bukti yang valid.</span></div>';
                                    echo '<button class="btn btn-outline-danger" onclick="window.location.href=\'dashboardmahasiswa.php?page=riwayat\'">Lihat Riwayat</button>';
                                    echo '</div>';
                                } elseif ($status_bayar_terakhir === 'Terverifikasi' || $status_bayar_terakhir === 'Lunas') {
                                    $adaNotif = true;
                                    echo '<div class="p-3 mb-3 rounded" style="background:#b2f5c7;box-shadow:0 2px 8px #e6f6f3;display:flex;align-items:center;justify-content:space-between;gap:10px;">';
                                    echo '<div><b>Pembayaran Diverifikasi</b><br><span style="font-size:0.98rem;">Pembayaran UKT sebesar Rp '.number_format($jumlah_bayar_terakhir,0,',','.').' telah disetujui.</span></div>';
                                    echo '<button class="btn btn-outline-success" onclick="window.location.href=\'dashboardmahasiswa.php?page=riwayat\'">Lihat Riwayat</button>';
                                    echo '</div>';
                                }
                            } else {
                                $q2 = $conn->prepare("SELECT COUNT(*) FROM Pembayaran WHERE id_tagihan=?");
                                $q2->bind_param("i", $t['id_tagihan']);
                                $q2->execute();
                                $q2->bind_result($jml_bayar);
                                $q2->fetch();
                                $q2->close();
                                if ($jml_bayar == 0) {
                                    $adaNotif = true;
                                    $adaBelumAjukan = true;
                                    echo '<div class="p-3 mb-3 rounded" style="background:#fff7c2;box-shadow:0 2px 8px #e6f6f3;display:flex;align-items:center;justify-content:space-between;gap:10px;">';
                                    echo '<div><b>Belum Ajukan Verifikasi Pembayaran</b><br><span style="font-size:0.98rem;">Tenggat pembayaran: '.date('j F Y', strtotime($t['batas_waktu'])).'. Segera lakukan pembayaran sebelum jatuh tempo!</span></div>';
                                    echo '<button class="btn btn-outline-warning" onclick="window.location.href=\'dashboardmahasiswa.php?page=pembayaran\'">Bayar Sekarang</button>';
                                    echo '</div>';
                                }
                            }
                            $q->close();
                        }
                        if (!$adaMenunggu && !$adaBelumAjukan) {
                            foreach ($riwayat as $r) {
                                if ($r['status_verifikasi'] === 'Lunas' || $r['status_verifikasi'] === 'Terverifikasi') {
                                    $adaNotif = true;
                                    echo '<div class="p-3 mb-3 rounded" style="background:#b2f5c7;box-shadow:0 2px 8px #e6f6f3;display:flex;align-items:center;justify-content:space-between;gap:10px;">';
                                    echo '<div><b>Pembayaran Diverifikasi</b><br><span style="font-size:0.98rem;">Pembayaran UKT sebesar Rp '.number_format($r['jumlah_dibayar'],0,',','.').' telah disetujui.</span></div>';
                                    echo '<button class="btn btn-outline-success" onclick="window.location.href=\'dashboardmahasiswa.php?page=riwayat\'">Lihat Riwayat</button>';
                                    echo '</div>';
                                    break;
                                } elseif ($r['status_verifikasi'] === 'Ditolak') {
                                    $adaNotif = true;
                                    echo '<div class="p-3 mb-3 rounded" style="background:#f7bdbd;box-shadow:0 2px 8px #e6f6f3;display:flex;align-items:center;justify-content:space-between;gap:10px;">';
                                    echo '<div><b>Pembayaran Ditolak</b><br><span style="font-size:0.98rem;">Bukti tidak sah. Mohon Unggah ulang bukti yang valid.</span></div>';
                                    echo '<button class="btn btn-outline-danger" onclick="window.location.href=\'dashboardmahasiswa.php?page=riwayat\'">Lihat Riwayat</button>';
                                    echo '</div>';
                                    break;
                                }
                            }
                        }
                        $notif_query = $conn->prepare("SELECT judul, isi, waktu_kirim, status_baca FROM Notifikasi WHERE id_mahasiswa=? ORDER BY waktu_kirim DESC LIMIT 10");
                        $notif_query->bind_param("i", $id_mahasiswa);
                        $notif_query->execute();
                        $notif_result = $notif_query->get_result();
                        $adaNotifDB = false;
                        while ($notif = $notif_result->fetch_assoc()) {
                            $adaNotif = true;
                            $adaNotifDB = true;
                            $warna = $notif['status_baca'] === 'belum' ? '#e6f6f3' : '#f8f9fa';
                            echo '<div class="p-3 mb-3 rounded" style="background:'.$warna.';box-shadow:0 2px 8px #e6f6f3;">';
                            echo '<div><b>'.htmlspecialchars($notif['judul']).'</b><br><span style="font-size:0.98rem;">'.htmlspecialchars($notif['isi']).'</span><br><span class="text-secondary" style="font-size:0.85rem;">'.date('d/m/Y H:i', strtotime($notif['waktu_kirim'])).'</span></div>';
                            echo '</div>';
                        }
                        $notif_query->close();
                        if (!$adaNotif) {
                            echo '<div class="text-center text-secondary py-4">Tidak ada Notifikasi saat ini</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            <script>
            document.getElementById('notifBellBtn').onclick = function() {
                document.getElementById('notifModal').style.display = 'flex';
            };
            document.getElementById('notifModal').onclick = function(e) {
                if (e.target === this) this.style.display = 'none';
            };
            </script>
            <?php if (!isset($_GET['page'])): ?>
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-2">Hi <?= htmlspecialchars($nama) ?><br><span class="fw-normal fs-5">Sudahkah Kamu Membayar UKT?</span></h3>
                    <?php
                    $status_bayar = 'Belum Bayar';
                    $tagihan_terbaru = null;
                    $q = $conn->prepare("SELECT * FROM Tagihan WHERE id_mahasiswa=? AND status_tagihan='Belum Bayar' ORDER BY batas_waktu ASC, id_tagihan DESC LIMIT 1");
                    $q->bind_param("i", $id_mahasiswa);
                    $q->execute();
                    $result = $q->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $tagihan_terbaru = $row;
                        $q2 = $conn->prepare("SELECT status_verifikasi FROM Pembayaran WHERE id_tagihan=? ORDER BY id_pembayaran DESC LIMIT 1");
                        $q2->bind_param("i", $tagihan_terbaru['id_tagihan']);
                        $q2->execute();
                        $q2->bind_result($status_verifikasi);
                        if ($q2->fetch()) {
                            if ($status_verifikasi === 'Terverifikasi') {
                                $status_bayar = 'Lunas';
                            } else if ($status_verifikasi === 'Menunggu') {
                                $status_bayar = 'Menunggu';
                            } else if ($status_verifikasi === 'Ditolak') {
                                $status_bayar = 'Ditolak';
                            }
                        }
                        $q2->close();
                    } else {
                        $q3 = $conn->prepare("SELECT t.*, p.status_verifikasi FROM Tagihan t JOIN Pembayaran p ON t.id_tagihan = p.id_tagihan WHERE t.id_mahasiswa=? AND p.status_verifikasi='Terverifikasi' ORDER BY p.tanggal_bayar DESC, t.id_tagihan DESC LIMIT 1");
                        $q3->bind_param("i", $id_mahasiswa);
                        $q3->execute();
                        $result3 = $q3->get_result();
                        if ($row3 = $result3->fetch_assoc()) {
                            $tagihan_terbaru = $row3;
                            $status_bayar = 'Lunas';
                        }
                        $q3->close();
                    }
                    $q->close();
                    ?>
                    <div class="status-box mb-4">
                        <div class="status-title mb-2">Pembayaran Semester Ini</div>
                        <?php if ($status_bayar === 'Lunas'): ?>
                            <div class="d-flex align-items-center mb-2">
                                <span class="status-badge lunas"><i class="bi bi-check-circle-fill"></i></span>
                                <span class="fs-4 fw-bold text-success ms-2">Lunas</span>
                            </div>
                        <?php elseif ($status_bayar === 'Menunggu'): ?>
                            <div class="d-flex align-items-center mb-2">
                                <span class="status-badge"><i class="bi bi-clock-history"></i></span>
                                <span class="fs-4 fw-bold text-warning ms-2">Menunggu Verifikasi</span>
                            </div>
                        <?php elseif ($status_bayar === 'Ditolak'): ?>
                            <div class="d-flex align-items-center mb-2">
                                <span class="status-badge"><i class="bi bi-x-circle-fill"></i></span>
                                <span class="fs-4 fw-bold text-danger ms-2">Ditolak</span>
                            </div>
                        <?php else: ?>
                            <div class="d-flex align-items-center mb-2">
                                <span class="status-badge"><i class="bi bi-x-circle-fill"></i></span>
                                <span class="fs-4 fw-bold text-danger ms-2">Belum Bayar</span>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            Tagihan: <b>Rp <?= isset($tagihan_terbaru['jumlah_tagihan']) ? number_format($tagihan_terbaru['jumlah_tagihan'],0,',','.') : '0' ?></b> - Jatuh Tempo: <b><?= isset($tagihan_terbaru['batas_waktu']) ? date('d M Y', strtotime($tagihan_terbaru['batas_waktu'])) : '-' ?></b>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if ($status_bayar === 'Belum Bayar' || $status_bayar === 'Ditolak'): ?>
                                <a href="dashboardmahasiswa.php?page=pembayaran" class="btn btn-primary px-4">Bayar Sekarang</a>
                            <?php endif; ?>
                            <a href="dashboardmahasiswa.php?page=riwayat" class="btn btn-outline-primary px-4">Lihat Tagihan</a>
                        </div>
                    </div>
                    <div class="riwayat-box p-3">
                        <div class="riwayat-title mb-2">Riwayat Singkat</div>
                        <table class="table table-borderless mb-1" style="background:transparent;">
                            <tbody>
                            <?php
                            $riwayat_singkat = array_slice($riwayat, 0, 3);
                            foreach ($riwayat_singkat as $row): ?>
                                <tr>
                                    <td><?= isset($row['tanggal_bayar']) ? htmlspecialchars(date('d/m/Y', strtotime($row['tanggal_bayar']))) : '-' ?></td>
                                    <td>Rp <?= isset($row['jumlah_dibayar']) ? number_format($row['jumlah_dibayar'],0,',','.') : '0' ?></td>
                                    <td><?= isset($row['status_verifikasi']) ? htmlspecialchars($row['status_verifikasi']) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <a href="dashboardmahasiswa.php?page=riwayat" class="text-decoration-underline text-primary" style="font-size:0.95rem;">Lainnya...</a>
                    </div>
                </div>
            </div>
            <?php elseif (isset($_GET['page']) && $_GET['page'] === 'pembayaran'): ?>
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-3"><i class="bi bi-cash-coin me-2"></i>Pembayaran</h3>
                    <?php if ($pembayaran_feedback) echo $pembayaran_feedback; ?>
                    <div class="bg-white p-4 rounded shadow-sm" style="max-width:700px;">
                        <?php if (!$profil['email_verified']): ?>
                            <div class="alert alert-warning text-center">Anda harus memverifikasi email terlebih dahulu sebelum dapat melakukan pembayaran.<br>
                            <a href="dashboardmahasiswa.php?page=profil" class="btn btn-warning btn-sm mt-2"><i class="bi bi-envelope-check me-1"></i> Verifikasi Email</a></div>
                        <?php else: ?>
                            <?php
                            $tagihan_belum_lunas = [];
                            foreach ($tagihan_aktif as $t) {
                                $status_bayar = 'Belum Bayar';
                                $q = $conn->prepare("SELECT status_verifikasi FROM Pembayaran WHERE id_tagihan=? ORDER BY id_pembayaran DESC LIMIT 1");
                                $q->bind_param("i", $t['id_tagihan']);
                                $q->execute();
                                $q->bind_result($status_verifikasi);
                                if ($q->fetch()) {
                                    if ($status_verifikasi === 'Terverifikasi') {
                                        $status_bayar = 'Lunas';
                                    } elseif ($status_verifikasi === 'Menunggu') {
                                        $status_bayar = 'Menunggu';
                                    } elseif ($status_verifikasi === 'Ditolak') {
                                        $status_bayar = 'Ditolak';
                                    }
                                }
                                $q->close();
                                $t['status_bayar'] = $status_bayar;
                                $tagihan_belum_lunas[] = $t;
                            }
                            $tagihan_tampil = [];
                            foreach ($tagihan_belum_lunas as $t) {
                                if ($t['status_bayar'] !== 'Lunas') {
                                    $tagihan_tampil[] = $t;
                                    break;
                                }
                            }
                            if (empty($tagihan_tampil) && !empty($tagihan_belum_lunas)) {
                                $tagihan_tampil[] = $tagihan_belum_lunas[0];
                            }
                            ?>
                            <?php if (empty($tagihan_tampil)): ?>
                                <div class="text-center text-secondary py-4">Tidak ada tagihan aktif.</div>
                            <?php else: ?>
                                <?php foreach ($tagihan_tampil as $t): ?>
                                <div class="mb-3 border-bottom pb-3">
                                    <b>Semester <?= htmlspecialchars($t['semester']) ?>, Tahun Ajaran <?= htmlspecialchars($t['tahun_ajaran']) ?></b><br>
                                    Total: <b>Rp <?= number_format($t['jumlah_tagihan'],0,',','.') ?></b><br>
                                    Batas Waktu: <b><?= date('d M Y', strtotime($t['batas_waktu'])) ?></b><br>
                                    Status: <b class="<?php
                                        if ($t['status_bayar'] === 'Lunas') {
                                            echo 'text-success';
                                        } elseif ($t['status_bayar'] === 'Menunggu') {
                                            echo 'text-warning';
                                        } elseif ($t['status_bayar'] === 'Ditolak') {
                                            echo 'text-danger';
                                        } else {
                                            echo 'text-danger';
                                        }
                                    ?>"><?php echo htmlspecialchars($t['status_bayar']); ?></b>
                                    <?php if ($t['status_bayar'] === 'Belum Bayar' || $t['status_bayar'] === 'Ditolak'): ?>
                                        <form method="post" enctype="multipart/form-data" class="mt-3">
                                            <input type="hidden" name="id_tagihan" value="<?= $t['id_tagihan'] ?>">
                                            <input type="hidden" name="jumlah_dibayar" value="<?= $t['jumlah_tagihan'] ?>">
                                            <div class="mb-2">
                                                <label class="form-label fw-bold">Pilih Metode Pembayaran</label>
                                                <select class="form-select" name="metode_pembayaran" required onchange="updateVA(this, 'va<?= $t['id_tagihan'] ?>')">
                                                    <option value="">Pilih Metode</option>
                                                    <option value="Dana">Dana</option>
                                                    <option value="Transfer">Transfer</option>
                                                    <option value="OVO">OVO</option>
                                                    <option value="Gopay">Gopay</option>
                                                </select>
                                                <span class="ms-2 text-primary">No Virtual Account (VA): <b id="va<?= $t['id_tagihan'] ?>">-</b></span>
                                                <input type="hidden" name="va_number" id="inputva<?= $t['id_tagihan'] ?>">
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label fw-bold">Unggah Bukti Pembayaran (JPG/PNG)</label>
                                                <input type="file" class="form-control" name="bukti_bayar" accept="image/jpeg,image/png" required>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label">Catatan Tambahan (Opsional)</label>
                                                <textarea class="form-control" name="catatan" rows="2"></textarea>
                                            </div>
                                            <button class="btn btn-primary px-4" type="submit" name="ajukan_verifikasi">Ajukan Verifikasi</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <script>
            function updateVA(sel, id) {
                var va = {
                    'Dana': '888100' + <?= json_encode($nim) ?>,
                    'Transfer': '0091' + <?= json_encode($nim) ?>,
                    'OVO': '39358' + <?= json_encode($nim) ?>,
                    'Gopay': '898' + <?= json_encode($nim) ?>
                };
                var val = sel.value;
                document.getElementById(id).innerText = va[val] || '-';
                document.getElementById('input'+id).value = va[val] || '';
            }
            </script>
            <?php elseif ($_GET['page'] === 'riwayat'): ?>
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-3"><i class="bi bi-clock-history me-2"></i>Riwayat Pembayaran</h3>
                    <div class="bg-white p-4 rounded shadow-sm" style="max-width:900px;">
                        <?php if (empty($riwayat)): ?>
                            <div class="text-center text-secondary py-4">Belum ada riwayat pembayaran.</div>
                        <?php else: ?>
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Jumlah</th>
                                        <th>Status</th>
                                        <th>Semester</th>
                                        <th>Tahun Ajaran</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($riwayat as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($r['tanggal_bayar']))) ?></td>
                                        <td>Rp. <?= number_format($r['jumlah_dibayar'], 0, ',', '.') ?></td>
                                        <td><?= htmlspecialchars($r['status_verifikasi']) ?></td>
                                        <td><?= htmlspecialchars($r['semester']) ?></td>
                                        <td><?= htmlspecialchars($r['tahun_ajaran']) ?></td>
                                        <td>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus data ini?');">
                                                <input type="hidden" name="hapus_riwayat" value="1">
                                                <input type="hidden" name="tanggal_bayar" value="<?= htmlspecialchars($r['tanggal_bayar']) ?>">
                                                <input type="hidden" name="jumlah_dibayar" value="<?= htmlspecialchars($r['jumlah_dibayar']) ?>">
                                                <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i> Hapus</button>
                                            </form>
                                            <a href="download_pdf.php?tanggal=<?= urlencode($r['tanggal_bayar']) ?>&jumlah=<?= urlencode($r['jumlah_dibayar']) ?>" class="btn btn-outline-primary btn-sm ms-1" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
            if (isset($_POST['hapus_riwayat'], $_POST['tanggal_bayar'], $_POST['jumlah_dibayar'])) {
                $tanggal = $_POST['tanggal_bayar'];
                $jumlah = $_POST['jumlah_dibayar'];
                $del = $conn->prepare("DELETE FROM Pembayaran WHERE tanggal_bayar=? AND jumlah_dibayar=? AND id_tagihan IN (SELECT id_tagihan FROM Tagihan WHERE id_mahasiswa=?)");
                $del->bind_param("sii", $tanggal, $jumlah, $id_mahasiswa);
                if ($del->execute()) {
                    echo '<div class="alert alert-success">Data riwayat pembayaran berhasil dihapus.</div>';
                    echo '<script>setTimeout(function(){ location.reload(); }, 1000);</script>';
                } else {
                    echo '<div class="alert alert-danger">Gagal menghapus data riwayat pembayaran.</div>';
                }
                $del->close();
            }
            ?>
            <?php elseif ($_GET['page'] === 'profil'): ?>
            <?php
            if (isset($_GET['verif']) && $_GET['verif'] === 'success') {
                echo '<div class="alert alert-success text-center">Email Anda berhasil diverifikasi. Silakan lanjutkan pembayaran UKT.</div>';
            }
            $ganti_pass_feedback = '';
            if (isset($_POST['ganti_password'])) {
                $old = $_POST['old_password'] ?? '';
                $new = $_POST['new_password'] ?? '';
                $confirm = $_POST['confirm_password'] ?? '';
                if ($old === '' || $new === '' || $confirm === '') {
                    $ganti_pass_feedback = '<div class="alert alert-danger">Semua field wajib diisi.</div>';
                } else if ($new !== $confirm) {
                    $ganti_pass_feedback = '<div class="alert alert-danger">Konfirmasi password tidak cocok.</div>';
                } else {
                    $cek = $conn->prepare("SELECT password FROM Mahasiswa WHERE nim=?");
                    $cek->bind_param("s", $nim);
                    $cek->execute();
                    $cek->bind_result($pass_db);
                    $cek->fetch();
                    $cek->close();
                    if ($old !== $pass_db) {
                        $ganti_pass_feedback = '<div class="alert alert-danger">Password lama salah.</div>';
                    } else {
                        $upd = $conn->prepare("UPDATE Mahasiswa SET password=? WHERE nim=?");
                        $upd->bind_param("ss", $new, $nim);
                        if ($upd->execute()) {
                            $ganti_pass_feedback = '<div class="alert alert-success">Password berhasil diubah.</div>';
                        } else {
                            $ganti_pass_feedback = '<div class="alert alert-danger">Gagal mengubah password.</div>';
                        }
                        $upd->close();
                    }
                }
            }
            $verif_feedback = '';
            if (isset($_POST['kirim_verifikasi_email'])) {
                $result = sendVerificationEmail($nim, $profil['email'], $conn);
                $verif_feedback = '<div class="alert alert-' . ($result['success'] ? 'success' : 'danger') . '">' . htmlspecialchars($result['message']) . '</div>';
            }
            ?>
            <div class="row">
                <div class="col-12">
                    <div class="bg-white p-4 rounded shadow-sm" style="max-width:1100px; position:relative;">
                        <div class="d-flex flex-wrap align-items-start gap-4">
                            <div style="min-width:240px;">
                                <div class="bg-secondary bg-opacity-25 rounded d-flex flex-column align-items-center justify-content-center" style="height:320px;">
                                    <?php if (!empty($profil['foto'])): ?>
                                        <img src="<?= htmlspecialchars($profil['foto']) ?>" alt="Foto Profil" class="rounded-circle mb-2" style="width:160px; height:160px; object-fit:cover;">
                                    <?php else: ?>
                                        <i class="bi bi-person-circle" style="font-size:7rem; color:#2bb6a8;"></i>
                                    <?php endif; ?>
                                    <form method="post" enctype="multipart/form-data" class="mt-3 w-100">
                                        <input type="file" name="foto_profil" class="form-control mb-2" accept="image/*">
                                        <button type="submit" name="upload_foto" class="btn btn-info w-100" style="border-radius:1rem; font-weight:500;">Upload</button>
                                    </form>
                                    <?php if ($foto_feedback) echo $foto_feedback; ?>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <?php if (isset($_GET['edit']) && $_GET['edit'] === '1'): ?>
                                    <form method="post">
                                        <h3 class="fw-bold mb-2">Edit Profil</h3>
                                        <?php if ($edit_feedback) echo $edit_feedback; ?>
                                        <div class="row mb-2">
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label">NIM</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($profil['nim']) ?>" readonly disabled>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label">Email</label>
                                                <input type="email" class="form-control" name="edit_email" value="<?= htmlspecialchars($profil['email']) ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label">Program Studi</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($profil['program_studi']) ?>" readonly disabled>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label">Status</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($profil['status_aktif']) ?>" readonly disabled>
                                            </div>
                                        </div>
                                        <button type="submit" name="simpan_edit_profil" class="btn btn-success px-4">Simpan</button>
                                        <a href="dashboardmahasiswa.php?page=profil" class="btn btn-secondary ms-2 px-4">Batal</a>
                                    </form>
                                <?php else: ?>
                                    <h3 class="fw-bold mb-2"><?= htmlspecialchars($profil['nama']) ?></h3>
                                    <div class="row mb-2">
                                        <div class="col-md-6">
                                            <div><span class="text-secondary">NIM</span> : <b><?= htmlspecialchars($profil['nim']) ?></b></div>
                                            <div><span class="text-secondary">Program studi</span> : <?= htmlspecialchars($profil['program_studi']) ?></div>
                                            <div><span class="text-secondary">Status</span> : <?= htmlspecialchars($profil['status_aktif']) ?></div>
                                            <?php
                                            // Info UKT & Semester (format singkat)
                                            if (!empty($riwayat)) {
                                                $last = $riwayat[0];
                                                echo '<div>ukt: ' . htmlspecialchars($last['semester']) . '</div>';
                                                echo '<div>semester: ' . htmlspecialchars($last['semester']) . '</div>';
                                            } elseif (!empty($tagihan_aktif)) {
                                                $aktif = $tagihan_aktif[0];
                                                echo '<div>ukt: ' . htmlspecialchars($aktif['semester']) . '</div>';
                                                echo '<div>semester: ' . htmlspecialchars($aktif['semester']) . '</div>';
                                            }
                                            ?>
                                        </div>
                                        <div class="col-md-6">
                                            <div><i class="bi bi-envelope me-2"></i><?= htmlspecialchars($profil['email']) ?>
                                                <?php if ($profil['email_verified']): ?>
                                                    <span class="text-success ms-2"><i class="bi bi-check-circle"></i> Terverifikasi</span>
                                                <?php else: ?>
                                                    <span class="text-danger ms-2"><i class="bi bi-x-circle"></i> Belum Terverifikasi</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <a href="dashboardmahasiswa.php?page=profil&edit=1" class="btn btn-outline-success mb-3" style="border-radius:0.7rem; font-weight:500; float:right;"><i class="bi bi-pencil-square me-1"></i>Edit</a>
                                    <div class="clearfix"></div>
                                    <hr>
                                    <?php if ($ganti_pass_feedback) echo $ganti_pass_feedback; ?>
                                    <div class="accordion" id="accordionPassword">
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingPassword">
                                                <button class="accordion-button fw-bold collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePassword" aria-expanded="false" aria-controls="collapsePassword">
                                                    Ganti password
                                                </button>
                                            </h2>
                                            <div id="collapsePassword" class="accordion-collapse collapse" aria-labelledby="headingPassword" data-bs-parent="#accordionPassword">
                                                <div class="accordion-body">
                                                    <form method="post">
                                                        <div class="mb-2">
                                                            <input type="password" class="form-control" name="old_password" placeholder="Password Lama" required>
                                                        </div>
                                                        <div class="mb-2">
                                                            <input type="password" class="form-control" name="new_password" placeholder="Password Baru" required>
                                                        </div>
                                                        <div class="mb-2">
                                                            <input type="password" class="form-control" name="confirm_password" placeholder="Konfirmasi Password" required>
                                                        </div>
                                                        <button class="btn btn-success px-4" type="submit" name="ganti_password">Simpan</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!$profil['email_verified']): ?>
                        <form method="post" style="position:absolute;bottom:24px;right:32px;z-index:10;">
                            <button type="submit" name="kirim_verifikasi_email" class="btn btn-warning btn-sm">
                                <i class="bi bi-envelope-check me-1"></i> Verifikasi Email
                            </button>
                        </form>
                        <?php endif; ?>
                        <?= $verif_feedback ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const sidebar = document.getElementById('sidebarNav');
const mainContent = document.getElementById('mainContent');
const toggleBtn = document.getElementById('toggleSidebarBtn');
let sidebarOpen = true;

function setSidebar(open) {
    sidebarOpen = open;
    if (!open) {
        sidebar.style.marginLeft = '-240px';
        sidebar.style.opacity = '0';
        sidebar.style.pointerEvents = 'none';
        toggleBtn.style.display = 'none';
        mainContent.style.marginLeft = '0';
        mainContent.style.width = '100%';
        if (!document.getElementById('showSidebarBtn')) {
            const btn = document.createElement('button');
            btn.id = 'showSidebarBtn';
            btn.className = 'btn p-0 border-0';
            btn.style.position = 'fixed';
            btn.style.top = '12px';
            btn.style.left = '12px';
            btn.style.zIndex = '2000';
            btn.style.width = '36px';
            btn.style.height = '36px';
            btn.style.background = 'transparent';
            btn.style.boxShadow = 'none';
            btn.style.display = 'flex';
            btn.style.alignItems = 'center';
            btn.style.justifyContent = 'center';
            btn.innerHTML = '<i class="bi bi-list" style="font-size:1.7rem;color:#2196f3;"></i>';
            btn.onclick = function() {
                setSidebar(true);
                btn.remove();
                toggleBtn.style.display = '';
            };
            document.body.appendChild(btn);
        }
    } else {
        sidebar.style.marginLeft = '0';
        sidebar.style.opacity = '1';
        sidebar.style.pointerEvents = 'auto';
        toggleBtn.style.display = '';
        toggleBtn.innerHTML = '<i class="bi bi-arrow-left"></i>';
        mainContent.style.marginLeft = '16.5%';
        mainContent.style.width = '83.5%';
        const btn = document.getElementById('showSidebarBtn');
        if (btn) btn.remove();
    }
}
toggleBtn.onclick = function() {
    setSidebar(!sidebarOpen);
};
window.addEventListener('resize', function() {
    if (window.innerWidth < 768) setSidebar(false);
    else setSidebar(true);
});
if (window.innerWidth < 768) setSidebar(false);
</script>
</body>
</html>
```