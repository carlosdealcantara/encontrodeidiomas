<?php
session_start();
require_once '../config.php';

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Initialize variables
$host = [
    'id' => '',
    'full_name' => '',
    'profile_picture' => '',
    'online_description' => '',
    'inperson_description' => '',
    'languages' => '',
    'region' => '',
    'category' => '',
    'role' => '',
    'social_media_links' => '',
    'status' => 'ativo',
    'active' => 1,
    'technical_status' => 'inativo',
    'technical_roles' => '',
    'technical_skills' => '',
    'technical_description' => '',
    'special_badge' => ''
];

$errors = [];
$success = false;
$editMode = false;
$pageTitle = 'Adicionar Novo Anfitrião';

// Get languages for dropdown
$languages = getLanguages();

// Get host data if editing
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $conn = connectDB();
    
    $stmt = $conn->prepare("SELECT * FROM hosts WHERE id = ?");
    $stmt->execute([$id]);
    $existingHost = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingHost) {
        $host = $existingHost;
        $editMode = true;
        $pageTitle = 'Editar Anfitrião';
        
        // Decode JSON for social media
        if (!empty($host['social_media_links'])) {
            $host['social_media_links'] = json_decode($host['social_media_links'], true);
        } else {
            $host['social_media_links'] = [
                'email' => '',
                'instagram' => '',
                'linkedin' => '',
                'whatsapp' => '',
                'github' => ''
            ];
        }
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    if (empty($_POST['full_name'])) {
        $errors[] = 'O nome completo é obrigatório.';
    }
    
    // If there are no errors, save the host
    if (empty($errors)) {
        $conn = connectDB();
        
        // Prepare social media links as JSON
        $socialMediaLinks = [
            'email' => $_POST['email'] ?? '',
            'instagram' => $_POST['instagram'] ?? '',
            'linkedin' => $_POST['linkedin'] ?? '',
            'whatsapp' => $_POST['whatsapp'] ?? '',
            'github' => $_POST['github'] ?? ''
        ];
        
        $socialMediaLinksJson = json_encode($socialMediaLinks);
        
        // Handle file upload if present
        $profilePicture = $host['profile_picture'];
        if (!empty($_FILES['profile_picture']['name'])) {
            $targetDir = "../assets/images/";
            $fileName = basename($_FILES["profile_picture"]["name"]);
            $targetFilePath = $targetDir . $fileName;
            $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
            
            // Check if image file is valid
            $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
            if (in_array($fileType, $allowTypes)) {
                // Upload file to server
                if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $targetFilePath)) {
                    $profilePicture = "assets/images/" . $fileName;
                } else {
                    $errors[] = "Erro ao fazer upload da imagem.";
                }
            } else {
                $errors[] = "Apenas JPG, JPEG, PNG e GIF são permitidos.";
            }
        }
        
        if (empty($errors)) {
            // Build data array
            $data = [
                'full_name' => $_POST['full_name'],
                'profile_picture' => $profilePicture,
                'online_description' => $_POST['online_description'],
                'inperson_description' => $_POST['inperson_description'],
                'languages' => $_POST['languages'],
                'region' => $_POST['region'],
                'category' => $_POST['category'],
                'role' => $_POST['role'],
                'social_media_links' => $socialMediaLinksJson,
                'status' => $_POST['status'],
                'active' => isset($_POST['active']) ? 1 : 0,
                'technical_status' => $_POST['technical_status'],
                'technical_roles' => $_POST['technical_roles'],
                'technical_skills' => $_POST['technical_skills'],
                'technical_description' => $_POST['technical_description'],
                'special_badge' => $_POST['special_badge']
            ];
            
            try {
                if ($editMode) {
                    // Update existing host
                    $sql = "UPDATE hosts SET 
                        full_name = :full_name,
                        profile_picture = :profile_picture,
                        online_description = :online_description,
                        inperson_description = :inperson_description,
                        languages = :languages,
                        region = :region,
                        category = :category,
                        role = :role,
                        social_media_links = :social_media_links,
                        status = :status,
                        active = :active,
                        technical_status = :technical_status,
                        technical_roles = :technical_roles,
                        technical_skills = :technical_skills,
                        technical_description = :technical_description,
                        special_badge = :special_badge,
                        updated_at = NOW()
                    WHERE id = :id";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':id', $host['id']);
                } else {
                    // Insert new host
                    $sql = "INSERT INTO hosts (
                        full_name, profile_picture, online_description, inperson_description, 
                        languages, region, category, role, 
                        social_media_links, status, active, technical_status, 
                        technical_roles, technical_skills, technical_description, 
                        special_badge, created_at, updated_at
                    ) VALUES (
                        :full_name, :profile_picture, :online_description, :inperson_description,
                        :languages, :region, :category, :role,
                        :social_media_links, :status, :active, :technical_status,
                        :technical_roles, :technical_skills, :technical_description,
                        :special_badge, NOW(), NOW()
                    )";
                    
                    $stmt = $conn->prepare($sql);
                }
                
                // Bind all parameters
                foreach ($data as $key => $value) {
                    $stmt->bindParam(":$key", $data[$key]);
                }
                
                $stmt->execute();
                $success = true;
                
                // Redirect after successful save
                header('Location: index.php?host_saved=1');
                exit;
                
            } catch (PDOException $e) {
                $errors[] = "Erro ao salvar: " . $e->getMessage();
            }
        }
    }
    
    // If there were errors, repopulate the form with submitted data
    if (!empty($errors)) {
        foreach ($_POST as $key => $value) {
            $host[$key] = $value;
        }
        
        // Rebuild social media links
        $host['social_media_links'] = [
            'email' => $_POST['email'] ?? '',
            'instagram' => $_POST['instagram'] ?? '',
            'linkedin' => $_POST['linkedin'] ?? '',
            'whatsapp' => $_POST['whatsapp'] ?? '',
            'github' => $_POST['github'] ?? ''
        ];
    }
}

// Page title
$title = $pageTitle . " - Admin";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1a1a1a;
            --accent-red: #e31d1c;
            --accent-blue: #002654;
            --accent-green: #28a745;
            --text-color: #333;
            --bg-light: #f8f9fa;
            --white: #ffffff;
            --gray: #6c757d;
            --border-radius: 16px;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            color: var(--text-color);
            background-color: #f7f7f7;
            line-height: 1.6;
        }
        
        .admin-header {
            background: var(--primary-color);
            color: var(--white);
            padding: 15px 0;
            box-shadow: var(--shadow);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }
        
        .header-title {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .admin-actions {
            display: flex;
            gap: 15px;
        }
        
        .admin-btn {
            padding: 8px 15px;
            border-radius: 8px;
            text-decoration: none;
            color: var(--white);
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .admin-btn.back {
            background-color: var(--gray);
        }
        
        .admin-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 30px 0 20px;
        }
        
        .content-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background-color: rgba(227, 29, 28, 0.15);
            color: var(--accent-red);
            border: 1px solid rgba(227, 29, 28, 0.3);
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.15);
            color: var(--accent-green);
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .form-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            background-color: var(--bg-light);
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--accent-blue);
            outline: none;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
        }
        
        .form-check-label {
            font-weight: 500;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--accent-red);
            color: var(--white);
        }
        
        .btn-secondary {
            background-color: var(--gray);
            color: var(--white);
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 20px;
        }
        
        .hint-text {
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 5px;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding: 20px 0;
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .header-title {
                font-size: 1rem;
            }
            
            .content-title {
                font-size: 1.3rem;
            }
            
            .form-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <div class="header-content">
                <div class="logo-container">
                    <img src="../assets/images/logo.png" alt="Encontro de Idiomas" class="logo">
                    <div class="header-title">Administração - Encontro de Idiomas</div>
                </div>
                
                <div class="admin-actions">
                    <a href="index.php" class="admin-btn back">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>
        </div>
    </header>
    
    <main class="container">
        <div class="content-header">
            <h1 class="content-title"><?= $pageTitle ?></h1>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong><i class="fas fa-exclamation-circle"></i> Atenção!</strong>
                <ul style="margin-top: 10px; margin-bottom: 0;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Anfitrião salvo com sucesso!
            </div>
        <?php endif; ?>
        
        <form action="" method="post" enctype="multipart/form-data">
            <?php if ($editMode): ?>
                <input type="hidden" name="id" value="<?= $host['id'] ?>">
            <?php endif; ?>
            
            <div class="form-card">
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-user"></i> Informações Básicas</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name">Nome Completo *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($host['full_name']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="profile_picture">Foto de Perfil</label>
                            <input type="file" class="form-control" id="profile_picture" name="profile_picture">
                            <?php if (!empty($host['profile_picture'])): ?>
                                <p class="hint-text">Foto atual: <?= $host['profile_picture'] ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select class="form-control" id="status" name="status">
                                <option value="ativo" <?= $host['status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                <option value="inativo" <?= $host['status'] === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="display: flex; align-items: flex-end;">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="active" name="active" value="1" <?= $host['active'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="active">Ativo no Sistema</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-comments"></i> Informações de Anfitrião Online</h2>
                    
                    <div class="form-group">
                        <label for="languages">Idiomas (separados por vírgula)</label>
                        <input type="text" class="form-control" id="languages" name="languages" value="<?= htmlspecialchars($host['languages']) ?>">
                        <p class="hint-text">Exemplo: Inglês, Francês, Espanhol</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="online_description">Descrição para Encontros Online</label>
                        <textarea class="form-control" id="online_description" name="online_description" rows="4"><?= htmlspecialchars($host['online_description']) ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="special_badge">Badge Especial</label>
                        <input type="text" class="form-control" id="special_badge" name="special_badge" value="<?= htmlspecialchars($host['special_badge']) ?>">
                        <p class="hint-text">Texto que aparecerá em destaque no card (opcional)</p>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-map-marker-alt"></i> Informações de Anfitrião Presencial</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="region">Região/Cidade</label>
                            <input type="text" class="form-control" id="region" name="region" value="<?= htmlspecialchars($host['region']) ?>">
                            <p class="hint-text">Exemplo: Brasília - DF, São Paulo - SP</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Categorias (separadas por vírgula)</label>
                            <input type="text" class="form-control" id="category" name="category" value="<?= htmlspecialchars($host['category']) ?>">
                            <p class="hint-text">Exemplo: Online, Presencial, Técnica</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="inperson_description">Descrição para Encontros Presenciais</label>
                        <textarea class="form-control" id="inperson_description" name="inperson_description" rows="4"><?= htmlspecialchars($host['inperson_description']) ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Papel/Função (separados por vírgula)</label>
                        <input type="text" class="form-control" id="role" name="role" value="<?= htmlspecialchars($host['role']) ?>">
                    </div>
                </div>
                
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-code"></i> Informações da Equipe Técnica</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="technical_status">Status na Equipe Técnica</label>
                            <select class="form-control" id="technical_status" name="technical_status">
                                <option value="ativo" <?= $host['technical_status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                <option value="inativo" <?= $host['technical_status'] === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="technical_roles">Papéis Técnicos (separados por vírgula)</label>
                            <input type="text" class="form-control" id="technical_roles" name="technical_roles" value="<?= htmlspecialchars($host['technical_roles']) ?>">
                            <p class="hint-text">Exemplo: Desenvolvimento, Design</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="technical_skills">Habilidades Técnicas (separadas por vírgula)</label>
                        <input type="text" class="form-control" id="technical_skills" name="technical_skills" value="<?= htmlspecialchars($host['technical_skills']) ?>">
                        <p class="hint-text">Exemplo: PHP, MySQL, JavaScript, HTML, CSS</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="technical_description">Descrição Técnica</label>
                        <textarea class="form-control" id="technical_description" name="technical_description" rows="4"><?= htmlspecialchars($host['technical_description']) ?></textarea>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-share-alt"></i> Redes Sociais e Contato</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($host['social_media_links']['email'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="whatsapp">WhatsApp</label>
                            <input type="text" class="form-control" id="whatsapp" name="whatsapp" value="<?= htmlspecialchars($host['social_media_links']['whatsapp'] ?? '') ?>">
                            <p class="hint-text">Formato: 5561999999999 (incluir código do país e DDD)</p>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="instagram">Instagram</label>
                            <input type="text" class="form-control" id="instagram" name="instagram" value="<?= htmlspecialchars($host['social_media_links']['instagram'] ?? '') ?>">
                            <p class="hint-text">URL completa: https://instagram.com/username</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="linkedin">LinkedIn</label>
                            <input type="text" class="form-control" id="linkedin" name="linkedin" value="<?= htmlspecialchars($host['social_media_links']['linkedin'] ?? '') ?>">
                            <p class="hint-text">URL completa: https://linkedin.com/in/username</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="github">GitHub (para equipe técnica)</label>
                        <input type="text" class="form-control" id="github" name="github" value="<?= htmlspecialchars($host['social_media_links']['github'] ?? '') ?>">
                        <p class="hint-text">URL completa: https://github.com/username</p>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </div>
        </form>
    </main>
    
    <footer class="footer">
        <div class="container">
            &copy; <?= date('Y') ?> Encontro de Idiomas - Painel Administrativo
        </div>
    </footer>
</body>
</html> 