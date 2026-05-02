<?php
session_start();
require_once '../config.php';

// Se já estiver logado, vai para o dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $user;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Credenciais inválidas. Tente novamente.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Encontro de Idiomas</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-bg: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --accent-red: #e31d1c;
            --accent-blue: #38bdf8;
            --text-main: #f1f5f9;
            --text-dim: #94a3b8;
            --white: #ffffff;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }

        body {
            background: radial-gradient(circle at top right, #1e1b4b, #0f172a);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-main);
            overflow: hidden;
        }

        /* Background elements for depth */
        .bg-blob {
            position: absolute;
            width: 300px;
            height: 300px;
            background: var(--accent-red);
            filter: blur(120px);
            opacity: 0.15;
            border-radius: 50%;
            z-index: 0;
        }
        .blob-1 { top: 10%; right: 10%; }
        .blob-2 { bottom: 10%; left: 10%; background: var(--accent-blue); }

        .login-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            padding: 40px;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: cardFade 0.8s ease-out;
        }

        @keyframes cardFade {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header { text-align: center; margin-bottom: 35px; }
        .logo-icon {
            width: 60px;
            height: 60px;
            background: var(--accent-red);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.8rem;
            color: white;
            box-shadow: 0 10px 20px rgba(227, 29, 28, 0.3);
        }
        .header h1 { font-size: 1.8rem; font-weight: 700; margin-bottom: 5px; }
        .header p { color: var(--text-dim); font-size: 0.95rem; }

        .form-group { margin-bottom: 20px; }
        .label { display: block; margin-bottom: 8px; font-size: 0.9rem; font-weight: 500; color: var(--text-dim); }
        .input-wrapper { position: relative; }
        .input-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-dim); }
        .input-field {
            width: 100%;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 12px 15px 12px 45px;
            color: var(--white);
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
        }
        .input-field:focus {
            border-color: var(--accent-red);
            background: rgba(15, 23, 42, 0.8);
            box-shadow: 0 0 0 4px rgba(227, 29, 28, 0.1);
        }

        .error-box {
            background: rgba(227, 29, 28, 0.1);
            border: 1px solid rgba(227, 29, 28, 0.2);
            color: #ff9494;
            padding: 12px;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .login-btn {
            width: 100%;
            background: var(--accent-red);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        .login-btn:hover {
            background: #f12e2d;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(227, 29, 28, 0.3);
        }
        .login-btn:active { transform: translateY(0); }

        .footer-links {
            margin-top: 25px;
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-dim);
        }
        .footer-links a { color: var(--accent-red); text-decoration: none; font-weight: 600; }
        .footer-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>

    <div class="login-card">
        <div class="header">
            <div class="logo-icon">
                <i class="fas fa-lock"></i>
            </div>
            <h1>Acesso Restrito</h1>
            <p>Painel Administrativo Encontro de Idiomas</p>
        </div>

        <?php if ($error): ?>
            <div class="error-box">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="label">Usuário</label>
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" class="input-field" placeholder="Digite seu usuário" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label class="label">Senha</label>
                <div class="input-wrapper">
                    <i class="fas fa-key"></i>
                    <input type="password" name="password" class="input-field" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="login-btn">
                Entrar no Sistema <i class="fas fa-arrow-right" style="margin-left: 8px; font-size: 0.8rem;"></i>
            </button>
        </form>

        <div class="footer-links">
            <p>Esqueceu a senha? <a href="mailto:<?= ADMIN_EMAIL ?>">Falar com suporte</a></p>
            <p style="margin-top: 15px;"><a href="../index.php"><i class="fas fa-chevron-left"></i> Voltar para o site</a></p>
        </div>
    </div>
</body>
</html>
