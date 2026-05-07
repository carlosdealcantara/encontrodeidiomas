<?php
require_once 'config.php';
require_once 'includes/components.php';

$title          = 'Equipe';
$current_page   = 'equipe.php';
$og_description = 'Conheça a equipe do Encontro de Idiomas - Anfitriões, desenvolvedores e criadores de conteúdo.';
$canonical      = 'https://encontrodeidiomas.com.br/equipe.php';

// Busca dados para os filtros
$hosts     = getHosts();
$languages = getLanguages();

// Parâmetros iniciais da URL
$initialTab      = $_GET['tab']     ?? 'online';
$initialLanguage = $_GET['idioma']  ?? 'all';
$initialRegion   = $_GET['regiao']  ?? 'all';
$initialRole     = $_GET['papel']   ?? 'all';

ob_start();
?>
    /* ---- EQUIPE PAGE STYLES ---- */
    .page-hero {
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
        background: linear-gradient(135deg, var(--accent-red) 0%, var(--accent-blue) 100%);
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

    /* Category Tabs */
    .category-tabs {
        display: flex;
        justify-content: center;
        margin-bottom: 40px;
        background: var(--white);
        border-radius: 50px;
        overflow: hidden;
        box-shadow: var(--shadow);
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
        padding: 5px;
    }

    .category-tab {
        flex: 1;
        padding: 12px 20px;
        border: none;
        background: none;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        border-radius: 40px;
        color: var(--text-color);
        font-family: inherit;
    }

    .category-tab.active {
        background: var(--accent-red);
        color: var(--white);
    }

    /* Filters */
    .filters-container {
        max-width: 500px;
        margin: 0 auto 40px;
    }

    .filter-group { display: none; }
    .filter-group.active { display: block; animation: fadeIn .3s ease; }

    .dropdown-wrapper { position: relative; }

    .dropdown-button {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 20px;
        background: var(--accent-red);
        color: var(--white);
        border: 2px solid var(--accent-red);
        border-radius: 25px;
        width: 100%;
        cursor: pointer;
        font-weight: 600;
        transition: var(--transition);
        font-family: inherit;
    }

    .dropdown-button:hover { 
        background: #c11817;
        border-color: #c11817;
    }

    .dropdown-content {
        display: none;
        position: absolute;
        background: var(--white);
        width: 100%;
        border-radius: 15px;
        box-shadow: 0 10px 25px rgba(0,0,0,.1);
        z-index: 100;
        margin-top: 5px;
        max-height: 300px;
        overflow-y: auto;
        left: 0;
    }

    .dropdown-content.show { display: block; }

    .dropdown-search-wrapper {
        padding: 10px 15px;
        position: sticky;
        top: 0;
        background: var(--white);
        z-index: 10;
        border-bottom: 1px solid #eee;
    }

    .dropdown-search-input {
        width: 100%;
        padding: 8px 30px 8px 15px;
        border: 1px solid #ddd;
        border-radius: 20px;
        font-family: inherit;
        font-size: 0.9rem;
        outline: none;
    }

    .dropdown-search-input:focus { border-color: var(--accent-red); }

    .dropdown-search-icon {
        position: absolute;
        right: 25px;
        top: 50%;
        transform: translateY(-50%);
        color: #999;
        pointer-events: none;
    }

    .dropdown-item {
        padding: 12px 20px;
        cursor: pointer;
        transition: background .2s;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .dropdown-item:hover { background: #f5f5f5; }

    /* Grid & Cards */
    .host-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:30px; }
    .host-card { background:var(--white); border-radius:var(--border-radius); overflow:hidden; box-shadow:var(--shadow); transition:var(--transition); position:relative; }
    .host-card:hover { transform:translateY(-10px); }
    
    .host-image-container { position: relative; height: 280px; overflow: hidden; }
    .host-image { width:100%; height:100%; object-fit:cover; border-bottom:4px solid var(--accent-red); transition: transform .5s; }
    .host-card:hover .host-image { transform: scale(1.05); }

    .host-info { padding:20px; }
    .host-name { font-size:1.4rem; font-weight:700; margin-bottom:10px; color:var(--primary-color); }
    
    .host-region { font-size: 0.85rem; color: #666; margin-bottom: 10px; display: flex; align-items: center; gap: 5px; }
    .host-region i { color: var(--accent-red); }

    .host-tags { display:flex; flex-wrap:wrap; gap:5px; margin-bottom:15px; }
    .tag { background:rgba(0,0,0,.05); padding:4px 10px; border-radius:20px; font-size:.75rem; font-weight:600; }
    
    .host-bio { margin-bottom:20px; font-size:.9rem; color:#555; line-height: 1.5; display: none; }
    .host-bio.active { display: block; }

    .host-contact { display:flex; justify-content:flex-start; gap:12px; margin-top: 10px; }
    .contact-btn { display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:50%; background:#f0f2f5; color:var(--text-color); transition:var(--transition); font-size: 0.9rem; }
    .contact-btn:hover { transform:translateY(-3px); background: var(--accent-red); color: white; }

    .cta-section { padding: 40px 0 60px; text-align: center; background: #f8f9fa; border-top: 1px solid #eee; margin-top: 60px; } 
    .cta-button-footer { margin-top: 20px; display:inline-block; padding:15px 40px; background:var(--accent-red); color:white; text-decoration:none; font-weight:700; border-radius:50px; }

    .host-badges-container { 
        position:absolute; 
        top:12px; 
        right:12px; 
        display:flex; 
        flex-direction:column; 
        gap:6px; 
        align-items:flex-end; 
        z-index: 10; 
    }
    .host-badge { 
        background:var(--accent-red); 
        color:#fff; 
        padding:5px 14px; 
        border-radius:20px; 
        font-size:.7rem; 
        font-weight:700; 
        box-shadow: 0 4px 12px rgba(227,29,28,.4); 
        text-transform: uppercase; 
        letter-spacing: 0.5px; 
        white-space: nowrap;
    }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    @media (max-width: 768px) {
        .category-tabs { flex-direction: column; border-radius: 15px; max-width: 90%; }
        .host-grid { grid-template-columns: 1fr; }
    }

    /* Benefits Section */
    .benefits-section { background:#f8f9fa; padding:80px 0; border-top:1px solid #eee; margin-top: 60px; }
    .benefits-grid { 
        display:grid; 
        grid-template-columns:repeat(3, 1fr); 
        gap:40px; 
        margin-top:50px; 
    }
    .benefit-item { text-align:center; padding:20px; transition: var(--transition); }
    .benefit-item:hover { transform: translateY(-10px); }
    .benefit-icon { font-size:3rem; color:var(--accent-red); margin-bottom:20px; display: inline-block; }
    .benefit-item h4 { font-size:1.3rem; margin-bottom:12px; color:var(--primary-color); font-weight: 700; }
    .benefit-item p { font-size:0.95rem; color:#666; line-height:1.5; }
    
    .cta-button-red { display:inline-block; padding:15px 40px; background:var(--accent-red); color:white; text-decoration:none; font-weight:700; border-radius:50px; transition: var(--transition); }
    .cta-button-red:hover { background: #c11817; transform: scale(1.05); box-shadow: 0 10px 20px rgba(193, 24, 23, 0.2); }
<?php
$page_styles = ob_get_clean();

include 'includes/header.php';
?>

<main>
    <section class="page-hero">
        <header class="hero-header">
            <div class="hero-image-wrapper">
                <img src="assets/images/hero_team.png" alt="Nossa Equipe" class="hero-image">
            </div>
            <h1>Equipe</h1>
            <p>Conheça as pessoas incríveis que <br> tornam o Encontro de Idiomas possível!</p>
        </header>
    </section>

    <div class="container page-wrapper" style="padding: 60px 0;">

        <!-- Navegação por Categorias -->
        <div class="category-tabs">
            <button class="category-tab <?= $initialTab === 'online' ? 'active' : '' ?>" data-tab="online">Online</button>
            <button class="category-tab <?= $initialTab === 'presencial' ? 'active' : '' ?>" data-tab="presencial">Presenciais</button>
            <button class="category-tab <?= $initialTab === 'tecnica' ? 'active' : '' ?>" data-tab="tecnica">Equipe Técnica</button>
        </div>

        <!-- Filtros Dinâmicos -->
        <div class="filters-container">
            <!-- Filtro Idioma (Online) -->
            <div id="filter-online" class="filter-group <?= $initialTab === 'online' ? 'active' : '' ?>">
                <div class="dropdown-wrapper">
                    <button class="dropdown-button" id="lang-btn">
                        <span><i class="fas fa-globe"></i> <span id="selected-lang-text">Todos os Idiomas</span></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-content" id="lang-dropdown">
                        <div class="dropdown-search-wrapper">
                            <input type="text" class="dropdown-search-input" placeholder="Buscar idioma...">
                            <i class="fas fa-search dropdown-search-icon"></i>
                        </div>
                        <div class="dropdown-item filterable-item" data-value="all">Todos os Idiomas</div>
                        <?php foreach ($languages as $lang): ?>
                            <div class="dropdown-item filterable-item" data-value="<?= strtolower($lang['name']) ?>">
                                <?php if (!empty($lang['flag_code'])): ?>
                                    <img src="https://flagcdn.com/20x15/<?= strtolower($lang['flag_code']) ?>.png" alt="">
                                <?php elseif (!empty($lang['flag_emoji'])): ?>
                                    <span class="flag-emoji"><?= $lang['flag_emoji'] ?></span>
                                <?php endif; ?>
                                <?= htmlspecialchars($lang['name']) ?>
                            </div>
                        <?php endforeach; ?>
                        <a href="#seja-host" class="dropdown-item dropdown-item-link" style="color:var(--accent-red); font-weight:600; justify-content:center; border-top:1px solid #eee;">Outros idiomas...</a>
                    </div>
                </div>
            </div>

            <!-- Filtro Cidade (Presencial) -->
            <div id="filter-presencial" class="filter-group <?= $initialTab === 'presencial' ? 'active' : '' ?>">
                <div class="dropdown-wrapper">
                    <button class="dropdown-button" id="region-btn">
                        <span><i class="fas fa-map-marker-alt"></i> <span id="selected-region-text">Todas as Cidades</span></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-content" id="region-dropdown">
                        <div class="dropdown-search-wrapper">
                            <input type="text" class="dropdown-search-input" placeholder="Buscar cidade...">
                            <i class="fas fa-search dropdown-search-icon"></i>
                        </div>
                        <div class="dropdown-item filterable-item" data-value="all">Todas as Cidades</div>
                        <!-- Itens dinâmicos serão inseridos aqui pelo JS -->
                        <a href="#seja-host" class="dropdown-item dropdown-item-link other-link" style="color:var(--accent-red); font-weight:600; justify-content:center; border-top:1px solid #eee;">Outras regiões...</a>
                    </div>
                </div>
            </div>

            <!-- Filtro Papel (Técnica) -->
            <div id="filter-tecnica" class="filter-group <?= $initialTab === 'tecnica' ? 'active' : '' ?>">
                <div class="dropdown-wrapper">
                    <button class="dropdown-button" id="role-btn">
                        <span><i class="fas fa-user-tag"></i> <span id="selected-role-text">Todos os Papéis</span></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-content" id="role-dropdown">
                        <div class="dropdown-search-wrapper">
                            <input type="text" class="dropdown-search-input" placeholder="Buscar área...">
                            <i class="fas fa-search dropdown-search-icon"></i>
                        </div>
                        <div class="dropdown-item filterable-item" data-value="all">Todos os Papéis</div>
                        <div class="dropdown-item filterable-item" data-value="desenvolvimento">Desenvolvimento</div>
                        <div class="dropdown-item filterable-item" data-value="design">Design</div>
                        <div class="dropdown-item filterable-item" data-value="conteudo">Conteúdo</div>
                        <a href="#seja-host" class="dropdown-item dropdown-item-link" style="color:var(--accent-red); font-weight:600; justify-content:center; border-top:1px solid #eee;">Outras áreas...</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="host-grid" id="hosts-grid">
            <?php foreach ($hosts as $host):
                renderHostCard($host);
            endforeach; ?>

            <!-- Card Especial: Torne-se um voluntário -->
            <div class="host-card special-card" id="become-host-card" style="border:2px dashed #ddd;display:flex;align-items:center;justify-content:center;min-height:400px;">
                <div style="text-align:center;padding:30px;">
                    <i class="fas fa-user-plus" style="font-size:3rem;color:var(--accent-red);margin-bottom:15px;"></i>
                    <h3 style="font-size:1.3rem;margin-bottom:10px;">Torne-se um Voluntário!</h3>
                    <p style="color:#666;margin-bottom:20px;">Quer fazer parte da nossa equipe? Conheça as vantagens!</p>
                    <a href="#seja-host" class="cta-button" style="display:inline-block; padding:10px 25px; background:var(--accent-red); color:white; text-decoration:none; border-radius:25px;">Ver Benefícios</a>
                </div>
            </div>
        </div>
    </div>

    <section class="benefits-section" id="seja-host">
        <div class="container">
            <h2 class="section-title">Por que ser um Voluntário?</h2>
            <p class="section-description">Muito mais do que voluntariado, uma oportunidade de crescimento real.</p>
            
            <div class="benefits-grid">
                <div class="benefit-item">
                    <div class="benefit-icon"><i class="fas fa-certificate"></i></div>
                    <h4>Certificado e Currículo</h4>
                    <p>Ganhe experiência prática comprovada e um certificado reconhecido para turbinar seu currículo e LinkedIn.</p>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon"><i class="fas fa-id-card"></i></div>
                    <h4>Vitrine no Site</h4>
                    <p>Seu perfil em destaque no site será seu espaço para divulgar suas redes, projetos e serviços para milhares de visitantes.</p>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon"><i class="fas fa-network-wired"></i></div>
                    <h4>Networking e Bastidores</h4>
                    <p>Conheça o projeto por dentro e faça conexões estratégicas com outros voluntários e profissionais.</p>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon"><i class="fas fa-gem"></i></div>
                    <h4>Comunidade VIP</h4>
                    <p>Acesso exclusivo ao nosso grupo de Membros da Equipe, com trocas de experiências e materiais exclusivos.</p>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon"><i class="fas fa-bullseye"></i></div>
                    <h4>Liderança e Mentoria</h4>
                    <p>Desenvolva habilidades de liderança e gestão de grupos com supervisão próxima e mentoria direta.</p>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon"><i class="fas fa-rocket"></i></div>
                    <h4>Suporte a Ideias</h4>
                    <p>Coloque suas próprias iniciativas em prática com nosso apoio total e suporte técnico para novos projetos.</p>
                </div>
            </div>

            <div style="text-align:center; margin-top:50px;">
                <a href="contato.php" class="cta-button-red">Quero me candidatar</a>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <div class="container">
            <h2 class="cta-title" style="margin-bottom:5px;">Ainda tem dúvidas?</h2>
            <p class="cta-description" style="margin-bottom:0;">Nossa equipe está pronta para te ajudar a dar os primeiros passos.</p>
            <a href="contato.php" class="cta-button-footer">Fale Conosco</a>
        </div>
    </section>
</main>

<?php
$page_scripts = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.category-tab');
    const filterGroups = document.querySelectorAll('.filter-group');
    const hostCards = document.querySelectorAll('.host-card:not(.special-card)');
    const becomeHostCard = document.getElementById('become-host-card');
    
    let currentTab = '{$initialTab}';
    let currentFilters = {
        online: 'all',
        presencial: 'all',
        tecnica: 'all'
    };

    // Inicialização das cidades no dropdown
    const regions = new Set();
    hostCards.forEach(card => {
        const reg = card.dataset.region;
        if (reg && reg !== 'all' && reg.trim() !== '') regions.add(reg);
    });
    const regionDropdown = document.getElementById('region-dropdown');
    regions.forEach(reg => {
        const cleanReg = reg.trim();
        if (!cleanReg || cleanReg.toLowerCase().includes('informado')) return;

        const item = document.createElement('div');
        item.className = 'dropdown-item';
        item.dataset.value = cleanReg;
        
        let displayText = cleanReg.split(' ').map(word => {
            if (word.length === 2 && !word.includes('-')) return word.toUpperCase();
            if (word.includes('-')) {
                return word.split('-').map(p => p.charAt(0).toUpperCase() + p.slice(1)).join(' - ');
            }
            return word.charAt(0).toUpperCase() + word.slice(1);
        }).join(' ');

        item.textContent = displayText;
        item.classList.add('filterable-item');

        const otherLink = regionDropdown.querySelector('.other-link');
        if (otherLink) {
            regionDropdown.insertBefore(item, otherLink);
        } else {
            regionDropdown.appendChild(item);
        }
    });

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentTab = this.dataset.tab;
            filterGroups.forEach(g => g.classList.remove('active'));
            const filterGroup = document.getElementById('filter-' + currentTab);
            if (filterGroup) filterGroup.classList.add('active');
            applyFilters();
            updateURL();
        });
    });

    document.addEventListener('click', function(e) {
        if (e.target.closest('.dropdown-button')) {
            const btn = e.target.closest('.dropdown-button');
            const content = btn.nextElementSibling;
            document.querySelectorAll('.dropdown-content').forEach(c => {
                if (c !== content) c.classList.remove('show');
            });
            content.classList.toggle('show');
            e.stopPropagation();
        } else if (e.target.classList.contains('dropdown-item') || e.target.closest('.dropdown-item')) {
            const item = e.target.classList.contains('dropdown-item') ? e.target : e.target.closest('.dropdown-item');
            if (item.classList.contains('dropdown-item-link')) return;
            const wrapper = item.closest('.filter-group');
            const type = wrapper.id.replace('filter-', '');
            currentFilters[type] = item.dataset.value;
            wrapper.querySelector('.dropdown-button span span').textContent = item.textContent.trim();
            document.querySelectorAll('.dropdown-content').forEach(c => c.classList.remove('show'));
            applyFilters();
            updateURL();
        } else {
            if (!e.target.closest('.dropdown-search-wrapper')) {
                document.querySelectorAll('.dropdown-content').forEach(c => c.classList.remove('show'));
            }
        }
    });

    document.querySelectorAll('.dropdown-search-input').forEach(input => {
        input.addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase().trim();
            const dropdown = e.target.closest('.dropdown-content');
            dropdown.querySelectorAll('.filterable-item').forEach(item => {
                item.style.display = item.textContent.toLowerCase().includes(term) ? 'flex' : 'none';
            });
        });
    });

    function applyFilters() {
        hostCards.forEach(card => {
            const cardCats = (card.dataset.categories || '').split(/[\s,]+/).map(s => s.trim().toLowerCase());
            const cardLangs = (card.dataset.languages || '').split(/[\s,]+/).map(s => s.trim().toLowerCase());
            const cardRegion = (card.dataset.region || '').trim().toLowerCase();
            const cardRoles = (card.dataset.roles || '').split(/[\s,]+/).map(s => s.trim().toLowerCase());

            let visible = cardCats.includes(currentTab.toLowerCase());
            if (visible) {
                if (currentTab === 'online' && currentFilters.online !== 'all') visible = cardLangs.includes(currentFilters.online.toLowerCase());
                else if (currentTab === 'presencial' && currentFilters.presencial !== 'all') visible = cardRegion === currentFilters.presencial.toLowerCase();
                else if (currentTab === 'tecnica' && currentFilters.tecnica !== 'all') visible = cardRoles.some(r => r.includes(currentFilters.tecnica.toLowerCase()));
            }

            card.style.display = visible ? 'block' : 'none';
            if (visible) {
                card.querySelectorAll('.context-online, .context-presencial, .context-tecnica').forEach(el => {
                    el.style.display = el.classList.contains('context-' + currentTab) ? '' : 'none';
                    if (el.tagName === 'P') el.classList.add('active');
                });
            } else {
                card.querySelectorAll('.host-bio').forEach(p => p.classList.remove('active'));
            }
        });
    }

    function updateURL() {
        const url = new URL(window.location);
        url.searchParams.set('tab', currentTab);
        const paramMap = { online: 'idioma', presencial: 'regiao', tecnica: 'papel' };
        Object.keys(paramMap).forEach(key => {
            if (key === currentTab && currentFilters[key] !== 'all') url.searchParams.set(paramMap[key], currentFilters[key]);
            else url.searchParams.delete(paramMap[key]);
        });
        window.history.pushState({}, '', url);
    }

    applyFilters();

    // Auto-scroll padronizado
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
        const tabsEl = document.querySelector('.category-tabs');
        if (tabsEl) {
            const offset = 100; // Restaurado para paridade com Links
            const targetY = tabsEl.getBoundingClientRect().top + window.pageYOffset - offset;
            smoothScrollTo(targetY, 1500);
        }
    }, 2000); 
});
</script>
JS;

include 'includes/footer.php';
?>
