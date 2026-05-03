<?php
require_once 'config.php';

$title          = 'Presencial';
$current_page   = 'presencial.php';
$og_description = 'Encontros presenciais do Encontro de Idiomas em diversas cidades do Brasil. Participe ou organize um encontro na sua cidade!';
$canonical      = 'https://encontrodeidiomas.com.br/presencial.php';

// Busca eventos presenciais ativos
function getInPersonEvents(): array {
    try {
        $conn = connectDB();
        $stmt = $conn->query("
            SELECT e.*, h.full_name AS host_name, h.profile_picture AS host_photo
            FROM in_person_events e
            LEFT JOIN hosts h ON e.host_id = h.id AND h.status = 'ativo'
            WHERE e.active = 1
            ORDER BY e.city ASC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

$events   = getInPersonEvents();
$cities   = array_unique(array_column($events, 'city'));
$cityCount = count($cities);

ob_start();
?>
    /* ---- PRESENCIAL PAGE STYLES ---- */
    :root {
        --presencial-green: #16a34a;
        --presencial-teal:  #0d9488;
    }

    .hero-presencial {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
        padding: 100px 0 80px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .hero-presencial::before {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(ellipse at 30% 50%, rgba(227,29,28,0.15) 0%, transparent 60%),
                    radial-gradient(ellipse at 70% 50%, rgba(0,38,84,0.3) 0%, transparent 60%);
    }
    .hero-presencial .container { position: relative; z-index: 1; }
    .hero-badge {
        display: inline-flex; align-items: center; gap: 8px;
        background: rgba(22,163,74,0.15); color: #4ade80;
        border: 1px solid rgba(22,163,74,0.3);
        padding: 6px 18px; border-radius: 50px; font-size: 0.85rem; font-weight: 600;
        margin-bottom: 24px; letter-spacing: 0.5px;
    }
    .hero-title {
        font-size: clamp(2.2rem, 5vw, 3.8rem); font-weight: 800;
        color: #fff; line-height: 1.1; margin-bottom: 20px;
    }
    .hero-title span { color: var(--accent-red); }
    .hero-subtitle {
        font-size: 1.15rem; color: rgba(255,255,255,0.75);
        max-width: 640px; margin: 0 auto 40px; line-height: 1.7;
    }
    .hero-stats {
        display: flex; justify-content: center; gap: 60px; flex-wrap: wrap; margin-top: 50px;
    }
    .hero-stat { text-align: center; }
    .hero-stat .num { font-size: 2.5rem; font-weight: 800; color: #fff; }
    .hero-stat .lbl { font-size: 0.85rem; color: rgba(255,255,255,0.6); margin-top: 4px; }

    /* How it works */
    .how-section { padding: 90px 0; background: #f8f9fa; }
    .section-label { text-align:center; font-size:0.8rem; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:var(--accent-red); margin-bottom:12px; }
    .section-title-lg { text-align:center; font-size:clamp(1.8rem,3vw,2.6rem); font-weight:800; color:var(--primary-color); margin-bottom:16px; }
    .section-desc { text-align:center; max-width:640px; margin:0 auto 60px; color:#666; font-size:1.05rem; line-height:1.7; }

    .steps-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:30px; }
    .step-card {
        background:#fff; border-radius:20px; padding:35px 28px;
        box-shadow:0 4px 20px rgba(0,0,0,0.06); text-align:center;
        transition: transform 0.3s, box-shadow 0.3s;
        position: relative; overflow: hidden;
    }
    .step-card::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
        background: linear-gradient(to right, var(--accent-red), var(--accent-blue));
    }
    .step-card:hover { transform: translateY(-8px); box-shadow: 0 15px 40px rgba(0,0,0,0.12); }
    .step-num {
        width: 52px; height: 52px; border-radius: 50%;
        background: linear-gradient(135deg, var(--accent-red), #991b1b);
        color: #fff; font-size: 1.3rem; font-weight: 800;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 20px;
    }
    .step-card h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 10px; color: var(--primary-color); }
    .step-card p  { font-size: 0.92rem; color: #666; line-height: 1.6; }

    /* Cities section */
    .cities-section { padding: 90px 0; }
    .cities-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:28px; }
    .city-card {
        background:#fff; border-radius:20px; overflow:hidden;
        box-shadow:0 4px 20px rgba(0,0,0,0.07);
        transition: transform 0.3s, box-shadow 0.3s;
        border: 1px solid #f0f0f0;
    }
    .city-card:hover { transform:translateY(-8px); box-shadow:0 20px 50px rgba(0,0,0,0.12); }
    .city-card-header {
        background: linear-gradient(135deg, var(--primary-color), #334155);
        padding: 24px 28px; display:flex; align-items:center; gap:15px;
    }
    .city-icon {
        width: 50px; height: 50px; border-radius: 14px;
        background: rgba(227,29,28,0.2); display:flex; align-items:center; justify-content:center;
        font-size: 1.4rem; color: #fff; flex-shrink:0;
    }
    .city-name { font-size:1.3rem; font-weight:700; color:#fff; }
    .city-state { font-size:0.85rem; color:rgba(255,255,255,0.6); margin-top:2px; }
    .city-card-body { padding:24px 28px; }
    .city-event-title { font-size:1rem; font-weight:700; color:var(--primary-color); margin-bottom:8px; }
    .city-desc { font-size:0.9rem; color:#666; line-height:1.6; margin-bottom:18px; }
    .city-host { display:flex; align-items:center; gap:10px; padding:12px 15px; background:#f8f9fa; border-radius:12px; margin-bottom:16px; }
    .city-host img { width:36px; height:36px; border-radius:50%; object-fit:cover; }
    .city-host-icon { width:36px; height:36px; border-radius:50%; background:var(--accent-red); display:flex; align-items:center; justify-content:center; color:#fff; font-size:0.9rem; flex-shrink:0; }
    .city-host-name { font-size:0.9rem; font-weight:600; color:var(--primary-color); }
    .city-host-label { font-size:0.75rem; color:#999; }
    .city-links { display:flex; gap:10px; flex-wrap:wrap; }
    .city-link {
        display:inline-flex; align-items:center; gap:7px;
        padding:8px 16px; border-radius:50px; font-size:0.85rem; font-weight:600;
        text-decoration:none; transition: all 0.3s;
    }
    .city-link-whatsapp { background:#25d366; color:#fff; }
    .city-link-whatsapp:hover { background:#1da851; }
    .city-link-instagram { background:linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888); color:#fff; }
    .city-link-instagram:hover { opacity:0.9; }

    /* No events */
    .no-events { text-align:center; padding:60px 20px; }
    .no-events i { font-size:3rem; color:#ccc; margin-bottom:20px; display:block; }
    .no-events p { color:#999; font-size:1rem; }

    /* CTA expandir */
    .expand-section {
        background: linear-gradient(135deg, var(--accent-red) 0%, #991b1b 100%);
        padding: 90px 0; color:#fff; text-align:center;
    }
    .expand-title { font-size:clamp(1.8rem,3.5vw,2.8rem); font-weight:800; margin-bottom:16px; }
    .expand-desc { font-size:1.1rem; opacity:0.9; max-width:600px; margin:0 auto 40px; line-height:1.7; }
    .expand-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:24px; max-width:900px; margin:0 auto 50px; }
    .expand-card {
        background:rgba(255,255,255,0.12); border-radius:16px; padding:28px 22px;
        backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,0.2);
        transition: transform 0.3s, background 0.3s;
    }
    .expand-card:hover { transform:translateY(-6px); background:rgba(255,255,255,0.18); }
    .expand-card i { font-size:2rem; margin-bottom:14px; display:block; opacity:0.9; }
    .expand-card h3 { font-size:1.05rem; font-weight:700; margin-bottom:8px; }
    .expand-card p { font-size:0.88rem; opacity:0.85; line-height:1.6; }
    .btn-expand {
        display:inline-flex; align-items:center; gap:10px;
        background:#fff; color:var(--accent-red);
        padding:16px 40px; border-radius:50px; font-weight:700; font-size:1.05rem;
        text-decoration:none; transition: all 0.3s; box-shadow:0 8px 25px rgba(0,0,0,0.2);
    }
    .btn-expand:hover { transform:translateY(-3px) scale(1.03); box-shadow:0 15px 35px rgba(0,0,0,0.3); }

    /* Benefits host */
    .host-benefits { padding:90px 0; background:#f8f9fa; }
    .benefits-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:30px; margin-top:50px; }
    .benefit-card {
        background:#fff; border-radius:18px; padding:32px 26px; text-align:center;
        box-shadow:0 4px 15px rgba(0,0,0,0.06); transition: transform 0.3s, box-shadow 0.3s;
    }
    .benefit-card:hover { transform:translateY(-8px); box-shadow:0 15px 35px rgba(0,0,0,0.1); }
    .benefit-icon { font-size:2.4rem; color:var(--accent-red); margin-bottom:18px; }
    .benefit-card h4 { font-size:1.1rem; font-weight:700; color:var(--primary-color); margin-bottom:10px; }
    .benefit-card p  { font-size:0.9rem; color:#666; line-height:1.6; }

    /* CTA final */
    .final-cta { padding:80px 0; text-align:center; }
    .final-cta h2 { font-size:2rem; font-weight:800; margin-bottom:16px; color:var(--primary-color); }
    .final-cta p { color:#666; max-width:560px; margin:0 auto 35px; line-height:1.7; }
    .btn-primary-red { display:inline-flex; align-items:center; gap:10px; background:var(--accent-red); color:#fff; padding:16px 40px; border-radius:50px; font-weight:700; font-size:1rem; text-decoration:none; transition:all 0.3s; }
    .btn-primary-red:hover { background:#c11817; transform:translateY(-3px); box-shadow:0 10px 25px rgba(227,29,28,0.3); }

    @media (max-width: 768px) {
        .hero-stats { gap: 30px; }
        .steps-grid { grid-template-columns: 1fr 1fr; }
        .cities-grid { grid-template-columns: 1fr; }
        .expand-cards { grid-template-columns: 1fr; }
        .benefits-grid { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 480px) {
        .steps-grid { grid-template-columns: 1fr; }
        .benefits-grid { grid-template-columns: 1fr; }
    }
<?php
$page_styles = ob_get_clean();

include 'includes/header.php';
?>

<main>
    <!-- HERO -->
    <section class="hero-presencial">
        <div class="container">
            <div class="hero-badge">
                <i class="fas fa-map-marker-alt"></i> Acontece presencialmente
            </div>
            <h1 class="hero-title">
                Encontros <span>Presenciais</span><br>em todo o Brasil
            </h1>
            <p class="hero-subtitle">
                Pratique idiomas de forma presencial com pessoas reais na sua cidade.
                Sem horário fixo, sem burocracia — apenas conversação genuína e conexões duradouras.
            </p>
            <div style="display:flex; gap:16px; justify-content:center; flex-wrap:wrap;">
                <?php if ($cityCount > 0): ?>
                    <a href="#cidades" class="btn-primary-red"><i class="fas fa-map-marked-alt"></i> Ver cidades</a>
                <?php endif; ?>
                <a href="#seja-organizador" style="display:inline-flex;align-items:center;gap:10px; background:rgba(255,255,255,0.12); color:#fff; padding:16px 32px; border-radius:50px; font-weight:700; text-decoration:none; border:1px solid rgba(255,255,255,0.25); transition:all 0.3s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.12)'">
                    <i class="fas fa-plus-circle"></i> Organizar na minha cidade
                </a>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="num"><?= $cityCount > 0 ? $cityCount . '+' : '?' ?></div>
                    <div class="lbl">Cidades</div>
                </div>
                <div class="hero-stat">
                    <div class="num">100%</div>
                    <div class="lbl">Gratuito</div>
                </div>
                <div class="hero-stat">
                    <div class="num">∞</div>
                    <div class="lbl">Idiomas</div>
                </div>
            </div>
        </div>
    </section>

    <!-- COMO FUNCIONA -->
    <section class="how-section" id="como-funciona">
        <div class="container">
            <p class="section-label">Como funciona</p>
            <h2 class="section-title-lg">Simples, flexível e gratuito</h2>
            <p class="section-desc">
                Os encontros presenciais não têm data fixa. Eles acontecem conforme a demanda da comunidade local se forma. Veja como é fácil participar.
            </p>
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-num">1</div>
                    <h3>Entre no grupo da cidade</h3>
                    <p>Encontre o grupo WhatsApp da sua cidade ou região e entre para acompanhar os próximos encontros.</p>
                </div>
                <div class="step-card">
                    <div class="step-num">2</div>
                    <h3>Espere a demanda se formar</h3>
                    <p>Quando houver participantes suficientes, o organizador local anuncia data e local no grupo.</p>
                </div>
                <div class="step-card">
                    <div class="step-num">3</div>
                    <h3>Apareça e pratique</h3>
                    <p>Vá ao local combinado, apresente-se e pratique o idioma com pessoas reais de forma descontraída.</p>
                </div>
                <div class="step-card">
                    <div class="step-num">4</div>
                    <h3>Convide mais pessoas</h3>
                    <p>Quanto mais participantes, mais encontros acontecem. Compartilhe com amigos que também queiram praticar.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CIDADES ATIVAS -->
    <section class="cities-section" id="cidades">
        <div class="container">
            <p class="section-label">Onde estamos</p>
            <h2 class="section-title-lg">Cidades com encontros ativos</h2>
            <p class="section-desc">
                Estas são as localidades onde já temos organização ativa. Clique no grupo WhatsApp para participar!
            </p>

            <?php if (empty($events)): ?>
                <div class="no-events">
                    <i class="fas fa-map-marked-alt"></i>
                    <p>Em breve teremos cidades cadastradas aqui. Enquanto isso, seja o primeiro a organizar na sua cidade!</p>
                </div>
            <?php else: ?>
            <div class="cities-grid">
                <?php foreach ($events as $ev): ?>
                <div class="city-card">
                    <div class="city-card-header">
                        <div class="city-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div>
                            <div class="city-name"><?= htmlspecialchars($ev['city']) ?></div>
                            <?php if (!empty($ev['state'])): ?>
                                <div class="city-state"><?= htmlspecialchars($ev['state']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="city-card-body">
                        <div class="city-event-title"><?= htmlspecialchars($ev['title']) ?></div>
                        <?php if (!empty($ev['description'])): ?>
                            <p class="city-desc"><?= htmlspecialchars($ev['description']) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($ev['host_name'])): ?>
                        <div class="city-host">
                            <?php if (!empty($ev['host_photo'])): ?>
                                <img src="assets/images/<?= htmlspecialchars($ev['host_photo']) ?>" alt="<?= htmlspecialchars($ev['host_name']) ?>">
                            <?php else: ?>
                                <div class="city-host-icon"><i class="fas fa-user"></i></div>
                            <?php endif; ?>
                            <div>
                                <div class="city-host-name"><?= htmlspecialchars($ev['host_name']) ?></div>
                                <div class="city-host-label">Organizador(a) local</div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="city-links">
                            <?php if (!empty($ev['whatsapp_link'])): ?>
                                <a href="<?= htmlspecialchars($ev['whatsapp_link']) ?>" target="_blank" rel="noopener" class="city-link city-link-whatsapp">
                                    <i class="fab fa-whatsapp"></i> Entrar no grupo
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($ev['instagram_link'])): ?>
                                <a href="<?= htmlspecialchars($ev['instagram_link']) ?>" target="_blank" rel="noopener" class="city-link city-link-instagram">
                                    <i class="fab fa-instagram"></i> Instagram
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- EXPANDA PARA SUA CIDADE -->
    <section class="expand-section" id="seja-organizador">
        <div class="container">
            <h2 class="expand-title">Não tem encontro na sua cidade?</h2>
            <p class="expand-desc">
                Você pode ser o primeiro a organizar! Precisamos de pessoas motivadas para liderar encontros presenciais em novas localidades. É simples, gratuito e muito gratificante.
            </p>
            <div class="expand-cards">
                <div class="expand-card">
                    <i class="fas fa-users"></i>
                    <h3>Reúna as pessoas</h3>
                    <p>Divulgue o grupo local para juntar participantes interessados em praticar idiomas.</p>
                </div>
                <div class="expand-card">
                    <i class="fas fa-calendar-plus"></i>
                    <h3>Marque quando houver demanda</h3>
                    <p>Sem compromisso fixo. Organize quando sentir que há participantes suficientes.</p>
                </div>
                <div class="expand-card">
                    <i class="fas fa-map-pin"></i>
                    <h3>Escolha o local</h3>
                    <p>Cafés, parques, livrarias, bibliotecas — qualquer lugar confortável serve!</p>
                </div>
            </div>
            <a href="contato.php" class="btn-expand">
                <i class="fas fa-hand-raised"></i> Quero organizar na minha cidade
            </a>
        </div>
    </section>

    <!-- BENEFÍCIOS DO HOST PRESENCIAL -->
    <section class="host-benefits" id="beneficios">
        <div class="container">
            <p class="section-label">Para organizadores</p>
            <h2 class="section-title-lg">Por que ser um host presencial?</h2>
            <p class="section-desc">Muito além de organizar um encontro — uma oportunidade de crescimento real.</p>
            <div class="benefits-grid">
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-certificate"></i></div>
                    <h4>Certificado e Currículo</h4>
                    <p>Receba um certificado de voluntariado reconhecido para turbinar seu currículo e LinkedIn.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-id-card"></i></div>
                    <h4>Perfil no Site</h4>
                    <p>Seu perfil em destaque na equipe do Encontro de Idiomas, visível para milhares de visitantes.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-network-wired"></i></div>
                    <h4>Networking Real</h4>
                    <p>Conecte-se com outros organizadores e voluntários de todo o Brasil.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-bullseye"></i></div>
                    <h4>Liderança na Prática</h4>
                    <p>Desenvolva habilidades de organização de grupos e liderança comunitária com suporte total.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-gem"></i></div>
                    <h4>Comunidade VIP</h4>
                    <p>Acesso ao grupo exclusivo de organizadores, com materiais, dicas e suporte entre pares.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-rocket"></i></div>
                    <h4>Impacto na Comunidade</h4>
                    <p>Seja o responsável por transformar sua cidade em um polo de aprendizado de idiomas.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA FINAL -->
    <section class="final-cta">
        <div class="container">
            <h2>Pronto para dar o primeiro passo?</h2>
            <p>Entre em contato conosco e nossa equipe vai te orientar em todos os passos para criar o primeiro encontro na sua cidade.</p>
            <a href="contato.php" class="btn-primary-red">
                <i class="fas fa-envelope"></i> Falar com a equipe
            </a>
        </div>
    </section>
</main>

<?php
$page_scripts = <<<JS
<script>
// Smooth scroll para âncoras
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', function(e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});
</script>
JS;

include 'includes/footer.php';
?>
