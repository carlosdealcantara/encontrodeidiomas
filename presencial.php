<?php
require_once 'config.php';

$title          = 'Presencial';
$current_page   = 'presencial.php';
$og_description = 'Encontros presenciais do Encontro de Idiomas em diversas cidades do Brasil, Argentina e Paraguai.';
$canonical      = 'https://encontrodeidiomas.com.br/presencial.php';

function getInPersonEvents(): array {
    try {
        $conn = connectDB();
        return $conn->query("
            SELECT e.*, COALESCE(e.country,'Brasil') AS country,
                   h.full_name AS host_name, h.profile_picture AS host_photo
            FROM in_person_events e
            LEFT JOIN hosts h ON e.host_id = h.id AND h.status = 'ativo'
            WHERE e.active = 1
            ORDER BY CASE WHEN COALESCE(e.country,'Brasil')='Brasil' THEN 0 ELSE 1 END, e.country, e.city ASC
        ")->fetchAll();
    } catch (PDOException $e) { return []; }
}

function getRegiao(string $state): string {
    $map = [
        'AC'=>'Norte','AM'=>'Norte','AP'=>'Norte','PA'=>'Norte','RO'=>'Norte','RR'=>'Norte','TO'=>'Norte',
        'AL'=>'Nordeste','BA'=>'Nordeste','CE'=>'Nordeste','MA'=>'Nordeste','PB'=>'Nordeste',
        'PE'=>'Nordeste','PI'=>'Nordeste','RN'=>'Nordeste','SE'=>'Nordeste',
        'DF'=>'Centro-Oeste','GO'=>'Centro-Oeste','MS'=>'Centro-Oeste','MT'=>'Centro-Oeste',
        'ES'=>'Sudeste','MG'=>'Sudeste','RJ'=>'Sudeste','SP'=>'Sudeste',
        'PR'=>'Sul','RS'=>'Sul','SC'=>'Sul',
    ];
    return $map[strtoupper(trim($state))] ?? 'Outras Regiões';
}

$regionOrder = ['Norte','Nordeste','Centro-Oeste','Sudeste','Sul','Outras Regiões'];

$countryCodes = ['Brasil'=>'br','Argentina'=>'ar','Paraguai'=>'py','Chile'=>'cl','Uruguai'=>'uy','Portugal'=>'pt','Angola'=>'ao','Moçambique'=>'mz'];
$countryFlag  = function(string $c) use ($countryCodes): string {
    $code = $countryCodes[$c] ?? null;
    if ($code) return "<img src=\"https://flagcdn.com/32x24/{$code}.png\" alt=\"Bandeira de {$c}\" style=\"width:40px;height:30px;object-fit:cover;border-radius:4px;box-shadow:0 1px 4px rgba(0,0,0,0.15);flex-shrink:0;\">";
    return '<span style="font-size:1.8rem;">🌎</span>';
};

$events    = getInPersonEvents();
$byCountry = [];
foreach ($events as $ev) {
    $byCountry[$ev['country']][] = $ev;
}
$totalGroups    = count($events);
$totalCountries = count($byCountry);

ob_start();
?>
    /* ---- PRESENCIAL PAGE ---- */
    .hero-presencial {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
        padding: 100px 0 80px; text-align: center; position: relative; overflow: hidden;
    }
    .hero-presencial::before {
        content: ''; position: absolute; inset: 0;
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
        font-size: 1.1rem; color: rgba(255,255,255,0.75);
        max-width: 680px; margin: 0 auto 40px; line-height: 1.7;
    }
    .hero-cta-row { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
    .btn-hero-outline {
        display: inline-flex; align-items: center; gap: 10px;
        background: rgba(255,255,255,0.1); color: #fff;
        padding: 14px 30px; border-radius: 50px; font-weight: 700;
        text-decoration: none; border: 1px solid rgba(255,255,255,0.25); transition: all 0.3s;
    }
    .btn-hero-outline:hover { background: rgba(255,255,255,0.2); }
    .hero-stats {
        display: flex; justify-content: center; gap: 60px; flex-wrap: wrap;
        margin-top: 50px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 40px;
    }
    .hero-stat { text-align: center; }
    .hero-stat .num { font-size: 2.8rem; font-weight: 800; color: #fff; line-height: 1; }
    .hero-stat .lbl { font-size: 0.78rem; color: rgba(255,255,255,0.5); margin-top: 6px; text-transform: uppercase; letter-spacing: 1px; }

    /* How it works */
    .how-section { padding: 90px 0; background: #f8f9fa; }
    .section-label { text-align:center; font-size:0.78rem; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:var(--accent-red); margin-bottom:12px; }
    .section-title-lg { text-align:center; font-size:clamp(1.8rem,3vw,2.6rem); font-weight:800; color:var(--primary-color); margin-bottom:16px; }
    .section-desc { text-align:center; max-width:640px; margin:0 auto 60px; color:#666; font-size:1.05rem; line-height:1.7; }
    .steps-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:30px; }
    .step-card {
        background:#fff; border-radius:20px; padding:35px 28px;
        box-shadow:0 4px 20px rgba(0,0,0,0.06); text-align:center;
        transition: transform 0.3s, box-shadow 0.3s; position: relative; overflow: hidden;
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
        display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;
    }
    .step-card h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 10px; color: var(--primary-color); }
    .step-card p  { font-size: 0.92rem; color: #666; line-height: 1.6; }

    /* Cities accordion */
    .cities-section { padding: 90px 0; }
    .country-accordion { margin-bottom: 16px; border-radius: 20px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #eee; }
    .country-header {
        display: flex; align-items: center; gap: 14px;
        background: #fff; padding: 20px 28px; cursor: pointer;
        transition: background 0.2s; user-select: none; border: none; width: 100%;
    }
    .country-header:hover { background: #fafafa; }
    .country-flag { flex-shrink: 0; }
    .country-info { flex: 1; text-align: left; }
    .country-name { font-size: 1.15rem; font-weight: 800; color: var(--primary-color); }
    .country-count { font-size: 0.82rem; color: #888; margin-top: 2px; }
    .country-chevron { color: #aaa; transition: transform 0.3s; font-size: 0.9rem; }
    .country-accordion.open .country-chevron { transform: rotate(180deg); }
    .country-body { display: none; background: #f8f9fa; }
    .country-accordion.open .country-body { display: block; }
    .country-body-inner { padding: 28px; }

    /* Region accordion inside Brazil */
    .region-accordion { margin-bottom: 12px; border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0; background: #fff; }
    .region-header {
        display: flex; align-items: center; gap: 10px; padding: 14px 20px;
        cursor: pointer; user-select: none; background: #fff; border: none; width: 100%;
        transition: background 0.2s;
    }
    .region-header:hover { background: #f8fafc; }
    .region-name {
        flex: 1; text-align: left; font-size: 0.8rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: 1.5px; color: var(--accent-red);
    }
    .region-count { font-size: 0.75rem; color: #94a3b8; margin-right: 6px; }
    .region-chevron { color: #aaa; transition: transform 0.3s; font-size: 0.8rem; }
    .region-accordion.open .region-chevron { transform: rotate(180deg); }
    .region-body { display: none; padding: 0 16px 16px; background: #f8f9fa; }
    .region-accordion.open .region-body { display: block; }
    .cities-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:16px; margin-top: 12px; }

    /* City card redesign — clean white, no heavy dark header */
    .city-card {
        background:#fff; border-radius:16px; overflow:hidden;
        box-shadow:0 3px 15px rgba(0,0,0,0.07);
        transition: transform 0.3s, box-shadow 0.3s;
        border: 1px solid #ebebeb; border-top: 3px solid var(--accent-red);
    }
    .city-card:hover { transform:translateY(-6px); box-shadow:0 15px 40px rgba(0,0,0,0.12); }
    .city-card-header {
        padding: 18px 22px 14px;
        display: flex; align-items: flex-start; gap: 12px;
        border-bottom: 1px solid #f3f3f3;
    }
    .city-pin { color: var(--accent-red); font-size: 1.1rem; margin-top: 4px; flex-shrink: 0; }
    .city-name { font-size: 1.15rem; font-weight: 800; color: var(--primary-color); line-height: 1.2; }
    .city-state-badge {
        display: inline-block; background: #f1f5f9; color: #64748b;
        padding: 2px 8px; border-radius: 6px; font-size: 0.72rem; font-weight: 700;
        margin-top: 5px; letter-spacing: 0.5px;
    }
    .city-card-body { padding: 18px 22px; }
    .city-event-title { font-size: 0.92rem; font-weight: 700; color: var(--primary-color); margin-bottom: 6px; }
    .city-desc { font-size: 0.87rem; color: #666; line-height: 1.6; margin-bottom: 14px; }
    .city-host { display:flex; align-items:center; gap:10px; padding:10px 13px; background:#f8f9fa; border-radius:10px; margin-bottom:14px; }
    .city-host img { width:32px; height:32px; border-radius:50%; object-fit:cover; }
    .city-host-icon { width:32px; height:32px; border-radius:50%; background: var(--accent-red); display:flex; align-items:center; justify-content:center; color:#fff; font-size:0.8rem; flex-shrink:0; }
    .city-host-name { font-size:0.85rem; font-weight:600; color:var(--primary-color); }
    .city-host-label { font-size:0.72rem; color:#999; }
    .city-links { display:flex; gap:8px; flex-wrap:wrap; }
    .city-link {
        display:inline-flex; align-items:center; gap:6px;
        padding:7px 16px; border-radius:50px; font-size:0.82rem; font-weight:600;
        text-decoration:none; transition: all 0.25s; border: 1.5px solid;
    }
    .city-link-whatsapp { color: #16a34a; border-color: #16a34a; }
    .city-link-whatsapp:hover { background:#16a34a; color:#fff; }
    .city-link-instagram { color: #9333ea; border-color: #9333ea; }
    .city-link-instagram:hover { background:#9333ea; color:#fff; }

    /* No events */
    .no-events { text-align:center; padding:60px 20px; }
    .no-events i { font-size:3rem; color:#ccc; margin-bottom:20px; display:block; }

    /* Expand CTA */
    .expand-section {
        background: linear-gradient(135deg, var(--accent-red) 0%, #991b1b 100%);
        padding: 90px 0; color:#fff; text-align:center;
    }
    .expand-title { font-size:clamp(1.8rem,3.5vw,2.8rem); font-weight:800; margin-bottom:16px; }
    .expand-desc { font-size:1.1rem; opacity:0.9; max-width:600px; margin:0 auto 40px; line-height:1.7; }
    .expand-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:22px; max-width:900px; margin:0 auto 48px; }
    .expand-card {
        background:rgba(255,255,255,0.12); border-radius:16px; padding:26px 20px;
        backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,0.2);
        transition: transform 0.3s, background 0.3s;
    }
    .expand-card:hover { transform:translateY(-6px); background:rgba(255,255,255,0.18); }
    .expand-card i { font-size:1.8rem; margin-bottom:12px; display:block; opacity:0.9; }
    .expand-card h3 { font-size:1rem; font-weight:700; margin-bottom:8px; }
    .expand-card p { font-size:0.85rem; opacity:0.85; line-height:1.6; }
    .btn-expand {
        display:inline-flex; align-items:center; gap:10px;
        background:#fff; color:var(--accent-red);
        padding:16px 40px; border-radius:50px; font-weight:700; font-size:1rem;
        text-decoration:none; transition: all 0.3s; box-shadow:0 8px 25px rgba(0,0,0,0.2);
    }
    .btn-expand:hover { transform:translateY(-3px) scale(1.03); box-shadow:0 15px 35px rgba(0,0,0,0.3); }

    /* Host CTA */
    .host-cta-section { padding: 90px 0; background: #f8f9fa; text-align: center; }
    .host-cta-inner {
        background: #fff; border-radius: 28px; padding: 60px 40px;
        box-shadow: 0 8px 40px rgba(0,0,0,0.08); max-width: 700px; margin: 0 auto;
        border: 1px solid #f0f0f0;
    }
    .host-cta-icon { font-size: 3rem; color: var(--accent-red); margin-bottom: 20px; }
    .host-cta-inner h2 { font-size: 1.8rem; font-weight: 800; color: var(--primary-color); margin-bottom: 14px; }
    .host-cta-inner p { font-size: 1rem; color: #666; line-height: 1.7; max-width: 520px; margin: 0 auto 32px; }
    .host-cta-btns { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }
    .btn-primary-red { display:inline-flex; align-items:center; gap:10px; background:var(--accent-red); color:#fff; padding:14px 32px; border-radius:50px; font-weight:700; font-size:0.95rem; text-decoration:none; transition:all 0.3s; }
    .btn-primary-red:hover { background:#c11817; transform:translateY(-2px); box-shadow:0 10px 25px rgba(227,29,28,0.3); }
    .btn-outline-red {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 14px 32px; border-radius: 50px; font-weight: 700; font-size: 0.95rem;
        text-decoration: none; transition: all 0.3s;
        border: 2px solid var(--accent-red); color: var(--accent-red); background: transparent;
    }
    .btn-outline-red:hover { background: var(--accent-red); color: #fff; transform: translateY(-2px); }

    @media (max-width: 768px) {
        .hero-stats { gap: 30px; }
        .steps-grid { grid-template-columns: 1fr 1fr; }
        .cities-grid { grid-template-columns: 1fr; }
        .expand-cards { grid-template-columns: 1fr; }
    }
    @media (max-width: 480px) {
        .steps-grid { grid-template-columns: 1fr; }
        .country-header { padding: 16px 20px; }
        .country-body-inner { padding: 20px 16px; }
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
                Encontros <span>Presenciais</span><br>em todo o Brasil e além
            </h1>
            <p class="hero-subtitle">
                Pratique idiomas pessoalmente com pessoas reais, onde quer que você esteja.
                Junte-se a uma comunidade global em expansão. Sem horário fixo, sem burocracia: apenas conversação genuína e conexões para a vida toda.
            </p>
            <div class="hero-cta-row">
                <?php if ($totalGroups > 0): ?>
                    <a href="#localidades" class="btn-primary-red"><i class="fas fa-map-marked-alt"></i> Ver localidades</a>
                <?php endif; ?>
                <a href="#seja-organizador" class="btn-hero-outline">
                    <i class="fas fa-plus-circle"></i> Organizar na minha cidade
                </a>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="num"><?= $totalGroups ?>+</div>
                    <div class="lbl">Grupos ativos</div>
                </div>
                <div class="hero-stat">
                    <div class="num"><?= $totalCountries ?>+</div>
                    <div class="lbl">Países</div>
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
                Os encontros não têm data fixa. Acontecem conforme a comunidade local se mobiliza.
                Você não precisa esperar passivamente — faça o encontro acontecer!
            </p>
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-num">1</div>
                    <h3>Entre no grupo da sua cidade</h3>
                    <p>Encontre o grupo WhatsApp da sua cidade ou região e junte-se a quem já quer praticar idiomas pessoalmente.</p>
                </div>
                <div class="step-card">
                    <div class="step-num">2</div>
                    <h3>Movimente o grupo — convide!</h3>
                    <p>Convide amigos enquanto os outros membros fazem o mesmo. Quando a demanda crescer, o organizador anuncia data e local. Quanto mais gente convida, mais rápido o encontro acontece!</p>
                </div>
                <div class="step-card">
                    <div class="step-num">3</div>
                    <h3>Apareça e viva momentos inesquecíveis</h3>
                    <p>Vá ao encontro, apresente-se e pratique. Você vai sair com amizades reais, conversas incríveis e histórias que não esperava ter.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- LOCALIDADES COM ACCORDION -->
    <section class="cities-section" id="localidades">
        <div class="container">
            <p class="section-label">Onde estamos</p>
            <h2 class="section-title-lg">Localidades com grupos ativos</h2>
            <p class="section-desc">
                Estas são as localidades com grupos já formados ao redor do mundo.
                Encontre a sua região e faça parte da comunidade!
            </p>

            <?php if (empty($events)): ?>
                <div class="no-events">
                    <i class="fas fa-map-marked-alt"></i>
                    <p>Em breve teremos cidades aqui. Seja o primeiro a organizar na sua cidade!</p>
                </div>
            <?php else: ?>
                <?php $isFirst = true; foreach ($byCountry as $country => $countryEvents): ?>
                <div class="country-accordion <?= $isFirst ? 'open' : '' ?>">
                    <button class="country-header" type="button" onclick="toggleCountry(this)">
                        <span class="country-flag"><?= $countryFlag($country) ?></span>
                        <div class="country-info">
                            <div class="country-name"><?= htmlspecialchars($country) ?></div>
                            <div class="country-count"><?= count($countryEvents) ?> <?= count($countryEvents) === 1 ? 'localidade' : 'localidades' ?></div>
                        </div>
                        <i class="fas fa-chevron-down country-chevron"></i>
                    </button>
                    <div class="country-body">
                        <div class="country-body-inner">
                            <?php if ($country === 'Brasil'): ?>
                                <?php
                                $byRegion = [];
                                foreach ($countryEvents as $ev) {
                                    $r = !empty($ev['state']) ? getRegiao($ev['state']) : 'Outras Regiões';
                                    $byRegion[$r][] = $ev;
                                }
                                foreach ($regionOrder as $reg):
                                    if (empty($byRegion[$reg])) continue;
                                    $regSlug = strtolower(preg_replace('/[^a-z]/i','-',$reg));
                                ?>
                                <div class="region-accordion" id="reg-<?= $regSlug ?>">
                                    <button class="region-header" type="button" onclick="toggleRegion(this)">
                                        <i class="fas fa-map-signs" style="color:var(--accent-red);font-size:0.8rem;"></i>
                                        <span class="region-name"><?= $reg ?></span>
                                        <span class="region-count"><?= count($byRegion[$reg]) ?> <?= count($byRegion[$reg]) === 1 ? 'cidade' : 'cidades' ?></span>
                                        <i class="fas fa-chevron-down region-chevron"></i>
                                    </button>
                                    <div class="region-body">
                                        <div class="cities-grid">
                                            <?php foreach ($byRegion[$reg] as $ev): ?>
                                                <?php include __DIR__ . '/includes/presencial_card.php'; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="cities-grid">
                                    <?php foreach ($countryEvents as $ev): ?>
                                        <?php include __DIR__ . '/includes/presencial_card.php'; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php $isFirst = false; endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- EXPANDA -->
    <section class="expand-section" id="seja-organizador">
        <div class="container">
            <h2 class="expand-title">Não tem encontro na sua região?</h2>
            <p class="expand-desc">
                Você pode ser o primeiro! Basta criar o grupo local, convidar pessoas e, quando juntar uma turma, combinar um lugar e um dia. É isso — sem complicação.
            </p>
            <div class="expand-cards">
                <div class="expand-card">
                    <i class="fas fa-users"></i>
                    <h3>Crie o grupo e convide</h3>
                    <p>Abra um grupo WhatsApp para a sua cidade e comece convidando amigos e colegas que queiram praticar idiomas.</p>
                </div>
                <div class="expand-card">
                    <i class="fas fa-calendar-plus"></i>
                    <h3>Marque quando tiver turma</h3>
                    <p>Sem compromisso fixo. Quando sentir que há gente suficiente, combine um dia e pronto.</p>
                </div>
                <div class="expand-card">
                    <i class="fas fa-map-pin"></i>
                    <h3>Escolha o local</h3>
                    <p>A praça de alimentação do shopping é perfeita: espaço gratuito, sem consumação obrigatória. Café, parque ou biblioteca também funcionam!</p>
                </div>
            </div>
            <a href="contato.php" class="btn-expand">
                <i class="fas fa-hand-raised"></i> Quero organizar na minha região
            </a>
        </div>
    </section>

    <!-- HOST CTA -->
    <section class="host-cta-section" id="seja-host">
        <div class="container">
            <div class="host-cta-inner">
                <div class="host-cta-icon">
                    <i class="fas fa-globe" style="color:#3b82f6;"></i>
                    <i class="fas fa-comments" style="color:var(--accent-red);font-size:2rem;margin:0 8px;"></i>
                    <i class="fas fa-award" style="color:#f59e0b;"></i>
                </div>
                <h2>Quer ser um organizador?</h2>
                <p>
                    Organizar um encontro vai muito além de juntar pessoas para praticar idiomas.
                    É uma oportunidade real de desenvolver liderança, expandir sua rede e causar impacto na sua cidade.
                    Conheça os benefícios completos de quem faz parte da nossa equipe de voluntários.
                </p>
                <div class="host-cta-btns">
                    <a href="equipe.php?tab=presencial#seja-host" class="btn-primary-red">
                        <i class="fas fa-users"></i> Conhecer os benefícios
                    </a>
                    <a href="contato.php" class="btn-outline-red">
                        <i class="fas fa-envelope"></i> Falar com a equipe
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
$page_scripts = <<<JS
<script>
function toggleCountry(btn) {
    const acc = btn.closest('.country-accordion');
    acc.classList.toggle('open');
}
function toggleRegion(btn) {
    const acc = btn.closest('.region-accordion');
    acc.classList.toggle('open');
}
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', function(e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
});
</script>
JS;

include 'includes/footer.php';
?>
