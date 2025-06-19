<?php
session_start();
include 'koneksi.php';
include 'email_functions.php';

if (isset($_GET['nim']) && isset($_GET['token'])) {
    $nim = $_GET['nim'];
    $token = $_GET['token'];

    // Verifikasi token menggunakan fungsi verifyEmailToken
    $result = verifyEmailToken($nim, $token, $conn);

    if ($result['success']) {
        // Update status email_verified di tabel Mahasiswa
        $update = $conn->prepare("UPDATE Mahasiswa SET email_verified=1 WHERE nim=?");
        $update->bind_param("s", $nim);
        if ($update->execute()) {
            // Reset session profil untuk memastikan data fresh
            unset($_SESSION['profil']);
            header('Location: dashboardmahasiswa.php?page=profil&verif=success');
            exit();
        } else {
            $error = 'Gagal memperbarui status verifikasi.';
            file_put_contents('debug.log', "Gagal update: " . $conn->error . "\n", FILE_APPEND);
        }
        $update->close();
    } else {
        $error = $result['message'];
    }
} else {
    $error = 'Token atau NIM tidak ditemukan.';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Email</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="card-title mb-4">Verifikasi Email</h3>
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php else: ?>
                            <div class="alert alert-success">Email berhasil diverifikasi.</div>
                        <?php endif; ?>
                        <p>Silakan kembali ke <a href="dashboardmahasiswa.php">dashboard</a> untuk melanjutkan.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>