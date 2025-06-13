<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MyUKT - Home</title>
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
        .navbar {
            background: #fff;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-size: 2rem;
            font-weight: bold;
            color: #2bb6a8;
            font-family: 'Segoe Script', cursive;
        }
        .login-btn {
            background: #2bb6a8;
            color: #fff;
            border-radius: 2rem;
            padding: 0.5rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            border: none;
            transition: background 0.2s;
        }
        .login-btn:hover {
            background: #249b8f;
        }
        .admin-checkbox {
            font-size: 0.9rem;
            color: #2bb6a8;
            cursor: pointer;
            text-decoration: underline;
        }
        .hero-section {
            min-height: calc(100vh - 56px);
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 80% 40%, #b2e5df 0%, #2bb6a8 60%, #1e7c72 100%);
            padding: 2rem;
        }
        .hero-content {
            max-width: 600px;
            text-align: center;
            color: #fff;
        }
        .hero-content h1 {
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .hero-content p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }
        .hero-content .get-started-btn {
            background: #fff;
            color: #2bb6a8;
            border-radius: 2rem;
            padding: 0.75rem 2rem;
            font-size: 1.1rem;
            font-weight: 500;
            border: none;
            transition: background 0.2s, color 0.2s;
        }
        .hero-content .get-started-btn:hover {
            background: #f8fefd;
            color: #249b8f;
        }
        @media (max-width: 768px) {
            .hero-content h1 {
                font-size: 2rem;
            }
            .hero-content p {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">MyUKT</a>
            <form id="loginForm" action="javascript:void(0);" class="d-flex align-items-center">
                <div class="form-check me-3">
                    <input class="form-check-input" type="checkbox" id="adminCheck" name="adminCheck">
                    <label class="form-check-label admin-checkbox" for="adminCheck">
                        Login sebagai admin?
                    </label>
                </div>
                <button type="submit" class="login-btn">Login</button>
            </form>
        </div>
    </nav>
    <div class="hero-section">
        <div class="hero-content">
            <h1>Welcome to MyUKT</h1>
            <p>MyUKT is your one-stop platform for managing your university tuition and academic needs. Easily access your dashboard, track payments, and stay updated with your academic progress.</p>
            <a href="user_login.php" class="get-started-btn">Get Started</a>
        </div>
    </div>
    <script>
        document.getElementById('loginForm').addEventListener('submit', function() {
            const adminCheck = document.getElementById('adminCheck').checked;
            window.location.href = adminCheck ? 'admin_login.php' : 'user_login.php';
        });
    </script>
</body>
</html>