<?php
session_start();
require_once '../config.php';

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

// Get host details
$id = (int)$_GET['id'];
$conn = connectDB();

$stmt = $conn->prepare("SELECT * FROM hosts WHERE id = ?");
$stmt->execute([$id]);
$host = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$host) {
    header('Location: index.php?error=host_not_found');
    exit;
}

// Format social media links
$socialLinks = !empty($host['social_media_links']) ? json_decode($host['social_media_links'], true) : [];

// Get language names
$languages = [];
if (!empty($host['languages'])) {
    $languageValues = explode(',', $host['languages']);
    $allLanguages = getLanguages();
    $languageMap = [];
    
    foreach ($allLanguages as $language) {
        $languageMap[$language['id']] = $language['name'];
    }
    
    // Check if languages are stored as IDs or names
    $firstValue = trim($languageValues[0]);
    
    if (is_numeric($firstValue) && isset($languageMap[$firstValue])) {
        // Languages are stored as IDs
        foreach ($languageValues as $langId) {
            $langId = trim($langId);
            if (isset($languageMap[$langId])) {
                $languages[] = $languageMap[$langId];
            }
        }
    } else {
        // Languages are stored directly as names
        $languages = array_map('trim', $languageValues);
    }
}

// Page title
$title = "Detalhes do Anfitrião - Admin";
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
        
        .admin-btn.edit {
            background-color: var(--accent-green);
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
        
        .host-card {
            display: flex;
            flex-direction: column;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .host-header {
            display: flex;
            align-items: center;
            padding: 20px;
            background-color: var(--primary-color);
            color: var(--white);
        }
        
        .host-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--white);
            margin-right: 20px;
        }
        
        .host-name {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .status-badges {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: var(--accent-green);
            color: var(--white);
        }
        
        .status-inactive {
            background-color: var(--accent-red);
            color: var(--white);
        }
        
        .detail-section {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .detail-section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-content {
            margin-left: 30px;
        }
        
        .detail-item {
            margin-bottom: 15px;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--gray);
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .detail-value {
            background-color: var(--bg-light);
            padding: 10px;
            border-radius: 8px;
            font-size: 0.95rem;
            white-space: pre-wrap;
        }
        
        .tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .tag {
            background-color: var(--bg-light);
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.85rem;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
        }
        
        .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            color: var(--white);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .social-link:hover {
            transform: translateY(-3px);
        }
        
        .social-link.email {
            background-color: var(--accent-red);
        }
        
        .social-link.whatsapp {
            background-color: #25D366;
        }
        
        .social-link.instagram {
            background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
        }
        
        .social-link.linkedin {
            background-color: #0077B5;
        }
        
        .social-link.github {
            background-color: #333;
        }
        
        .empty-text {
            color: var(--gray);
            font-style: italic;
        }
        
        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            padding: 20px;
            background-color: var(--bg-light);
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--accent-blue);
            color: var(--white);
        }
        
        .btn-success {
            background-color: var(--accent-green);
            color: var(--white);
        }
        
        .btn-danger {
            background-color: var(--accent-red);
            color: var(--white);
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding: 20px 0;
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .host-header {
                flex-direction: column;
                text-align: center;
            }
            
            .host-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .status-badges {
                justify-content: center;
            }
            
            .section-content {
                margin-left: 0;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
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
                    <a href="host_form.php?id=<?= $host['id'] ?>" class="admin-btn edit">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                </div>
            </div>
        </div>
    </header>
    
    <main class="container">
        <div class="content-header">
            <h1 class="content-title">Detalhes do Anfitrião</h1>
        </div>
        
        <div class="host-card">
            <div class="host-header">
                <img src="<?= !empty($host['profile_picture']) 
                    ? '../' . (strpos($host['profile_picture'], 'assets/') === 0 
                        ? $host['profile_picture'] 
                        : 'assets/images/' . $host['profile_picture']) 
                    : '../assets/images/HostSemFoto.png' ?>" 
                    alt="<?= htmlspecialchars($host['full_name']) ?>" class="host-avatar">
                
                <div class="host-info">
                    <h2 class="host-name"><?= htmlspecialchars($host['full_name']) ?></h2>
                    
                    <div class="status-badges">
                        <span class="status-badge status-<?= $host['status'] === 'ativo' ? 'active' : 'inactive' ?>">
                            Status: <?= $host['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?>
                        </span>
                        
                        <span class="status-badge status-<?= $host['technical_status'] === 'ativo' ? 'active' : 'inactive' ?>">
                            Técnico: <?= $host['technical_status'] === 'ativo' ? 'Ativo' : 'Inativo' ?>
                        </span>
                        
                        <span class="status-badge status-<?= $host['active'] ? 'active' : 'inactive' ?>">
                            Sistema: <?= $host['active'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="detail-section">
                <h3 class="section-title"><i class="fas fa-user"></i> Informações Básicas</h3>
                
                <div class="section-content">
                    <div class="detail-item">
                        <div class="detail-label">ID</div>
                        <div class="detail-value"><?= $host['id'] ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Nome Completo</div>
                        <div class="detail-value"><?= htmlspecialchars($host['full_name']) ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Foto de Perfil</div>
                        <div class="detail-value">
                            <?php if(!empty($host['profile_picture'])): ?>
                                <img src="<?= '../' . (strpos($host['profile_picture'], 'assets/') === 0 
                                    ? $host['profile_picture'] 
                                    : 'assets/images/' . $host['profile_picture']) ?>" 
                                    alt="<?= htmlspecialchars($host['full_name']) ?>" 
                                    style="max-width: 200px; max-height: 200px; border-radius: 8px; object-fit: cover;">
                            <?php else: ?>
                                <span class="empty-text">Sem foto</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Caminho da Foto</div>
                        <div class="detail-value">
                            <?= !empty($host['profile_picture']) ? htmlspecialchars($host['profile_picture']) : '<span class="empty-text">Sem foto</span>' ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($host['special_badge'])): ?>
                    <div class="detail-item">
                        <div class="detail-label">Badge Especial</div>
                        <div class="detail-value"><?= htmlspecialchars($host['special_badge']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="detail-section">
                <h3 class="section-title"><i class="fas fa-comments"></i> Informações de Anfitrião Online</h3>
                
                <div class="section-content">
                    <div class="detail-item">
                        <div class="detail-label">Idiomas</div>
                        <div class="detail-value">
                            <?php if (!empty($languages)): ?>
                                <div class="tags-container">
                                    <?php foreach ($languages as $language): ?>
                                        <span class="tag"><?= htmlspecialchars($language) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span class="empty-text">Nenhum idioma cadastrado</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Descrição para Encontros Online</div>
                        <div class="detail-value">
                            <?= !empty($host['online_description']) ? nl2br(htmlspecialchars($host['online_description'])) : '<span class="empty-text">Sem descrição</span>' ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="detail-section">
                <h3 class="section-title"><i class="fas fa-map-marker-alt"></i> Informações de Anfitrião Presencial</h3>
                
                <div class="section-content">
                    <div class="detail-item">
                        <div class="detail-label">Região/Cidade</div>
                        <div class="detail-value">
                            <?= !empty($host['region']) ? htmlspecialchars($host['region']) : '<span class="empty-text">Não informada</span>' ?>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Categorias</div>
                        <div class="detail-value">
                            <?php if (!empty($host['category'])): ?>
                                <div class="tags-container">
                                    <?php foreach (explode(',', $host['category']) as $category): ?>
                                        <span class="tag"><?= htmlspecialchars(trim($category)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span class="empty-text">Nenhuma categoria cadastrada</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Descrição para Encontros Presenciais</div>
                        <div class="detail-value">
                            <?= !empty($host['inperson_description']) ? nl2br(htmlspecialchars($host['inperson_description'])) : '<span class="empty-text">Sem descrição</span>' ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($host['role'])): ?>
                    <div class="detail-item">
                        <div class="detail-label">Papéis/Funções</div>
                        <div class="detail-value">
                            <div class="tags-container">
                                <?php foreach (explode(',', $host['role']) as $role): ?>
                                    <span class="tag"><?= htmlspecialchars(trim($role)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="detail-section">
                <h3 class="section-title"><i class="fas fa-code"></i> Informações da Equipe Técnica</h3>
                
                <div class="section-content">
                    <div class="detail-item">
                        <div class="detail-label">Status na Equipe Técnica</div>
                        <div class="detail-value">
                            <span class="status-badge status-<?= $host['technical_status'] === 'ativo' ? 'active' : 'inactive' ?>" style="min-width: auto;">
                                <?= $host['technical_status'] === 'ativo' ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if (!empty($host['technical_roles'])): ?>
                    <div class="detail-item">
                        <div class="detail-label">Papéis Técnicos</div>
                        <div class="detail-value">
                            <div class="tags-container">
                                <?php foreach (explode(',', $host['technical_roles']) as $role): ?>
                                    <span class="tag"><?= htmlspecialchars(trim($role)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($host['technical_skills'])): ?>
                    <div class="detail-item">
                        <div class="detail-label">Habilidades Técnicas</div>
                        <div class="detail-value">
                            <div class="tags-container">
                                <?php foreach (explode(',', $host['technical_skills']) as $skill): ?>
                                    <span class="tag"><?= htmlspecialchars(trim($skill)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="detail-item">
                        <div class="detail-label">Descrição Técnica</div>
                        <div class="detail-value">
                            <?= !empty($host['technical_description']) ? nl2br(htmlspecialchars($host['technical_description'])) : '<span class="empty-text">Sem descrição</span>' ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="detail-section">
                <h3 class="section-title"><i class="fas fa-share-alt"></i> Redes Sociais e Contato</h3>
                
                <div class="section-content">
                    <div class="detail-item">
                        <div class="detail-label">Links</div>
                        <div class="detail-value">
                            <div class="social-links">
                                <?php if (!empty($socialLinks['email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($socialLinks['email']) ?>" class="social-link email" title="Email">
                                        <i class="fas fa-envelope"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($socialLinks['whatsapp'])): ?>
                                    <a href="https://wa.me/<?= htmlspecialchars($socialLinks['whatsapp']) ?>" class="social-link whatsapp" title="WhatsApp">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($socialLinks['instagram'])): ?>
                                    <a href="<?= htmlspecialchars($socialLinks['instagram']) ?>" class="social-link instagram" title="Instagram">
                                        <i class="fab fa-instagram"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($socialLinks['linkedin'])): ?>
                                    <a href="<?= htmlspecialchars($socialLinks['linkedin']) ?>" class="social-link linkedin" title="LinkedIn">
                                        <i class="fab fa-linkedin-in"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($socialLinks['github'])): ?>
                                    <a href="<?= htmlspecialchars($socialLinks['github']) ?>" class="social-link github" title="GitHub">
                                        <i class="fab fa-github"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (empty($socialLinks)): ?>
                                    <span class="empty-text">Nenhuma rede social cadastrada</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="detail-section">
                <h3 class="section-title"><i class="fas fa-clock"></i> Datas do Sistema</h3>
                
                <div class="section-content">
                    <div class="detail-item">
                        <div class="detail-label">Criado em</div>
                        <div class="detail-value">
                            <?= !empty($host['created_at']) ? date('d/m/Y H:i:s', strtotime($host['created_at'])) : 'N/A' ?>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Atualizado em</div>
                        <div class="detail-value">
                            <?= !empty($host['updated_at']) ? date('d/m/Y H:i:s', strtotime($host['updated_at'])) : 'N/A' ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
                <a href="host_form.php?id=<?= $host['id'] ?>" class="btn btn-success">
                    <i class="fas fa-edit"></i> Editar
                </a>
                <a href="?action=toggle_status&id=<?= $host['id'] ?>&field=status" class="btn btn-<?= $host['status'] === 'ativo' ? 'danger' : 'success' ?>">
                    <i class="fas fa-exchange-alt"></i> Alterar Status para <?= $host['status'] === 'ativo' ? 'Inativo' : 'Ativo' ?>
                </a>
            </div>
        </div>
    </main>
    
    <footer class="footer">
        <div class="container">
            &copy; <?= date('Y') ?> Encontro de Idiomas - Painel Administrativo
        </div>
    </footer>
</body>
</html> 