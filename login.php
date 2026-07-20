<?php
session_start();
include "connection.php";

/* ================= AUTO LOGIN ================= */
if (!isset($_SESSION['login']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $koneksi->prepare("SELECT * FROM users WHERE remember_token=? AND token_expired > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $_SESSION['login'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['level'] = strtolower($user['level']);
    }
}

/* ================= REDIRECT ================= */
if (isset($_SESSION['login'])) {
    if ($_SESSION['level'] === 'admin') {
        header("Location: admin/home_admin.php");
    } elseif ($_SESSION['level'] === 'frontliner') {
        header("Location: frontliner/home_frontliner.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Happy Cellular - Sistem Inventory</title>
    
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Base Reset */
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0A192F 0%, #0F2A3F 50%, #1A3A5C 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }

        /* Container & Card */
        .login-container {
            width: 100%;
            max-width: 440px;
            padding: 20px;
        }

        .login-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 40px 32px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.6s ease-out forwards;
            opacity: 0;
            transform: translateY(20px);
        }

        @keyframes slideUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Typography & Logo */
        .logo-wrapper { 
            text-align: center; 
            margin-bottom: 20px; 
        }
        
        .logo-img { 
            width: 70px; 
            height: auto;
            border-radius: 12px;
        }

        h1 {
            text-align: center;
            font-family: 'Poppins', sans-serif;
            font-size: 24px;
            color: #111827;
            margin-bottom: 8px;
        }

        p.subtitle {
            text-align: center;
            color: #6B7280;
            font-size: 14px;
            margin-bottom: 30px;
        }

        /* Form Inputs */
        .form-group {
            margin-bottom: 16px;
        }

        .input-group {
            display: flex;
            align-items: center;
            background: #F3F4F6;
            border: 2px solid transparent;
            border-radius: 12px;
            padding: 14px 16px;
            transition: all 0.3s ease;
        }

        .input-group:focus-within {
            background: #ffffff;
            border-color: #3B82F6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .input-group i {
            color: #9CA3AF;
            font-size: 18px;
            width: 24px;
            transition: color 0.3s ease;
        }

        .input-group:focus-within i {
            color: #3B82F6;
        }

        .input-group input {
            border: none;
            background: none;
            outline: none;
            flex: 1;
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            color: #1F2937;
            margin-left: 10px;
        }

        .input-group input::placeholder {
            color: #9CA3AF;
        }

        /* Checkbox */
        .remember {
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #4B5563;
            margin-bottom: 24px;
            cursor: pointer;
        }

        .remember input[type="checkbox"] {
            margin-right: 8px;
            width: 16px;
            height: 16px;
            accent-color: #1E4A7A;
            cursor: pointer;
        }

        /* Button */
        .btn-primary {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: #1E4A7A;
            color: white;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.1s ease;
        }

        .btn-primary:hover {
            background: #153860;
        }

        .btn-primary:active {
            transform: scale(0.98);
        }

        .btn-primary:disabled {
            background: #9CA3AF;
            cursor: not-allowed;
            transform: none;
        }

        /* Alert */
        .alert-error {
            display: flex;
            align-items: center;
            background: #FEF2F2;
            border-left: 4px solid #EF4444;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            color: #991B1B;
            font-size: 14px;
            font-weight: 500;
        }
        
        .alert-error i {
            margin-right: 10px;
            font-size: 16px;
        }

        /* Footer */
        .footer {
            text-align: center;
            font-size: 12px;
            color: #9CA3AF;
            margin-top: 30px;
        }

        /* Mobile Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>

<div class="login-container">
    <div class="login-card">

        <div class="logo-wrapper">
            <img src="asset/Happy.png" alt="Logo Happy Cellular" class="logo-img">
        </div>

        <h1>Happy Cellular</h1>
        <p class="subtitle">Sistem Manajemen Inventory</p>

        <!-- Pesan Error -->
        <?php if(isset($_GET['error'])){ ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span>
                <?php
                if($_GET['error'] == "gagal"){
                    echo "Username atau Password salah!";
                } elseif($_GET['error'] == "kosong"){
                    echo "Username dan Password wajib diisi!";
                } elseif($_GET['error'] == "level"){
                    echo "Level user tidak dikenali!";
                } else {
                    echo "Terjadi kesalahan, silakan coba lagi.";
                }
                ?>
            </span>
        </div>
        <?php } ?>

        <!-- Form Login -->
        <form action="aksi_login" method="POST">

            <div class="form-group">
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="Username" required autocomplete="username">
                </div>
            </div>

            <div class="form-group">
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
                </div>
            </div>

            <label class="remember">
                <input type="checkbox" name="remember"> Ingat Saya
            </label>

            <button type="submit" class="btn-primary" id="btn-login">
                Masuk ke Sistem
            </button>

        </form>

        <div class="footer">
            &copy; <?php echo date("Y"); ?> Happy Cellular Inventory System
        </div>

    </div>
</div>

<script>
    // UX Enhancement: Disable button on submit to prevent multiple clicks
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            if(btn) {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
                btn.disabled = true;
            }
        });
    });
</script>

</body>
</html>