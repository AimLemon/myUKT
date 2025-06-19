<?php
include 'koneksi.php';

$error = '';
$signup_success = '';
$signup_error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_type = $_POST['login_type'] ?? 'mahasiswa';
    if ($login_type === 'signup') {
        // Proses Sign Up Mahasiswa
        $signup_username = trim($_POST['signup_username'] ?? '');
        $signup_nama = trim($_POST['signup_nama'] ?? '');
        $signup_email = trim($_POST['signup_email'] ?? '');
        $signup_password = $_POST['signup_password'] ?? '';
        $signup_prodi = trim($_POST['signup_prodi'] ?? '');
        if ($signup_username === '' || $signup_nama === '' || $signup_email === '' || $signup_password === '' || $signup_prodi === '') {
            $signup_error = 'Semua field wajib diisi.';
        } else {
            // Cek apakah username/email sudah ada
            $stmt = $conn->prepare("SELECT * FROM Mahasiswa WHERE nim=? OR email=?");
            $stmt->bind_param("ss", $signup_username, $signup_email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $signup_error = 'NIM atau Email sudah terdaftar.';
            } else {
                // Insert ke database
                $stmt = $conn->prepare("INSERT INTO Mahasiswa (nim, nama, email, password, program_studi, status_aktif) VALUES (?, ?, ?, ?, ?, 'aktif')");
                $stmt->bind_param("sssss", $signup_username, $signup_nama, $signup_email, $signup_password, $signup_prodi);
                if ($stmt->execute()) {
                    $signup_success = 'Pendaftaran berhasil! Silakan login.';
                } else {
                    $signup_error = 'Gagal mendaftar. Silakan coba lagi.';
                }
            }
        }
    } else {
        // Login Mahasiswa
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $stmt = $conn->prepare("SELECT * FROM Mahasiswa WHERE (nim=? OR email=?) AND password=? AND status_aktif='aktif'");
        $stmt->bind_param("sss", $username, $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            session_start();
            $_SESSION['user'] = $row['nim']; // Set session user ke NIM asli
            header("Location: dashboardmahasiswa.php");
            exit();
        } else {
            $error = "Username atau password salah, atau akun nonaktif.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MyUKT Mahasiswa Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body, html {
            height: 100%;
            margin: 0;
            background: #fff;
            font-family: 'Segoe UI', sans-serif;
        }
        .main-container {
            min-height: 100vh;
            display: flex;
        }
        .left-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding-left: 6vw;
            z-index: 2;
            background: #fff;
        }
        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2bb6a8;
            font-family: 'Segoe Script', cursive;
            line-height: 1;
        }
        .ukt-text {
            font-size: 2rem;
            font-weight: 600;
            color: #2bb6a8;
            margin-top: 0.5rem;
            margin-bottom: 2.5rem;
        }
        .tab-btn {
            background: none;
            border: none;
            font-size: 1.3rem;
            font-weight: 500;
            color: #b2e5df;
            margin-right: 2rem;
            border-bottom: 3px solid #b2e5df;
            border-radius: 0;
            padding-bottom: 0.2rem;
            transition: color 0.2s, border-bottom 0.2s;
        }
        .tab-btn.active {
            color: #2bb6a8;
            border-bottom: 3px solid #2bb6a8;
        }
        .tab-btn:last-child {
            margin-right: 0;
        }
        .form-control {
            border-radius: 2rem;
            background: #f8fefd;
            border: none;
            box-shadow: 0 2px 6px #e6f6f3;
            padding-left: 2.5rem;
            margin-bottom: 1.2rem;
            font-size: 1.1rem;
        }
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #2bb6a8;
            font-size: 1.2rem;
        }
        .login-btn {
            background: #2bb6a8;
            color: #fff;
            border-radius: 2rem;
            padding: 0.5rem 2.5rem;
            font-size: 1.2rem;
            font-weight: 500;
            border: none;
            box-shadow: 0 2px 6px #b2e5df;
            transition: background 0.2s;
        }
        .login-btn:hover {
            background: #249b8f;
        }
        .create-account, .already-account {
            color: #2bb6a8;
            font-size: 1rem;
            margin-bottom: 1.5rem;
            margin-top: -0.5rem;
            cursor: pointer;
            text-decoration: underline;
        }
        .already-account {
            margin-bottom: 0;
            margin-top: 0;
        }
        .right-panel {
            flex: 1.2;
            background: radial-gradient(circle at 80% 40%, #b2e5df 0%, #2bb6a8 60%, #1e7c72 100%);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .img-figure {
            max-width: 420px;
            width: 90%;
            z-index: 2;
        }
        @media (max-width: 900px) {
            .main-container {
                flex-direction: column;
            }
            .left-panel, .right-panel {
                flex: unset;
                width: 100%;
                min-height: 50vh;
                padding-left: 0;
                align-items: center;
            }
            .left-panel {
                padding: 2rem 1rem 1rem 1rem;
            }
            .right-panel {
                padding: 2rem 0;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="left-panel">
            <div class="logo mb-1">My<br>UKT.</div>
            <!-- <div class="d-flex align-items-center mb-4">
                <button id="loginTab" class="tab-btn active" type="button">Login</button>
                <button id="signupTab" class="tab-btn" type="button">Sign Up</button>
            </div> -->
            <!-- Login Form Mahasiswa -->
            <form id="loginForm" method="POST" action="user_login.php" style="max-width: 340px;">
                <input type="hidden" name="login_type" value="mahasiswa">
                <div class="position-relative">
                    <span class="input-icon"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control ps-5" name="username" placeholder="NIM" required>
                </div>
                <div class="position-relative">
                    <span class="input-icon"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control ps-5" name="password" placeholder="Password" required>
                </div>
                <?php if (!empty($error)): ?>
                    <div class="text-danger mb-2"><?= $error ?></div>
                <?php endif; ?>
                <div class="d-flex justify-content-end align-items-center mb-3">
                    <button type="submit" class="login-btn ms-2">Login</button>
                </div>
            </form>
            <!-- Sign Up Form Dihilangkan -->
        </div>
        <div class="right-panel">
            <img id="mainImage" src="duduk.jpeg" alt="Mahasiswa" class="img-figure">
        </div>
    </div>
    <script>
        // Hapus semua logic tab dan sign up
        // Ganti gambar default ke duduk.jpeg
        const mainImage = document.getElementById('mainImage');
        mainImage.src = "duduk.jpeg";
    </script>
</body>
</html>