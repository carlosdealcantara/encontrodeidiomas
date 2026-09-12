<?php
session_start();
require_once '../config.php';

// Proteção da página
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();

// Lógica de alternar status
if (isset($_GET['toggle_active']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $newStatus = $_GET['toggle_active'] == '1' ? 0 : 1;
    
    $stmt = $conn->prepare("UPDATE meetings SET active = :status WHERE id = :id");
    $stmt->execute(['status' => $newStatus, 'id' => $id]);
    
    header('Location: meetings.php?msg=Status do encontro atualizado');
    exit;
}

// Lógica de exclusão
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("DELETE FROM meetings WHERE id = :id");
    $stmt->execute(['id' => $id]);
    header('Location: meetings.php?msg=Encontro excluído com sucesso');
    exit;
}

// Verifica se a tabela meeting_sessions existe
$hasSessionsTable = false;
try {
    $res = $conn->query("SHOW TABLES LIKE 'meeting_sessions'")->fetch();
    $hasSessionsTable = !empty($res);
} catch (Exception $e) {
    $hasSessionsTable = false;
}

// Busca todos os encontros conceituais (1 por encontro)
$stmt = $conn->query("
    SELECT m.*, l.name as language_name, l.flag_code, l.flag_emoji,
           h.full_name as host_name
    FROM meetings m
    JOIN languages l ON m.language_id = l.id
    LEFT JOIN hosts h ON m.host_id = h.id AND h.status = 'ativo'
    ORDER BY l.name ASC, m.id ASC
");
$meetings = $stmt->fetchAll();

// Se a tabela meeting_sessions existir, busca as sessões de cada encontro
$sessionsByMeeting = [];
if ($hasSessionsTable && !empty($meetings)) {
    $sessionsStmt = $conn->query("
        SELECT * FROM meeting_sessions 
        ORDER BY day_of_week ASC, time_hour ASC
    ");
    $allSessions = $sessionsStmt->fetchAll();
    foreach ($allSessions as $s) {
        $sessionsByMeeting[$s['meeting_id']][] = $s;
    }
}

function getDayLabel($day) {
    $days = [1=>'Segunda', 2=>'Terça', 3=>'Quarta', 4=>'Quinta', 5=>'Sexta', 6=>'Sábado', 7=>'Domingo'];
    return $days[$day] ?? 'Desconhecido';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Encontros | Admin</title>
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
            --white: #ffffff;
            --card-bg: #1e293b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar { width: 280px; background: var(--sidebar-bg); padding: 30px; display: flex; flex-direction: column; border-right: 1px solid rgba(255,255,255,0.05); }
        .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 50px; padding: 0 10px; }
        .brand-logo { width: 35px; height: 35px; background: var(--accent-red); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; }
        .brand-name { font-size: 1.2rem; font-weight: 700; letter-spacing: -0.5px; }
        .nav-menu { flex: 1; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 14px 18px; color: var(--text-dim); text-decoration: none; border-radius: 12px; margin-bottom: 8px; transition: all 0.3s ease; font-weight: 500; }
        .nav-item:hover, .nav-item.active { background: rgba(227, 29, 28, 0.1); color: var(--white); }
        .nav-item.active { background: var(--accent-red); color: white; }
        .nav-logout { margin-top: auto; color: #ff6b6b; border: 1px solid rgba(255, 107, 107, 0.2); }

        /* Main Content */
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .header-title h2 { font-size: 1.8rem; font-weight: 700; }

        .btn-add { background: var(--accent-red); color: white; text-decoration: none; padding: 12px 24px; border-radius: 12px; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease; }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(227, 29, 28, 0.3); }

        /* Table Style */
        .table-container { background: var(--card-bg); border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 20px; background: rgba(0,0,0,0.1); color: var(--text-dim); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        td { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }

        .meeting-info { display: flex; align-items: center; gap: 15px; }
        .lang-flag { width: 30px; height: 22px; border-radius: 4px; object-fit: cover; }
        .meeting-name { font-weight: 600; color: var(--white); font-size: 1.05rem; }

        /* Chips de sessões */
        .sessions-badges { display: flex; flex-wrap: wrap; gap: 8px; max-width: 320px; }
        .session-chip {
            background: rgba(56, 189, 248, 0.12);
            color: var(--accent-blue);
            border: 1px solid rgba(56, 189, 248, 0.25);
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .session-chip.inactive {
            opacity: 0.4;
            text-decoration: line-through;
        }

        .badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .badge-active { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .badge-inactive { background: rgba(239, 68, 68, 0.1); color: var(--danger); }

        .actions { display: flex; gap: 8px; }
        .action-btn { width: 35px; height: 35px; border-radius: 8px; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.1); }
        .btn-edit { color: var(--accent-blue); }
        .btn-edit:hover { background: var(--accent-blue); color: white; }
        .btn-toggle { color: var(--text-dim); }
        .btn-toggle:hover { background: var(--text-main); color: var(--primary-bg); }
        .btn-delete { color: var(--danger); }
        .btn-delete:hover { background: var(--danger); color: white; }
        .btn-copy { color: var(--success); cursor: pointer; }
        .btn-copy:hover { background: var(--success); color: white; }

        .alert { padding: 15px 25px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--success); border-radius: 12px; margin-bottom: 25px; }
        
        .empty-state { text-align: center; padding: 60px; color: var(--text-dim); }
        .empty-state i { font-size: 3rem; margin-bottom: 20px; opacity: 0.2; }
    </style>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'%3E%3Crect width='512' height='512' rx='128' fill='%23e31d1c'/%3E%3Ctext x='256' y='256' dy='.35em' font-family='system-ui, -apple-system, sans-serif' font-weight='900' font-size='300' fill='white' text-anchor='middle'%3EEi%3C/text%3E%3C/svg%3E">
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <div class="header-title">
                <h2>Gestão da Agenda</h2>
                <p>Configure os encontros e adicione múltiplos horários/dias para cada idioma.</p>
            </div>
            <a href="meeting_form.php" class="btn-add">
                <i class="fas fa-plus"></i> Novo Encontro
            </a>
        </header>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert">
                <i class="fas fa-check-circle" style="margin-right: 8px;"></i> <?= htmlspecialchars($_GET['msg']) ?>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <?php if (empty($meetings)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>Nenhum encontro cadastrado ainda.</p>
                </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Idioma</th>
                        <th>Dias e Horários</th>
                        <th>Anfitrião</th>
                        <th>Comunidade</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($meetings as $m): 
                        $mSessions = $sessionsByMeeting[$m['id']] ?? [];
                        // Fallback caso não haja sessões na tabela nova ainda
                        if (empty($mSessions) && isset($m['day_of_week']) && $m['day_of_week'] !== null) {
                            $mSessions = [[
                                'day_of_week' => $m['day_of_week'],
                                'time_hour' => $m['time_hour'],
                                'active' => $m['active']
                            ]];
                        }
                    ?>
                    <tr>
                        <td>
                            <div class="meeting-info">
                                <?php if ($m['flag_code']): ?>
                                    <img src="https://flagcdn.com/32x24/<?= $m['flag_code'] ?>.png" class="lang-flag" alt="Bandeira">
                                <?php elseif ($m['flag_emoji']): ?>
                                    <span style="font-size: 1.5rem;"><?= $m['flag_emoji'] ?></span>
                                <?php endif; ?>
                                <div>
                                    <span class="meeting-name"><?= htmlspecialchars($m['language_name']) ?></span>
                                    <?php if (!empty($m['title'])): ?>
                                        <div style="font-size:0.8rem; color:var(--text-dim); margin-top:2px;">
                                            <?= htmlspecialchars($m['title']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="sessions-badges">
                                <?php if (!empty($mSessions)): ?>
                                    <?php foreach ($mSessions as $s): ?>
                                        <span class="session-chip <?= (isset($s['active']) && !$s['active']) ? 'inactive' : '' ?>">
                                            <i class="far fa-clock"></i>
                                            <?= getDayLabel($s['day_of_week']) ?> às <?= $s['time_hour'] ?>h
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color:var(--text-dim); font-size:0.85rem;">Nenhum horário definido</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span style="color: var(--text-dim); font-size: 0.9rem;">
                                <?php if ($m['host_name']): ?>
                                    <i class="fas fa-user-circle" style="margin-right: 5px;"></i>
                                    <?= htmlspecialchars($m['host_name']) ?>
                                <?php else: ?>
                                    <i class="fas fa-comments" style="margin-right: 5px; color: var(--accent-blue);"></i>
                                    <span style="color: var(--accent-blue); font-weight: 600;">Free Conversation</span>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td>
                            <?php
                                $com = $m['comunidade'] ?? 'brasil';
                                $comLabel = match($com) {
                                    'global' => '<img src="https://flagcdn.com/w20/us.png" style="width:16px; margin-right:5px; border-radius:2px;" alt="US"> Global',
                                    default  => '<img src="https://flagcdn.com/w20/br.png" style="width:16px; margin-right:5px; border-radius:2px;" alt="BR"> Brasil',
                                };
                                $comColor = match($com) {
                                    'global' => '#38bdf8',
                                    default  => '#10b981',
                                };
                            ?>
                            <span class="badge" style="background:<?= $comColor ?>20;color:<?= $comColor ?>; display: inline-flex; align-items: center;"><?= $comLabel ?></span>
                        </td>
                        <td>
                            <?php if ($m['active']): ?>
                                <span class="badge badge-active">Ativo</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="meeting_form.php?id=<?= $m['id'] ?>" class="action-btn btn-edit" title="Editar encontro e horários"><i class="fas fa-edit"></i></a>
                                <a href="meetings.php?toggle_active=<?= $m['active'] ?>&id=<?= $m['id'] ?>" class="action-btn btn-toggle" title="Alternar Status Geral">
                                    <i class="fas fa-power-off"></i>
                                </a>
                                <?php 
                                    $firstSess = !empty($mSessions) ? $mSessions[0] : null;
                                    $copyDay = $firstSess ? getDayLabel($firstSess['day_of_week']) : '';
                                    $copyHour = $firstSess ? $firstSess['time_hour'] : '';
                                ?>
                                <div class="action-btn btn-copy" title="Copiar para WhatsApp" onclick="copyToWhatsapp('<?= addslashes($m['language_name']) ?>', '<?= $copyDay ?>', '<?= $copyHour ?>', '<?= $m['meet_link'] ?>')">
                                    <i class="fab fa-whatsapp"></i>
                                </div>
                                <a href="meetings.php?delete=1&id=<?= $m['id'] ?>" class="action-btn btn-delete" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este encontro e todos os seus horários?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function copyToWhatsapp(lang, day, hour, link) {
            const text = `🚀 *ENCONTRO DE IDIOMAS* 🚀\n\n` +
                         `🗣 Idioma: *${lang}*\n` +
                         (day ? `🗓 Quando: ${day} às ${hour}h\n` : '') +
                         `🔗 Link da Sala: ${link || 'Link será enviado em breve'}\n\n` +
                         `Vem praticar com a gente!`;
            
            navigator.clipboard.writeText(text).then(() => {
                alert('Texto copiado para o WhatsApp!');
            });
        }
    </script>
</body>
</html>
