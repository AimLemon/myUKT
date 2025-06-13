<?php
session_start();
if (!isset($_SESSION['user']) && !isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}
date_default_timezone_set('Asia/Jakarta');
include_once 'config.php';
include_once 'email_functions.php';
// ===================== END SESSION & AUTH =====================

// ===================== ROLE CHECK =====================
$isAdmin = isset($_SESSION['admin']);
// ===================== END ROLE CHECK =====================

// ===================== PROSES HAPUS TAGIHAN (AJAX) =====================
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_tagihan']) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    include 'koneksi.php';
    $id_tagihan = intval($_POST['id_tagihan']);
    $stmt = $conn->prepare('DELETE FROM Tagihan WHERE id_tagihan=?');
    $stmt->bind_param('i', $id_tagihan);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus tagihan.']);
    }
    $stmt->close();
    $conn->close();
    exit();
}
// ===================== END PROSES HAPUS TAGIHAN (AJAX) =====================

// ===================== PROSES HAPUS PEMBAYARAN (AJAX) =====================
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_pembayaran']) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    include 'koneksi.php';
    $id_pembayaran = intval($_POST['id_pembayaran']);

    // Mulai transaksi
    $conn->begin_transaction();
    try {
        // Ambil id_mahasiswa dan id_tagihan untuk riwayat
        $stmt = $conn->prepare("SELECT p.id_tagihan, t.id_mahasiswa FROM Pembayaran p JOIN Tagihan t ON p.id_tagihan = t.id_tagihan WHERE p.id_pembayaran = ?");
        $stmt->bind_param('i', $id_pembayaran);
        $stmt->execute();
        $stmt->bind_result($id_tagihan, $id_mahasiswa);
        if ($stmt->fetch()) {
            $stmt->close();

            // Catat aksi hapus di Riwayat_Pembayaran
            $aksi = 'Hapus Pembayaran';
            $ins = $conn->prepare("INSERT INTO Riwayat_Pembayaran (id_pembayaran, id_mahasiswa, aksi) VALUES (?, ?, ?)");
            $ins->bind_param('iis', $id_pembayaran, $id_mahasiswa, $aksi);
            $ins->execute();
            $ins->close();

            // Hapus entri pembayaran
            $del = $conn->prepare("DELETE FROM Pembayaran WHERE id_pembayaran = ?");
            $del->bind_param('i', $id_pembayaran);
            if ($del->execute()) {
                $conn->commit();
                echo json_encode(['success' => true]);
            } else {
                throw new Exception('Gagal menghapus pembayaran.');
            }
            $del->close();
        } else {
            throw new Exception('Pembayaran tidak ditemukan.');
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    $conn->close();
    exit();
}
// ===================== END PROSES HAPUS PEMBAYARAN (AJAX) =====================

// ===================== PROSES KELOLA MAHASISWA (ADMIN) =====================
$feedback = '';
$mahasiswa = [];
if ($isAdmin && isset($_GET['page']) && $_GET['page'] === 'kelola-mahasiswa') {
    include 'koneksi.php';
    $feedback = '';
    // Query data mahasiswa
    $result = $conn->query("SELECT nim, nama, email, program_studi, status_aktif FROM Mahasiswa ORDER BY nim ASC");
    if ($result !== false) {
        $mahasiswa = $result->fetch_all(MYSQLI_ASSOC);
    }
    // Proses edit data mahasiswa
    if (isset($_POST['edit_mahasiswa'])) {
        $nim_lama = trim($_POST['nim_lama']);
        $nim = trim($_POST['nim_edit']);
        $nama = trim($_POST['nama_edit']);
        $email = trim($_POST['email_edit']);
        $prodi = trim($_POST['prodi_edit']);
        $status = trim($_POST['status_edit']);
        if ($nim && $nama && $email && $prodi && $status) {
            // Cek duplikat NIM/email jika NIM atau email berubah
            if ($nim !== $nim_lama) {
                $cek = $conn->prepare("SELECT nim FROM Mahasiswa WHERE (nim=? OR email=?) AND nim!=?");
                $cek->bind_param("sss", $nim, $email, $nim_lama);
                $cek->execute();
                $cek->store_result();
                if ($cek->num_rows > 0) {
                    $feedback = '<div class="alert alert-danger">NIM atau Email sudah terdaftar.</div>';
                } else {
                    $stmt = $conn->prepare("UPDATE Mahasiswa SET nim=?, nama=?, email=?, program_studi=?, status_aktif=? WHERE nim=?");
                    if ($stmt) {
                        $stmt->bind_param("ssssss", $nim, $nama, $email, $prodi, $status, $nim_lama);
                        if ($stmt->execute()) {
                            header('Location: dashboard.php?page=kelola-mahasiswa&edit=1');
                            exit();
                        } else {
                            $feedback = '<div class="alert alert-danger">Gagal update data: ' . htmlspecialchars($stmt->error) . '</div>';
                        }
                        $stmt->close();
                    } else {
                        $feedback = '<div class="alert alert-danger">Gagal update data: ' . htmlspecialchars($conn->error) . '</div>';
                    }
                }
                $cek->close();
            } else {
                $stmt = $conn->prepare("UPDATE Mahasiswa SET nim=?, nama=?, email=?, program_studi=?, status_aktif=? WHERE nim=?");
                if ($stmt) {
                    $stmt->bind_param("ssssss", $nim, $nama, $email, $prodi, $status, $nim_lama);
                    if ($stmt->execute()) {
                        header('Location: dashboard.php?page=kelola-mahasiswa&edit=1');
                        exit();
                    } else {
                        $feedback = '<div class="alert alert-danger">Gagal update data: ' . htmlspecialchars($stmt->error) . '</div>';
                    }
                    $stmt->close();
                } else {
                    $feedback = '<div class="alert alert-danger">Gagal update data: ' . htmlspecialchars($conn->error) . '</div>';
                }
            }
        } else {
            $feedback = '<div class="alert alert-danger">Semua field wajib diisi.</div>';
        }
    }
    if (isset($_POST['tambah_mahasiswa'])) {
        $nim = trim($_POST['nim']);
        $nama = trim($_POST['nama']);
        $email = trim($_POST['email']);
        $prodi = trim($_POST['prodi']);
        $status = trim($_POST['status']);
        $password = $nim;
        if ($nim && $nama && $email && $prodi && $status) {
            $cek = $conn->prepare("SELECT nim FROM Mahasiswa WHERE nim=? OR email=?");
            $cek->bind_param("ss", $nim, $email);
            $cek->execute();
            $cek->store_result();
            if ($cek->num_rows > 0) {
                $feedback = '<div class="alert alert-danger">NIM atau Email sudah terdaftar.</div>';
            } else {
                $stmt = $conn->prepare("INSERT INTO Mahasiswa (nim, nama, email, program_studi, status_aktif, password) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ssssss", $nim, $nama, $email, $prodi, $status, $password);
                    if ($stmt->execute()) {
                        header('Location: dashboard.php?page=kelola-mahasiswa&success=1');
                        exit();
                    } else {
                        $feedback = '<div class="alert alert-danger">Gagal menambah data: ' . htmlspecialchars($stmt->error) . '</div>';
                    }
                } else {
                    $feedback = '<div class="alert alert-danger">Gagal menambah data: ' . htmlspecialchars($conn->error) . '</div>';
                }
            }
        } else {
            $feedback = '<div class="alert alert-danger">Semua field wajib diisi.</div>';
        }
    }
    if (isset($_POST['hapus_mahasiswa'])) {
        $nim = trim($_POST['nim']);
        if ($nim) {
            $getId = $conn->prepare("SELECT id_mahasiswa FROM Mahasiswa WHERE nim=?");
            $getId->bind_param("s", $nim);
            $getId->execute();
            $getId->bind_result($id_mahasiswa);
            if ($getId->fetch()) {
                $getId->close();
                $id_tagihan_list = [];
                $getTagihan = $conn->prepare("SELECT id_tagihan FROM tagihan WHERE id_mahasiswa=?");
                $getTagihan->bind_param("i", $id_mahasiswa);
                $getTagihan->execute();
                $getTagihan->bind_result($id_tagihan);
                while ($getTagihan->fetch()) {
                    $id_tagihan_list[] = $id_tagihan;
                }
                $getTagihan->close();
                if (!empty($id_tagihan_list)) {
                    $in = implode(',', array_fill(0, count($id_tagihan_list), '?'));
                    $types = str_repeat('i', count($id_tagihan_list));
                    $delPembayaran = $conn->prepare("DELETE FROM pembayaran WHERE id_tagihan IN ($in)");
                    $delPembayaran->bind_param($types, ...$id_tagihan_list);
                    $delPembayaran->execute();
                    $delPembayaran->close();
                }
                $delNotif = $conn->prepare("DELETE FROM notifikasi WHERE id_mahasiswa=?");
                $delNotif->bind_param("i", $id_mahasiswa);
                $delNotif->execute();
                $delNotif->close();
                $delRiwayat = $conn->prepare("DELETE FROM riwayat_pembayaran WHERE id_mahasiswa=?");
                $delRiwayat->bind_param("i", $id_mahasiswa);
                $delRiwayat->execute();
                $delRiwayat->close();
                $delTagihan = $conn->prepare("DELETE FROM tagihan WHERE id_mahasiswa=?");
                $delTagihan->bind_param("i", $id_mahasiswa);
                $delTagihan->execute();
                $delTagihan->close();
                $stmt = $conn->prepare("DELETE FROM Mahasiswa WHERE nim=?");
                if ($stmt) {
                    $stmt->bind_param("s", $nim);
                    if ($stmt->execute()) {
                        header('Location: dashboard.php?page=kelola-mahasiswa&deleted=1');
                        exit();
                    } else {
                        $feedback = '<div class="alert alert-danger">Gagal menghapus data: ' . htmlspecialchars($stmt->error) . '</div>';
                    }
                } else {
                    $feedback = '<div class="alert alert-danger">Gagal menghapus data: ' . htmlspecialchars($conn->error) . '</div>';
                }
            } else {
                $feedback = '<div class="alert alert-danger">Data mahasiswa tidak ditemukan.</div>';
            }
        }
    }
}
// ===================== END PROSES KELOLA MAHASISWA (ADMIN) =====================

// ===================== AMBIL DATA ADMIN =====================
if ($isAdmin) {
    include 'koneksi.php';
    $adminName = 'Admin';
    $adminId = '';
    $adminEmail = '';
    $adminPhoto = '';
    $adminEmailSession = $_SESSION['admin'];
    $q = $conn->prepare("SELECT id_admin, nama, email, foto FROM Admin WHERE email=? LIMIT 1");
    if ($q === false) {
        die('<div class="alert alert-danger">Query admin gagal: ' . htmlspecialchars($conn->error) . '</div>');
    }
    $q->bind_param("s", $adminEmailSession);
    $q->execute();
    $q->bind_result($adminId, $adminName, $adminEmail, $adminPhoto);
    $q->fetch();
    $q->close();
    // Mahasiswa Aktif
    $q = $conn->query("SELECT COUNT(*) FROM Mahasiswa WHERE status_aktif='aktif'");
    $mahasiswaAktif = ($q && $row = $q->fetch_row()) ? (int)$row[0] : 0;
    // Pembayaran Belum Diverifikasi
    $q = $conn->query("SELECT COUNT(*) FROM Pembayaran WHERE status_verifikasi='Menunggu'");
    $belumVerifikasi = ($q && $row = $q->fetch_row()) ? (int)$row[0] : 0;
    // Total Pembayaran (Total Tagihan)
    $q = $conn->query("SELECT SUM(jumlah_tagihan) FROM Tagihan");
    $totalPembayaran = ($q && $row = $q->fetch_row()) ? (int)$row[0] : 0;
    // Statistik Mahasiswa Aktif per semester
    $statistikLabel = [];
    $statistikData = [];
    $q = $conn->query("SELECT t.semester, t.tahun_ajaran, COUNT(DISTINCT t.id_mahasiswa) as jumlah FROM Tagihan t JOIN Mahasiswa m ON t.id_mahasiswa=m.id_mahasiswa WHERE m.status_aktif='aktif' GROUP BY t.tahun_ajaran, t.semester ORDER BY t.tahun_ajaran, t.semester");
    if ($q) {
        while ($row = $q->fetch_assoc()) {
            $semesterInt = (int)$row['semester'];
            $label = ($semesterInt % 2 == 0 ? 'Genap ' : 'Ganjil ') . $row['tahun_ajaran'];
            $statistikLabel[] = $label;
            $statistikData[] = (int)$row['jumlah'];
        }
    }
    // Notifikasi: jumlah pembayaran diajukan
    $notif_count = $belumVerifikasi;
    $notifikasi = [
        $notif_count . ' Bukti Pembayaran Baru Perlu Diverifikasi'
    ];
} else {
    // Data dummy untuk mahasiswa dashboard
    $nama = 'Aditya';
    $status_bayar = false;
    $tagihan = 500000;
    $jatuh_tempo = '9 September 2025';
    $riwayat = [
        ['tanggal' => '7 Aug 2025', 'jumlah' => 500000, 'status' => 'Menunggu..'],
        ['tanggal' => '13 Jan 2025', 'jumlah' => 500000, 'status' => 'Lunas'],
    ];
}
// ===================== END AMBIL DATA ADMIN =====================

// ===================== HTML & STYLE =====================
require_once __DIR__ . '/PHPMailer-PHPMailer-19debc7/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer-PHPMailer-19debc7/src/SMTP.php';
require_once __DIR__ . '/PHPMailer-PHPMailer-19debc7/src/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard MyUKT</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ===================== DASHBOARD STYLE ===================== */
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
        .stat-box {
            border-radius: 1.2rem;
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.2rem;
            font-size: 1.5rem;
            font-weight: bold;
            color: #000;
            display: inline-block;
            min-width: 220px;
        }
        .stat-blue { background: #4fc3f7; color: #000; }
        .stat-red { background: #ef9a9a; color: #000; }
        .stat-green { background: #69f0ae; color: #000; }
        .notif-list { font-size: 1.1rem; margin-top: 1.5rem; }
        .notif-list li { margin-bottom: 0.5rem; }
        .search-box {
            margin-top: 1.5rem;
            max-width: 350px;
        }
        .chart-container {
            background: #fff;
            border-radius: 1.2rem;
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.2rem;
            min-width: 350px;
        }
        .admin-header-icon {
            font-size: 2rem;
            margin-left: 1.5rem;
            color: #2bb6a8;
        }
        .status-box {
            background: #fff0f0;
            border-radius: 1.2rem;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px #e6f6f3;
        }
        .status-box .status-title {
            font-size: 1.2rem;
            color: #444;
            font-weight: 500;
        }
        .status-box .status-badge {
            font-size: 1.5rem;
            font-weight: bold;
            color: #d32f2f;
            margin-right: 0.5rem;
        }
        .status-box .status-badge.lunas {
            color: #388e3c;
        }
        .status-box .btn {
            border-radius: 2rem;
            min-width: 120px;
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
        .header-bar {
            background: #fff;
            border-bottom: 1px solid #b2e5df;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header-bar .menu-btn {
            font-size: 2rem;
            color: #2bb6a8;
            background: none;
            border: none;
        }
        .header-bar .profile-icon {
            font-size: 2rem;
            color: #2bb6a8;
        }
        @media (max-width: 900px) {
            .main-content { padding: 1rem; }
            .header-bar { padding: 1rem; }
        }
        /* ===================== END DASHBOARD STYLE ===================== */
    </style>
</head>
<body>
<?php
// Tentukan judul halaman dinamis
if (!isset($_GET['page']) || $_GET['page'] === 'home') {
    $judulHalaman = 'Home';
} elseif ($_GET['page'] === 'kelola-mahasiswa') {
    $judulHalaman = 'Kelola Mahasiswa';
} elseif ($_GET['page'] === 'tambah-tagihan') {
    $judulHalaman = 'Tambah Tagihan';
} elseif ($_GET['page'] === 'verifikasi-pembayaran') {
    $judulHalaman = 'Verifikasi Pembayaran';
} elseif ($_GET['page'] === 'profil') {
    $judulHalaman = 'Profil';
} else {
    $judulHalaman = 'Dashboard';
}
?>

<div class="container-fluid">
    <div class="row">
        <!-- ===================== SIDEBAR ===================== -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar">
            <div class="logo">MyUKT</div>
            <div class="user-section mb-3">
                <a href="dashboard.php?page=profil" class="d-flex align-items-center text-white text-decoration-none fw-bold" style="font-size:1.1rem;<?= (isset($_GET['page']) && $_GET['page']==='profil') ? ' background:#1e7c72; border-radius:1.5rem; padding:0.2rem 1rem;' : '' ?>">
                    <?php if ($isAdmin && $adminPhoto): ?>
                        <img src="<?= htmlspecialchars($adminPhoto) ?>" alt="Foto Profil" class="rounded-circle me-2" style="width:50px; height:50px; object-fit:cover;">
                    <?php else: ?>
                        <i class="bi bi-person-circle me-2" style="font-size:2.2rem;"></i>
                    <?php endif; ?>
                    <span><?= $isAdmin ? htmlspecialchars($adminName) : 'Username' ?></span>
                </a>
            </div>
            <ul class="nav flex-column mb-4">
                <li class="nav-item">
                    <a class="nav-link<?= !isset($_GET['page']) ? ' active' : '' ?>" href="dashboard.php"><i class="bi bi-house-door me-2"></i>Home</a>
                </li>
                <?php if ($isAdmin): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= (isset($_GET['page']) && $_GET['page'] === 'kelola-mahasiswa') ? ' active' : '' ?>" href="dashboard.php?page=kelola-mahasiswa"><i class="bi bi-journal-text me-2"></i>Kelola Mahasiswa</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= (isset($_GET['page']) && $_GET['page'] === 'tambah-tagihan') ? ' active' : '' ?>" href="dashboard.php?page=tambah-tagihan"><i class="bi bi-plus-circle me-2"></i>Tambah Tagihan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= (isset($_GET['page']) && $_GET['page'] === 'verifikasi-pembayaran') ? ' active' : '' ?>" href="dashboard.php?page=verifikasi-pembayaran"><i class="bi bi-check2-square me-2"></i>Verifikasi Pembayaran</a>
                    </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="#"><i class="bi bi-cash-coin me-2"></i>Pembayaran</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#"><i class="bi bi-clock-history me-2"></i>Riwayat Pembayaran</a>
                </li>
                <?php endif; ?>
                <li class="nav-item mt-4">
                    <a class="nav-link" href="login.php"><i class="bi bi-box-arrow-left me-2"></i>Keluar</a>
                </li>
            </ul>
        </nav>
        <!-- ===================== END SIDEBAR ===================== -->
        <!-- ===================== MAIN CONTENT ===================== -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="header-bar mb-4 d-flex align-items-center justify-content-between">
                <div>
                    <button class="menu-btn d-md-none"><i class="bi bi-list"></i></button>
                    <span class="fw-bold fs-5 ms-2"><?= htmlspecialchars($judulHalaman) ?></span>
                </div>
                <?php if ($isAdmin): ?>
                <span>
                    <i class="bi bi-headset admin-header-icon"></i>
                    <i class="bi bi-bell admin-header-icon"></i>
                </span>
                <?php else: ?>
                <i class="bi bi-person-circle profile-icon"></i>
                <?php endif; ?>
            </div>
            <?php if ($isAdmin && (!isset($_GET['page']) || $_GET['page'] === 'home')): ?>
            <div class="row">
                <div class="col-lg-5">
                    <h3 class="mb-3">Statistik Cepat</h3>
                    <div class="stat-box stat-blue mb-2">Mahasiswa Aktif<br><span style="font-size:2.5rem; color:#000;"><?= $mahasiswaAktif ?></span></div>
                    <div class="stat-box stat-red mb-2">Pembayaran Belum Diverifikasi<br><span style="font-size:2.5rem; color:#000;"><?= $belumVerifikasi ?></span></div>
                    <div class="stat-box stat-green mb-2">Total Pembayaran Hari Ini<br><span style="font-size:2rem; color:#000;">Rp <?= number_format($totalPembayaran, 0, ',', '.') ?></span></div>
                    <div class="notif-list mt-4">
                        <b>Notifikasi</b>
                        <ul>
                            <?php foreach ($notifikasi as $notif): ?>
                                <li><?= htmlspecialchars($notif) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-7">
                    <h3 class="mb-3">Statistik Cepat</h3>
                    <div class="chart-container">
                        <canvas id="statChart"></canvas>
                    </div>
                </div>
            </div>
            <script>
                const ctx = document.getElementById('statChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?= json_encode($statistikLabel) ?>,
                        datasets: [{
                            label: 'Mahasiswa Aktif',
                            data: <?= json_encode($statistikData) ?>,
                            borderColor: '#2bb6a8',
                            backgroundColor: 'rgba(43,182,168,0.1)',
                            tension: 0.3,
                            fill: true,
                            pointRadius: 5,
                            pointBackgroundColor: '#2bb6a8',
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { stepSize: 500 } }
                        }
                    }
                });
            </script>
            <?php endif; ?>
            <?php if ($isAdmin && isset($_GET['page']) && $_GET['page'] === 'kelola-mahasiswa'): ?>
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-3">Kelola Mahasiswa</h3>
                    <?php
                    if (isset($_GET['success']) && empty($feedback)) {
                        echo '<div class="alert alert-success">Data mahasiswa berhasil ditambahkan.</div>';
                    }
                    if (isset($_GET['deleted'])) {
                        echo '<div class="alert alert-success">Data mahasiswa berhasil dihapus.</div>';
                    }
                    if (isset($_GET['edit'])) {
                        echo '<div class="alert alert-success">Data mahasiswa berhasil diupdate.</div>';
                    }
                    if (!empty($feedback)) echo $feedback;
                    ?>
                    <div class="bg-white p-4 rounded shadow-sm">
                        <form class="d-flex mb-3" method="get" action="dashboard.php">
                            <input type="hidden" name="page" value="kelola-mahasiswa">
                        </form>
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>NIM</th>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>Program Studi</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mahasiswa as $m): ?>
                                <tr>
                                    <td><?= htmlspecialchars($m['nim']) ?></td>
                                    <td><?= htmlspecialchars($m['nama']) ?></td>
                                    <td><?= htmlspecialchars($m['email']) ?></td>
                                    <td><?= htmlspecialchars($m['program_studi']) ?></td>
                                    <td>
                                        <?php if (strtolower($m['status_aktif']) === 'aktif'): ?>
                                            <span class="badge bg-success px-3 py-2" style="font-size:1rem;">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary px-3 py-2" style="font-size:1rem;">Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="nim" value="<?= htmlspecialchars($m['nim']) ?>">
                                            <button type="submit" name="hapus_mahasiswa" class="btn btn-sm btn-danger" onclick="return confirm('Hapus mahasiswa ini?')"><i class="bi bi-trash"></i></button>
                                        </form>
                                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editMahasiswaModal" 
                                            data-nim="<?= htmlspecialchars($m['nim']) ?>" 
                                            data-nama="<?= htmlspecialchars($m['nama']) ?>" 
                                            data-email="<?= htmlspecialchars($m['email']) ?>" 
                                            data-prodi="<?= htmlspecialchars($m['program_studi']) ?>" 
                                            data-status="<?= htmlspecialchars($m['status_aktif']) ?>">
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <!-- Modal Edit Mahasiswa -->
                        <div class="modal fade" id="editMahasiswaModal" tabindex="-1" aria-labelledby="editMahasiswaModalLabel" aria-hidden="true">
                          <div class="modal-dialog">
                            <div class="modal-content">
                              <form method="post" id="formEditMahasiswa" autocomplete="off">
                                <div class="modal-header">
                                  <h5 class="modal-title" id="editMahasiswaModalLabel">Edit Data Mahasiswa</h5>
                                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                  <input type="hidden" name="nim_lama" id="editNimLama">
                                  <div class="mb-3">
                                    <label for="editNim" class="form-label">NIM</label>
                                    <input type="text" class="form-control" name="nim_edit" id="editNim" required pattern="\d*" maxlength="20">
                                  </div>
                                  <div class="mb-3">
                                    <label for="editNama" class="form-label">Nama</label>
                                    <input type="text" class="form-control" name="nama_edit" id="editNama" required>
                                  </div>
                                  <div class="mb-3">
                                    <label for="editEmail" class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email_edit" id="editEmail" required>
                                  </div>
                                  <div class="mb-3">
                                    <label for="editProdi" class="form-label">Program Studi</label>
                                    <input type="text" class="form-control" name="prodi_edit" id="editProdi" required>
                                  </div>
                                  <div class="mb-3">
                                    <label for="editStatus" class="form-label">Status</label>
                                    <select class="form-select" name="status_edit" id="editStatus" required>
                                      <option value="Aktif">Aktif</option>
                                      <option value="Nonaktif">Nonaktif</option>
                                    </select>
                                  </div>
                                </div>
                                <div class="modal-footer">
                                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                  <button type="submit" name="edit_mahasiswa" class="btn btn-primary">Simpan Perubahan</button>
                                </div>
                              </form>
                            </div>
                          </div>
                        </div>
                        <script>
                        const editModal = document.getElementById('editMahasiswaModal');
                        editModal.addEventListener('show.bs.modal', function (event) {
                          const button = event.relatedTarget;
                          document.getElementById('editNimLama').value = button.getAttribute('data-nim');
                          document.getElementById('editNim').value = button.getAttribute('data-nim');
                          document.getElementById('editNama').value = button.getAttribute('data-nama');
                          document.getElementById('editEmail').value = button.getAttribute('data-email');
                          document.getElementById('editProdi').value = button.getAttribute('data-prodi');
                          document.getElementById('editStatus').value = button.getAttribute('data-status');
                        });
                        const editNim = document.getElementById('editNim');
                        editNim.addEventListener('input', function(e) {
                          this.value = this.value.replace(/[^0-9]/g, '');
                        });
                        const formEdit = document.getElementById('formEditMahasiswa');
                        formEdit.addEventListener('submit', function(e) {
                          let valid = true;
                          let fields = formEdit.querySelectorAll('input, select');
                          fields.forEach(function(field) {
                            if (!field.value.trim()) valid = false;
                          });
                          if (!valid) {
                            e.preventDefault();
                            alert('Wajib mengisi semua data pada form!');
                            return false;
                          }
                          if (!/^\d+$/.test(editNim.value)) {
                            e.preventDefault();
                            alert('NIM hanya boleh diisi angka!');
                            editNim.focus();
                            return false;
                          }
                        });
                        </script>
                        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
                        <hr>
                        <h5 class="mb-3">Tambah dan Hapus Data Mahasiswa/i</h5>
                        <form class="row g-2" method="post" id="formTambahMahasiswa" autocomplete="off">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="nim" id="inputNIM" placeholder="NIM" required pattern="\d*" maxlength="20" inputmode="numeric" autocomplete="off">
                            </div>
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="nama" placeholder="Nama Lengkap" required autocomplete="off">
                            </div>
                            <div class="col-md-4">
                                <input type="email" class="form-control" name="email" placeholder="Email" required autocomplete="off">
                            </div>
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="prodi" placeholder="Program Studi" required autocomplete="off">
                            </div>
                            <div class="col-md-4">
                                <select class="form-control" name="status" required>
                                    <option value="">Status</option>
                                    <option value="Aktif">Aktif</option>
                                    <option value="Nonaktif">Nonaktif</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end gap-2">
                                <button type="submit" name="tambah_mahasiswa" class="btn btn-info w-50">Tambah</button>
                            </div>
                        </form>
                        <script>
                        const inputNIM = document.getElementById('inputNIM');
                        inputNIM.addEventListener('input', function(e) {
                            this.value = this.value.replace(/[^0-9]/g, '');
                        });
                        const form = document.getElementById('formTambahMahasiswa');
                        form.addEventListener('submit', function(e) {
                            let valid = true;
                            let fields = form.querySelectorAll('input, select');
                            fields.forEach(function(field) {
                                if (!field.value.trim()) valid = false;
                            });
                            if (!valid) {
                                e.preventDefault();
                                alert('Wajib mengisi semua data pada form!');
                                return false;
                            }
                            if (!/^\d+$/.test(inputNIM.value)) {
                                e.preventDefault();
                                alert('NIM hanya boleh diisi angka!');
                                inputNIM.focus();
                                return false;
                            }
                        });
                        </script>
                    </div>
                </div>
            </div>
            <?php elseif ($isAdmin && isset($_GET['page']) && $_GET['page'] === 'tambah-tagihan'): ?>
            <?php
            // Define UKT categories and their amounts
            $uktCategories = [
                'UKT1' => 3000000,
                'UKT2' => 6000000,
                'UKT3' => 9000000,
                'UKT4' => 12000000
            ];
            // Ambil data untuk filter
            include 'koneksi.php';
            $semesterList = [1, 2, 3, 4, 5, 6, 7, 8];
            $uktList = array_keys($uktCategories);

            // Fungsi untuk menghasilkan opsi tahun ajaran dengan Ganjil/Genap
            function getTahunAjaranOptions($startYear = null, $jumlahTahun = 7) {
                $options = [];
                $startYear = $startYear ?: (int)date('Y') - 2;
                for ($y = $startYear; $y < $startYear + $jumlahTahun; $y++) {
                    $options[] = ['value' => "$y:Genap", 'label' => "Genap $y"];
                    $options[] = ['value' => "$y:Ganjil", 'label' => "Ganjil $y"];
                }
                return $options;
            }
            $tahunAjaranOptions = getTahunAjaranOptions();

            // Proses tambah tagihan (individu atau massal)
            $tagihan_feedback = '';
            if ($isAdmin && isset($_POST['mode'], $_POST['jatuh_tempo'], $_POST['semester'], $_POST['tahun_ajaran'])) {
                $mode = trim($_POST['mode']);
                $keterangan = trim($_POST['keterangan'] ?? '-');
                $jatuh_tempo = trim($_POST['jatuh_tempo']);
                $semester = (int)$_POST['semester'];
                $tahun_ajaran_input = trim($_POST['tahun_ajaran']);

                // Pisahkan tahun dan paritas dari input tahun_ajaran
                $tahun_parts = explode(':', $tahun_ajaran_input);
                if (count($tahun_parts) !== 2 || !in_array($tahun_parts[1], ['Ganjil', 'Genap']) || !preg_match('/^\d{4}$/', $tahun_parts[0])) {
                    $tagihan_feedback = '<div class="alert alert-danger">Format tahun ajaran tidak valid.</div>';
                } else {
                    $tahun_ajaran = $tahun_parts[0];
                    $paritas = $tahun_parts[1];

                    // Validasi paritas dengan semester
                    $isSemesterGenap = $semester % 2 === 0;
                    if (($paritas === 'Ganjil' && $isSemesterGenap) || ($paritas === 'Genap' && !$isSemesterGenap)) {
                        $tagihan_feedback = '<div class="alert alert-danger">Semester tidak sesuai dengan paritas tahun ajaran (Ganjil untuk semester 1,3,5,7; Genap untuk semester 2,4,6,8).</div>';
                    } elseif (!$jatuh_tempo || !$tahun_ajaran || !$semester) {
                        $tagihan_feedback = '<div class="alert alert-danger">Semester, jatuh tempo, dan tahun ajaran wajib diisi.</div>';
                    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $jatuh_tempo)) {
                        $tagihan_feedback = '<div class="alert alert-danger">Format jatuh tempo tidak valid.</div>';
                    } else {
                        $conn->begin_transaction();
                        try {
                            $insertStmt = $conn->prepare("INSERT INTO Tagihan (id_mahasiswa, semester, tahun_ajaran, jumlah_tagihan, batas_waktu, status_tagihan, keterangan) VALUES (?, ?, ?, ?, ?, 'Belum Bayar', ?)");
                            $success_count = 0;
                            if ($mode === 'individu') {
                                $nim = trim($_POST['nim']);
                                $ukt = trim($_POST['ukt_individu']);
                                if (!$nim || !$ukt) {
                                    throw new Exception('NIM dan Kategori UKT wajib diisi untuk mode individu.');
                                }
                                if (!array_key_exists($ukt, $uktCategories)) {
                                    throw new Exception('Kategori UKT tidak valid.');
                                }
                                $stmt = $conn->prepare("SELECT id_mahasiswa, semester, email FROM Mahasiswa WHERE nim = ? AND status_aktif = 'aktif'");
                                $stmt->bind_param("s", $nim);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $student = $result->fetch_assoc();
                                $stmt->close();
                                if (!$student) {
                                    throw new Exception('Mahasiswa dengan NIM tersebut tidak ditemukan atau tidak aktif.');
                                }
                                // Cek duplikat tagihan
                                $checkStmt = $conn->prepare("SELECT id_tagihan FROM Tagihan WHERE id_mahasiswa = ? AND semester = ? AND tahun_ajaran = ?");
                                $checkStmt->bind_param("iis", $student['id_mahasiswa'], $semester, $tahun_ajaran);
                                $checkStmt->execute();
                                $checkStmt->store_result();
                                if ($checkStmt->num_rows > 0) {
                                    throw new Exception('Tagihan untuk mahasiswa ini pada semester dan tahun ajaran tersebut sudah ada.');
                                }
                                $checkStmt->close();
                                // Tambah tagihan
                                $jumlah_int = $uktCategories[$ukt];
                                $insertStmt->bind_param("iisiss", $student['id_mahasiswa'], $semester, $tahun_ajaran, $jumlah_int, $jatuh_tempo, $keterangan);
                                if ($insertStmt->execute()) {
                                    $success_count++;
                                    // Kirim notifikasi email
                                    if ($student['email']) {
                                        require_once __DIR__ . '/vendor/autoload.php';
                                        $mail = new PHPMailer(true);
                                        try {
                                            $mail->isSMTP();
                                            $mail->Host = SMTP_HOST;
                                            $mail->SMTPAuth = true;
                                            $mail->Username = SMTP_USERNAME;
                                            $mail->Password = SMTP_PASSWORD;
                                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                            $mail->Port = SMTP_PORT;
                                            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                                            $mail->addAddress($student['email']);
                                            $mail->isHTML(true);
                                            $mail->Subject = 'Tagihan Baru - ' . APP_NAME;
                                            $jumlah_formatted = number_format($jumlah_int, 0, ',', '.');
                                            $semester_safe = htmlspecialchars($semester);
                                            $tahun_ajaran_safe = htmlspecialchars($tahun_ajaran);
                                            $jatuh_tempo_formatted = date('d/m/Y', strtotime($jatuh_tempo));
                                            $keterangan_safe = htmlspecialchars($keterangan);
                                            $paritas_safe = htmlspecialchars($paritas);
                                            $mail->Body = <<<EOD
            <!DOCTYPE html>
            <html>
            <body>
                <h2>Tagihan Baru</h2>
                <p>Tagihan baru telah dibuat di MyUKT.</p>
                <p><strong>Detail Tagihan:</strong></p>
                <ul>
                    <li>Jumlah: Rp {$jumlah_formatted}</li>
                    <li>Semester: {$semester_safe}</li>
                    <li>Tahun Ajaran: {$paritas_safe} {$tahun_ajaran_safe}</li>
                    <li>Batas Waktu: {$jatuh_tempo_formatted}</li>
                    <li>Keterangan: {$keterangan_safe}</li>
                </ul>
                <p>Silakan login ke MyUKT untuk melihat detail dan melakukan pembayaran.</p>
            </body>
            </html>
            EOD;
                                            $mail->send();
                                            $tagihan_feedback = '<div class="alert alert-success">Berhasil menambahkan tagihan untuk mahasiswa dengan NIM ' . htmlspecialchars($nim) . ' dan notifikasi email dikirim.</div>';
                                        } catch (Exception $mail_error) {
                                            $tagihan_feedback = '<div class="alert alert-success">Berhasil menambahkan tagihan untuk mahasiswa dengan NIM ' . htmlspecialchars($nim) . ', tetapi gagal mengirim email: ' . htmlspecialchars($mail_error->getMessage()) . '</div>';
                                        }
                                    } else {
                                        $tagihan_feedback = '<div class="alert alert-success">Berhasil menambahkan tagihan untuk mahasiswa dengan NIM ' . htmlspecialchars($nim) . ', tetapi mahasiswa tidak memiliki email.</div>';
                                    }
                                } else {
                                    throw new Exception('Gagal menambahkan tagihan untuk mahasiswa dengan NIM ' . htmlspecialchars($nim) . '.');
                                }
                            } else {
                                $semester_filter = (int)$_POST['semester'];
                                $ukt = trim($_POST['ukt']);
                                if (!$semester_filter || !$ukt) {
                                    throw new Exception('Semester dan Kategori UKT wajib diisi untuk mode massal.');
                                }
                                if (!in_array($semester_filter, $semesterList)) {
                                    throw new Exception('Semester tidak valid.');
                                }
                                if (!array_key_exists($ukt, $uktCategories)) {
                                    throw new Exception('Kategori UKT tidak valid.');
                                }
                                $stmt = $conn->prepare("SELECT id_mahasiswa, semester, email FROM Mahasiswa WHERE semester = ? AND status_aktif = 'aktif'");
                                $stmt->bind_param("i", $semester_filter);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $students = $result->fetch_all(MYSQLI_ASSOC);
                                $stmt->close();
                                if (empty($students)) {
                                    throw new Exception('Tidak ada mahasiswa yang cocok dengan filter semester.');
                                }
                                foreach ($students as $student) {
                                    $checkStmt = $conn->prepare("SELECT id_tagihan FROM Tagihan WHERE id_mahasiswa = ? AND semester = ? AND tahun_ajaran = ?");
                                    $checkStmt->bind_param("iis", $student['id_mahasiswa'], $semester_filter, $tahun_ajaran);
                                    $checkStmt->execute();
                                    $checkStmt->store_result();
                                    if ($checkStmt->num_rows == 0) {
                                        $jumlah_int = $uktCategories[$ukt];
                                        $insertStmt->bind_param("iisiss", $student['id_mahasiswa'], $semester_filter, $tahun_ajaran, $jumlah_int, $jatuh_tempo, $keterangan);
                                        if ($insertStmt->execute()) {
                                            $success_count++;
                                            if ($student['email']) {
                                                require_once __DIR__ . '/vendor/autoload.php';
                                                $mail = new PHPMailer(true);
                                                try {
                                                    $mail->isSMTP();
                                                    $mail->Host = SMTP_HOST;
                                                    $mail->SMTPAuth = true;
                                                    $mail->Username = SMTP_USERNAME;
                                                    $mail->Password = SMTP_PASSWORD;
                                                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                                    $mail->Port = SMTP_PORT;
                                                    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                                                    $mail->addAddress($student['email']);
                                                    $mail->isHTML(true);
                                                    $mail->Subject = 'Tagihan Baru - ' . APP_NAME;
                                                    $jumlah_formatted = number_format($jumlah_int, 0, ',', '.');
                                                    $semester_safe = htmlspecialchars($semester_filter);
                                                    $tahun_ajaran_safe = htmlspecialchars($tahun_ajaran);
                                                    $jatuh_tempo_formatted = date('d/m/Y', strtotime($jatuh_tempo));
                                                    $keterangan_safe = htmlspecialchars($keterangan);
                                                    $paritas_safe = htmlspecialchars($paritas);
                                                    $mail->Body = <<<EOD
            <!DOCTYPE html>
            <html>
            <body>
                <h2>Tagihan Baru</h2>
                <p>Tagihan baru telah dibuat di MyUKT.</p>
                <p><strong>Detail Tagihan:</strong></p>
                <ul>
                    <li>Jumlah: Rp {$jumlah_formatted}</li>
                    <li>Semester: {$semester_safe}</li>
                    <li>Tahun Ajaran: {$paritas_safe} {$tahun_ajaran_safe}</li>
                    <li>Batas Waktu: {$jatuh_tempo_formatted}</li>
                    <li>Keterangan: {$keterangan_safe}</li>
                </ul>
                <p>Silakan login ke MyUKT untuk melihat detail dan melakukan pembayaran.</p>
            </body>
            </html>
            EOD;
                                                    $mail->send();
                                                } catch (Exception $mail_error) {
                                                    // Lanjutkan meskipun email gagal
                                                }
                                            }
                                        }
                                    }
                                    $checkStmt->close();
                                }
                                $tagihan_feedback = '<div class="alert alert-success">Berhasil menambahkan ' . $success_count . ' tagihan untuk mahasiswa dengan semester ' . htmlspecialchars($semester_filter) . ' dan UKT ' . htmlspecialchars($ukt) . ' pada ' . htmlspecialchars($paritas) . ' ' . htmlspecialchars($tahun_ajaran) . '.</div>';
                            }
                            $insertStmt->close();
                            $conn->commit();
                        } catch (Exception $e) {
                            $conn->rollback();
                            $tagihan_feedback = '<div class="alert alert-danger">Gagal menambah tagihan: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                    }
                }
            }
            $tagihan_aktif = [];
            if ($isAdmin && isset($_GET['page']) && $_GET['page'] === 'tambah-tagihan') {
                // Ambil semua tagihan aktif (status_tagihan != 'Lunas')
                $result = $conn->query("SELECT t.id_tagihan, m.nim, m.nama, m.status_aktif, t.jumlah_tagihan, t.batas_waktu, t.status_tagihan, t.keterangan, t.semester, t.tahun_ajaran FROM Tagihan t JOIN Mahasiswa m ON t.id_mahasiswa = m.id_mahasiswa WHERE t.status_tagihan != 'Lunas' ORDER BY t.batas_waktu ASC");
                if ($result) {
                    $tagihan_aktif = $result->fetch_all(MYSQLI_ASSOC);
                }
            }
            ?>
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-3"><i class="bi bi-plus-circle me-2"></i>Tambah Tagihan</h3>
                    <div class="bg-white p-4 rounded shadow-sm mb-4">
                        <form class="row g-3 align-items-end" id="formTambahTagihan" method="POST">
                            <div class="col-md-6">
                                <label class="form-label">Mode Tagihan</label>
                                <select class="form-select" id="inputMode" name="mode" required>
                                    <option value="massal">Banyak</option>
                                    <option value="individu">Individu</option>
                                </select>
                            </div>
                            <div class="col-md-6" id="nimField" style="display:none;">
                                <label class="form-label">NIM Mahasiswa</label>
                                <input type="text" class="form-control" id="inputNIM" name="nim" placeholder="Masukkan NIM" pattern="\d*" maxlength="20" inputmode="numeric">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Semester</label>
                                <select class="form-select" id="inputSemester" name="semester" required>
                                    <option value="">Pilih Semester</option>
                                    <?php foreach ($semesterList as $sem): ?>
                                        <option value="<?= $sem ?>">Semester <?= $sem ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tahun Ajaran</label>
                                <select class="form-select" id="inputTahunAjaran" name="tahun_ajaran" required>
                                    <option value="">Pilih Tahun Ajaran</option>
                                    <?php foreach ($tahunAjaranOptions as $option): ?>
                                        <option value="<?= htmlspecialchars($option['value']) ?>"><?= htmlspecialchars($option['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6" id="uktIndividuField" style="display:none;">
                                <label class="form-label">Kategori UKT</label>
                                <select class="form-select" id="inputUKTIndividu" name="ukt_individu">
                                    <option value="">Pilih Kategori UKT</option>
                                    <?php foreach ($uktList as $ukt): ?>
                                    <option value="<?= htmlspecialchars($ukt) ?>" data-amount="<?= $uktCategories[$ukt] ?>">
                                        <?= htmlspecialchars($ukt) ?> (Rp. <?= number_format($uktCategories[$ukt], 0, ',', '.') ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6" id="uktField">
                                <label class="form-label">Kategori UKT</label>
                                <select class="form-select" id="inputUKT" name="ukt">
                                    <option value="">Pilih Kategori UKT</option>
                                    <?php foreach ($uktList as $ukt): ?>
                                    <option value="<?= htmlspecialchars($ukt) ?>" data-amount="<?= $uktCategories[$ukt] ?>">
                                        <?= htmlspecialchars($ukt) ?> (Rp. <?= number_format($uktCategories[$ukt], 0, ',', '.') ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Jumlah Tagihan</label>
                                <input type="text" class="form-control" id="inputJumlah" name="jumlah" readonly required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Keterangan</label>
                                <input type="text" class="form-control" id="inputKeterangan" name="keterangan" placeholder="Keterangan">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Jatuh Tempo</label>
                                <input type="date" class="form-control" id="inputJatuhTempo" name="jatuh_tempo" required>
                            </div>
                            <div class="col-md-12 d-flex justify-content-end align-items-end">
                                <button type="button" class="btn btn-success px-5 py-2" style="font-size:1.2rem;" onclick="handleTambahTagihan()">Tambahkan</button>
                            </div>
                        </form>
                        <script>
                        // Toggle fields based on mode
                        const modeDropdown = document.getElementById('inputMode');
                        const nimField = document.getElementById('nimField');
                        const uktField = document.getElementById('uktField');
                        const uktIndividuField = document.getElementById('uktIndividuField');
                        const inputNIM = document.getElementById('inputNIM');
                        const inputUKT = document.getElementById('inputUKT');
                        const inputUKTIndividu = document.getElementById('inputUKTIndividu');
                        const inputJumlah = document.getElementById('inputJumlah');
                        const inputSemester = document.getElementById('inputSemester');
                        const inputTahunAjaran = document.getElementById('inputTahunAjaran');

                        modeDropdown.addEventListener('change', function() {
                            if (this.value === 'individu') {
                                nimField.style.display = 'block';
                                uktIndividuField.style.display = 'block';
                                uktField.style.display = 'none';
                                inputNIM.setAttribute('required', 'required');
                                inputUKTIndividu.setAttribute('required', 'required');
                                inputUKT.removeAttribute('required');
                                inputJumlah.value = '';
                            } else {
                                nimField.style.display = 'none';
                                uktIndividuField.style.display = 'none';
                                uktField.style.display = 'block';
                                inputNIM.removeAttribute('required');
                                inputUKTIndividu.removeAttribute('required');
                                inputUKT.setAttribute('required', 'required');
                                inputJumlah.value = '';
                            }
                        });

                        // Auto-fill jumlah based on UKT selection
                        inputUKT.addEventListener('change', function() {
                            const selectedOption = this.options[this.selectedIndex];
                            const amount = selectedOption ? selectedOption.getAttribute('data-amount') : '';
                            inputJumlah.value = amount ? 'Rp.' + parseInt(amount).toLocaleString('id-ID') : '';
                        });

                        inputUKTIndividu.addEventListener('change', function() {
                            const selectedOption = this.options[this.selectedIndex];
                            const amount = selectedOption ? selectedOption.getAttribute('data-amount') : '';
                            inputJumlah.value = amount ? 'Rp.' + parseInt(amount).toLocaleString('id-ID') : '';
                        });

                        // Restrict NIM to numbers only
                        inputNIM.addEventListener('input', function(e) {
                            this.value = this.value.replace(/[^0-9]/g, '');
                        });

                        // Validasi paritas semester dengan tahun ajaran
                        function validateSemesterParitas() {
                            const semester = parseInt(inputSemester.value);
                            const tahunAjaran = inputTahunAjaran.value;
                            if (!semester || !tahunAjaran) return true;
                            const isSemesterGenap = semester % 2 === 0;
                            const isTahunGenap = tahunAjaran.includes(':Genap');
                            return (isSemesterGenap && isTahunGenap) || (!isSemesterGenap && !isTahunGenap);
                        }

                        // Form validation and submission
                        function handleTambahTagihan() {
                            const mode = document.getElementById('inputMode').value;
                            const jumlah = document.getElementById('inputJumlah').value;
                            const jatuhTempo = document.getElementById('inputJatuhTempo').value;
                            const tahunAjaran = document.getElementById('inputTahunAjaran').value;
                            const semester = document.getElementById('inputSemester').value;
                            const keterangan = document.getElementById('inputKeterangan');
                            if (!keterangan.value.trim()) {
                                keterangan.value = '-';
                            }
                            if (mode === 'individu') {
                                const nim = document.getElementById('inputNIM').value;
                                const uktIndividu = document.getElementById('inputUKTIndividu').value;
                                if (!nim || !uktIndividu || !jumlah || !jatuhTempo || !tahunAjaran || !semester) {
                                    alert('Semua field wajib diisi kecuali keterangan!');
                                    return false;
                                }
                                if (!/^\d+$/.test(nim)) {
                                    alert('NIM hanya boleh diisi angka!');
                                    inputNIM.focus();
                                    return false;
                                }
                            } else {
                                const ukt = document.getElementById('inputUKT').value;
                                if (!semester || !ukt || !jumlah || !jatuhTempo || !tahunAjaran) {
                                    alert('Semua field wajib diisi kecuali keterangan!');
                                    return false;
                                }
                            }
                            if (!validateSemesterParitas()) {
                                alert('Semester tidak sesuai dengan paritas tahun ajaran (Ganjil untuk semester 1,3,5,7; Genap untuk semester 2,4,6,8)!');
                                inputSemester.focus();
                                return false;
                            }
                            document.getElementById('formTambahTagihan').submit();
                        }
                        </script>
                    </div>
                    <?php if (!empty($tagihan_feedback)) echo $tagihan_feedback; ?>
                    <h5 class="mb-3">Tagihan Aktif</h5>
                    <div class="bg-white p-3 rounded shadow-sm">
                        <?php
                        // Filter hanya tagihan yang status_tagihan bukan 'Lunas'
                        $tagihan_aktif_filtered = array_filter($tagihan_aktif, function($t) {
                            return strtolower($t['status_tagihan']) !== 'lunas';
                        });
                        ?>
                        <?php if (empty($tagihan_aktif_filtered)): ?>
                            <div class="text-center text-secondary py-4">Tidak ada Tagihan Aktif saat ini.</div>
                        <?php else: ?>
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>NIM</th>
                                    <th>Nama</th>
                                    <th>Status</th>
                                    <th>Jumlah</th>
                                    <th>Semester</th>
                                    <th>Tahun Ajaran</th>
                                    <th>Jatuh Tempo</th>
                                    <th>Status Tagihan</th>
                                    <th>Keterangan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tagihan_aktif_filtered as $t): ?>
                                <tr id="row_tagihan_<?= $t['id_tagihan'] ?>">
                                    <td><span class="badge bg-light text-dark px-3 py-2"><?= htmlspecialchars($t['nim']) ?></span></td>
                                    <td><?= htmlspecialchars($t['nama']) ?></td>
                                    <td><?= htmlspecialchars($t['status_aktif']) ?></td>
                                    <td>Rp. <?= number_format($t['jumlah_tagihan'],0,',','.') ?></td>
                                    <td><?= htmlspecialchars($t['semester']) ?></td>
                                    <td><?= ($t['semester'] % 2 == 0 ? 'Genap' : 'Ganjil') . ' ' . htmlspecialchars($t['tahun_ajaran']) ?></td>
                                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($t['batas_waktu']))) ?></td>
                                    <td><?= htmlspecialchars($t['status_tagihan']) ?></td>
                                    <td><?= htmlspecialchars($t['keterangan']) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="hapusTagihan(<?= $t['id_tagihan'] ?>, document.getElementById('row_tagihan_<?= $t['id_tagihan'] ?>'))"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php elseif ($isAdmin && isset($_GET['page']) && $_GET['page'] === 'verifikasi-pembayaran'): ?>
            <?php
            // --- PROSES VERIFIKASI PEMBAYARAN ADMIN ---
            if ($isAdmin && isset($_POST['verifikasi_id']) && isset($_POST['verifikasi'])) {
                include 'koneksi.php';
                $id = intval($_POST['verifikasi_id']);
                $status = $_POST['verifikasi'] == '1' ? 'Terverifikasi' : 'Ditolak';
                // Update status pembayaran
                $stmt = $conn->prepare("UPDATE Pembayaran SET status_verifikasi=? WHERE id_pembayaran=?");
                $stmt->bind_param('si', $status, $id);
                $stmt->execute();
                $stmt->close();
                // Ambil id_tagihan, id_mahasiswa, email, jumlah, metode, tanggal untuk notifikasi
                $q = $conn->prepare("SELECT p.id_tagihan, t.id_mahasiswa, m.email, p.jumlah_dibayar, p.metode_pembayaran, p.tanggal_bayar FROM Pembayaran p JOIN Tagihan t ON p.id_tagihan = t.id_tagihan JOIN Mahasiswa m ON t.id_mahasiswa = m.id_mahasiswa WHERE p.id_pembayaran=?");
                $q->bind_param('i', $id);
                $q->execute();
                $q->bind_result($id_tagihan, $id_mahasiswa, $email, $jumlah, $metode, $tanggal);
                if ($q->fetch()) {
                    $q->close();
                    // Jika diverifikasi, update status tagihan menjadi Lunas
                    if ($status === 'Terverifikasi') {
                        $upd = $conn->prepare("UPDATE Tagihan SET status_tagihan='Lunas' WHERE id_tagihan=?");
                        $upd->bind_param('i', $id_tagihan);
                        $upd->execute();
                        $upd->close();
                        // Kirim email notifikasi pembayaran berhasil
                        if (!empty($email)) {
                            $subject = 'Pembayaran UKT Berhasil';
                            $message = 'Pembayaran UKT Anda telah diverifikasi dan dinyatakan LUNAS. Terima kasih telah melakukan pembayaran.';
                            sendGeneralNotification($email, $subject, $message, $id_mahasiswa, $id_tagihan, 'pembayaran_sukses', $conn);
                        }
                    } elseif ($status === 'Ditolak') {
                        // Kirim email notifikasi pembayaran ditolak
                        if (!empty($email)) {
                            $subject = 'Pembayaran UKT Ditolak';
                            $message = 'Pembayaran UKT Anda ditolak. Silakan cek dashboard dan unggah ulang bukti pembayaran yang valid.';
                            sendGeneralNotification($email, $subject, $message, $id_mahasiswa, $id_tagihan, 'pembayaran_ditolak', $conn);
                        }
                    }
                    // Masukkan ke riwayat pembayaran
                    $aksi = ($status === 'Terverifikasi') ? 'Verifikasi Pembayaran' : 'Tolak Pembayaran';
                    $ins = $conn->prepare("INSERT INTO Riwayat_Pembayaran (id_pembayaran, id_mahasiswa, aksi) VALUES (?, ?, ?)");
                    $ins->bind_param('iis', $id, $id_mahasiswa, $aksi);
                    $ins->execute();
                    $ins->close();
                } else {
                    $q->close();
                }
                echo '<script>window.location.href = "dashboard.php?page=verifikasi-pembayaran";</script>';
                exit();
            }
            ?>
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-3">Verifikasi Pembayaran</h3>
                    <div class="bg-white p-4 rounded shadow-sm">
                        <?php
                        include 'koneksi.php';
                        function getSemesterTahunOptions($startYear = null, $jumlahTahun = 5) {
                            $options = [];
                            $startYear = $startYear ?: (int)date('Y');
                            for ($y = $startYear; $y < $startYear + $jumlahTahun; $y++) {
                                $options[] = ['semester' => 2, 'tahun_ajaran' => $y, 'label' => 'Genap ' . $y];
                                $options[] = ['semester' => 1, 'tahun_ajaran' => $y + 1, 'label' => 'Ganjil ' . ($y + 1)];
                            }
                            return $options;
                        }
                        $semesterTahunOptions = getSemesterTahunOptions(2025, 5);
                        $where = '';
                        $params = [];
                        if (!empty($_GET['cari'])) {
                            $cari = '%' . $_GET['cari'] . '%';
                            $where = "WHERE m.nim LIKE ? OR m.nama LIKE ?";
                            $params = [$cari, $cari];
                        }
                        $sql = "SELECT p.*, t.jumlah_tagihan, t.batas_waktu, t.semester, t.tahun_ajaran, m.nim, m.nama FROM Pembayaran p JOIN Tagihan t ON p.id_tagihan = t.id_tagihan JOIN Mahasiswa m ON t.id_mahasiswa = m.id_mahasiswa $where ORDER BY p.tanggal_bayar DESC";
                        $stmt = $conn->prepare($where ? $sql : str_replace(' WHERE m.nim LIKE ? OR m.nama LIKE ?', '', $sql));
                        if ($where) $stmt->bind_param('ss', ...$params);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        ?>
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>NIM</th>
                                    <th>Nama</th>
                                    <th>Semester</th>
                                    <th>Tahun Ajaran</th>
                                    <th>Jumlah</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($result->num_rows === 0): ?>
                                <tr><td colspan="10" class="text-center text-secondary">Belum ada pembayaran diajukan.</td></tr>
                            <?php else: ?>
                                <?php foreach ($result as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['nim']) ?></td>
                                    <td><?= htmlspecialchars($row['nama']) ?></td>
                                    <td><?= htmlspecialchars($row['semester']) ?></td>
                                    <td><?= htmlspecialchars($row['tahun_ajaran']) ?></td>
                                    <td>Rp. <?= number_format($row['jumlah_tagihan'], 0, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($row['status_verifikasi']) ?></td>
                                    <td>
                                        <?php if ($row['status_verifikasi'] === 'Menunggu'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="verifikasi_id" value="<?= $row['id_pembayaran'] ?>">
                                                <button type="submit" name="verifikasi" value="1" class="btn btn-success btn-sm">Verifikasi</button>
                                                <button type="submit" name="verifikasi" value="0" class="btn btn-danger btn-sm">Tolak</button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-warning ms-1" onclick="hapusPembayaran(<?= $row['id_pembayaran'] ?>, this.closest('tr'))"><i class="bi bi-trash"></i></button>
                                        <?php elseif ($row['status_verifikasi'] === 'Terverifikasi'): ?>
                                            <span class="badge bg-success">Selesai</span>
                                        <?php elseif ($row['status_verifikasi'] === 'Ditolak'): ?>
                                            <span class="badge bg-danger">Selesai</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php elseif ($isAdmin && isset($_GET['page']) && $_GET['page'] === 'profil'): ?>
            <?php
            // Ambil password admin dari database
            $adminPassword = '';
            $q = $conn->query("SELECT password FROM Admin LIMIT 1");
            if ($q && $row = $q->fetch_assoc()) {
                $adminPassword = $row['password'];
            }
            ?>
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-3"><i class="bi bi-person me-2"></i>Profil</h3>
                    <div class="bg-white p-4 rounded shadow-sm d-flex flex-wrap align-items-center" style="min-height:340px;">
                        <div class="me-4 mb-3" style="min-width:180px;">
                            <div class="bg-light d-flex align-items-center justify-content-center" style="width:180px; height:180px; border-radius:1.2rem; border:2px solid #b2e5df;">
                                <?php if ($adminPhoto): ?>
                                    <img src="<?= htmlspecialchars($adminPhoto) ?>" alt="Foto Profil" class="rounded-circle" style="width:100%; height:100%; object-fit:cover;">
                                <?php else: ?>
                                    <i class="bi bi-person" style="font-size:5rem; color:#2bb6a8;"></i>
                                <?php endif; ?>
                            </div>
                            <form method="post" enctype="multipart/form-data" class="mt-3">
                                <input type="file" name="foto" class="form-control mb-2" accept="image/*">
                                <button type="submit" name="upload_foto" class="btn btn-success w-100" style="border-radius:1.2rem;">Upload</button>
                            </form>
                            <?php
                            if (isset($_POST['upload_foto']) && isset($_FILES['foto'])) {
                                $foto = $_FILES['foto'];
                                $allowed = ['jpg','jpeg','png','gif','webp'];
                                $maxSize = 15*1024*1024; // 15MB
                                $ext = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));
                                if (!in_array($ext, $allowed)) {
                                    echo '<div class="alert alert-danger mt-2">Format file tidak didukung. Hanya jpg, jpeg, png, gif, webp.</div>';
                                } elseif ($foto['size'] > $maxSize) {
                                    echo '<div class="alert alert-danger mt-2">Ukuran file maksimal 15MB.</div>';
                                } elseif ($foto['error'] === UPLOAD_ERR_OK) {
                                    $newName = 'bukti_bayar/admin_' . $adminId . '_' . time() . '.' . $ext;
                                    if (move_uploaded_file($foto['tmp_name'], $newName)) {
                                        $stmt = $conn->prepare("UPDATE Admin SET foto=? WHERE id_admin=?");
                                        $stmt->bind_param("si", $newName, $adminId);
                                        if ($stmt->execute()) {
                                            echo '<div class="alert alert-success mt-2">Foto berhasil diupload. Halaman akan di-refresh...</div>';
                                            echo '<script>setTimeout(function(){ location.reload(); }, 1200);</script>';
                                            $adminPhoto = $newName;
                                        } else {
                                            echo '<div class="alert alert-danger mt-2">Gagal menyimpan foto ke database.</div>';
                                        }
                                    } else {
                                        echo '<div class="alert alert-danger mt-2">Gagal mengupload foto.</div>';
                                    }
                                } else {
                                    echo '<div class="alert alert-danger mt-2">Terjadi kesalahan saat mengupload foto.</div>';
                                }
                            }
                            ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="mb-2" style="font-size:2rem; font-weight:bold;"><?= htmlspecialchars($adminName) ?></div>
                            <hr>
                            <div class="row mb-2">
                                <div class="col-4">ID</div>
                                <div class="col-8">: <?= htmlspecialchars($adminId) ?></div>
                            </div>
                            <div class="row mb-2 align-items-center">
                                <div class="col-4">Password</div>
                                <div class="col-8">: <span id="adminPassField">**********</span> <i class="bi bi-eye ms-2" id="showAdminPass" style="cursor:pointer;"></i></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-4">Email</div>
                                <div class="col-8">: <?= htmlspecialchars($adminEmail) ?></div>
                            </div>
                            <div class="d-flex justify-content-end mt-4">
                                <p style="color: red;">Gunakan mysql untuk mengubah data admin di database.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <script>
            const showBtn = document.getElementById('
        <!-- Blok Statistik Cepat hanya untuk Home Admin sudah dipindahkan ke atas, tidak perlu di sini lagi -->
        <!-- Jika ingin menambah konten khusus admin di halaman lain, tambahkan di sini -->
    <?php endif; ?>

    <?php if (!$status): ?>
        <div class="d-flex align-items-center mb-2">
            <span class="status-badge"><i class="bi bi-x-circle-fill"></i></span>
            <span class="fs-4 fw-bold text-danger">Belum Bayar</span>
        </div>
        <div class="mb-3">Tagihan: <b>Rp <?= number_format($tagihan,0,',','.') ?></b> - Jatuh Tempo: <b><?= $jatuh_tempo ?></b></div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary">Bayar Sekarang</button>
            <button class="btn btn-outline-primary">Lihat Tagihan</button>
            <button class="btn btn-outline-primary">Unduh PDF</button>
        </div>
    <?php else: ?>
        <div class="d-flex align-items-center mb-2">
            <span class="status-badge lunas"><i class="bi bi-check-circle-fill"></i></span>
            <span class="fs-4 fw-bold text-success">Lunas</span>
        </div>
        <div class="mb-3">Tagihan: <b>Rp <?= number_format($tagihan,0,',','.') ?></b> - Jatuh Tempo: <b><?= $jatuh_tempo ?></b></div>
    <?php endif; ?>

    <div class="riwayat-box">
        <div class="riwayat-title">Riwayat Singkat</div>
        <table class="table table-borderless mb-1">
            <tbody>
            <?php foreach ($riwayat as $row): ?>
                <tr>
                    <td><?= $row['tanggal'] ?></td>
                    <td>Rp <?= number_format($row['jumlah'],0,',','.') ?></td>
                    <td><?= $row['status'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <a href="#" class="text-decoration-underline text-primary" style="font-size:0.95rem;">Lainnya...</a>
    </div>
<script>
function hapusTagihan(id, row) {
    if (!confirm('Hapus tagihan ini?')) return;
    fetch('dashboard.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'id_tagihan=' + encodeURIComponent(id)
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            row.remove();
        } else {
            alert(res.message || 'Gagal menghapus tagihan.');
        }
    })
    .catch(() => alert('Gagal menghapus tagihan.'));
}
function hapusPembayaran(id, row) {
    if (!confirm('Hapus pembayaran ini?')) return;
    fetch('dashboard.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'id_pembayaran=' + encodeURIComponent(id)
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            row.remove();
        } else {
            alert(res.message || 'Gagal menghapus pembayaran.');
        }
    })
    .catch(() => alert('Gagal menghapus pembayaran.'));
}
</script>
</body>
</html>