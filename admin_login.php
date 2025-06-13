<?php
include 'koneksi.php';

$error = '';
$showAdminLogin = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['ajax_check_pin'])) {
        header('Content-Type: application/json');
        $admin_pin = $_POST['admin_pin'] ?? '';
        if ($admin_pin === '545454') {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'PIN admin salah.']);
        }
        exit();
    }
    $admin_pin = $_POST['admin_pin'] ?? '';
    if (isset($_POST['check_pin'])) {
        // Cek PIN admin
        if ($admin_pin === '545454') {
            $showAdminLogin = true;
        } else {
            $error = "PIN admin salah.";
        }
    } elseif (isset($_POST['admin_login'])) {
        // Proses login admin
        $admin_email = $_POST['admin_email'] ?? '';
        $admin_password = $_POST['admin_password'] ?? '';
        $stmt = $conn->prepare("SELECT * FROM Admin WHERE email=? AND password=?");
        $stmt->bind_param("ss", $admin_email, $admin_password);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            session_start();
            $_SESSION['admin'] = $admin_email;
            header("Location: dashboard.php");
            exit();
        } else {
            $showAdminLogin = true;
            $error = "Email atau password admin salah.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MyUKT Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            <div class="ukt-text">Admin<br>Login</div>
            <!-- PIN Admin Form -->
            <form id="adminPinForm" method="POST" action="admin_login.php" style="max-width: 340px;<?php echo $showAdminLogin ? ' display:none;' : ''; ?>">
                <input type="hidden" name="login_type" value="admin">
                <div class="position-relative mb-3">
                    <span class="input-icon"><i class="bi bi-key"></i></span>
                    <input type="password" class="form-control ps-5" name="admin_pin" placeholder="Masukkan PIN Admin" required id="adminPinInput">
                </div>
                <div id="adminPinError" class="text-danger mb-2"></div>
                <button type="submit" name="check_pin" class="login-btn w-100">Lanjut</button>
            </form>
            <!-- Login Form Admin -->
            <form id="adminLoginForm" method="POST" action="admin_login.php" style="max-width: 340px;<?php echo $showAdminLogin ? '' : ' display:none;'; ?>">
                <input type="hidden" name="login_type" value="admin">
                <input type="hidden" name="admin_pin" value="545454">
                <div class="position-relative">
                    <span class="input-icon"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control ps-5" name="admin_email" placeholder="Email Admin" required>
                </div>
                <div class="position-relative">
                    <span class="input-icon"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control ps-5" name="admin_password" placeholder="Password Admin" required>
                </div>
                <?php if (!empty($error)): ?>
                    <div class="text-danger mb-2"><?php echo $error; ?></div>
                <?php endif; ?>
                <button type="submit" name="admin_login" class="login-btn w-100">Login Admin</button>
            </form>
        </div>
        <div class="right-panel">
            <img src="https://i.ibb.co/0jzjK6y/mahasiswa-3d.png" alt="Mahasiswa" class="img-figure">
        </div>
    </div>
    <script>
        const adminPinForm = document.getElementById('adminPinForm');
        const adminLoginForm = document.getElementById('adminLoginForm');
        const adminPinInput = document.getElementById('adminPinInput');
        const adminPinError = document.getElementById('adminPinError');

        function showAdminPin() {
            adminPinForm.style.display = '';
            adminLoginForm.style.display = 'none';
        }
        function showAdminLogin() {
            adminPinForm.style.display = 'none';
            adminLoginForm.style.display = '';
        }
        <?php if ($showAdminLogin): ?>
            showAdminLogin();
        <?php else: ?>
            showAdminPin();
        <?php endif; ?>

        if (adminPinForm) {
            adminPinForm.addEventListener('submit', function(e) {
                e.preventDefault();
                adminPinError.textContent = '';
                const pin = adminPinInput.value;
                fetch('admin_login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'ajax_check_pin=1&admin_pin=' + encodeURIComponent(pin)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showAdminLogin();
                    } else {
                        adminPinError.textContent = data.error || 'PIN admin salah.';
                    }
                })
                .catch(() => {
                    adminPinError.textContent = 'Terjadi kesalahan. Coba lagi.';
                });
            });
        }
    </script>
</body>
</html>