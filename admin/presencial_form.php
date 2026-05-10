<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$ev   = null;
$errors = [];

// Busca hosts presenciais
$hosts = $conn->query("SELECT id, full_name FROM hosts WHERE status = 'ativo' ORDER BY full_name")->fetchAll();

// Carregar para edição
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM in_person_events WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $ev = $stmt->fetch();
    if (!$ev) { header('Location: presencial.php'); exit; }
}

// Salvar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title          = trim($_POST['title'] ?? '');
    $city           = trim($_POST['city'] ?? '');
    $state          = trim($_POST['state'] ?? '');
    $country        = trim($_POST['country'] ?? 'Brasil');
    $description    = trim($_POST['description'] ?? '');
    $host_id        = !empty($_POST['host_id']) ? (int)$_POST['host_id'] : null;
    $whatsapp_link  = trim($_POST['whatsapp_link'] ?? '');
    $instagram_link = trim($_POST['instagram_link'] ?? '');
    $active         = isset($_POST['active']) ? 1 : 0;

    if (empty($title))   $errors[] = 'Título é obrigatório.';
    if (empty($city))    $errors[] = 'Cidade é obrigatória.';
    if (empty($country)) $errors[] = 'País é obrigatório.';

    if (empty($errors)) {
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE in_person_events SET title=:title, city=:city, state=:state, country=:country, description=:description, host_id=:host_id, whatsapp_link=:whatsapp_link, instagram_link=:instagram_link, active=:active WHERE id=:id");
            $stmt->execute(compact('title','city','state','country','description','host_id','whatsapp_link','instagram_link','active','id'));
        } else {
            $stmt = $conn->prepare("INSERT INTO in_person_events (title,city,state,country,description,host_id,whatsapp_link,instagram_link,active) VALUES (:title,:city,:state,:country,:description,:host_id,:whatsapp_link,:instagram_link,:active)");
            $stmt->execute(compact('title','city','state','country','description','host_id','whatsapp_link','instagram_link','active'));
        }
        header('Location: presencial.php?success=1');
        exit;
    }
    $ev = compact('title','city','state','country','description','host_id','whatsapp_link','instagram_link','active');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id > 0 ? 'Editar' : 'Novo' ?> Evento Presencial | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-bg:#0f172a; --sidebar-bg:#1e293b; --accent-red:#e31d1c;
            --accent-blue:#38bdf8; --text-main:#f1f5f9; --text-dim:#94a3b8;
            --white:#ffffff; --card-bg:#1e293b; --success:#10b981; --danger:#ef4444;
        }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Outfit',sans-serif; }
        body { background:var(--primary-bg); color:var(--text-main); display:flex; min-height:100vh; }
        .sidebar { width:280px; background:var(--sidebar-bg); padding:30px; display:flex; flex-direction:column; border-right:1px solid rgba(255,255,255,0.05); flex-shrink:0; }
        .brand { display:flex; align-items:center; gap:12px; margin-bottom:50px; padding:0 10px; }
        .brand-logo { width:35px; height:35px; background:var(--accent-red); border-radius:8px; display:flex; align-items:center; justify-content:center; color:white; font-weight:700; }
        .brand-name { font-size:1.2rem; font-weight:700; }
        .nav-menu { flex:1; }
        .nav-item { display:flex; align-items:center; gap:12px; padding:14px 18px; color:var(--text-dim); text-decoration:none; border-radius:12px; margin-bottom:8px; transition:all 0.3s; font-weight:500; }
        .nav-item:hover { background:rgba(227,29,28,0.1); color:var(--white); }
        .nav-item.active { background:var(--accent-red); color:white; }
        .nav-logout { margin-top:auto; color:#ff6b6b; border:1px solid rgba(255,107,107,0.2); }
        .main-content { flex:1; padding:40px; overflow-y:auto; }
        .page-header { display:flex; align-items:center; gap:15px; margin-bottom:40px; }
        .back-btn { display:flex; align-items:center; gap:8px; color:var(--text-dim); text-decoration:none; padding:10px 16px; border-radius:10px; border:1px solid rgba(255,255,255,0.1); transition:all 0.3s; }
        .back-btn:hover { color:var(--white); border-color:rgba(255,255,255,0.3); }
        .page-title { font-size:1.8rem; font-weight:700; }
        .form-card { background:var(--card-bg); border-radius:20px; border:1px solid rgba(255,255,255,0.05); padding:35px; }
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:25px; }
        .form-group { display:flex; flex-direction:column; gap:8px; }
        .form-group.full { grid-column:1/-1; }
        label { font-weight:600; font-size:0.9rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:0.5px; }
        input, select, textarea {
            background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1);
            border-radius:12px; padding:14px 18px; color:var(--text-main);
            font-family:'Outfit',sans-serif; font-size:1rem; outline:none; transition:all 0.3s; width:100%;
        }
        input:focus, select:focus, textarea:focus { border-color:var(--accent-red); background:rgba(255,255,255,0.07); }
        textarea { resize:vertical; min-height:120px; }
        select option { background:var(--sidebar-bg); }
        .toggle-group { display:flex; align-items:center; gap:12px; }
        .toggle-label { font-weight:500; }
        input[type="checkbox"] { width:auto; }
        .errors { background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.2); color:var(--danger); padding:15px 20px; border-radius:12px; margin-bottom:25px; }
        .errors li { margin-left:15px; margin-bottom:5px; }
        .btn-row { display:flex; gap:15px; margin-top:30px; justify-content:flex-end; }
        .btn-save { background:var(--accent-red); color:white; border:none; padding:14px 32px; border-radius:12px; font-weight:600; font-size:1rem; cursor:pointer; display:flex; align-items:center; gap:8px; transition:all 0.3s; }
        .btn-save:hover { transform:translateY(-2px); box-shadow:0 10px 20px rgba(227,29,28,0.3); }
        .btn-cancel { background:transparent; color:var(--text-dim); border:1px solid rgba(255,255,255,0.1); padding:14px 24px; border-radius:12px; font-weight:600; cursor:pointer; text-decoration:none; display:flex; align-items:center; gap:8px; transition:all 0.3s; }
        .btn-cancel:hover { color:var(--white); border-color:rgba(255,255,255,0.3); }
        .section-divider { grid-column:1/-1; border-top:1px solid rgba(255,255,255,0.07); padding-top:20px; margin-top:5px; }
        .section-divider h3 { font-size:0.85rem; text-transform:uppercase; letter-spacing:1px; color:var(--text-dim); margin-bottom:0; }

        /* Estilo do Toggle */
        .switch { display: inline-flex; align-items: center; gap: 12px; cursor: pointer; user-select: none; }
        .switch input { display: none; }
        .slider { 
            width: 44px; height: 22px; background: #334155; border-radius: 20px; position: relative; transition: .3s; flex-shrink: 0;
        }
        .slider:before { 
            position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background: white; transition: .3s; border-radius: 50%; 
        }
        input:checked + .slider { background: var(--success); }
        input:checked + .slider:before { transform: translateX(22px); }
    </style>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'%3E%3Crect width='512' height='512' rx='128' fill='%23e31d1c'/%3E%3Ctext x='256' y='256' dy='.35em' font-family='system-ui, -apple-system, sans-serif' font-weight='900' font-size='300' fill='white' text-anchor='middle'%3EEi%3C/text%3E%3C/svg%3E">
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <a href="presencial.php" class="back-btn"><i class="fas fa-arrow-left"></i> Voltar</a>
            <h1 class="page-title"><?= $id > 0 ? 'Editar Evento Presencial' : 'Novo Evento Presencial' ?></h1>
        </div>

        <?php if (!empty($errors)): ?>
            <ul class="errors"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>

        <form method="POST" class="form-card">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Título do Evento *</label>
                    <input type="text" name="title" placeholder="Ex: Encontro Presencial de Inglês em São Paulo" value="<?= htmlspecialchars($ev['title'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Cidade *</label>
                    <input type="text" name="city" placeholder="Ex: São Paulo" value="<?= htmlspecialchars($ev['city'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>País *</label>
                    <select name="country">
                        <?php foreach (['Brasil','Argentina','Paraguai','Chile','Uruguai','Peru','Colômbia','Bolívia','Venezuela','México','Portugal','Angola','Moçambique','Outro'] as $c): ?>
                            <option value="<?= $c ?>" <?= ($ev['country'] ?? 'Brasil') === $c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Estado (UF) <small style="font-size:0.7rem;text-transform:none;opacity:0.6;">— apenas Brasil</small></label>
                    <input type="text" name="state" placeholder="Ex: SP" maxlength="2" value="<?= htmlspecialchars($ev['state'] ?? '') ?>">
                </div>

                <div class="form-group full">
                    <label>Descrição</label>
                    <textarea name="description" placeholder="Descreva o evento: idiomas, perfil dos participantes, frequência aproximada..."><?= htmlspecialchars($ev['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group full section-divider">
                    <h3><i class="fas fa-plug" style="margin-right:6px;"></i> Redes Sociais e Contato</h3>
                </div>

                <div class="form-group">
                    <label><i class="fab fa-whatsapp" style="color:#25d366;"></i> Link do Grupo WhatsApp</label>
                    <input type="url" name="whatsapp_link" placeholder="https://chat.whatsapp.com/..." value="<?= htmlspecialchars($ev['whatsapp_link'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label><i class="fab fa-instagram" style="color:#e1306c;"></i> Instagram</label>
                    <input type="url" name="instagram_link" placeholder="https://instagram.com/..." value="<?= htmlspecialchars($ev['instagram_link'] ?? '') ?>">
                </div>

                <div class="form-group full section-divider">
                    <h3><i class="fas fa-user" style="margin-right:6px;"></i> Host Responsável</h3>
                </div>

                <div class="form-group">
                    <label>Host Organizador</label>
                    <select name="host_id">
                        <option value="">— Sem host definido —</option>
                        <?php foreach ($hosts as $h): ?>
                            <option value="<?= $h['id'] ?>" <?= ($ev['host_id'] ?? '') == $h['id'] ? 'selected' : '' ?>><?= htmlspecialchars($h['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <label class="switch" style="margin-top:10px;">
                        <input type="checkbox" name="active" id="active" <?= ($ev['active'] ?? 1) ? 'checked' : '' ?>>
                        <span class="slider"></span>
                        <span style="color: var(--text-dim); font-size: 0.85rem;">Evento ativo (visível no site)</span>
                    </label>
                </div>
            </div>

            <div class="btn-row">
                <a href="presencial.php" class="btn-cancel"><i class="fas fa-times"></i> Cancelar</a>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> <?= $id > 0 ? 'Salvar Alterações' : 'Criar Evento' ?></button>
            </div>
        </form>
    </main>
</body>
</html>
