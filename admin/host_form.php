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
$host = [];
$social = [];

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM hosts WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $host = $stmt->fetch();
    if (!$host) die("Host não encontrado.");
    
    $social = !empty($host['social_media_links']) ? json_decode($host['social_media_links'], true) : [];
} else {
    $host = [
        'full_name' => '', 'status' => 'ativo', 'profile_picture' => '',
        'languages' => '', 'online_description' => '', 'online_description_en' => '', 'special_badge' => '',
        'region' => '', 'category' => 'Online', 'inperson_description' => '', 'inperson_description_en' => '',
        'role' => '', 'technical_status' => 'inativo', 'technical_roles' => '',
        'technical_skills' => '', 'technical_description' => '', 'technical_description_en' => '',
        'initiative_description' => '', 'initiative_description_en' => '',
        'initiative_label_en' => ''
    ];
}

// Processamento do Formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = $_POST;
        
        // Tratamento de Redes Sociais (JSON)
        // Converte arrays de checkboxes em string separada por vírgula
        $languagesStr = !empty($data['languages_ordered']) ? $data['languages_ordered'] : (isset($data['languages']) && is_array($data['languages']) ? implode(', ', $data['languages']) : ($data['languages'] ?? ''));
        $skillsStr    = isset($data['technical_skills']) && is_array($data['technical_skills']) ? implode(', ', $data['technical_skills']) : ($data['technical_skills'] ?? '');
        $categoryArr  = isset($data['category']) && is_array($data['category']) ? $data['category'] : [];
        $categoryStr  = implode(', ', $categoryArr);
        
        // Status técnico (Bastidores) é definido pela presença da categoria 'Bastidores'
        $techStatus = in_array('Bastidores', $categoryArr) ? 'ativo' : 'inativo';

        $socialData = [
            'whatsapp'  => $data['whatsapp'] ?? '',
            'email'     => $data['email'] ?? '',
            'instagram' => $data['instagram'] ?? '',
            'linkedin'  => $data['linkedin'] ?? '',
            'github'    => $data['github'] ?? ''
        ];
        $socialJson = json_encode($socialData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);        // Tratamento de Upload de Foto - Só altera se enviar uma nova
        $profilePic = $host['profile_picture'] ?? 'HostSemFoto.png';
        if (!empty($_FILES['photo']['name'])) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $newFileName = str_replace(' ', '_', $data['full_name'] ?? 'host') . '.' . $ext;
            $targetPath = '../assets/images/' . $newFileName;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
                $profilePic = $newFileName;
                
                // --- GERAÇÃO DE THUMBNAIL (Otimização) ---
                try {
                    $thumbName = str_replace('.', '_thumb.', $newFileName);
                    $thumbPath = '../assets/images/' . $thumbName;
                    
                    // Carrega a imagem original
                    $img = null;
                    if ($ext === 'jpg' || $ext === 'jpeg') $img = @imagecreatefromjpeg($targetPath);
                    elseif ($ext === 'png') $img = @imagecreatefrompng($targetPath);
                    elseif ($ext === 'webp') $img = @imagecreatefromwebp($targetPath);
                    
                    if ($img) {
                        $width = imagesx($img);
                        $height = imagesy($img);
                        $size = min($width, $height);
                        $thumb = imagecreatetruecolor(80, 80);
                        
                        // Crop centralizado e resize
                        imagecopyresampled($thumb, $img, 0, 0, ($width-$size)/2, ($height-$size)/2, 80, 80, $size, $size);
                        
                        // Salva a miniatura como JPEG para ser bem leve
                        imagejpeg($thumb, $thumbPath, 80);
                        imagedestroy($img);
                        imagedestroy($thumb);
                    }
                } catch (Exception $e) {
                    error_log("Erro ao gerar thumbnail: " . $e->getMessage());
                }
            }
        }

        // Preparação de dados
        $dataToSave = [
            'full_name'             => (string)($data['full_name'] ?? ''),
            'status'                => isset($_POST['status']) ? 'ativo' : 'inativo',
            'profile_picture'       => (string)$profilePic,
            'languages'             => (string)$languagesStr,
            'online_description'    => (string)($data['online_description'] ?? ''),
            'online_description_en' => (string)($data['online_description_en'] ?? ''),
            'region'                => (string)($data['region'] ?? ''),
            'category'              => (string)$categoryStr,
            'inperson_description'  => (string)($data['inperson_description'] ?? ''),
            'inperson_description_en' => (string)($data['inperson_description_en'] ?? ''),
            'technical_status'      => (string)$techStatus,
            'technical_roles'       => (string)($data['technical_roles'] ?? ''),
            'technical_skills'      => (string)$skillsStr,
            'technical_description' => (string)($data['technical_description'] ?? ''),
            'technical_description_en' => (string)($data['technical_description_en'] ?? ''),
            'social_media_links'    => (string)$socialJson,
            'initiative_label'      => (string)($data['initiative_label'] ?? ''),
            'initiative_url'        => (string)($data['initiative_url'] ?? ''),
            'initiative_description' => (string)($data['initiative_description'] ?? ''),
            'initiative_description_en' => (string)($data['initiative_description_en'] ?? ''),
            'initiative_label_en'      => (string)($data['initiative_label_en'] ?? '')
        ];

        if ($id > 0) {
            $sql = "UPDATE hosts SET 
                    full_name = :full_name, status = :status, profile_picture = :profile_picture,
                    languages = :languages, online_description = :online_description, online_description_en = :online_description_en,
                    region = :region, category = :category, inperson_description = :inperson_description, inperson_description_en = :inperson_description_en,
                    technical_status = :technical_status, technical_roles = :technical_roles,
                    technical_skills = :technical_skills, technical_description = :technical_description, technical_description_en = :technical_description_en,
                    social_media_links = :social_media_links,
                    initiative_label = :initiative_label, initiative_url = :initiative_url,
                    initiative_description = :initiative_description, initiative_description_en = :initiative_description_en,
                    initiative_label_en = :initiative_label_en
                    WHERE id = :id";
            $dataToSave['id'] = $id;
        } else {
            $sql = "INSERT INTO hosts (
                    full_name, status, profile_picture, languages, online_description, online_description_en,
                    region, category, inperson_description, inperson_description_en, technical_status, technical_roles,
                    technical_skills, technical_description, technical_description_en, social_media_links,
                    initiative_label, initiative_url, initiative_description, initiative_description_en, initiative_label_en
                    ) VALUES (
                    :full_name, :status, :profile_picture, :languages, :online_description, :online_description_en,
                    :region, :category, :inperson_description, :inperson_description_en, :technical_status, :technical_roles,
                    :technical_skills, :technical_description, :technical_description_en, :social_media_links,
                    :initiative_label, :initiative_url, :initiative_description, :initiative_description_en, :initiative_label_en
                    ) ";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($dataToSave);
        header('Location: hosts.php?success=1');
        exit;
    } catch (Exception $e) {
        $error = "Erro ao salvar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id > 0 ? 'Editar' : 'Novo' ?> Anfitrião | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        :root {
            --primary-bg: #0f172a;
            --sidebar-bg: #1e293b;
            --accent-red: #e31d1c;
            --text-main: #f1f5f9;
            --text-dim: #94a3b8;
            --card-bg: #1e293b;
            --input-bg: rgba(15, 23, 42, 0.6);
            --success: #10b981;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: var(--sidebar-bg); padding: 30px; display: flex; flex-direction: column; border-right: 1px solid rgba(255,255,255,0.05); }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { margin-bottom: 40px; }
        .form-card { background: var(--card-bg); padding: 40px; border-radius: 24px; border: 1px solid rgba(255,255,255,0.05); max-width: 900px; }
        .section-title { font-size: 1.2rem; font-weight: 700; color: var(--accent-red); margin: 30px 0 20px; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 10px; }
        .section-title:first-child { margin-top: 0; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .full-width { grid-column: span 2; }
        label { display: block; margin-bottom: 8px; font-size: 0.9rem; font-weight: 500; color: var(--text-dim); }
        input, select, textarea { width: 100%; background: var(--input-bg); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 12px 15px; color: var(--text-main); outline: none; transition: all 0.3s ease; }
        input:focus, select:focus, textarea:focus { border-color: var(--accent-red); box-shadow: 0 0 0 4px rgba(227, 29, 28, 0.1); }
        .btn-save { background: var(--accent-red); color: white; border: none; padding: 14px 40px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.3s ease; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(227, 29, 28, 0.3); }
        .photo-preview { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; }
        .preview-img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-red); }
        .tag-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 5px; }
        .tag-item { position: relative; }
        .tag-item input { position: absolute; opacity: 0; cursor: pointer; height: 0; width: 0; }
        .tag-label {
            display: inline-block;
            padding: 6px 14px;
            background: #f0f2f5;
            border: 1px solid #ddd;
            border-radius: 20px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
        }
        .tag-item input:checked + .tag-label {
            background: var(--accent-red);
            color: #fff;
            border-color: var(--accent-red);
            box-shadow: 0 2px 8px rgba(227, 29, 28, 0.3);
        }
        .tag-label:hover { background: #e4e6e9; }

        /* Priority List Styles */
        .priority-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            border: 1px dashed rgba(255, 255, 255, 0.1);
        }
        .priority-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 15px;
            background: var(--sidebar-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            cursor: grab;
            transition: all 0.2s;
        }
        .priority-item:active { cursor: grabbing; }
        .priority-item .handle { color: var(--text-dim); }
        .priority-item .lang-name { flex: 1; font-weight: 500; }
        .priority-item .lang-badge {
            background: var(--accent-red);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .sortable-ghost { opacity: 0.4; background: var(--accent-red) !important; }

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
        <div class="header">
            <h2><?= $id > 0 ? 'Editar' : 'Cadastrar' ?> Anfitrião</h2>
            <p style="color: var(--text-dim);">Preencha todos os campos para manter a vitrine do site atualizada.</p>
        </div>

        <?php if (isset($error)): ?>
            <div style="background: rgba(227, 29, 28, 0.1); border: 1px solid var(--accent-red); color: #ff9494; padding: 15px; border-radius: 12px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="form-card">
            <!-- Informações Básicas -->
            <div class="section-title"><i class="fas fa-id-card"></i> Informações Básicas</div>
            <div class="photo-preview">
                <?php 
                    $photoUrl = getHostPhotoUrl($host['profile_picture'] ?? null);
                ?>
                <img src="<?= $photoUrl ?>" class="preview-img" onerror="this.src='../assets/images/HostSemFoto.png'">
                <div>
                    <label>Trocar Foto de Perfil</label>
                    <input type="file" name="photo" accept="image/*">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Nome Completo</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($host['full_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Status Global</label>
                    <label class="switch">
                        <input type="checkbox" name="status" value="ativo" <?= ($host['status'] ?? 'ativo') === 'ativo' ? 'checked' : '' ?>>
                        <span class="slider"></span>
                        <span style="color: var(--text-dim); font-size: 0.85rem;">Ativo para exibição</span>
                    </label>
                </div>
            </div>

            <!-- CATEGORIAS (O Motor do Formulário) -->
            <div class="section-title"><i class="fas fa-tags"></i> O que este anfitrião faz?</div>
            <div class="form-group" style="margin-bottom: 30px;">
                <div class="tag-grid">
                    <?php 
                    $availableCats = ['Online', 'Presencial', 'Bastidores', 'Iniciativas'];
                    $currentCats = array_map('trim', explode(',', $host['category'] ?? 'Online'));
                    foreach ($availableCats as $cat): ?>
                        <?php 
                        $catId = 'check-' . strtolower($cat); 
                        ?>
                        <label class="tag-item">
                            <input type="checkbox" name="category[]" value="<?= $cat ?>" 
                                   <?= in_array($cat, $currentCats) ? 'checked' : '' ?>
                                   id="<?= $catId ?>">
                            <span class="tag-label"><?= $cat ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- SEÇÃO: ONLINE (Condicional) -->
            <div id="section-online" class="conditional-section" style="display: none; border-left: 4px solid var(--accent-red); padding-left: 20px; margin-bottom: 30px;">
                <div class="section-title"><i class="fas fa-globe"></i> Encontros Online</div>
                <div class="form-group">
                    <label>Idiomas como Host (Programação)</label>
                    <div class="tag-grid">
                        <?php 
                        $stmtLangs = $conn->query("SELECT name FROM languages ORDER BY name ASC");
                        $availableLangs = $stmtLangs->fetchAll(PDO::FETCH_COLUMN);
                        $currentLangs = array_map('trim', explode(',', $host['languages']));
                        foreach ($availableLangs as $lang): 
                            $displayName = str_replace(' (Estrangeiros)', '', $lang);
                        ?>
                            <label class="tag-item">
                                <input type="checkbox" name="languages[]" value="<?= $lang ?>" 
                                       <?= in_array($lang, $currentLangs) ? 'checked' : '' ?>
                                       class="lang-checkbox"
                                       data-name="<?= $displayName ?>">
                                <span class="tag-label"><?= $displayName ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-top: 25px;">
                        <label><i class="fas fa-sort-amount-down"></i> Ordem de Prioridade (Arraste para organizar)</label>
                        <p style="font-size: 0.8rem; color: var(--text-dim); margin-bottom: 10px;">O primeiro idioma da lista será o principal exibido no card.</p>
                        <div id="language-priority-list" class="priority-list">
                            <!-- Populando via JS -->
                        </div>
                        <input type="hidden" name="languages_ordered" id="languages_ordered" value="<?= htmlspecialchars($host['languages']) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Descrição para os Encontros Online (Português)</label>
                    <textarea name="online_description" rows="3"><?= htmlspecialchars($host['online_description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Descrição para os Encontros Online (Inglês)</label>
                    <textarea name="online_description_en" rows="3"><?= htmlspecialchars($host['online_description_en'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- SEÇÃO: PRESENCIAL (Condicional) -->
            <div id="section-presencial" class="conditional-section" style="display: none; border-left: 4px solid var(--accent-blue); padding-left: 20px; margin-bottom: 30px;">
                <div class="section-title"><i class="fas fa-map-marker-alt"></i> Encontros Presenciais</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Região / Cidade</label>
                        <input type="text" name="region" value="<?= htmlspecialchars($host['region'] ?? '') ?>" placeholder="Ex: Brasília - DF">
                    </div>
                    <div class="form-group">
                        <label>Papéis no Presencial (ex: Host, Co-host)</label>
                        <input type="text" name="role" value="<?= htmlspecialchars($host['role'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Descrição para os Encontros Presenciais (Português)</label>
                    <textarea name="inperson_description" rows="3"><?= htmlspecialchars($host['inperson_description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Descrição para os Encontros Presenciais (Inglês)</label>
                    <textarea name="inperson_description_en" rows="3"><?= htmlspecialchars($host['inperson_description_en'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- SEÇÃO: BASTIDORES (Condicional) -->
            <div id="section-bastidores" class="conditional-section" style="display: none; border-left: 4px solid #f39c12; padding-left: 20px; margin-bottom: 30px;">
                <div class="section-title"><i class="fas fa-user-shield"></i> Bastidores (Backstage)</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Papéis / Funções (ex: Dev, Coordenador)</label>
                        <input type="text" name="technical_roles" value="<?= htmlspecialchars($host['technical_roles'] ?? '') ?>" placeholder="Sua função nos bastidores...">
                    </div>
                    <div class="form-group">
                        <label>Habilidades Técnicas</label>
                        <div class="tag-grid">
                            <?php 
                            $availableSkills = ['PHP', 'JavaScript', 'HTML', 'CSS', 'MySQL', 'WordPress', 'Design Gráfico', 'UI/UX', 'Edição de Vídeo'];
                            $currentSkills = array_map('trim', explode(',', $host['technical_skills']));
                            foreach ($availableSkills as $skill): ?>
                                <label class="tag-item">
                                    <input type="checkbox" name="technical_skills[]" value="<?= $skill ?>" <?= in_array($skill, $currentSkills) ? 'checked' : '' ?>>
                                    <span class="tag-label"><?= $skill ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Bio nos Bastidores / Experiência (Português)</label>
                    <textarea name="technical_description" rows="3"><?= htmlspecialchars($host['technical_description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Bio nos Bastidores / Experiência (Inglês)</label>
                    <textarea name="technical_description_en" rows="3"><?= htmlspecialchars($host['technical_description_en'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- SEÇÃO: INICIATIVAS (Condicional) -->
            <div id="section-iniciativas" class="conditional-section" style="display: none; border-left: 4px solid #9b59b6; padding-left: 20px; margin-bottom: 30px;">
                <div class="section-title"><i class="fas fa-users"></i> Iniciativas & Comunidade</div>
                <p style="font-size: 0.85rem; color: var(--text-dim); margin-bottom: 15px; margin-top: -10px;">Se este membro organiza um grupo, clube ou iniciativa específica, preencha os dados abaixo.</p>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nome da Iniciativa (Português)</label>
                        <input type="text" name="initiative_label" value="<?= htmlspecialchars($host['initiative_label'] ?? '') ?>" placeholder="Ex: Clube do Livro de Alemão">
                    </div>
                    <div class="form-group">
                        <label>Nome da Iniciativa (Inglês)</label>
                        <input type="text" name="initiative_label_en" value="<?= htmlspecialchars($host['initiative_label_en'] ?? '') ?>" placeholder="Ex: German Book Club">
                    </div>
                    <div class="form-group">
                        <label>Link de Acesso (WhatsApp, Telegram, etc.)</label>
                        <input type="url" name="initiative_url" value="<?= htmlspecialchars($host['initiative_url'] ?? '') ?>" placeholder="https://chat.whatsapp.com/...">
                    </div>
                </div>
                <div class="form-group">
                    <label>Descrição da Iniciativa (Português)</label>
                    <textarea name="initiative_description" rows="3"><?= htmlspecialchars($host['initiative_description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Descrição da Iniciativa (Inglês)</label>
                    <textarea name="initiative_description_en" rows="3"><?= htmlspecialchars($host['initiative_description_en'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Redes Sociais -->
            <div class="section-title"><i class="fas fa-share-alt"></i> Redes Sociais & Contato</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>WhatsApp</label>
                    <input type="text" name="whatsapp" value="<?= htmlspecialchars($social['whatsapp'] ?? '') ?>" placeholder="556199999999">
                </div>
                <div class="form-group">
                    <label>E-mail</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($social['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Instagram</label>
                    <input type="text" name="instagram" value="<?= htmlspecialchars($social['instagram'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>LinkedIn</label>
                    <input type="text" name="linkedin" value="<?= htmlspecialchars($social['linkedin'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>GitHub</label>
                    <input type="text" name="github" value="<?= htmlspecialchars($social['github'] ?? '') ?>">
                </div>
            </div>

            <div style="margin-top: 40px; display: flex; gap: 20px;">
                <button type="submit" class="btn-save">Salvar Alterações</button>
                <a href="hosts.php" style="color: var(--text-dim); text-decoration: none; padding: 14px 20px;">Cancelar</a>
            </div>
        </form>

    </main>

    <script>
    // Lógica de Exibição Dinâmica do Formulário
    const config = [
        { check: 'check-online', section: 'section-online' },
        { check: 'check-presencial', section: 'section-presencial' },
        { check: 'check-bastidores', section: 'section-bastidores' },
        { check: 'check-iniciativas', section: 'section-iniciativas' }
    ];

    function updateSections() {
        config.forEach(item => {
            const checkbox = document.getElementById(item.check);
            const section = document.getElementById(item.section);
            if (checkbox && section) {
                section.style.display = checkbox.checked ? 'block' : 'none';
                if (checkbox.checked) {
                    section.style.animation = 'fadeIn 0.3s ease-out';
                }
            }
        });
    }

    config.forEach(item => {
        const checkbox = document.getElementById(item.check);
        if (checkbox) {
            checkbox.addEventListener('change', updateSections);
        }
    });

    // Inicialização
    updateSections();

    // --- LÓGICA DE ORDENAÇÃO DE IDIOMAS ---
    const priorityList = document.getElementById('language-priority-list');
    const languagesOrderedInput = document.getElementById('languages_ordered');
    const langCheckboxes = document.querySelectorAll('.lang-checkbox');

    function updateOrderedInput() {
        const items = priorityList.querySelectorAll('.priority-item');
        const orderedNames = Array.from(items).map(item => item.dataset.id);
        languagesOrderedInput.value = orderedNames.join(', ');
    }

    function createPriorityItem(id, name) {
        const div = document.createElement('div');
        div.className = 'priority-item';
        div.dataset.id = id;
        div.innerHTML = `
            <i class="fas fa-grip-lines handle"></i>
            <span class="lang-name">${name}</span>
            <span class="lang-badge">Ativo</span>
        `;
        return div;
    }

    function syncCheckboxesWithPriority() {
        const currentOrdered = languagesOrderedInput.value.split(',').map(s => s.trim()).filter(s => s !== '');
        
        // Limpa e repopula baseado na ordem salva ou atual
        priorityList.innerHTML = '';
        
        // Primeiro adiciona os que já estão na ordem salva
        currentOrdered.forEach(langId => {
            const cb = Array.from(langCheckboxes).find(c => c.value === langId);
            if (cb && cb.checked) {
                const displayName = cb.dataset.name;
                priorityList.appendChild(createPriorityItem(langId, displayName));
            }
        });

        // Adiciona novos que foram marcados mas não estavam na ordem (caso ocorra)
        langCheckboxes.forEach(cb => {
            if (cb.checked && !currentOrdered.includes(cb.value)) {
                priorityList.appendChild(createPriorityItem(cb.value, cb.dataset.name));
            }
        });

        updateOrderedInput();
    }

    langCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if (this.checked) {
                priorityList.appendChild(createPriorityItem(this.value, this.dataset.name));
            } else {
                const item = priorityList.querySelector(`[data-id="${this.value}"]`);
                if (item) item.remove();
            }
            updateOrderedInput();
        });
    });

    // Inicializa Sortable
    if (priorityList) {
        new Sortable(priorityList, {
            animation: 150,
            handle: '.handle',
            ghostClass: 'sortable-ghost',
            onEnd: updateOrderedInput
        });
    }

    // Sincronização inicial
    syncCheckboxesWithPriority();
    </script>
</body>
</html>
