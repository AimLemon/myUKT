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
        body {
            height: 100%;
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            position: relative;
        }
        html {
            height: 100%;
        }
        
        .navbar {
            background: #fff;
            padding: 1rem 2rem;
            box-shadow: 0 2px 8px rgba(43,182,168,0.08);
            border-radius: 0;
            position: relative;
            z-index: 2;
        }
        .navbar-brand {
            font-size: 2.2rem;
            font-weight: bold;
            color: #2bb6a8;
            font-family: 'Segoe Script', cursive;
            letter-spacing: 2px;
            text-shadow: 1px 2px 8px #b2e5df60;
        }
        .login-btn {
            background: linear-gradient(90deg, #2bb6a8 60%, #1e7c72 100%);
            color: #fff;
            border-radius: 2rem;
            padding: 0.5rem 1.7rem;
            font-size: 1.1rem;
            font-weight: 500;
            border: none;
            box-shadow: 0 2px 8px #b2e5df60;
            transition: background 0.2s, box-shadow 0.2s;
        }
        .login-btn:hover {
            background: #249b8f;
            box-shadow: 0 4px 16px #2bb6a830;
        }
        .admin-checkbox {
            font-size: 0.98rem;
            color: #2bb6a8;
            cursor: pointer;
            text-decoration: underline;
            font-weight: 500;
        }
        .hero-section {
            min-height: calc(100vh - 80px);
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            padding: 2rem;
            position: relative;
            z-index: 9999;
            overflow: hidden;
        }
        /* Hilangkan motif gambar duduk di hero-section */
        .hero-section::after {
            display: none;
        }
        .hero-content {
            max-width: 600px;
            text-align: center;
            color: #fff;
            position: relative;
            z-index: 9999;
            background: rgba(43,182,168,0.10);
            border-radius: 2rem;
            box-shadow: 0 4px 32px #1e7c7210;
            padding: 2.5rem 2rem 2.5rem 2rem;
        }
        .hero-content h1 {
            font-size: 2.7rem;
            font-weight: 700;
            margin-bottom: 1.1rem;
            letter-spacing: 1px;
            text-shadow: 1px 2px 8px #1e7c7260;
        }
        .hero-content p {
            font-size: 1.18rem;
            margin-bottom: 2.2rem;
            color: #e6f6f3;
        }
        .hero-content .get-started-btn {
            background: #fff;
            color: #2bb6a8;
            border-radius: 2rem;
            padding: 0.75rem 2.2rem;
            font-size: 1.13rem;
            font-weight: 600;
            border: none;
            box-shadow: 0 2px 8px #b2e5df60;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
        }
        .hero-content .get-started-btn:hover {
            background: #f8fefd;
            color: #249b8f;
            box-shadow: 0 4px 16px #2bb6a830;
        }
        /* Motif shapes acak dekoratif dengan warna hijau muda/tua */
        .shape-decor {
            position: absolute;
            z-index: 0;
            opacity: 0.18;
            filter: blur(0.5px);
            pointer-events: none;
        }
        .shape-circle {
            width: 90px; height: 90px;
            border-radius: 50%;
            background: radial-gradient(circle, #2bb6a8 0%, #b2e5df 100%);
            left: 8vw; top: 18vh;
            animation: float1 7s ease-in-out infinite alternate;
            box-shadow: 0 0 24px 0 #2bb6a844;
        }
        .shape-rect {
            width: 70px; height: 70px;
            border-radius: 18px;
            background: linear-gradient(135deg, #b2e5df 60%, #2bb6a8 100%);
            right: 10vw; top: 12vh;
            transform: rotate(18deg);
            animation: float2 8s ease-in-out infinite alternate;
            box-shadow: 0 0 18px 0 #1e7c7244;
        }
        .shape-triangle {
            width: 0; height: 0;
            border-left: 55px solid transparent;
            border-right: 55px solid transparent;
            border-bottom: 95px solid #1e7c72;
            left: 18vw; bottom: 10vh;
            opacity: 0.16;
            animation: float3 6s ease-in-out infinite alternate;
        }
        .shape-ellipse {
            width: 120px; height: 50px;
            border-radius: 60px / 25px;
            background: linear-gradient(90deg, #b2e5df 60%, #1e7c72 100%);
            right: 12vw; bottom: 8vh;
            animation: float4 9s ease-in-out infinite alternate;
            box-shadow: 0 0 18px 0 #2bb6a844;
        }
        .background-image {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: url('tampilan.png') center center/cover no-repeat;
            z-index: 2;
            pointer-events: none;
        }
        @keyframes float1 {
            0% { transform: translateY(0); }
            100% { transform: translateY(30px); }
        }
        @keyframes float2 {
            0% { transform: translateY(0); }
            100% { transform: translateY(-25px); }
        }
        @keyframes float3 {
            0% { transform: translateY(0); }
            100% { transform: translateY(18px); }
        }
        @keyframes float4 {
            0% { transform: translateY(0); }
            100% { transform: translateY(-20px); }
        }
        @media (max-width: 768px) {
            .hero-content h1 {
                font-size: 2rem;
            }
            .hero-content p {
                font-size: 1rem;
            }
            .hero-content {
                padding: 1.5rem 0.7rem 1.5rem 0.7rem;
            }
            .motif-circle1, .motif-circle2, .shape-circle, .shape-rect, .shape-triangle, .shape-ellipse {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="background-image"></div>
    <div class="motif-circle motif-circle1"></div>
    <div class="motif-circle motif-circle2"></div>
    <!-- Shapes dekoratif acak -->
    <div class="shape-decor shape-circle"></div>
    <div class="shape-decor shape-rect"></div>
    <div class="shape-decor shape-triangle"></div>
    <div class="shape-decor shape-ellipse"></div>
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