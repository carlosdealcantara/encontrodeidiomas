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
    // Valores padrão para novo cadastro
    $host = [
        'full_name' => '', 'status' => 'ativo', 'profile_picture' => '',
        'languages' => '', 'online_description' => '', 'special_badge' => '',
        'region' => '', 'category' => 'Online', 'inperson_description' => '',
        'role' => '', 'technical_status' => 'inativo', 'technical_roles' => '',
        'technical_skills' => '', 'technical_description' => ''
    ];
}

// Processamento do Formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = $_POST;
        
        // Tratamento de Redes Sociais (JSON)
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
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $newFileName = str_replace(' ', '_', $data['full_name'] ?? 'host') . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], '../assets/images/' . $newFileName)) {
                $profilePic = $newFileName;
            }
        }

        // Preparação de dados - RIGOROSAMENTE as 13 colunas da tabela + ID se update
        $dataToSave = [
            'full_name'             => (string)($data['full_name'] ?? ''),
            'status'                => (string)($data['status'] ?? 'ativo'),
            'profile_picture'       => (string)$profilePic,
            'languages'             => (string)($data['languages'] ?? ''),
            'online_description'    => (string)($data['online_description'] ?? ''),
            'region'                => (string)($data['region'] ?? ''),
            'category'              => (string)($data['category'] ?? 'Online'),
            'inperson_description'  => (string)($data['inperson_description'] ?? ''),
            'technical_status'      => (string)($data['technical_status'] ?? 'inativo'),
            'technical_roles'       => (string)($data['technical_roles'] ?? ''),
            'technical_skills'      => (string)($data['technical_skills'] ?? ''),
            'technical_description' => (string)($data['technical_description'] ?? ''),
            'social_media_links'    => (string)$socialJson
        ];

        if ($id > 0) {
            $sql = "UPDATE hosts SET 
                    full_name = :full_name, status = :status, profile_picture = :profile_picture,
                    languages = :languages, online_description = :online_description,
                    region = :region, category = :category, inperson_description = :inperson_description,
                    technical_status = :technical_status, technical_roles = :technical_roles,
                    technical_skills = :technical_skills, technical_description = :technical_description,
                    social_media_links = :social_media_links
                    WHERE id = :id";
            $dataToSave['id'] = $id;
        } else {
            $sql = "INSERT INTO hosts (
                    full_name, status, profile_picture, languages, online_description,
                    region, category, inperson_description, technical_status, technical_roles,
                    technical_skills, technical_description, social_media_links
                    ) VALUES (
                    :full_name, :status, :profile_picture, :languages, :online_description,
                    :region, :category, :inperson_description, :technical_status, :technical_roles,
                    :technical_skills, :technical_description, :social_media_links
                    )";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($dataToSave);
        header('Location: hosts.php?msg=Dados salvos com sucesso');
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
    <style>
        :root {
            --primary-bg: #0f172a;
            --sidebar-bg: #1e293b;
            --accent-red: #e31d1c;
            --text-main: #f1f5f9;
            --text-dim: #94a3b8;
            --card-bg: #1e293b;
            --input-bg: rgba(15, 23, 42, 0.6);
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
    </style>
</head>
<body>
    <aside class="sidebar">
        <div style="font-size: 1.2rem; font-weight: 700; margin-bottom: 40px;">ADMIN CENTRAL</div>
        <nav>
            <a href="index.php" style="color: var(--text-dim); text-decoration: none; display: block; padding: 10px 0;"><i class="fas fa-chart-pie"></i> Dashboard</a>
            <a href="hosts.php" style="color: white; text-decoration: none; display: block; padding: 10px 0;"><i class="fas fa-users"></i> Anfitriões</a>
            <a href="logout.php" style="color: #ff6b6b; text-decoration: none; display: block; padding: 10px 0; margin-top: 50px;"><i class="fas fa-sign-out-alt"></i> Sair</a>
        </nav>
    </aside>

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
                <?php if ($host['profile_picture']): ?>
                    <img src="../assets/images/<?= $host['profile_picture'] ?>" class="preview-img">
                <?php else: ?>
                    <div class="preview-img" style="background: #333; display: flex; align-items: center; justify-content: center;"><i class="fas fa-user"></i></div>
                <?php endif; ?>
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
                    <select name="status">
                        <option value="ativo" <?= $host['status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                        <option value="inativo" <?= $host['status'] === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>
            </div>

            <!-- Online -->
            <div class="section-title"><i class="fas fa-globe"></i> Encontros Online</div>
            <div class="form-group">
                <label>Idiomas (ex: Inglês, Francês)</label>
                <input type="text" name="languages" list="list-languages" value="<?= htmlspecialchars($host['languages']) ?>" placeholder="Comece a digitar um idioma...">
            </div>
            <div class="form-group">
                <label>Descrição Online (Resumo)</label>
                <textarea name="online_description" rows="3"><?= htmlspecialchars($host['online_description']) ?></textarea>
            </div>

            <!-- Presencial -->
            <div class="section-title"><i class="fas fa-map-marker-alt"></i> Encontros Presenciais</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Região / Cidade</label>
                    <input type="text" name="region" value="<?= htmlspecialchars($host['region'] ?? '') ?>" placeholder="Ex: Brasília - DF">
                </div>
                <div class="form-group">
                    <label>Categorias (separadas por vírgula)</label>
                    <input type="text" name="category" list="list-categories" value="<?= htmlspecialchars($host['category'] ?? 'Online') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Papéis / Funções (ex: Host, Co-host)</label>
                <input type="text" name="role" value="<?= htmlspecialchars($host['role'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Descrição Presencial</label>
                <textarea name="inperson_description" rows="3"><?= htmlspecialchars($host['inperson_description'] ?? '') ?></textarea>
            </div>

            <!-- Redes Sociais -->
            <div class="section-title"><i class="fas fa-share-alt"></i> Redes Sociais (Links)</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>WhatsApp (com DDD)</label>
                    <input type="text" name="whatsapp" value="<?= htmlspecialchars($social['whatsapp'] ?? '') ?>" placeholder="556199999999">
                </div>
                <div class="form-group">
                    <label>E-mail Público</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($social['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Instagram URL</label>
                    <input type="text" name="instagram" value="<?= htmlspecialchars($social['instagram'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>LinkedIn URL</label>
                    <input type="text" name="linkedin" value="<?= htmlspecialchars($social['linkedin'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>GitHub URL (Técnico)</label>
                    <input type="text" name="github" value="<?= htmlspecialchars($social['github'] ?? '') ?>">
                </div>
            </div>

            <!-- Técnico -->
            <div class="section-title"><i class="fas fa-code"></i> Equipe Técnica</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Status Técnico</label>
                    <select name="technical_status">
                        <option value="inativo" <?= $host['technical_status'] === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                        <option value="ativo" <?= $host['technical_status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Habilidades (ex: PHP, Design)</label>
                    <input type="text" name="technical_skills" list="list-skills" value="<?= htmlspecialchars($host['technical_skills']) ?>">
                </div>
                <div class="form-group">
                    <label>Papéis Técnicos (ex: Dev, UI/UX)</label>
                    <input type="text" name="technical_roles" list="list-roles" value="<?= htmlspecialchars($host['technical_roles'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Descrição Técnica</label>
                <textarea name="technical_description" rows="3"><?= htmlspecialchars($host['technical_description'] ?? '') ?></textarea>
            </div>

            <div style="margin-top: 40px; display: flex; gap: 20px;">
                <button type="submit" class="btn-save">Salvar Alterações</button>
                <a href="hosts.php" style="color: var(--text-dim); text-decoration: none; padding: 14px 20px;">Cancelar</a>
            </div>
        </form>

        <!-- Listas de Sugestões (Datalists - Apenas versões corretas) -->
        <datalist id="list-languages">
            <option value="Inglês">
            <option value="Francês">
            <option value="Espanhol">
            <option value="Alemão">
            <option value="Italiano">
            <option value="Russo">
            <option value="Japonês">
            <option value="Chinês">
            <option value="Coreano">
            <option value="Português (Estrangeiros)">
        </datalist>

        <datalist id="list-categories">
            <option value="Online">
            <option value="Presencial">
            <option value="Técnica">
            <option value="Online, Presencial">
        </datalist>

        <datalist id="list-skills">
            <option value="PHP">
            <option value="JavaScript">
            <option value="HTML">
            <option value="CSS">
            <option value="MySQL">
            <option value="WordPress">
            <option value="Design Gráfico">
            <option value="UI/UX">
            <option value="Edição de Vídeo">
        </datalist>

        <datalist id="list-roles">
            <option value="Desenvolvedor">
            <option value="Designer">
            <option value="Criador de Conteúdo">
            <option value="Coordenador Técnico">
            <option value="Social Media">
        </datalist>

        <script>
        // Inteligência para Sugestões após Vírgula (Estilo LinkedIn/Tags)
        function enableMultiSuggest(inputId) {
            const input = document.getElementsByName(inputId)[0];
            if (!input) return;

            input.addEventListener('input', function(e) {
                const value = this.value;
                const lastComma = value.lastIndexOf(',');
                
                // Se houver vírgula, o navegador para de sugerir nativamente.
                // Aqui poderíamos implementar um dropdown customizado, 
                // mas para manter leve, vamos apenas limpar o 'list' se estiver no meio de uma palavra 
                // e reativar se for o início de uma nova tag.
                if (lastComma !== -1) {
                    const currentTag = value.substring(lastComma + 1).trim();
                    // O datalist nativo não suporta troca dinâmica de contexto facilmente,
                    // mas ao menos limpamos o placeholder para ajudar o usuário.
                }
            });
        }
        
        // Ativando nos campos principais
        enableMultiSuggest('languages');
        enableMultiSuggest('technical_skills');
        enableMultiSuggest('category');
        </script>
    </main>
</body>
</html>
