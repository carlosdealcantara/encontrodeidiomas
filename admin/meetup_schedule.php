<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$msg = '';
$error = '';

try {
    $conn = connectDB();
    
    // Process form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            
            if ($action === 'add' || $action === 'edit') {
                $day_of_week = (int)$_POST['day_of_week'];
                $start_time = $_POST['start_time'];
                $meet_link = trim($_POST['meet_link']);
                
                // Get group JID from config
                require_once '../includes/whatsapp_helper.php';
                $config = getMentoriaConfig();
                $group_jid = $config['groups']['our_meetups']['jid'] ?? '';
                
                if (empty($group_jid)) {
                    $error = "Por favor, configure primeiro o grupo Our Meetups na aba de Automações Mentoria.";
                } else {
                    if ($action === 'add') {
                        $stmt = $conn->prepare("INSERT INTO meetup_schedule (group_jid, day_of_week, start_time, meet_link) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$group_jid, $day_of_week, $start_time, $meet_link]);
                        $msg = "Horário adicionado com sucesso!";
                    } else {
                        $id = (int)$_POST['id'];
                        $stmt = $conn->prepare("UPDATE meetup_schedule SET day_of_week=?, start_time=?, meet_link=? WHERE id=?");
                        $stmt->execute([$day_of_week, $start_time, $meet_link, $id]);
                        $msg = "Horário atualizado com sucesso!";
                    }
                }
            } elseif ($action === 'toggle') {
                $id = (int)$_POST['id'];
                $status = (int)$_POST['status'];
                $stmt = $conn->prepare("UPDATE meetup_schedule SET is_active=? WHERE id=?");
                $stmt->execute([$status, $id]);
                $msg = "Status do horário atualizado.";
            } elseif ($action === 'delete') {
                $id = (int)$_POST['id'];
                $stmt = $conn->prepare("DELETE FROM meetup_schedule WHERE id=?");
                $stmt->execute([$id]);
                $msg = "Horário removido com sucesso.";
            }
        }
    }
    
    // Fetch current schedule
    $schedules = $conn->query("SELECT * FROM meetup_schedule ORDER BY day_of_week ASC, start_time ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

$days = [
    1 => 'Segunda-feira',
    2 => 'Terça-feira',
    3 => 'Quarta-feira',
    4 => 'Quinta-feira',
    5 => 'Sexta-feira',
    6 => 'Sábado',
    7 => 'Domingo'
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda Our Meetups - Encontro de Idiomas</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .table-container { background: var(--bg-card); padding: 20px; border-radius: 8px; border: 1px solid var(--border-color); margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); color: var(--text-color); }
        th { background: rgba(0,0,0,0.2); }
        .btn-sm { padding: 5px 10px; font-size: 12px; }
        .status-active { color: #4ade80; }
        .status-inactive { color: #f87171; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: var(--bg-card); padding: 25px; border-radius: 8px; width: 100%; max-width: 500px; border: 1px solid var(--border-color); }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="header-actions">
                <h1>📅 Agenda Our Meetups</h1>
                <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> Novo Horário</button>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Dia da Semana</th>
                            <th>Horário (BRT)</th>
                            <th>Link do Google Meet</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($schedules)): ?>
                        <tr><td colspan="5" style="text-align: center;">Nenhum horário cadastrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($schedules as $s): ?>
                            <tr>
                                <td><strong><?php echo $days[$s['day_of_week']]; ?></strong></td>
                                <td><?php echo date('H:i', strtotime($s['start_time'])); ?></td>
                                <td><a href="https://<?php echo htmlspecialchars($s['meet_link']); ?>" target="_blank" style="color: #60a5fa;"><?php echo htmlspecialchars($s['meet_link']); ?></a></td>
                                <td>
                                    <?php if ($s['is_active']): ?>
                                        <span class="status-active"><i class="fas fa-check-circle"></i> Ativo</span>
                                    <?php else: ?>
                                        <span class="status-inactive"><i class="fas fa-times-circle"></i> Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="editSchedule(<?php echo htmlspecialchars(json_encode($s)); ?>)"><i class="fas fa-edit"></i></button>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo $s['is_active'] ? 0 : 1; ?>">
                                        <button type="submit" class="btn <?php echo $s['is_active'] ? 'btn-danger' : 'btn-success'; ?> btn-sm" title="<?php echo $s['is_active'] ? 'Desativar' : 'Ativar'; ?>">
                                            <i class="fas <?php echo $s['is_active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este horário?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Excluir"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 20px;">
                <p><i class="fas fa-info-circle"></i> Para alterar o conteúdo das mensagens enviadas no grupo (Aviso Matinal, Cancelamento e Início), acesse a página <strong>Automações Mentoria</strong>.</p>
            </div>
        </div>
    </div>

    <!-- Modal Form -->
    <div id="scheduleModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">Adicionar Horário</h2>
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="formId" value="">
                
                <div class="form-group">
                    <label>Dia da Semana</label>
                    <select name="day_of_week" id="day_of_week" required>
                        <?php foreach ($days as $num => $nome): ?>
                            <option value="<?php echo $num; ?>"><?php echo $nome; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Horário de Início (BRT)</label>
                    <input type="time" name="start_time" id="start_time" required>
                </div>
                
                <div class="form-group">
                    <label>Link do Google Meet (sem https://)</label>
                    <input type="text" name="meet_link" id="meet_link" placeholder="meet.google.com/xyz-abcd-123" required>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-success" style="flex: 1;">Salvar</button>
                    <button type="button" class="btn btn-danger" style="flex: 1;" onclick="closeModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('modalTitle').textContent = 'Adicionar Horário';
            document.getElementById('formAction').value = 'add';
            document.getElementById('formId').value = '';
            document.getElementById('day_of_week').value = '1';
            document.getElementById('start_time').value = '';
            document.getElementById('meet_link').value = '';
            document.getElementById('scheduleModal').classList.add('active');
        }
        
        function editSchedule(schedule) {
            document.getElementById('modalTitle').textContent = 'Editar Horário';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('formId').value = schedule.id;
            document.getElementById('day_of_week').value = schedule.day_of_week;
            document.getElementById('start_time').value = schedule.start_time.substring(0, 5); // get HH:mm
            document.getElementById('meet_link').value = schedule.meet_link;
            document.getElementById('scheduleModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('scheduleModal').classList.remove('active');
        }
    </script>
</body>
</html>
