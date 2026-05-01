<?php
require_once 'config.php';

$title          = 'Nossa Equipe';
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
    .section-title { text-align:center; margin-bottom:1rem; font-weight:700; font-size:2.5rem; color:var(--primary-color); position:relative; padding-bottom:15px; }
    .section-title::after { content:''; position:absolute; bottom:0; left:50%; transform:translateX(-50%); width:100px; height:4px; background:linear-gradient(to right,var(--accent-red),var(--accent-blue)); border-radius:2px; }
    .section-description { text-align:center; max-width:800px; margin:0 auto 30px; font-size:1.1rem; color:#666; }

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
        background: var(--white);
        border: 2px solid #eee;
        border-radius: 25px;
        width: 100%;
        cursor: pointer;
        font-weight: 600;
        transition: var(--transition);
        font-family: inherit;
    }

    .dropdown-button:hover { border-color: var(--accent-red); }

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

    .host-contact { display:flex; justify-content:center; gap:12px; border-top: 1px solid #eee; padding-top: 15px; }
    .contact-btn { display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:50%; background:#f0f2f5; color:var(--text-color); transition:var(--transition); }
    .contact-btn:hover { transform:translateY(-3px); background: var(--accent-red); color: white; }

    .host-badge { position:absolute; top:15px; right:15px; background:var(--accent-red); color:#fff; padding:4px 12px; border-radius:20px; font-size:.75rem; font-weight:700; z-index: 10; box-shadow: 0 4px 8px rgba(0,0,0,.2); }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    @media (max-width: 768px) {
        .category-tabs { flex-direction: column; border-radius: 15px; max-width: 90%; }
        .host-grid { grid-template-columns: 1fr; }
    }
<?php
$page_styles = ob_get_clean();

include 'includes/header.php';
?>

<main>
    <div class="container page-wrapper" style="padding: 60px 0;">
        <h1 class="section-title">Nossa Equipe</h1>
        <p class="section-description">Conheça as pessoas incríveis que tornam o Encontro de Idiomas possível!</p>

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
                        <div class="dropdown-item" data-value="all">Todos os Idiomas</div>
                        <?php foreach ($languages as $lang): ?>
                            <div class="dropdown-item" data-value="<?= strtolower($lang['name']) ?>">
                                <?php if (!empty($lang['flag_code'])): ?>
                                    <img src="https://flagcdn.com/20x15/<?= strtolower($lang['flag_code']) ?>.png" alt="">
                                <?php endif; ?>
                                <?= htmlspecialchars($lang['name']) ?>
                            </div>
                        <?php endforeach; ?>
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
                        <div class="dropdown-item" data-value="all">Todas as Cidades</div>
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
                        <div class="dropdown-item" data-value="all">Todos os Papéis</div>
                        <div class="dropdown-item" data-value="desenvolvimento">Desenvolvimento</div>
                        <div class="dropdown-item" data-value="design">Design</div>
                        <div class="dropdown-item" data-value="conteudo">Conteúdo</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="host-grid" id="hosts-grid">
            <?php foreach ($hosts as $host):
                // Mapeamento de colunas da produção (com fallback para colunas comuns)
                $photo = !empty($host['profile_picture']) ? 'assets/images/' . $host['profile_picture'] : 
                        (!empty($host['photo']) ? 'assets/images/' . $host['photo'] : 'assets/images/HostSemFoto.png');
                
                // Processamento de Categorias (Lógica idêntica ao dev)
                $rawCats = $host['category'] ?? $host['categories'] ?? '';
                $categories = array_map('trim', explode(',', $rawCats));
                
                // Adiciona 'tecnica' se status técnico estiver ativo
                if (!empty($host['technical_status']) && $host['technical_status'] === 'ativo') {
                    if (!in_array('Técnica', $categories) && !in_array('tecnica', $categories)) {
                        $categories[] = 'tecnica';
                    }
                }
                
                // Se estiver vazio, assume Online por padrão
                if (empty(array_filter($categories))) {
                    $categories[] = 'online';
                }
                
                $categoriesAttr = strtolower(implode(' ', $categories));
                $categoriesAttr = str_replace('técnica', 'tecnica', $categoriesAttr);
                
                $region     = $host['region'] ?? '';
                $langs      = !empty($host['languages']) ? array_map('trim', explode(',', $host['languages'])) : [];
                
                // Papéis técnicos
                $roles = [];
                if (!empty($host['technical_status']) && $host['technical_status'] === 'ativo' && !empty($host['technical_roles'])) {
                    $roles = array_map('trim', explode(',', $host['technical_roles']));
                } else if (!empty($host['role'])) {
                    $roles = array_map('trim', explode(',', $host['role']));
                } else if (!empty($host['roles'])) {
                    $roles = array_map('trim', explode(',', $host['roles']));
                }
                
                $skills = !empty($host['technical_skills']) ? array_map('trim', explode(',', $host['technical_skills'])) : [];
            ?>
            <div class="host-card" 
                 data-categories="<?= $categoriesAttr ?>" 
                 data-languages="<?= strtolower(implode(',', $langs)) ?>" 
                 data-region="<?= strtolower($region) ?>"
                 data-roles="<?= strtolower(implode(',', $roles)) ?>">
                
                <?php if (!empty($host['badge'])): ?>
                    <span class="host-badge"><?= htmlspecialchars($host['badge']) ?></span>
                <?php endif; ?>

                <div class="host-image-container">
                    <img src="<?= $photo ?>" alt="Foto de <?= htmlspecialchars($host['full_name']) ?>" class="host-image"
                         onerror="this.src='assets/images/HostSemFoto.png'">
                </div>

                <div class="host-info">
                    <h2 class="host-name"><?= htmlspecialchars($host['full_name']) ?></h2>
                    
                    <?php if ($region): ?>
                    <div class="host-region context-presencial">
                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($region) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Tags de Idiomas -->
                    <div class="host-tags context-online">
                        <?php foreach ($langs as $l): ?>
                            <span class="tag"><?= htmlspecialchars($l) ?></span>
                        <?php endforeach; ?>
                    </div>

                    <!-- Tags de Skills Técnicas -->
                    <div class="host-tags context-tecnica">
                        <?php foreach ($skills as $s): ?>
                            <span class="tag"><?= htmlspecialchars($s) ?></span>
                        <?php endforeach; ?>
                    </div>

                    <!-- Biografias Específicas -->
                    <p class="host-bio context-online"><?= htmlspecialchars($host['online_description'] ?? $host['bio'] ?? '') ?></p>
                    <p class="host-bio context-presencial"><?= htmlspecialchars($host['inperson_description'] ?? $host['bio'] ?? '') ?></p>
                    <p class="host-bio context-tecnica"><?= htmlspecialchars($host['bio'] ?? '') ?></p>

                    <div class="host-contact">
                        <?php if (!empty($host['whatsapp'])): ?>
                            <a href="https://wa.me/<?= preg_replace('/\D/', '', $host['whatsapp']) ?>" target="_blank" class="contact-btn" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($host['email'])): ?>
                            <a href="mailto:<?= htmlspecialchars($host['email']) ?>" class="contact-btn" title="Email"><i class="fas fa-envelope"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($host['instagram'])): ?>
                            <a href="<?= htmlspecialchars($host['instagram']) ?>" target="_blank" class="contact-btn" title="Instagram"><i class="fab fa-instagram"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Card Especial: Torne-se um anfitrião -->
            <div class="host-card special-card" id="become-host-card" style="border:2px dashed #ddd;display:flex;align-items:center;justify-content:center;min-height:400px;">
                <div style="text-align:center;padding:30px;">
                    <i class="fas fa-user-plus" style="font-size:3rem;color:var(--accent-red);margin-bottom:15px;"></i>
                    <h3 style="font-size:1.3rem;margin-bottom:10px;">Torne-se um Anfitrião!</h3>
                    <p style="color:#666;margin-bottom:20px;">Quer fazer parte da nossa equipe? Entre em contato!</p>
                    <a href="contato.php" class="cta-button" style="display:inline-block; padding:10px 25px; background:var(--accent-red); color:white; text-decoration:none; border-radius:25px;">Saiba Mais</a>
                </div>
            </div>
        </div>
    </div>

    <section class="cta-section">
        <div class="container">
            <h2 class="cta-title">Quer fazer parte?</h2>
            <p class="cta-description">Nossa comunidade cresce a cada dia. Seja um anfitrião e ajude pessoas a aprenderem idiomas!</p>
            <a href="contato.php" class="cta-button" style="display:inline-block; padding:15px 40px; background:var(--white); color:var(--accent-red); text-decoration:none; font-weight:700; border-radius:50px;">Entre em Contato</a>
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
        const item = document.createElement('div');
        item.className = 'dropdown-item';
        item.dataset.value = reg;
        item.textContent = reg.charAt(0).toUpperCase() + reg.slice(1);
        regionDropdown.appendChild(item);
    });

    // Alternância de Abas
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

    // Eventos de Dropdown (Global)
    document.addEventListener('click', function(e) {
        // Toggle dropdown
        if (e.target.closest('.dropdown-button')) {
            const btn = e.target.closest('.dropdown-button');
            const content = btn.nextElementSibling;
            
            document.querySelectorAll('.dropdown-content').forEach(c => {
                if (c !== content) c.classList.remove('show');
            });
            content.classList.toggle('show');
            e.stopPropagation();
        } 
        // Seleção de item
        else if (e.target.classList.contains('dropdown-item')) {
            const item = e.target;
            const wrapper = item.closest('.filter-group');
            const type = wrapper.id.replace('filter-', '');
            const value = item.dataset.value;
            const text = item.textContent;

            currentFilters[type] = value;
            wrapper.querySelector('.dropdown-button span span').textContent = text;
            
            document.querySelectorAll('.dropdown-content').forEach(c => c.classList.remove('show'));
            
            applyFilters();
            updateURL();
        }
        // Fechar ao clicar fora
        else {
            document.querySelectorAll('.dropdown-content').forEach(c => c.classList.remove('show'));
        }
    });

    function applyFilters() {
        console.log('Filtrando para aba:', currentTab);
        let visibleCount = 0;

        hostCards.forEach(card => {
            const cardCatsRaw = card.dataset.categories || '';
            // No dev a separação é por ESPAÇO, mas vamos garantir ambos
            const cardCats = cardCatsRaw.split(/[\s,]+/).map(s => s.trim().toLowerCase());
            
            const cardLangs = (card.dataset.languages || '').split(/[\s,]+/).map(s => s.trim().toLowerCase());
            const cardRegion = (card.dataset.region || '').trim().toLowerCase();
            const cardRoles = (card.dataset.roles || '').split(/[\s,]+/).map(s => s.trim().toLowerCase());

            let visible = cardCats.includes(currentTab.toLowerCase());

            if (visible) {
                if (currentTab === 'online' && currentFilters.online !== 'all') {
                    visible = cardLangs.includes(currentFilters.online.toLowerCase());
                } else if (currentTab === 'presencial' && currentFilters.presencial !== 'all') {
                    visible = cardRegion === currentFilters.presencial.toLowerCase();
                } else if (currentTab === 'tecnica' && currentFilters.tecnica !== 'all') {
                    visible = cardRoles.some(r => r.includes(currentFilters.tecnica.toLowerCase()));
                }
            }

            card.style.display = visible ? 'block' : 'none';
            if (visible) {
                visibleCount++;
                // Atualizar contextos internos
                card.querySelectorAll('.context-online, .context-presencial, .context-tecnica').forEach(el => {
                    el.style.display = el.classList.contains('context-' + currentTab) ? '' : 'none';
                    if (el.tagName === 'P') el.classList.add('active');
                });
            } else {
                card.querySelectorAll('.host-bio').forEach(p => p.classList.remove('active'));
            }
        });

        console.log('Membros visíveis:', visibleCount);
        becomeHostCard.style.display = 'flex';
    }

    function updateURL() {
        const url = new URL(window.location);
        url.searchParams.set('tab', currentTab);
        
        const paramMap = { online: 'idioma', presencial: 'regiao', tecnica: 'papel' };
        Object.keys(paramMap).forEach(key => {
            if (key === currentTab && currentFilters[key] !== 'all') {
                url.searchParams.set(paramMap[key], currentFilters[key]);
            } else {
                url.searchParams.delete(paramMap[key]);
            }
        });
        
        window.history.pushState({}, '', url);
    }

    // Inicialização
    applyFilters();
});
</script>
JS;

include 'includes/footer.php';
?>
