<?php
require_once 'config.php';

$title          = 'Contato';
$current_page   = 'contato.php';
$og_description = 'Entre em contato com o Encontro de Idiomas - Envie uma mensagem para nossa equipe ou participe de nossos grupos.';
$canonical      = 'https://encontrodeidiomas.com.br/contato.php';

$page_styles = <<<CSS
    :root {
        --glass-bg: rgba(255, 255, 255, 0.85);
        --glass-border: rgba(255, 255, 255, 0.3);
        --accent-gradient: linear-gradient(135deg, var(--accent-red) 0%, var(--accent-blue) 100%);
        --shadow-sm: 0 4px 6px rgba(0,0,0,0.05);
        --shadow-md: 0 10px 20px rgba(0,0,0,0.1);
        --success-color: #28a745;
        --error-color: #e31d1c;
    }

    body {
        background: linear-gradient(180deg, #f0f2f5 0%, #ffffff 100%);
        min-height: 100vh;
    }

    .contact-container {
        max-width: 600px;
        margin: 0 auto;
        padding: 40px 15px 80px;
    }

    /* Page Banner Full-Bleed */
    .page-banner {
        width: 100%;
        height: 45vh !important;
        background: linear-gradient(135deg, rgba(0, 38, 84, 0.4) 0%, #ffffff 50%, rgba(227, 29, 28, 0.4) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        padding: 20px 0;
        overflow: hidden;
    }

    /* Header Visual */
    .hero-header {
        text-align: center;
        animation: fadeInDown 0.8s ease-out;
        max-width: 600px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .hero-image-wrapper {
        width: 100%;
        max-width: 220px;
        margin: 0 auto 10px;
        position: relative;
    }

    .hero-image {
        width: 100%;
        height: auto;
        filter: drop-shadow(0 10px 20px rgba(0,38,84,0.1));
        transform: scale(1.05);
    }

    .hero-header h1 {
        font-size: 2.2rem;
        font-weight: 800;
        margin-bottom: 5px;
        background: var(--accent-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        letter-spacing: -1.5px;
    }

    .hero-header p {
        color: #666;
        font-size: 1rem;
        max-width: 100%;
        margin: 0 auto;
        line-height: 1.5;
    }

    /* Form Card */
    .contact-form-card {
        background: var(--glass-bg);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 40px;
        box-shadow: var(--shadow-sm);
        animation: fadeInUp 0.8s ease-out both 0.2s;
    }

    .form-group { margin-bottom: 20px; }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--primary-color);
        padding-left: 5px;
    }

    .form-control {
        width: 100%;
        padding: 14px 18px;
        background: rgba(255, 255, 255, 0.6);
        border: 1px solid rgba(0,0,0,0.05);
        border-radius: 12px;
        font-family: 'Poppins', sans-serif;
        font-size: 1rem;
        transition: all 0.3s ease;
        color: var(--text-color);
    }

    .form-control:focus {
        outline: none;
        background: var(--white);
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 4px rgba(0,38,84,0.05);
    }

    textarea.form-control {
        min-height: 140px;
        resize: vertical;
    }

    .submit-btn {
        width: 100%;
        background: var(--accent-gradient);
        color: var(--white);
        border: none;
        padding: 16px;
        border-radius: 30px;
        font-size: 1.1rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        box-shadow: 0 4px 15px rgba(227,29,28,0.2);
    }

    .submit-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(227,29,28,0.3);
        filter: brightness(1.1);
    }

    .submit-btn:active {
        transform: translateY(-1px);
    }

    /* Alerts */
    .alert {
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: none;
        font-size: 0.95rem;
        font-weight: 500;
        text-align: center;
        animation: fadeIn 0.4s ease;
    }

    .alert-success {
        background: rgba(40,167,69,0.1);
        border: 1px solid var(--success-color);
        color: var(--success-color);
    }

    .alert-error {
        background: rgba(227,29,28,0.1);
        border: 1px solid var(--error-color);
        color: var(--error-color);
    }

    /* Social Icons Footer */
    .social-footer {
        text-align: center;
        margin-top: 40px;
        padding-top: 30px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: center;
        gap: 25px;
        animation: fadeInUp 0.8s ease-out both 0.4s;
    }

    .social-footer a {
        font-size: 1.8rem;
        transition: all 0.3s ease;
    }

    .social-footer a:hover {
        transform: scale(1.2) translateY(-5px);
    }

    /* Animations */
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @media (max-width: 480px) {
        .hero-header h1 { font-size: 1.8rem; }
        .contact-form-card { padding: 20px; }
    }
CSS;

$page_scripts = <<<JS
<script>
    document.getElementById('contact-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('submit-btn');
        const originalContent = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

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
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalContent;
        });
    });
</script>
JS;

include 'includes/header.php';
?>

    <main>
        <section class="page-banner">
            <header class="hero-header">
                <div class="hero-image-wrapper">
                    <img src="assets/images/hero_contact.png" alt="Fale Conosco" class="hero-image">
                </div>
                <h1>Fale Conosco</h1>
                <p>Quer tirar dúvidas, dar sugestões ou se juntar ao nosso time de voluntários e anfitriões? Envie sua mensagem!</p>
            </header>
        </section>

        <div class="contact-container">

            <div class="alert alert-success" id="success-message" role="alert">
                <i class="fas fa-check-circle"></i> Sua mensagem foi enviada com sucesso! Responderemos em breve.
            </div>

            <div class="alert alert-error" id="error-message" role="alert">
                <i class="fas fa-exclamation-circle"></i> Ocorreu um erro ao enviar. Por favor, tente novamente mais tarde.
            </div>

            <div class="contact-form-card">
                <form id="contact-form" novalidate>
                    <div class="form-group">
                        <label for="name">Nome Completo</label>
                        <input type="text" id="name" name="name" class="form-control" placeholder="Como podemos te chamar?" required autocomplete="name">
                    </div>
                    <div class="form-group">
                        <label for="email">Seu melhor E-mail</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="exemplo@email.com" required autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="message">Sua Mensagem</label>
                        <textarea id="message" name="message" class="form-control" placeholder="Escreva sua mensagem, dúvida ou conte-nos como gostaria de colaborar..." required></textarea>
                    </div>
                    <button type="submit" class="submit-btn" id="submit-btn">
                        <i class="fas fa-paper-plane"></i> Enviar Mensagem
                    </button>
                </form>
            </div>

            <!-- Rodapé Social -->
            <div class="social-footer">
                <a href="https://www.instagram.com/encontrodeidiomas/" target="_blank" style="color: var(--accent-red);"><i class="fab fa-instagram"></i></a>
                <a href="https://www.tiktok.com/@encontrodeidiomas" target="_blank" style="color: #000;"><i class="fab fa-tiktok"></i></a>
                <a href="https://discord.gg/STHkrEhMpP" target="_blank" style="color: #5865F2;"><i class="fab fa-discord"></i></a>
            </div>

        </div>
    </main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Função de rolagem suave padronizada (mesma do Online/Presencial)
    function smoothScrollTo(endY, duration) {
        const startY = window.pageYOffset;
        const distance = endY - startY;
        let startTime = null;
        function animation(currentTime) {
            if (startTime === null) startTime = currentTime;
            const timeElapsed = currentTime - startTime;
            const run = ease(timeElapsed, startY, distance, duration);
            window.scrollTo(0, run);
            if (timeElapsed < duration) requestAnimationFrame(animation);
        }
        function ease(t, b, c, d) {
            t /= d / 2;
            if (t < 1) return c / 2 * t * t + b;
            t--;
            return -c / 2 * (t * (t - 2) - 1) + b;
        }
        requestAnimationFrame(animation);
    }

    setTimeout(function() {
        const formCard = document.querySelector('.contact-form-card');
        if (formCard) {
            const offset = 80; // Exatamente após o fim do Hero (altura do header)
            const targetY = formCard.getBoundingClientRect().top + window.pageYOffset - offset;
            smoothScrollTo(targetY, 1500);
        }
    }, 2000); // 2 segundos de espera real
});
</script>

<?php include 'includes/footer.php'; ?>
