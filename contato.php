<?php
require_once 'config.php';

$title          = 'Contato';
$current_page   = 'contato.php';
$og_description = 'Entre em contato com o Encontro de Idiomas - Envie uma mensagem para nossa equipe ou participe de nossos grupos.';
$canonical      = 'https://encontrodeidiomas.com.br/contato.php';

$page_styles = <<<CSS
    :root {
        --link-bg:       rgba(0,0,0,.05);
        --link-hover-bg: rgba(0,0,0,.1);
        --error-color:   #e31d1c;
        --success-color: #28a745;
    }

    .profile {
        text-align: center;
        margin-top: 60px;
        margin-bottom: 40px;
    }

    .profile-image {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        margin: 0 auto 20px;
        overflow: hidden;
        border: 3px solid var(--primary-color);
        box-shadow: 0 10px 25px rgba(0,0,0,.1);
    }

    .logo-text {
        font-weight: bold;
        font-size: 42px;
        background: linear-gradient(135deg, var(--accent-red) 0%, var(--accent-blue) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100%;
        background-color: var(--white);
    }

    h1 {
        font-size: 2.2rem;
        color: var(--primary-color);
        margin-bottom: 5px;
    }

    h2 {
        font-size: 1.2rem;
        color: var(--text-color);
        font-weight: 500;
        margin-bottom: 30px;
        opacity: 0.9;
    }

    .contact-intro {
        text-align: center;
        margin-bottom: 40px;
    }

    .contact-form {
        background: var(--bg-light);
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,.1);
        margin-bottom: 40px;
    }

    .form-group { margin-bottom: 20px; }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
    }

    .form-control {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-family: 'Poppins', sans-serif;
        font-size: 1rem;
        transition: border-color 0.3s;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(0,38,84,.1);
    }

    textarea.form-control {
        min-height: 150px;
        resize: vertical;
    }

    .submit-btn {
        background: var(--accent-red);
        color: var(--white);
        border: none;
        padding: 12px 30px;
        border-radius: 25px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
    }

    .submit-btn:hover {
        background: #c41a1a;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,.1);
    }

    .submit-btn i { margin-right: 10px; }

    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: none;
    }

    .alert-success {
        background: rgba(40,167,69,.1);
        border: 1px solid var(--success-color);
        color: var(--success-color);
    }

    .alert-error {
        background: rgba(227,29,28,.1);
        border: 1px solid var(--error-color);
        color: var(--error-color);
    }

    .social-icons {
        display: flex;
        justify-content: center;
        gap: 30px;
        margin-top: 40px;
        margin-bottom: 60px;
    }

    .social-icons a {
        color: var(--primary-color);
        font-size: 1.8rem;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--link-bg);
        transition: all 0.3s ease;
    }

    .social-icons a:hover {
        transform: scale(1.1);
        background: var(--link-hover-bg);
    }

    @media (max-width: 768px) {
        .profile { margin-top: 30px; }
    }
CSS;

$page_scripts = <<<JS
<script>
    document.getElementById('contact-form').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('_subject', 'Nova mensagem do Encontro de Idiomas');

        fetch('https://formspree.io/f/xyzelebl', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        })
        .then(response => {
            if (response.ok) {
                document.getElementById('success-message').style.display = 'block';
                document.getElementById('error-message').style.display = 'none';
                this.reset();
            } else {
                throw new Error('Server error');
            }
        })
        .catch(() => {
            document.getElementById('error-message').style.display = 'block';
            document.getElementById('success-message').style.display = 'none';
        });
    });
</script>
JS;

include 'includes/header.php';
?>

    <main>
        <div class="container">
            <div class="profile">
                <div class="profile-image">
                    <div class="logo-text">EI</div>
                </div>
                <h1>Fale Conosco</h1>
                <h2>Estamos aqui para ajudar! Envie sua mensagem.</h2>
            </div>

            <div class="contact-intro">
                <p>Tem alguma dúvida sobre o Encontro de Idiomas? Quer sugerir um novo idioma para os encontros? Ou talvez queira compartilhar sua experiência? Entre em contato conosco!</p>
            </div>

            <div class="alert alert-success" id="success-message" role="alert">
                Sua mensagem foi enviada com sucesso! Entraremos em contato em breve.
            </div>

            <div class="alert alert-error" id="error-message" role="alert">
                Ocorreu um erro ao enviar sua mensagem. Por favor, tente novamente.
            </div>

            <form class="contact-form" id="contact-form" novalidate>
                <div class="form-group">
                    <label for="name">Nome</label>
                    <input type="text" id="name" name="name" class="form-control" required autocomplete="name">
                </div>
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" class="form-control" required autocomplete="email">
                </div>
                <div class="form-group">
                    <label for="message">Mensagem</label>
                    <textarea id="message" name="message" class="form-control" required></textarea>
                </div>
                <button type="submit" class="submit-btn" id="submit-btn">
                    <i class="fas fa-paper-plane"></i> Enviar
                </button>
            </form>

            <div class="social-icons">
                <a href="https://instagram.com/encontrodeidiomas" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                    <i class="fab fa-instagram"></i>
                </a>
                <a href="https://discord.com/invite/STHkrEhMpP" target="_blank" rel="noopener noreferrer" aria-label="Discord">
                    <i class="fab fa-discord"></i>
                </a>
            </div>
        </div>
    </main>

<?php include 'includes/footer.php'; ?>
