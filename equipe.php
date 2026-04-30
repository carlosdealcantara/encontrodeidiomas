<?php
require_once 'config.php';

$title          = 'Nossa Equipe';
$current_page   = 'equipe.php';
$og_description = 'Conheça a equipe do Encontro de Idiomas - Anfitriões, desenvolvedores e criadores de conteúdo.';
$canonical      = 'https://encontrodeidiomas.com.br/equipe.php';

$hosts = getHosts();

ob_start();
?>
    .section-title { text-align:center; margin-bottom:1rem; font-weight:700; font-size:2.5rem; color:var(--primary-color); position:relative; padding-bottom:15px; }
    .section-title::after { content:''; position:absolute; bottom:0; left:50%; transform:translateX(-50%); width:100px; height:4px; background:linear-gradient(to right,var(--accent-red),var(--accent-blue)); border-radius:2px; }
    .section-description { text-align:center; max-width:800px; margin:0 auto 50px; font-size:1.1rem; color:#666; }
    .host-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:30px; margin-top:20px; }
    .host-card { background:var(--white); border-radius:var(--border-radius); overflow:hidden; box-shadow:var(--shadow); transition:var(--transition); position:relative; }
    .host-card:hover { transform:translateY(-10px); box-shadow:0 15px 35px rgba(0,0,0,.15); }
    .host-image { height:250px; width:100%; object-fit:cover; border-bottom:4px solid var(--accent-red); display:block; }
    .host-info { padding:20px; }
    .host-name { font-size:1.5rem; font-weight:700; margin-bottom:5px; color:var(--primary-color); }
    .host-languages,.host-role { display:flex; flex-wrap:wrap; gap:5px; margin-bottom:15px; }
    .language-tag,.role-tag { background:rgba(0,0,0,.05); padding:5px 10px; border-radius:20px; font-size:.8rem; font-weight:500; }
    .host-bio { margin-bottom:20px; font-size:.95rem; color:#666; }
    .host-contact { display:flex; justify-content:center; gap:10px; }
    .contact-btn { display:inline-flex; align-items:center; justify-content:center; width:40px; height:40px; border-radius:50%; text-decoration:none; background:#f0f2f5; color:var(--text-color); border:1px solid #ddd; transition:all .3s ease; }
    .contact-btn:hover { transform:translateY(-5px); box-shadow:0 5px 15px rgba(0,0,0,.1); }
    .contact-btn.whatsapp:hover { color:#25D366; }
    .contact-btn.email:hover { color:var(--accent-red); }
    .contact-btn.instagram:hover { color:#E1306C; }
    .host-badge { position:absolute; top:20px; right:20px; background:var(--accent-red); color:#fff; padding:5px 15px; border-radius:20px; font-size:.85rem; font-weight:600; }
    .cta-section { background:linear-gradient(135deg,var(--accent-blue),var(--primary-color)); padding:80px 0; margin:80px 0 0; text-align:center; color:#fff; }
    .cta-title { font-size:2.5rem; margin-bottom:20px; }
    .cta-description { max-width:700px; margin:0 auto 30px; font-size:1.1rem; opacity:.9; }
    .cta-button { display:inline-block; padding:15px 40px; background:var(--accent-red); color:#fff; text-decoration:none; font-weight:600; border-radius:50px; transition:all .3s ease; }
    .cta-button:hover { transform:translateY(-5px); }
    .page-wrapper { padding:60px 0; }
    @media (max-width:768px) { .host-grid { grid-template-columns:1fr; } .section-title { font-size:2rem; } }
<?php
$page_styles = ob_get_clean();

include 'includes/header.php';
?>

<main>
    <div class="container page-wrapper">
        <h1 class="section-title">Nossa Equipe</h1>
        <p class="section-description">Conheça as pessoas incríveis que tornam o Encontro de Idiomas possível!</p>

        <div class="host-grid">
            <?php foreach ($hosts as $host):
                $photo = !empty($host['photo']) ? 'assets/images/' . htmlspecialchars($host['photo']) : 'assets/images/HostSemFoto.png';
                $langs = !empty($host['languages']) ? explode(',', $host['languages']) : [];
                $roles = !empty($host['roles'])     ? explode(',', $host['roles'])     : [];
            ?>
            <div class="host-card">
                <?php if (!empty($host['badge'])): ?>
                <span class="host-badge"><?= htmlspecialchars($host['badge']) ?></span>
                <?php endif; ?>
                <img src="<?= $photo ?>" alt="Foto de <?= htmlspecialchars($host['full_name']) ?>" class="host-image"
                     onerror="this.src='assets/images/HostSemFoto.png'">
                <div class="host-info">
                    <h2 class="host-name"><?= htmlspecialchars($host['full_name']) ?></h2>
                    <?php if ($langs): ?>
                    <div class="host-languages">
                        <?php foreach ($langs as $l): ?>
                        <span class="language-tag"><?= htmlspecialchars(trim($l)) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($roles): ?>
                    <div class="host-role">
                        <?php foreach ($roles as $r): ?>
                        <span class="role-tag"><?= htmlspecialchars(trim($r)) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($host['bio'])): ?>
                    <p class="host-bio"><?= htmlspecialchars($host['bio']) ?></p>
                    <?php endif; ?>
                    <div class="host-contact">
                        <?php if (!empty($host['whatsapp'])): ?>
                        <a href="https://wa.me/<?= preg_replace('/\D/', '', $host['whatsapp']) ?>" target="_blank" class="contact-btn whatsapp" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($host['email'])): ?>
                        <a href="mailto:<?= htmlspecialchars($host['email']) ?>" class="contact-btn email" title="Email"><i class="fas fa-envelope"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($host['instagram'])): ?>
                        <a href="<?= htmlspecialchars($host['instagram']) ?>" target="_blank" class="contact-btn instagram" title="Instagram"><i class="fab fa-instagram"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Card "Torne-se um anfitrião" -->
            <div class="host-card" style="border:2px dashed #ddd;display:flex;align-items:center;justify-content:center;min-height:300px;">
                <div style="text-align:center;padding:30px;">
                    <i class="fas fa-user-plus" style="font-size:3rem;color:var(--accent-red);margin-bottom:15px;"></i>
                    <h3 style="font-size:1.3rem;margin-bottom:10px;">Torne-se um Anfitrião!</h3>
                    <p style="color:#666;margin-bottom:20px;">Quer fazer parte da nossa equipe? Entre em contato!</p>
                    <a href="contato.php" class="cta-button">Saiba Mais</a>
                </div>
            </div>
        </div>
    </div>

    <section class="cta-section">
        <div class="container">
            <h2 class="cta-title">Quer fazer parte?</h2>
            <p class="cta-description">Nossa comunidade cresce a cada dia. Seja um anfitrião e ajude pessoas a aprenderem idiomas!</p>
            <a href="contato.php" class="cta-button">Entre em Contato</a>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
