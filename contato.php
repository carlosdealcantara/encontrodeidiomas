<?php
$title = "Contato";

// Check if a form was submitted
$formSubmitted = false;
$formError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config.php';
    
    // Basic validation
    if (
        !empty($_POST['name']) && 
        !empty($_POST['email']) && 
        !empty($_POST['subject']) && 
        !empty($_POST['message'])
    ) {
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $subject = sanitize($_POST['subject']);
        $message = sanitize($_POST['message']);
        
        // Send email
        $to = ADMIN_EMAIL;
        $mailSubject = "Contato via site - " . $subject;
        $mailBody = "Nome: $name\n";
        $mailBody .= "Email: $email\n";
        $mailBody .= "Assunto: $subject\n\n";
        $mailBody .= "Mensagem:\n$message";
        $headers = "From: " . SITE_NAME . " <" . ADMIN_EMAIL . ">\r\n";
        $headers .= "Reply-To: $email\r\n";
        
        $mailSent = mail($to, $mailSubject, $mailBody, $headers);
        
        if ($mailSent) {
            $formSubmitted = true;
        } else {
            $formError = true;
        }
    } else {
        $formError = true;
    }
}

// Get subject from URL if provided
$defaultSubject = '';
if (isset($_GET['assunto'])) {
    $subject = $_GET['assunto'];
    
    // Map URL parameter to readable subject
    $subjectMap = [
        'novo_idioma' => 'Quero propor um novo idioma',
        'duvida' => 'Tenho uma dúvida',
        'sugestao' => 'Sugestão',
        'reclamacao' => 'Reclamação',
        'parceria' => 'Proposta de parceria'
    ];
    
    $defaultSubject = isset($subjectMap[$subject]) ? $subjectMap[$subject] : '';
}

// Additional styles for this page
$page_styles = <<<EOT
.main-content {
    padding: 60px 0;
}

.page-title {
    text-align: center;
    margin-bottom: 40px;
}

.page-title h1 {
    font-size: 2.5rem;
    margin-bottom: 10px;
    color: var(--primary-color);
}

.page-title p {
    font-size: 1.1rem;
    color: #666;
    max-width: 800px;
    margin: 0 auto;
}

.contact-container {
    display: flex;
    flex-wrap: wrap;
    gap: 40px;
    max-width: 1000px;
    margin: 0 auto;
    padding: 0 20px;
}

.contact-form-container {
    flex: 1;
    min-width: 300px;
}

.contact-info {
    flex: 1;
    min-width: 300px;
}

.contact-form {
    background-color: var(--white);
    padding: 30px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    border-color: var(--accent-blue);
    outline: none;
}

textarea.form-control {
    min-height: 150px;
    resize: vertical;
}

.submit-button {
    display: inline-block;
    padding: 12px 25px;
    background-color: var(--accent-red);
    color: var(--white);
    font-weight: 600;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.submit-button:hover {
    background-color: #c51919;
    transform: translateY(-3px);
}

.contact-card {
    background-color: var(--white);
    padding: 30px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    margin-bottom: 30px;
}

.contact-card h3 {
    margin-bottom: 20px;
    color: var(--primary-color);
    font-size: 1.4rem;
}

.contact-methods {
    margin-bottom: 25px;
}

.contact-method {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.contact-icon {
    width: 40px;
    height: 40px;
    background-color: #f0f2f5;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    color: var(--accent-red);
}

.social-links {
    display: flex;
    gap: 15px;
    margin-top: 20px;
}

.social-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background-color: #f0f2f5;
    border-radius: 50%;
    color: var(--text-color);
    text-decoration: none;
    transition: all 0.3s ease;
}

.social-link:hover {
    transform: translateY(-3px);
    color: var(--white);
}

.social-link.instagram:hover {
    background-color: #E1306C;
}

.social-link.youtube:hover {
    background-color: #FF0000;
}

.social-link.tiktok:hover {
    background-color: #000000;
}

.success-message, .error-message {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.success-message {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.error-message {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
EOT;

include 'includes/header.php';
?>

<div class="main-content">
    <div class="container">
        <div class="page-title">
            <h1>Entre em Contato</h1>
            <p>Tem alguma pergunta ou sugestão? Estamos aqui para ajudar!</p>
        </div>
        
        <div class="contact-container">
            <div class="contact-form-container">
                <?php if ($formSubmitted): ?>
                    <div class="success-message">
                        <p><strong>Mensagem enviada com sucesso!</strong></p>
                        <p>Agradecemos o seu contato. Responderemos o mais breve possível.</p>
                    </div>
                <?php elseif ($formError): ?>
                    <div class="error-message">
                        <p><strong>Erro ao enviar mensagem!</strong></p>
                        <p>Por favor, verifique se todos os campos foram preenchidos corretamente e tente novamente.</p>
                    </div>
                <?php endif; ?>
                
                <div class="contact-form">
                    <form method="POST" action="contato.php">
                        <div class="form-group">
                            <label for="name" class="form-label">Nome</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject" class="form-label">Assunto</label>
                            <select id="subject" name="subject" class="form-control" required>
                                <option value="" disabled <?= empty($defaultSubject) ? 'selected' : '' ?>>Selecione um assunto</option>
                                <option value="Quero propor um novo idioma" <?= $defaultSubject === 'Quero propor um novo idioma' ? 'selected' : '' ?>>Quero propor um novo idioma</option>
                                <option value="Tenho uma dúvida" <?= $defaultSubject === 'Tenho uma dúvida' ? 'selected' : '' ?>>Tenho uma dúvida</option>
                                <option value="Sugestão" <?= $defaultSubject === 'Sugestão' ? 'selected' : '' ?>>Sugestão</option>
                                <option value="Reclamação" <?= $defaultSubject === 'Reclamação' ? 'selected' : '' ?>>Reclamação</option>
                                <option value="Proposta de parceria" <?= $defaultSubject === 'Proposta de parceria' ? 'selected' : '' ?>>Proposta de parceria</option>
                                <option value="Outro" <?= $defaultSubject === 'Outro' ? 'selected' : '' ?>>Outro</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="message" class="form-label">Mensagem</label>
                            <textarea id="message" name="message" class="form-control" required></textarea>
                        </div>
                        
                        <button type="submit" class="submit-button">Enviar Mensagem</button>
                    </form>
                </div>
            </div>
            
            <div class="contact-info">
                <div class="contact-card">
                    <h3>Informações de Contato</h3>
                    
                    <div class="contact-methods">
                        <div class="contact-method">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <strong>Email</strong>
                                <p><a href="mailto:<?= ADMIN_EMAIL ?>"><?= ADMIN_EMAIL ?></a></p>
                            </div>
                        </div>
                        
                        <div class="contact-method">
                            <div class="contact-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <strong>Encontros Presenciais</strong>
                                <p>Diversas cidades do Brasil</p>
                            </div>
                        </div>
                    </div>
                    
                    <h3>Redes Sociais</h3>
                    <p>Nos siga para ficar por dentro de todas as novidades!</p>
                    
                    <div class="social-links">
                        <a href="https://www.instagram.com/encontrodeidiomas" target="_blank" class="social-link instagram" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="https://www.youtube.com/@encontrodeidiomas" target="_blank" class="social-link youtube" title="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                        <a href="https://www.tiktok.com/@encontrodeidiomas" target="_blank" class="social-link tiktok" title="TikTok">
                            <i class="fab fa-tiktok"></i>
                        </a>
                    </div>
                </div>
                
                <div class="contact-card">
                    <h3>Quer ser um anfitrião?</h3>
                    <p>Se você tem interesse em liderar encontros de idiomas, seja online ou presencial, entre em contato conosco! Estamos sempre em busca de pessoas apaixonadas por idiomas para expandir nossa comunidade.</p>
                    <p style="margin-top: 10px;"><strong>Benefícios:</strong></p>
                    <ul style="margin-left: 20px; margin-bottom: 15px;">
                        <li>Praticar o idioma regularmente</li>
                        <li>Conhecer pessoas de diferentes culturas</li>
                        <li>Desenvolver habilidades de liderança</li>
                        <li>Contribuir para uma comunidade global</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 