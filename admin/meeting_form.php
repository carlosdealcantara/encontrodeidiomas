<?php
session_start();
require_once '../config.php';

// Proteção da página
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$meeting = [
    'language_id' => '',
    'host_id' => '',
    'day_of_week' => '',
    'time_hour' => '',
    'title' => '',
    'description' => '',
    'meet_link' => '',
    'replay_link' => '',
    'active' => 1
];

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM meetings WHERE id = ?");
    $stmt->execute([$id]);
    $meeting = $stmt->fetch() ?: $meeting;
}

// Busca listas para o form
$languages = $conn->query("SELECT id, name FROM languages ORDER BY name ASC")->fetchAll();
$hosts = $conn->query("SELECT id, full_name FROM hosts WHERE status = 'ativo' ORDER BY full_name ASC")->fetchAll();

// Processamento do Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'lang'   => (int)$_POST['language_id'],
        'host'   => !empty($_POST['host_id']) ? (int)$_POST['host_id'] : null,
        'day'    => (int)$_POST['day_of_week'],
        'hour'   => (int)$_POST['time_hour'],
        'title'  => $_POST['title'],
        'desc'   => $_POST['description'],
        'meet'   => $_POST['meet_link'],
        'replay' => $_POST['replay_link'],
        'active' => isset($_POST['active']) ? 1 : 0
    ];

    if ($id > 0) {
        $sql = "UPDATE meetings SET 
                language_id = :lang, host_id = :host, day_of_week = :day, time_hour = :hour,
                title = :title, description = :desc, meet_link = :meet, 
                replay_link = :replay, active = :active 
                WHERE id = :id";
        $data['id'] = $id;
    } else {
        $sql = "INSERT INTO meetings 
                (language_id, host_id, day_of_week, time_hour, title, description, meet_link, replay_link, active) 
                VALUES (:lang, :host, :day, :hour, :title, :desc, :meet, :replay, :active)";
    }

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($data);
        header('Location: meetings.php?msg=Salvo com sucesso!');
        exit;
    } catch (PDOException $e) {
        $error = "Erro ao salvar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id > 0 ? 'Editar' : 'Novo' ?> Encontro | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-bg: #0f172a;
            --sidebar-bg: #1e293b;
            --accent-red: #e31d1c;
            --accent-blue: #38bdf8;
            --text-main: #f1f5f9;
            --text-dim: #94a3b8;
            --card-bg: #1e293b;
            --input-bg: #0f172a;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }

        .sidebar { width: 280px; background: var(--sidebar-bg); padding: 30px; display: flex; flex-direction: column; border-right: 1px solid rgba(255,255,255,0.05); }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 14px 18px; color: var(--text-dim); text-decoration: none; border-radius: 12px; margin-bottom: 8px; transition: all 0.3s ease; font-weight: 500; }
        .nav-item.active { background: var(--accent-red); color: white; }

        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { margin-bottom: 40px; display: flex; align-items: center; gap: 20px; }
        .btn-back { color: var(--text-dim); text-decoration: none; font-size: 1.2rem; }
        .btn-back:hover { color: var(--white); }

        .form-container { background: var(--card-bg); border-radius: 24px; padding: 40px; border: 1px solid rgba(255,255,255,0.05); max-width: 900px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; }
        .full-width { grid-column: span 2; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 10px; color: var(--text-dim); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 12px 15px; color: var(--text-main); outline: none; transition: all 0.3s ease; font-size: 1rem;
        }
        .form-group input:focus, .form-group select:focus { border-color: var(--accent-red); box-shadow: 0 0 0 4px rgba(227, 29, 28, 0.1); }

        .form-actions { margin-top: 30px; display: flex; gap: 15px; }
        .btn-save { background: var(--accent-red); color: white; border: none; padding: 14px 35px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.3s ease; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(227, 29, 28, 0.3); }
        .btn-cancel { background: rgba(255,255,255,0.05); color: var(--text-dim); text-decoration: none; padding: 14px 35px; border-radius: 12px; font-weight: 600; }

        .switch { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .switch input { display: none; }
        .slider { width: 50px; height: 26px; background: #334155; border-radius: 13px; position: relative; transition: .4s; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background: var(--success); }
        input:checked + .slider:before { transform: translateX(24px); }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="brand" style="display:flex; align-items:center; gap:12px; margin-bottom:50px;">
            <div style="width:35px; height:35px; background:var(--accent-red); border-radius:8px; display:flex; align-items:center; justify-content:center; color:white; font-weight:700;">EI</div>
            <span style="font-size:1.2rem; font-weight:700;">ADMIN</span>
        </div>
        <nav class="nav-menu">
            <a href="index.php" class="nav-item">Dashboard</a>
            <a href="hosts.php" class="nav-item">Anfitriões</a>
            <a href="meetings.php" class="nav-item active">Encontros</a>
            <a href="languages.php" class="nav-item">Idiomas</a>
            <a href="settings.php" class="nav-item">Configurações</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <a href="meetings.php" class="btn-back"><i class="fas fa-arrow-left"></i></a>
            <h2><?= $id > 0 ? 'Editar' : 'Cadastrar Novo' ?> Encontro</h2>
        </header>

        <form method="POST" class="form-container">
            <div class="form-grid">
                <div class="form-group">
                    <label>Idioma</label>
                    <select name="language_id" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($languages as $lang): ?>
                            <option value="<?= $lang['id'] ?>" <?= $meeting['language_id'] == $lang['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lang['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Anfitrião (Host)</label>
                    <select name="host_id">
                        <option value="">Nenhum / A definir</option>
                        <?php foreach ($hosts as $h): ?>
                            <option value="<?= $h['id'] ?>" <?= $meeting['host_id'] == $h['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($h['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Dia da Semana</label>
                    <select name="day_of_week" required>
                        <?php 
                        $days = [1=>'Segunda', 2=>'Terça', 3=>'Quarta', 4=>'Quinta', 5=>'Sexta', 6=>'Sábado', 7=>'Domingo'];
                        foreach ($days as $num => $name): ?>
                            <option value="<?= $num ?>" <?= $meeting['day_of_week'] == $num ? 'selected' : '' ?>>
                                <?= $name ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Horário (Apenas a hora, ex: 19)</label>
                    <input type="number" name="time_hour" value="<?= htmlspecialchars($meeting['time_hour']) ?>" min="0" max="23" required>
                </div>

                <div class="form-group full-width">
                    <label>Título / Nome do Encontro (Opcional)</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($meeting['title']) ?>" placeholder="Ex: Intermediate Practice">
                </div>

                <div class="form-group full-width">
                    <label>Link da Reunião (Google Meet / Odysee)</label>
                    <input type="url" name="meet_link" value="<?= htmlspecialchars($meeting['meet_link']) ?>" placeholder="https://...">
                </div>



                <div class="form-group">
                    <label>Link de Replays (Odysee)</label>
                    <input type="url" name="replay_link" value="<?= htmlspecialchars($meeting['replay_link']) ?>" placeholder="https://odysee.com/...">
                </div>

                <div class="form-group full-width">
                    <label>Descrição Curta</label>
                    <textarea name="description" rows="3"><?= htmlspecialchars($meeting['description']) ?></textarea>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <label class="switch">
                        <input type="checkbox" name="active" <?= $meeting['active'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                        <span style="color: var(--text-dim);">Ativo para exibição</span>
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save">Salvar Encontro</button>
                <a href="meetings.php" class="btn-cancel">Cancelar</a>
            </div>
        </form>
    </main>
</body>
</html>
