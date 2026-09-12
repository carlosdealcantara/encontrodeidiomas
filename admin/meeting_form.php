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
    'day_of_week' => '1',
    'time_hour' => '19',
    'title' => '',
    'description' => '',
    'description_en' => '',
    'meet_link' => '',
    'replay_link' => '',
    'comunidade' => 'brasil',
    'active' => 1
];

// Verifica se a tabela meeting_sessions existe
$hasSessionsTable = false;
try {
    $res = $conn->query("SHOW TABLES LIKE 'meeting_sessions'")->fetch();
    $hasSessionsTable = !empty($res);
} catch (Exception $e) {
    $hasSessionsTable = false;
}

$sessions = [];

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM meetings WHERE id = ?");
    $stmt->execute([$id]);
    $meetingData = $stmt->fetch();
    if ($meetingData) {
        $meeting = array_merge($meeting, $meetingData);
    }

    if ($hasSessionsTable) {
        $stmtSessions = $conn->prepare("SELECT * FROM meeting_sessions WHERE meeting_id = ? ORDER BY day_of_week ASC, time_hour ASC");
        $stmtSessions->execute([$id]);
        $sessions = $stmtSessions->fetchAll();
    }
}

// Se não houver sessões cadastradas no BD ainda, usa o horário padrão do próprio meeting
if (empty($sessions)) {
    $defaultDay = !empty($meeting['day_of_week']) ? (int)$meeting['day_of_week'] : 1;
    $defaultHour = isset($meeting['time_hour']) && $meeting['time_hour'] !== '' ? (int)$meeting['time_hour'] : 19;
    $sessions = [
        [
            'id' => 0,
            'day_of_week' => $defaultDay,
            'time_hour' => $defaultHour,
            'active' => 1
        ]
    ];
}

// Busca listas para o form
$languages = $conn->query("SELECT id, name FROM languages ORDER BY name ASC")->fetchAll();
$hosts = $conn->query("SELECT id, full_name FROM hosts WHERE status = 'ativo' ORDER BY full_name ASC")->fetchAll();

$daysMap = [
    1 => 'Segunda-feira',
    2 => 'Terça-feira',
    3 => 'Quarta-feira',
    4 => 'Quinta-feira',
    5 => 'Sexta-feira',
    6 => 'Sábado',
    7 => 'Domingo'
];

// Processamento do Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postDays = $_POST['session_day'] ?? [];
    $postHours = $_POST['session_hour'] ?? [];
    $postActives = $_POST['session_active'] ?? [];

    // Primeiro horário como primário para compatibilidade com colunas legadas se existirem
    $firstDay = !empty($postDays) ? (int)$postDays[0] : 1;
    $firstHour = !empty($postHours) ? (int)$postHours[0] : 19;

    $hasDayCol = (bool)$conn->query("SHOW COLUMNS FROM meetings LIKE 'day_of_week'")->fetch();

    $langId = (int)$_POST['language_id'];
    $hostId = !empty($_POST['host_id']) ? (int)$_POST['host_id'] : null;
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $descEn = trim($_POST['description_en'] ?? '');
    $meet = trim($_POST['meet_link'] ?? '');
    $replay = trim($_POST['replay_link'] ?? '');
    $comunidade = in_array($_POST['comunidade'] ?? '', ['brasil','global']) ? $_POST['comunidade'] : 'brasil';
    $active = isset($_POST['active']) ? 1 : 0;

    try {
        $conn->beginTransaction();

        if ($id > 0) {
            if ($hasDayCol) {
                $sql = "UPDATE meetings SET 
                        language_id = :lang, host_id = :host, day_of_week = :day, time_hour = :hour,
                        title = :title, description = :desc, description_en = :desc_en, meet_link = :meet, 
                        replay_link = :replay, comunidade = :comunidade, active = :active 
                        WHERE id = :id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':lang' => $langId, ':host' => $hostId, ':day' => $firstDay, ':hour' => $firstHour,
                    ':title' => $title, ':desc' => $desc, ':desc_en' => $descEn, ':meet' => $meet,
                    ':replay' => $replay, ':comunidade' => $comunidade, ':active' => $active, ':id' => $id
                ]);
            } else {
                $sql = "UPDATE meetings SET 
                        language_id = :lang, host_id = :host,
                        title = :title, description = :desc, description_en = :desc_en, meet_link = :meet, 
                        replay_link = :replay, comunidade = :comunidade, active = :active 
                        WHERE id = :id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':lang' => $langId, ':host' => $hostId,
                    ':title' => $title, ':desc' => $desc, ':desc_en' => $descEn, ':meet' => $meet,
                    ':replay' => $replay, ':comunidade' => $comunidade, ':active' => $active, ':id' => $id
                ]);
            }
            $targetMeetingId = $id;
        } else {
            if ($hasDayCol) {
                $sql = "INSERT INTO meetings 
                        (language_id, host_id, day_of_week, time_hour, title, description, description_en, meet_link, replay_link, comunidade, active) 
                        VALUES (:lang, :host, :day, :hour, :title, :desc, :desc_en, :meet, :replay, :comunidade, :active)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':lang' => $langId, ':host' => $hostId, ':day' => $firstDay, ':hour' => $firstHour,
                    ':title' => $title, ':desc' => $desc, ':desc_en' => $descEn, ':meet' => $meet,
                    ':replay' => $replay, ':comunidade' => $comunidade, ':active' => $active
                ]);
            } else {
                $sql = "INSERT INTO meetings 
                        (language_id, host_id, title, description, description_en, meet_link, replay_link, comunidade, active) 
                        VALUES (:lang, :host, :title, :desc, :desc_en, :meet, :replay, :comunidade, :active)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':lang' => $langId, ':host' => $hostId,
                    ':title' => $title, ':desc' => $desc, ':desc_en' => $descEn, ':meet' => $meet,
                    ':replay' => $replay, ':comunidade' => $comunidade, ':active' => $active
                ]);
            }
            $targetMeetingId = (int)$conn->lastInsertId();
        }

        // Salva as sessões na tabela meeting_sessions se ela existir
        if ($hasSessionsTable && $targetMeetingId > 0) {
            // Remove as sessões atuais deste encontro para regravar
            $delStmt = $conn->prepare("DELETE FROM meeting_sessions WHERE meeting_id = ?");
            $delStmt->execute([$targetMeetingId]);

            $insStmt = $conn->prepare("
                INSERT INTO meeting_sessions (meeting_id, day_of_week, time_hour, active) 
                VALUES (?, ?, ?, ?)
            ");

            foreach ($postDays as $idx => $d) {
                $dayVal = (int)$d;
                $hourVal = isset($postHours[$idx]) ? (int)$postHours[$idx] : 19;
                // active por sessão: se a flag correspondente existir
                $sessActive = isset($postActives[$idx]) ? 1 : 0;
                
                if ($dayVal >= 1 && $dayVal <= 7 && $hourVal >= 0 && $hourVal <= 23) {
                    $insStmt->execute([$targetMeetingId, $dayVal, $hourVal, $sessActive]);
                }
            }
        }

        $conn->commit();
        header('Location: meetings.php?msg=Encontro+salvo+com+sucesso!');
        exit;
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
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
            --success: #10b981;
            --danger: #ef4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }

        .sidebar { width: 280px; background: var(--sidebar-bg); padding: 30px; display: flex; flex-direction: column; border-right: 1px solid rgba(255,255,255,0.05); }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 14px 18px; color: var(--text-dim); text-decoration: none; border-radius: 12px; margin-bottom: 8px; transition: all 0.3s ease; font-weight: 500; }
        .nav-item.active { background: var(--accent-red); color: white; }

        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { margin-bottom: 40px; display: flex; align-items: center; gap: 20px; }
        .btn-back { color: var(--text-dim); text-decoration: none; font-size: 1.2rem; }
        .btn-back:hover { color: var(--text-main); }

        .form-container { background: var(--card-bg); border-radius: 24px; padding: 40px; border: 1px solid rgba(255,255,255,0.05); max-width: 900px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; }
        .full-width { grid-column: span 2; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 10px; color: var(--text-dim); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 12px 15px; color: var(--text-main); outline: none; transition: all 0.3s ease; font-size: 1rem;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--accent-red); box-shadow: 0 0 0 4px rgba(227, 29, 28, 0.1); }

        /* Sessões Card */
        .sessions-section {
            grid-column: span 2;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 25px;
            margin-top: 10px;
        }
        .sessions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .sessions-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--accent-blue);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn-add-session {
            background: rgba(56, 189, 248, 0.15);
            color: var(--accent-blue);
            border: 1px solid rgba(56, 189, 248, 0.3);
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        .btn-add-session:hover {
            background: var(--accent-blue);
            color: #0f172a;
            transform: translateY(-1px);
        }

        .session-row {
            display: grid;
            grid-template-columns: 2fr 1.2fr 1fr auto;
            gap: 15px;
            align-items: center;
            background: rgba(30, 41, 59, 0.8);
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.2s ease;
        }
        .session-row:hover {
            border-color: rgba(255, 255, 255, 0.12);
        }
        .session-col select, .session-col input {
            background: var(--input-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-main);
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 0.95rem;
            width: 100%;
        }
        .btn-remove-session {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-remove-session:hover {
            background: var(--danger);
            color: #fff;
        }

        .form-actions { margin-top: 30px; display: flex; gap: 15px; }
        .btn-save { background: var(--accent-red); color: white; border: none; padding: 14px 35px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.3s ease; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(227, 29, 28, 0.3); }
        .btn-cancel { background: rgba(255,255,255,0.05); color: var(--text-dim); text-decoration: none; padding: 14px 35px; border-radius: 12px; font-weight: 600; display: inline-flex; align-items: center; }

        .form-group .switch { display: inline-flex !important; align-items: center; gap: 12px; cursor: pointer; user-select: none; width: auto; }
        .form-group .switch input { display: none; }
        .slider { 
            width: 44px; height: 22px; background: #334155; border-radius: 20px; position: relative; transition: .3s; flex-shrink: 0;
        }
        .slider:before { 
            position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background: white; transition: .3s; border-radius: 50%; 
        }
        input:checked + .slider { background: var(--success); }
        input:checked + .slider:before { transform: translateX(22px); }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
    </style>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'%3E%3Crect width='512' height='512' rx='128' fill='%23e31d1c'/%3E%3Ctext x='256' y='256' dy='.35em' font-family='system-ui, -apple-system, sans-serif' font-weight='900' font-size='300' fill='white' text-anchor='middle'%3EEi%3C/text%3E%3C/svg%3E">
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <a href="meetings.php" class="btn-back"><i class="fas fa-arrow-left"></i></a>
            <h2><?= $id > 0 ? 'Editar' : 'Cadastrar Novo' ?> Encontro</h2>
        </header>

        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

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
                        <option value="">Nenhum / A definir (Free Conversation)</option>
                        <?php foreach ($hosts as $h): ?>
                            <option value="<?= $h['id'] ?>" <?= $meeting['host_id'] == $h['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($h['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label>Comunidade</label>
                    <select name="comunidade" id="comunidade" required>
                        <option value="brasil" <?= ($meeting['comunidade'] ?? 'brasil') === 'brasil' ? 'selected' : '' ?>>🇧🇷 Brasil (Apenas grupos BR)</option>
                        <option value="global" <?= ($meeting['comunidade'] ?? '') === 'global' ? 'selected' : '' ?>>🌐 Global (Grupos Global e BR)</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label>Título / Nome do Encontro (Opcional)</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($meeting['title'] ?? '') ?>" placeholder="Ex: Conversação Geral ou Intermediate Practice">
                </div>

                <div class="form-group full-width">
                    <label>Link da Reunião (Google Meet / Odysee)</label>
                    <input type="url" name="meet_link" value="<?= htmlspecialchars($meeting['meet_link'] ?? '') ?>" placeholder="https://meet.google.com/...">
                </div>

                <div class="form-group full-width">
                    <label>Link de Replays (Odysee)</label>
                    <input type="url" name="replay_link" value="<?= htmlspecialchars($meeting['replay_link'] ?? '') ?>" placeholder="https://odysee.com/...">
                </div>

                <div class="form-group full-width">
                    <label>Descrição Curta (Português)</label>
                    <textarea name="description" rows="3"><?= htmlspecialchars($meeting['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group full-width">
                    <label>Descrição Curta (Inglês) - Opcional</label>
                    <textarea name="description_en" rows="3"><?= htmlspecialchars($meeting['description_en'] ?? '') ?></textarea>
                </div>

                <!-- SEÇÃO DE DIAS E HORÁRIOS -->
                <div class="sessions-section">
                    <div class="sessions-header">
                        <div class="sessions-title">
                            <i class="far fa-calendar-alt"></i>
                            Dias e Horários das Sessões
                        </div>
                        <button type="button" class="btn-add-session" onclick="addSessionRow()">
                            <i class="fas fa-plus"></i> Adicionar Horário
                        </button>
                    </div>

                    <div id="sessions-container">
                        <?php foreach ($sessions as $sIndex => $sess): ?>
                        <div class="session-row">
                            <div class="session-col">
                                <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:5px; text-transform:uppercase;">Dia da Semana</label>
                                <select name="session_day[]" required>
                                    <?php foreach ($daysMap as $dNum => $dLabel): ?>
                                        <option value="<?= $dNum ?>" <?= (int)$sess['day_of_week'] === $dNum ? 'selected' : '' ?>>
                                            <?= $dLabel ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="session-col">
                                <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:5px; text-transform:uppercase;">Horário (0-23h)</label>
                                <input type="number" name="session_hour[]" value="<?= htmlspecialchars($sess['time_hour']) ?>" min="0" max="23" required placeholder="Ex: 19">
                            </div>
                            <div class="session-col" style="display:flex; flex-direction:column; justify-content:center;">
                                <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:5px; text-transform:uppercase;">Status</label>
                                <label class="switch">
                                    <input type="checkbox" name="session_active[<?= $sIndex ?>]" value="1" <?= (!isset($sess['active']) || $sess['active']) ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div>
                                <button type="button" class="btn-remove-session" title="Remover horário" onclick="removeSessionRow(this)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group full-width" style="margin-top: 10px;">
                    <label>Status do Encontro Geral</label>
                    <label class="switch">
                        <input type="checkbox" name="active" <?= $meeting['active'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                        <span style="color: var(--text-dim);">Ativo para exibição no site</span>
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save">Salvar Encontro</button>
                <a href="meetings.php" class="btn-cancel">Cancelar</a>
            </div>
        </form>
    </main>

    <script>
        const daysOptions = `
            <?php foreach ($daysMap as $dNum => $dLabel): ?>
                <option value="<?= $dNum ?>"><?= $dLabel ?></option>
            <?php endforeach; ?>
        `;

        function addSessionRow(day = 1, hour = 19) {
            const container = document.getElementById('sessions-container');
            const newIndex = container.querySelectorAll('.session-row').length;

            const row = document.createElement('div');
            row.className = 'session-row';
            row.innerHTML = `
                <div class="session-col">
                    <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:5px; text-transform:uppercase;">Dia da Semana</label>
                    <select name="session_day[]" required>
                        ${daysOptions}
                    </select>
                </div>
                <div class="session-col">
                    <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:5px; text-transform:uppercase;">Horário (0-23h)</label>
                    <input type="number" name="session_hour[]" value="${hour}" min="0" max="23" required placeholder="Ex: 19">
                </div>
                <div class="session-col" style="display:flex; flex-direction:column; justify-content:center;">
                    <label style="display:block; font-size:0.75rem; color:var(--text-dim); margin-bottom:5px; text-transform:uppercase;">Status</label>
                    <label class="switch">
                        <input type="checkbox" name="session_active[${newIndex}]" value="1" checked>
                        <span class="slider"></span>
                    </label>
                </div>
                <div>
                    <button type="button" class="btn-remove-session" title="Remover horário" onclick="removeSessionRow(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(row);
        }

        function removeSessionRow(btn) {
            const container = document.getElementById('sessions-container');
            const rows = container.querySelectorAll('.session-row');
            if (rows.length <= 1) {
                alert('O encontro deve ter pelo menos um horário configurado.');
                return;
            }
            btn.closest('.session-row').remove();
            // Reindexar inputs de checkbox para manter array consistente
            container.querySelectorAll('.session-row').forEach((row, idx) => {
                const chk = row.querySelector('input[type="checkbox"]');
                if (chk) {
                    chk.name = `session_active[${idx}]`;
                }
            });
        }
    </script>
</body>
</html>
