<?php
$title = "Nossa Equipe";

// Get all hosts
require_once 'config.php';
$hosts = getHosts();
$languages = getLanguages();

// Debug print for troubleshooting
// Uncomment these lines to check the database values directly
/*
echo "<pre style='background:#fff;position:fixed;top:0;left:0;z-index:9999;padding:20px;max-height:100vh;overflow:auto;'>";
echo "Languages from DB:<br>";
print_r($languages);
echo "<br><br>Hosts from DB:<br>";
print_r($hosts);
echo "</pre>";
*/

// Create a map of language IDs to names for easy lookup
$languageMap = [];
$languageMapInt = []; // Integer key map
foreach ($languages as $language) {
    $id = $language['id'];
    $languageMap[$id] = $language['name']; // Original key (likely string)
    $languageMapInt[(int)$id] = $language['name']; // Integer key
}

// Debug: Print language map
// echo "<pre>Language Map: " . print_r($languageMap, true) . "</pre>";
// echo "<pre>Int Language Map: " . print_r($languageMapInt, true) . "</pre>";

// Additional styles for this page
$page_styles = <<<EOT
.main-content {
    padding: 60px 0;
}

:root {
    --accent-purple: #6f42c1;
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

/* Hosts Grid */
.hosts-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.hosts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 30px;
    margin-bottom: 40px;
}

.host-card {
    position: relative;
    background: var(--white);
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--shadow);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    margin-bottom: 20px;
}

.host-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
}

.host-badge {
    position: absolute;
    top: 20px;
    right: 20px;
    background-color: var(--accent-red);
    color: var(--white);
    padding: 6px 16px;
    border-radius: 30px;
    font-size: 1rem;
    font-weight: 600;
    z-index: 10;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    letter-spacing: 0.5px;
}

.host-image {
    width: 100%;
    height: 250px;
    object-fit: cover;
}

.host-info {
    padding: 20px;
}

.host-name {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: var(--primary-color);
}

.host-languages {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin: 5px 0 15px 0;
}

.language-tag {
    display: inline-block;
    background-color: #f0f2f5;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.85rem;
    color: #333;
    font-weight: 500;
    margin-right: 5px;
    border: 1px solid rgba(0,0,0,0.05);
    transition: all 0.2s ease;
}

.language-tag:hover {
    background-color: #e6e9ec;
}

.host-bio {
    color: #666;
    margin-bottom: 20px;
    line-height: 1.6;
}

.host-contact {
    display: flex;
    gap: 10px;
}

.contact-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: #f0f2f5;
    color: var(--text-color);
    text-decoration: none;
    transition: all 0.3s ease;
}

.contact-btn:hover {
    transform: translateY(-3px);
    background-color: var(--accent-blue);
    color: var(--white);
}

.contact-btn.email:hover {
    background-color: #e31d1c;
}

.contact-btn.instagram:hover {
    background-color: #E1306C;
}

.contact-btn.linkedin:hover {
    background-color: #0077B5;
}

.contact-btn.whatsapp:hover {
    background-color: #25D366;
}

/* Filter controls - Hide the original filter */
.filter-controls {
    display: none;
}

/* Language Dropdown Styles */
.mobile-dropdown {
    display: flex;
    flex-direction: column;
    position: relative;
    width: 100%;
    max-width: 500px;
    margin: 0 auto 20px;
}

@media (min-width: 769px) {
    .mobile-dropdown {
        max-width: 400px;
    }
}

.dropdown-button {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background-color: var(--accent-red);
    color: white;
    border: none;
    border-radius: 25px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(227, 29, 28, 0.3);
    transition: all 0.3s;
}

.dropdown-button:hover {
    background-color: #c01a19;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(227, 29, 28, 0.4);
}

.dropdown-content {
    display: none;
    position: absolute;
    top: calc(100% + 10px);
    left: 0;
    width: 100%;
    background-color: white;
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    z-index: 100;
    max-height: 350px;
    overflow-y: auto;
    padding: 8px 0;
}

.dropdown-content.show {
    display: block;
    animation: fadeInDown 0.3s ease-out;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.search-filter {
    padding: 10px 15px;
    margin: 5px 15px 10px;
    position: relative;
}

.search-input {
    width: 100%;
    padding: 10px 15px 10px 35px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    background-color: #f5f5f7;
    transition: all 0.3s;
}

.search-input:focus {
    border-color: var(--accent-red);
    background-color: white;
    box-shadow: 0 0 0 2px rgba(227, 29, 28, 0.1);
    outline: none;
}

.search-icon {
    position: absolute;
    left: 25px;
    top: 50%;
    transform: translateY(-50%);
    color: #888;
    font-size: 14px;
}

.no-results {
    display: none;
    text-align: center;
    padding: 15px;
    color: #666;
    font-style: italic;
    font-size: 14px;
}

.dropdown-content .language-button {
    border-radius: 0;
    margin: 0;
    border: none;
    border-bottom: 1px solid #f0f0f0;
    width: 100%;
    text-align: left;
    padding: 12px 20px;
    transition: background 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    background-color: transparent;
}

.dropdown-content .language-button:hover {
    background-color: #f5f5f5;
    transform: none;
    box-shadow: none;
}

.dropdown-flag-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

#selected-language-flag {
    width: 24px;
    height: 18px;
    border-radius: 3px;
}

.language-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.language-badge {
    background-color: #f0f0f0;
    color: #555;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

/* Team section */
.team-section {
    margin-bottom: 60px;
}

.team-section h2 {
    text-align: center;
    margin-bottom: 15px;
    font-size: 1.8rem;
    color: var(--primary-color);
}

.team-description {
    text-align: center;
    max-width: 800px;
    margin: 0 auto 30px;
    color: #666;
}

/* No hosts message */
.no-hosts-message {
    text-align: center;
    margin: 50px auto;
    padding: 30px;
    background-color: #f5f5f7;
    border-radius: 10px;
    max-width: 500px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.no-hosts-message p {
    margin-bottom: 10px;
    color: #666;
}

.no-hosts-message a {
    color: var(--accent-red);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.no-hosts-message a:hover {
    text-decoration: underline;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .hosts-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    }
}

.prominent-languages {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 12px 0 15px 0;
}

.prominent-language-tag {
    display: inline-block;
    background-color: var(--accent-red);
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    box-shadow: 0 2px 5px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
}

.prominent-language-tag:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

/* Category tabs */
.category-tabs {
    display: flex;
    justify-content: center;
    margin-bottom: 30px;
    border-radius: 50px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.category-tab {
    flex: 1;
    padding: 15px 20px;
    background-color: #f5f5f7;
    border: none;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
    text-align: center;
    color: var(--text-color);
}

.category-tab:hover {
    background-color: #e8e8e8;
}

.category-tab.active {
    background-color: var(--accent-red);
    color: white;
}

@media (max-width: 600px) {
    .category-tabs {
        flex-direction: column;
        border-radius: 15px;
    }
    
    .category-tab {
        padding: 12px;
    }
}

/* Hosts Grid */
.host-region {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.host-region i {
    color: var(--accent-red);
    font-size: 0.85rem;
}

.dropdown-content .region-button,
.dropdown-content .role-button {
    border-radius: 0;
    margin: 0;
    border: none;
    border-bottom: 1px solid #f0f0f0;
    width: 100%;
    text-align: left;
    padding: 12px 20px;
    transition: background 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    background-color: transparent;
}

.dropdown-content .region-button:hover,
.dropdown-content .role-button:hover {
    background-color: #f5f5f5;
    transform: none;
    box-shadow: none;
}

.region-info,
.role-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.region-icon,
.role-icon {
    font-size: 1.2rem;
    width: 24px;
    height: 24px;
    display: inline-block;
    text-align: center;
}

/* Ensure all dropdowns are hidden by default */
.language-dropdown, 
.region-dropdown, 
.role-dropdown {
    display: none;
}

EOT;

include 'includes/header.php';
?>

<div class="main-content">
    <div class="container">
        <div class="page-title">
            <h1>Nossa Equipe</h1>
            <p>Conheça as pessoas que fazem o Encontro de Idiomas acontecer, desde nossos anfitriões até a equipe de desenvolvedores e criadores de conteúdo.</p>
        </div>
        
        <div class="team-section">
            <!-- Category filter tabs -->
            <div class="category-tabs">
                <button class="category-tab active" data-category="online">Anfitriões Online</button>
                <button class="category-tab" data-category="presencial">Presenciais</button>
                <button class="category-tab" data-category="tecnica">Equipe Técnica</button>
            </div>
            
            <!-- Original filter controls (hidden) -->
            <div class="filter-controls">
                <button class="filter-button active" data-filter="all">Todos</button>
                <?php foreach ($languages as $language): ?>
                    <button class="filter-button" data-filter="<?= strtolower($language['name']) ?>"><?= $language['name'] ?></button>
                <?php endforeach; ?>
            </div>
            
            <!-- Language Dropdown -->
            <div class="mobile-dropdown">
                <!-- Dropdown label changes based on category -->
                <p class="dropdown-label language-label" style="text-align: center; margin-bottom: 10px; font-size: 0.9rem; font-weight: 500;">Selecione um idioma:</p>
                <p class="dropdown-label region-label" style="text-align: center; margin-bottom: 10px; font-size: 0.9rem; font-weight: 500; display: none;">Selecione uma cidade:</p>
                <p class="dropdown-label role-label" style="text-align: center; margin-bottom: 10px; font-size: 0.9rem; font-weight: 500; display: none;">Selecione um papel:</p>
                
                <button class="dropdown-button">
                    <div class="dropdown-flag-container">
                        <span id="selected-language-flag" class="flag-icon" style="font-size: 1.2rem; width: 24px; height: 24px; display: inline-block; text-align: center; box-shadow: none;">🌍</span>
                        <span id="selected-option">Todos os idiomas</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </button>
                
                <!-- Language Dropdown Content -->
                <div class="dropdown-content language-dropdown">
                    <div class="search-filter">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input" id="language-search" placeholder="Buscar idioma...">
                    </div>
                    <div class="no-results" id="no-language-results">
                        Nenhum idioma encontrado.
                    </div>
                    <button class="language-button" data-language="all" data-normalized="all">
                        <div class="language-info">
                            <span class="flag-icon" style="font-size: 1.2rem; width: 24px; height: 24px; display: inline-block; text-align: center; box-shadow: none;">🌍</span>
                            <span>Todos os idiomas</span>
                        </div>
                    </button>
                    <?php foreach ($languages as $language): 
                        // Define flag URL or emoji based on language
                        $flagUrl = '';
                        $flagEmoji = '';
                        
                        // Map common languages to flags
                        switch(strtolower($language['name'])) {
                            case 'inglês':
                                $flagUrl = 'https://flagcdn.com/32x24/us.png';
                                break;
                            case 'espanhol':
                                $flagUrl = 'https://flagcdn.com/32x24/es.png';
                                break;
                            case 'francês':
                                $flagUrl = 'https://flagcdn.com/32x24/fr.png';
                                break;
                            case 'português':
                                $flagUrl = 'https://flagcdn.com/32x24/br.png';
                                break;
                            case 'alemão':
                                $flagUrl = 'https://flagcdn.com/32x24/de.png';
                                break;
                            case 'italiano':
                                $flagUrl = 'https://flagcdn.com/32x24/it.png';
                                break;
                            case 'coreano':
                                $flagUrl = 'https://flagcdn.com/32x24/kr.png';
                                break;
                            case 'chinês':
                                $flagUrl = 'https://flagcdn.com/32x24/cn.png';
                                break;
                            case 'russo':
                                $flagUrl = 'https://flagcdn.com/32x24/ru.png';
                                break;
                            case 'polonês':
                                $flagUrl = 'https://flagcdn.com/32x24/pl.png';
                                break;
                            case 'libras':
                                $flagEmoji = '👋';
                                break;
                            default:
                                $flagEmoji = '🚩';
                        }
                    ?>
                    <button class="language-button" data-language="<?= strtolower($language['name']) ?>" data-normalized="<?= strtolower(str_replace(['á','ã','â','é','ê','í','ó','ô','ú','ç','ñ'], ['a','a','a','e','e','i','o','o','u','c','n'], $language['name'])) ?>">
                        <div class="language-info">
                            <?php if (!empty($flagUrl)): ?>
                                <img src="<?= $flagUrl ?>" class="flag-icon" alt="<?= $language['name'] ?>">
                            <?php else: ?>
                                <span class="flag-icon" style="font-size: 1.2rem; width: 24px; height: 24px; display: inline-block; text-align: center; box-shadow: none;"><?= $flagEmoji ?></span>
                            <?php endif; ?>
                            <span><?= $language['name'] ?></span>
                        </div>
                    </button>
                    <?php endforeach; ?>
                    <button class="language-button" data-language="outros" data-normalized="outros">
                        <div class="language-info">
                            <span class="flag-icon" style="font-size: 1.2rem; width: 24px; height: 24px; display: inline-block; text-align: center; box-shadow: none;">🚩</span>
                            <span>Seu idioma aqui!</span>
                        </div>
                    </button>
                </div>
                
                <!-- Region Dropdown Content -->
                <div class="dropdown-content region-dropdown" style="display: none;">
                    <div class="search-filter">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input" id="region-search" placeholder="Buscar cidade...">
                    </div>
                    <div class="no-results" id="no-region-results">
                        Nenhuma cidade encontrada.
                    </div>
                    <button class="region-button" data-region="all">
                        <div class="region-info">
                            <span class="region-icon">🏙️</span>
                            <span>Todas as cidades</span>
                        </div>
                    </button>
                    
                    <!-- Get unique regions from hosts -->
                    <?php 
                    $regions = [];
                    foreach ($hosts as $host) {
                        if (!empty($host['region']) && !in_array($host['region'], $regions) && strpos($host['category'], 'Presencial') !== false) {
                            $regions[] = $host['region'];
                        }
                    }
                    sort($regions);
                    
                    // Add Brasília, São Paulo, and Other Regions if not already in the list
                    if (!in_array('Brasília - DF', $regions)) $regions[] = 'Brasília - DF';
                    if (!in_array('São Paulo - SP', $regions)) $regions[] = 'São Paulo - SP';
                    
                    foreach ($regions as $region): 
                        $icon = '🏙️';
                        if (strpos($region, 'Brasília') !== false) $icon = '🏛️';
                        elseif (strpos($region, 'São Paulo') !== false) $icon = '🏙️';
                    ?>
                    <button class="region-button" data-region="<?= strtolower(str_replace(' ', '-', $region)) ?>">
                        <div class="region-info">
                            <span class="region-icon"><?= $icon ?></span>
                            <span><?= $region ?></span>
                        </div>
                    </button>
                    <?php endforeach; ?>
                    
                    <button class="region-button" data-region="outras-regioes">
                        <div class="region-info">
                            <span class="region-icon">📍</span>
                            <span>Outras regiões</span>
                        </div>
                    </button>
                </div>
                
                <!-- Role Dropdown Content -->
                <div class="dropdown-content role-dropdown" style="display: none;">
                    <div class="search-filter">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input" id="role-search" placeholder="Buscar papel...">
                    </div>
                    <div class="no-results" id="no-role-results">
                        Nenhum papel encontrado.
                    </div>
                    <button class="role-button" data-role="all">
                        <div class="role-info">
                            <span class="role-icon">👥</span>
                            <span>Todos os papéis</span>
                        </div>
                    </button>
                    
                    <!-- Predefined roles -->
                    <button class="role-button" data-role="desenvolvimento">
                        <div class="role-info">
                            <span class="role-icon">💻</span>
                            <span>Desenvolvimento</span>
                        </div>
                    </button>
                    
                    <button class="role-button" data-role="design">
                        <div class="role-info">
                            <span class="role-icon">🎨</span>
                            <span>Design</span>
                        </div>
                    </button>
                    
                    <button class="role-button" data-role="fazer-parte">
                        <div class="role-info">
                            <span class="role-icon">✨</span>
                            <span>Faça parte!</span>
                        </div>
                    </button>
                </div>
            </div>
            
            <!-- TEST DROPDOWN - New implementation for debugging -->
            <div class="test-section" style="margin-top: 20px; padding: 15px; background: #f5f5f7; border-radius: 10px;">
                <p style="text-align: center; margin-bottom: 15px; font-weight: bold; color: #333;">Dropdown de Teste (Versão Melhorada)</p>
                
                <div class="mobile-dropdown test-mobile-dropdown">
                    <p class="dropdown-label" style="text-align: center; margin-bottom: 10px; font-size: 0.9rem; font-weight: 500;">Selecione uma opção:</p>
                    
                    <button id="test-dropdown-btn" class="dropdown-button">
                        <div class="dropdown-flag-container">
                            <span id="test-selected-icon" class="flag-icon" style="font-size: 1.2rem; width: 24px; height: 24px; display: inline-block; text-align: center; box-shadow: none;">🌍</span>
                            <span id="test-selected-text">Selecione uma opção</span>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    
                    <!-- Test dropdown content -->
                    <div id="test-dropdown-content" class="dropdown-content" style="display: block;">
                        <div class="search-filter">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="test-search" class="search-input" placeholder="Buscar...">
                        </div>
                        
                        <div class="no-results" id="test-no-results" style="display: none;">
                            Nenhuma opção encontrada.
                        </div>
                        
                        <button class="language-button test-option" data-value="option1">
                            <div class="language-info">
                                <span class="flag-icon" style="font-size: 1.2rem; width: 24px; height: 24px; display: inline-block; text-align: center; box-shadow: none;">🌍</span>
                                <span>Todas as opções</span>
                            </div>
                        </button>
                        
                        <button class="language-button test-option" data-value="option2">
                            <div class="language-info">
                                <span class="flag-icon" style="font-size: 1.2rem; width: 24px; height: 24px; display: inline-block; text-align: center; box-shadow: none;">🏙️</span>
                                <span>São Paulo</span>
                            </div>
                        </button>
                        
                        <button class="language-button test-option" data-value="option3">
                            <div class="language-info">
                                <span class="flag-icon" style="font-size: 1.2rem; width: 24px; height: 24px; display: inline-block; text-align: center; box-shadow: none;">🏛️</span>
                                <span>Brasília</span>
                            </div>
                        </button>
                        
                        <button class="language-button test-option" data-value="option4">
                            <div class="language-info">
                                <span class="flag-icon" style="font-size: 1.2rem; width: 24px; height: 24px; display: inline-block; text-align: center; box-shadow: none;">📍</span>
                                <span>Outras regiões</span>
                            </div>
                        </button>
                        
                        <?php
                        // Add actual options from the database for testing
                        foreach ($languages as $language): 
                            // Map common languages to flags/emojis as in the original dropdown
                            $flagEmoji = '🚩';
                            
                            switch(strtolower($language['name'])) {
                                case 'inglês': $flagEmoji = '🇺🇸'; break;
                                case 'espanhol': $flagEmoji = '🇪🇸'; break;
                                case 'francês': $flagEmoji = '🇫🇷'; break;
                                case 'português': $flagEmoji = '🇧🇷'; break;
                                case 'alemão': $flagEmoji = '🇩🇪'; break;
                                case 'italiano': $flagEmoji = '🇮🇹'; break;
                                case 'coreano': $flagEmoji = '🇰🇷'; break;
                                case 'chinês': $flagEmoji = '🇨🇳'; break;
                                case 'russo': $flagEmoji = '🇷🇺'; break;
                                case 'polonês': $flagEmoji = '🇵🇱'; break;
                                case 'libras': $flagEmoji = '👋'; break;
                            }
                        ?>
                        <button class="language-button test-option" data-value="<?= strtolower($language['name']) ?>" data-normalized="<?= strtolower(str_replace(['á','ã','â','é','ê','í','ó','ô','ú','ç','ñ'], ['a','a','a','e','e','i','o','o','u','c','n'], $language['name'])) ?>">
                            <div class="language-info">
                                <span class="flag-icon" style="font-size: 1.2rem; width: 24px; height: 24px; display: inline-block; text-align: center; box-shadow: none;"><?= $flagEmoji ?></span>
                                <span><?= $language['name'] ?></span>
                            </div>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="hosts-container">
                <div class="hosts-grid">
                    <!-- Become a host card -->
                    <div class="host-card" data-languages="outros" data-categories="online presencial tecnica">
                        <div class="host-badge" style="background-color: var(--accent-purple);">Seja Anfitrião</div>
                        <img src="assets/images/MaisIdiomasCidades.png" alt="Novo idioma" class="host-image">
                        <div class="host-info">
                            <h3 class="host-name">Torne-se anfitrião!</h3>
                            <div class="host-languages">
                                <span class="language-tag">Qualquer idioma</span>
                                <span class="language-tag">Voluntário</span>
                            </div>
                            <p class="host-bio">
                                Gostaria de praticar ou ensinar um idioma? Seja para um idioma já disponível ou para um novo, você pode se tornar anfitrião voluntário! Compartilhe seu conhecimento e ajude a expandir nossa comunidade de aprendizado.
                            </p>
                            <div class="host-contact">
                                <a href="contato.php" class="contact-btn email" title="Entre em contato">
                                    <i class="fas fa-envelope"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <?php foreach ($hosts as $host): ?>
                        <?php 
                        // Process languages in hosts' data
                        $hostLanguages = [];
                        
                        if (!empty($host['languages'])) {
                            // In this case, languages are stored as text names separated by commas
                            $languageNames = explode(',', $host['languages']);
                            
                            foreach ($languageNames as $langName) {
                                $langName = trim($langName);
                                if (!empty($langName)) {
                                    $hostLanguages[] = $langName;
                                }
                            }
                        }
                        
                        // Get first language for badge
                        $primaryLanguage = !empty($hostLanguages) ? $hostLanguages[0] : '';
                        $badgeText = $primaryLanguage;
                        
                        // Use special badge from database if available
                        if (!empty($host['special_badge'])) {
                            $badgeText = $host['special_badge'];
                        }
                        
                        // Get badge color based on language
                        $badgeColor = 'var(--accent-red)'; // Default red color
                        
                        if (!empty($primaryLanguage)) {
                            switch(strtolower($primaryLanguage)) {
                                case 'inglês':
                                    $badgeColor = 'var(--accent-red)';
                                    break;
                                case 'espanhol':
                                    $badgeColor = 'var(--accent-red)';
                                    break;
                                case 'francês':
                                    $badgeColor = '#0055a4'; // French flag blue
                                    break;  
                                case 'alemão':
                                    $badgeColor = '#dd0000'; // German flag red
                                    break;
                                case 'italiano':
                                    $badgeColor = '#009246'; // Italian flag green
                                    break;
                                case 'libras':
                                    $badgeColor = '#009c3b'; // Brazilian flag green
                                    break;  
                                case 'português':
                                    $badgeColor = '#009c3b'; // Brazilian flag green
                                    break;
                                case 'russo':
                                    $badgeColor = '#0039a6'; // Russian flag blue
                                    break;
                                case 'polonês':
                                    $badgeColor = '#dc143c'; // Polish flag red
                                    break;
                                case 'coreano':
                                    $badgeColor = '#003478'; // Korean flag blue
                                    break;
                                case 'chinês':
                                    $badgeColor = '#de2910'; // Chinese flag red
                                    break;
                                default:
                                    $badgeColor = 'var(--accent-red)';
                            }
                        }
                        
                        // Get social media links
                        $socialLinks = !empty($host['social_media_links']) ? json_decode($host['social_media_links'], true) : [];
                        
                        // Process categories for filtering
                        $categories = [];
                        if (!empty($host['category'])) {
                            $categories = array_map('trim', explode(',', $host['category']));
                        }
                        
                        // Default to "Online" if no category is set
                        if (empty($categories)) {
                            $categories[] = 'Online';
                        }
                        
                        // Create category data attribute
                        $categoriesAttr = strtolower(implode(' ', $categories));
                        
                        // Process roles for filtering
                        $roles = [];
                        if (!empty($host['role'])) {
                            $roles = array_map('trim', explode(',', $host['role']));
                        }
                        
                        // Create role data attribute
                        $rolesAttr = strtolower(implode(' ', $roles));
                        
                        // Process region for filtering
                        $regionAttr = !empty($host['region']) ? strtolower(str_replace(' ', '-', $host['region'])) : '';
                        
                        // Prepare language data string for filtering
                        // Convert all language names to lowercase for consistent matching
                        $languageNamesFormatted = array_map('mb_strtolower', $hostLanguages);
                        $languagesDataAttr = implode(' ', $languageNamesFormatted);
                        
                        // Debug: track language data for filtering
                        $debugLog = "<!-- Host: {$host['full_name']} | Categories: " . implode(',', $categories) . " | ";
                        $debugLog .= "Region: {$host['region']} | Roles: " . implode(',', $roles) . " | ";
                        $debugLog .= "Raw Languages: {$host['languages']} | ";
                        $debugLog .= "Processed: " . implode(',', $hostLanguages) . " | ";
                        $debugLog .= "Data Attribute: $languagesDataAttr -->";
                        echo $debugLog;
                        ?>
                        
                        <div class="host-card" 
                             data-languages="<?= $languagesDataAttr ?>" 
                             data-categories="<?= $categoriesAttr ?>"
                             data-region="<?= $regionAttr ?>"
                             data-roles="<?= $rolesAttr ?>"
                        >
                            <?php if (!empty($primaryLanguage)): ?>
                                <div class="host-badge" style="background-color: <?= $badgeColor ?>;"><?= $badgeText ?></div>
                            <?php endif; ?>
                            
                            <img src="<?= !empty($host['profile_picture']) ? (strpos($host['profile_picture'], 'assets/') === 0 ? $host['profile_picture'] : 'assets/images/' . $host['profile_picture']) : 'assets/images/HostSemFoto.png' ?>" 
                                 alt="<?= $host['full_name'] ?>" class="host-image">
                                 
                            <div class="host-info">
                                <h3 class="host-name"><?= $host['full_name'] ?></h3>
                                
                                <?php if (!empty($host['region'])): ?>
                                <div class="host-region">
                                    <i class="fas fa-map-marker-alt"></i> <?= $host['region'] ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="host-languages">
                                    <?php 
                                    if (empty($hostLanguages)) {
                                        echo "<span class='language-tag'>Português</span>"; // Default if no languages found
                                    } else {
                                        foreach ($hostLanguages as $lang): ?>
                                        <span class="language-tag"><?= $lang ?></span>
                                        <?php endforeach;
                                    }
                                    ?>
                                </div>
                                
                                <p class="host-bio">
                                    <?= $host['description'] ?>
                                </p>
                                
                                <div class="host-contact">
                                    <?php if (!empty($socialLinks['email'])): ?>
                                        <a href="mailto:<?= $socialLinks['email'] ?>" class="contact-btn email" title="Email">
                                            <i class="fas fa-envelope"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($socialLinks['instagram'])): ?>
                                        <a href="<?= $socialLinks['instagram'] ?>" target="_blank" class="contact-btn instagram" title="Instagram">
                                            <i class="fab fa-instagram"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($socialLinks['linkedin'])): ?>
                                        <a href="<?= $socialLinks['linkedin'] ?>" target="_blank" class="contact-btn linkedin" title="LinkedIn">
                                            <i class="fab fa-linkedin"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($socialLinks['whatsapp'])): ?>
                                        <a href="https://wa.me/<?= $socialLinks['whatsapp'] ?>" target="_blank" class="contact-btn whatsapp" title="WhatsApp">
                                            <i class="fab fa-whatsapp"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Debugging - Check data attributes on all host cards
    console.log("Host language data:");
    document.querySelectorAll('.host-card').forEach(card => {
        const hostName = card.querySelector('.host-name').textContent;
        const languages = card.getAttribute('data-languages');
        const categories = card.getAttribute('data-categories');
        const badge = card.querySelector('.host-badge') ? card.querySelector('.host-badge').textContent : 'No badge';
        console.log(`Host: ${hostName}, Badge: ${badge}, Categories: ${categories}, Languages data-attr: ${languages}`);
        
        // Log visible language tags
        const languageTags = Array.from(card.querySelectorAll('.language-tag')).map(tag => tag.textContent);
        console.log(`  Language tags: ${languageTags.join(', ')}`);
    });
    
    // Category tabs functionality
    const categoryTabs = document.querySelectorAll('.category-tab');
    let currentCategory = 'online'; // Default category
    
    categoryTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            categoryTabs.forEach(t => t.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Get category value
            currentCategory = this.getAttribute('data-category');
            
            // Show the appropriate dropdown based on category
            updateDropdownForCategory(currentCategory);
            
            // Filter hosts by category
            filterHostsByCategory(currentCategory);
            
            // Update URL with category parameter
            updateURL();
            
            // Ensure all dropdowns are closed when switching tabs
            document.querySelectorAll('.dropdown-content').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        });
    });
    
    // Function to show the appropriate dropdown based on category
    function updateDropdownForCategory(category) {
        // Hide all dropdown content and labels
        document.querySelectorAll('.dropdown-content').forEach(dropdown => {
            dropdown.style.display = 'none';
            dropdown.classList.remove('show'); // Make sure dropdowns are closed
        });
        
        document.querySelectorAll('.dropdown-label').forEach(label => {
            label.style.display = 'none';
        });
        
        // Reset selected option text
        const selectedOption = document.getElementById('selected-option');
        const selectedIcon = document.getElementById('selected-language-flag');
        
        // Show the appropriate dropdown based on category
        if (category === 'online') {
            // Show language dropdown container but keep it closed
            document.querySelector('.language-dropdown').style.display = 'block';
            document.querySelector('.language-label').style.display = 'block';
            selectedOption.textContent = 'Todos os idiomas';
            selectedIcon.textContent = '🌍';
        } else if (category === 'presencial') {
            // Show region dropdown container but keep it closed
            document.querySelector('.region-dropdown').style.display = 'block';
            document.querySelector('.region-label').style.display = 'block';
            selectedOption.textContent = 'Todas as cidades';
            selectedIcon.textContent = '🏙️';
        } else if (category === 'tecnica') {
            // Show role dropdown container but keep it closed
            document.querySelector('.role-dropdown').style.display = 'block';
            document.querySelector('.role-label').style.display = 'block';
            selectedOption.textContent = 'Todos os papéis';
            selectedIcon.textContent = '👥';
        }
    }
    
    // Function to filter hosts by category
    function filterHostsByCategory(category) {
        console.log(`Filtering hosts by category: ${category}`);
        let visibleCount = 0;
        
    const hostCards = document.querySelectorAll('.host-card');
        hostCards.forEach(card => {
            const categories = card.getAttribute('data-categories');
            const hostName = card.querySelector('.host-name').textContent;
            
            // Check if host belongs to selected category
            const isMatch = categories && categories.includes(category.toLowerCase());
            console.log(`Host: ${hostName}, Categories: ${categories}, Filter: ${category}, Match: ${isMatch}`);
            
            if (isMatch) {
                card.style.display = 'block';
                card.classList.add('category-visible');
                visibleCount++;
            } else {
                card.style.display = 'none';
                card.classList.remove('category-visible');
            }
        });
        
        console.log(`Found ${visibleCount} visible cards after filtering by category: ${category}`);
        
        // Show message if no hosts match the selected category
        if (visibleCount === 0) {
            let noResults = document.querySelector('.no-hosts-category');
            if (!noResults) {
                noResults = document.createElement('div');
                noResults.className = 'no-hosts-message no-hosts-category';
                noResults.innerHTML = `
                    <p>Não há anfitriões nesta categoria ainda.</p>
                    <p>Que tal <a href="contato.php">se tornar um?</a></p>
                `;
                
                const hostGrid = document.querySelector('.hosts-grid');
                if (hostGrid) {
                    hostGrid.prepend(noResults);
                }
            }
        } else {
            // Remove no results message if it exists
            const noResults = document.querySelector('.no-hosts-category');
            if (noResults) {
                noResults.remove();
            }
        }
    }
    
    // Original filter functionality (keep for compatibility)
    const filterButtons = document.querySelectorAll('.filter-button');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            filterButtons.forEach(btn => btn.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Get filter value
            const filter = this.getAttribute('data-filter');
            
            // Show/hide host cards based on filter
            filterHostsByLanguage(filter);
        });
    });
    
    // Dropdown functionality
    const dropdownButton = document.querySelector('.dropdown-button');
    const languageButtons = document.querySelectorAll('.language-button');
    const selectedOption = document.getElementById('selected-option');
    const selectedLanguageFlag = document.getElementById('selected-language-flag');
    const searchInput = document.getElementById('language-search');
    const noLanguageResults = document.getElementById('no-language-results');
    
    // Toggle dropdown when button is clicked
    dropdownButton.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Get the currently visible dropdown based on category
        let visibleDropdown;
        if (currentCategory === 'online') {
            visibleDropdown = document.querySelector('.language-dropdown');
        } else if (currentCategory === 'presencial') {
            visibleDropdown = document.querySelector('.region-dropdown');
        } else if (currentCategory === 'tecnica') {
            visibleDropdown = document.querySelector('.role-dropdown');
        }
        
        if (visibleDropdown) {
            // Toggle the current dropdown
            const isCurrentlyOpen = visibleDropdown.classList.contains('show');
            
            // Close all dropdowns first
            document.querySelectorAll('.dropdown-content').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
            
            // If it wasn't open before, open it now
            if (!isCurrentlyOpen) {
                visibleDropdown.classList.add('show');
                
                // Focus search input if dropdown is shown
                const searchInput = visibleDropdown.querySelector('.search-input');
                if (searchInput) {
                    setTimeout(() => {
                        searchInput.focus();
                    }, 100);
                }
            }
            // If it was open, it's now closed (no need to do anything else)
        }
    });
    
    // Close dropdown when clicking outside
    window.addEventListener('click', function(event) {
        if (!event.target.closest('.mobile-dropdown')) {
            document.querySelectorAll('.dropdown-content').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        }
    });
    
    // Improved search functionality for language dropdown with accent-insensitive search
    document.getElementById('language-search').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        let resultsFound = false;
        
        languageButtons.forEach(button => {
            const languageName = button.querySelector('span:not(.flag-icon)').textContent
                                .toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            if (languageName.includes(searchTerm)) {
                button.style.display = 'flex';
                resultsFound = true;
            } else {
                button.style.display = 'none';
            }
        });
        
        // Show/hide no results message
        if (resultsFound) {
            noLanguageResults.style.display = 'none';
        } else {
            noLanguageResults.style.display = 'block';
        }
    });
    
    // Improved search functionality for region dropdown with accent-insensitive search
    document.getElementById('region-search').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        let resultsFound = false;
        
        document.querySelectorAll('.region-button').forEach(button => {
            const regionName = button.querySelector('span:not(.region-icon)').textContent
                              .toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            if (regionName.includes(searchTerm)) {
                button.style.display = 'flex';
                resultsFound = true;
            } else {
                button.style.display = 'none';
            }
        });
        
        // Show/hide no results message
        if (resultsFound) {
            document.getElementById('no-region-results').style.display = 'none';
        } else {
            document.getElementById('no-region-results').style.display = 'block';
        }
    });
    
    // Improved search functionality for role dropdown with accent-insensitive search
    document.getElementById('role-search').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        let resultsFound = false;
        
        document.querySelectorAll('.role-button').forEach(button => {
            const roleName = button.querySelector('span:not(.role-icon)').textContent
                            .toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            if (roleName.includes(searchTerm)) {
                button.style.display = 'flex';
                resultsFound = true;
            } else {
                button.style.display = 'none';
            }
        });
        
        // Show/hide no results message
        if (resultsFound) {
            document.getElementById('no-role-results').style.display = 'none';
        } else {
            document.getElementById('no-role-results').style.display = 'block';
        }
    });
    
    // Language selection
    languageButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const language = this.getAttribute('data-language');
            const normalizedLang = this.getAttribute('data-normalized') || language; // Get normalized version if available
            const languageText = this.querySelector('span:not(.flag-icon)').textContent;
            
            // Update selected language text
            selectedOption.textContent = languageText;
            
            // Update selected language flag
            const flagElement = this.querySelector('.flag-icon');
            if (flagElement.tagName === 'IMG') {
                // If it's an image flag
                selectedLanguageFlag.innerHTML = '';
                const newFlag = document.createElement('img');
                newFlag.src = flagElement.src;
                newFlag.alt = flagElement.alt;
                newFlag.className = 'flag-icon';
                newFlag.style.width = '24px';
                newFlag.style.height = '18px';
                newFlag.style.borderRadius = '3px';
                selectedLanguageFlag.appendChild(newFlag);
            } else {
                // If it's an emoji flag
                selectedLanguageFlag.innerHTML = flagElement.innerHTML;
            }
            
            // Close dropdown
            document.querySelectorAll('.dropdown-content').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
            
            // Log for debugging
            console.log(`Selected language: ${language}`);
            console.log(`Normalized language: ${normalizedLang}`);
            console.log(`Available host languages: ${Array.from(document.querySelectorAll('.host-card')).map(card => card.getAttribute('data-languages')).join(', ')}`);
            
            // Filter hosts
            filterHostsByLanguage(language, normalizedLang);
            
            // Update the original filter buttons UI (for compatibility)
            document.querySelectorAll('.filter-button').forEach(btn => {
                btn.classList.remove('active');
                if (btn.getAttribute('data-filter') === language) {
                    btn.classList.add('active');
                }
            });
            
            // Update URL with language param while maintaining category param
            const urlParams = new URLSearchParams(window.location.search);
            if (language === 'all') {
                urlParams.delete('idioma');
            } else {
                urlParams.set('idioma', language);
            }
            
            // Maintain category parameter if present
            if (currentCategory !== 'online') {
                urlParams.set('categoria', currentCategory);
            }
            
            history.replaceState(null, '', urlParams.toString() ? `?${urlParams.toString()}` : window.location.pathname);
        });
    });
    
    // Function to update URL with current filters
    function updateURL() {
        const urlParams = new URLSearchParams(window.location.search);
        
        // Update category parameter
        if (currentCategory !== 'online') { // Only set if not default
            urlParams.set('categoria', currentCategory);
        } else {
            urlParams.delete('categoria');
        }
        
        // Keep language parameter if it exists
        const currentLang = urlParams.get('idioma');
        if (!currentLang) {
            urlParams.delete('idioma');
        }
        
        // Update URL without reloading page
        const newUrl = urlParams.toString() ? `?${urlParams.toString()}` : window.location.pathname;
        history.replaceState(null, '', newUrl);
    }
    
    // Function to filter hosts by language
    function filterHostsByLanguage(language, normalizedLang) {
        console.log(`Filtering hosts by language: ${language} (normalized: ${normalizedLang})`);
        let visibleCount = 0;
        
        // First, check what languages are available
        const availableLanguages = new Set();
        const hostCards = document.querySelectorAll('.host-card');
            hostCards.forEach(card => {
            const cardLanguages = card.getAttribute('data-languages');
            if (cardLanguages) {
                cardLanguages.split(' ').forEach(lang => {
                    if (lang) availableLanguages.add(lang);
                });
            }
        });
        console.log(`Available languages for filtering: ${[...availableLanguages].join(', ')}`);
        
        // Fix for special characters in language names
        const normalizeString = (str) => {
            if (!str) return '';
            return str.toLowerCase()
                .normalize("NFD").replace(/[\u0300-\u036f]/g, ""); // Remove accents
        };
        
        // Use the provided normalized language for more accurate filtering
        const filterToUse = normalizedLang || language;
        const normalizedFilter = normalizeString(filterToUse);
        console.log(`Normalized filter: ${normalizedFilter}`);
        
        hostCards.forEach(card => {
            const cardLanguages = card.getAttribute('data-languages');
            const cardCategories = card.getAttribute('data-categories') || '';
            const hostName = card.querySelector('.host-name').textContent;
            
            // Make sure the card belongs to the "online" category
            const isOnlineCategory = cardCategories.includes('online');
            
            // Special handling for the "become host" card - always show it
            const isSpecialCard = hostName.includes('Torne-se anfitrião');
            
            let isMatch = (language === 'all');
            
            // Enhanced language matching that handles accents and special characters
            if (!isMatch && cardLanguages) {
                const normalizedCardLanguages = normalizeString(cardLanguages);
                console.log(`Host: ${hostName}, Normalized languages: ${normalizedCardLanguages}`);
                
                // Try exact match first (more reliable)
                if (normalizedCardLanguages.split(' ').includes(normalizedFilter)) {
                    isMatch = true;
                } 
                // Then try substring match (as fallback)
                else if (normalizedCardLanguages.includes(normalizedFilter)) {
                    isMatch = true;
                }
            }
            
            console.log(`Host: ${hostName}, Category: ${cardCategories}, Raw Languages: ${cardLanguages}, Filter: ${language}, Match: ${isMatch}, Online: ${isOnlineCategory}`);
            
            // Only show if card matches both the language filter and is in the "online" category (or is the special card)
            if ((isMatch && isOnlineCategory) || isSpecialCard) {
                    card.style.display = 'block';
                card.classList.add('visible');
                visibleCount++;
                    } else {
                        card.style.display = 'none';
                card.classList.remove('visible');
            }
        });
        
        console.log(`Found ${visibleCount} visible cards after filtering by language: ${language}`);
        
        // Verificar se existe algum card visível após o filtro
        if (visibleCount === 0) {
            // Se não houver cards visíveis, exiba uma mensagem
            let noResults = document.querySelector('.no-hosts-message');
            if (!noResults) {
                noResults = document.createElement('div');
                noResults.className = 'no-hosts-message';
                noResults.innerHTML = `
                    <p>Não há anfitriões para este idioma ainda.</p>
                    <p>Que tal <a href="contato.php">se tornar um?</a></p>
                `;
                
                const hostGrid = document.querySelector('.hosts-grid');
                if (hostGrid) {
                    hostGrid.prepend(noResults);
                }
            }
        } else {
            // Se houver cards visíveis, remova a mensagem caso exista
            const noResults = document.querySelector('.no-hosts-message');
            if (noResults) {
                noResults.remove();
            }
        }
        
        // Atualizar a URL com o filtro selecionado
        const urlParams = new URLSearchParams(window.location.search);
        if (language === 'all') {
            urlParams.delete('idioma');
        } else {
            urlParams.set('idioma', language);
        }
        history.replaceState(null, '', urlParams.toString() ? `?${urlParams.toString()}` : window.location.pathname);
        
        // Scroll to the top of the host grid
        setTimeout(() => {
            const headerHeight = document.querySelector('header').offsetHeight || 0;
            const hostGrid = document.querySelector('.hosts-grid');
            if (hostGrid) {
                const y = hostGrid.getBoundingClientRect().top + window.pageYOffset - headerHeight - 20;
                window.scrollTo({top: y, behavior: 'smooth'});
            }
        }, 100);
    }
    
    // Check URL parameters on page load and apply filters accordingly
    function parseURL() {
        const urlParams = new URLSearchParams(window.location.search);
        const idioma = urlParams.get('idioma');
        const categoria = urlParams.get('categoria');
        const regiao = urlParams.get('regiao');
        const papel = urlParams.get('papel');
        
        // Handle category parameter first
        if (categoria) {
            const categoryTab = document.querySelector(`.category-tab[data-category="${categoria}"]`);
            if (categoryTab) {
                // Set active class but don't trigger click event
                categoryTabs.forEach(t => t.classList.remove('active'));
                categoryTab.classList.add('active');
                currentCategory = categoria;
                
                // Update UI for this category
                updateDropdownForCategory(categoria);
                filterHostsByCategory(categoria);
                
                // Handle secondary filters based on category
                if (categoria === 'presencial' && regiao) {
                    // Apply region filter without opening dropdown
                    applyRegionFilter(regiao);
                } else if (categoria === 'tecnica' && papel) {
                    // Apply role filter without opening dropdown
                    applyRoleFilter(papel);
                } else if (categoria === 'online' && idioma) {
                    // Apply language filter without opening dropdown
                    applyLanguageFilter(idioma);
                }
            } else {
                // If invalid category, apply default category filter
                filterHostsByCategory(currentCategory);
            }
        } else {
            // If no category specified, apply default category filter
            filterHostsByCategory(currentCategory);
            
            // Check for language parameter if default category is online
            if (idioma) {
                applyLanguageFilter(idioma);
            }
        }
        
        // Ensure all dropdowns are closed
        document.querySelectorAll('.dropdown-content').forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }
    
    // Helper functions to apply filters without opening dropdowns
    function applyLanguageFilter(idioma) {
        const dropdownButtons = document.querySelectorAll('.language-button[data-language]');
        
        // Try to find an exact match first
        let selectedButton = Array.from(dropdownButtons).find(button => 
            button.getAttribute('data-language').toLowerCase() === idioma.toLowerCase()
        );
        
        // If no exact match, check the normalized language values (for accented characters)
        if (!selectedButton) {
            const normalizeString = (str) => str.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            const normalizedIdioma = normalizeString(idioma);
            
            selectedButton = Array.from(dropdownButtons).find(button => {
                const normalizedButtonLang = normalizeString(button.getAttribute('data-language'));
                return normalizedButtonLang === normalizedIdioma;
            });
        }
        
        if (selectedButton) {
            // Update dropdown UI without opening it
            const languageText = selectedButton.querySelector('span:not(.flag-icon)').textContent;
            document.getElementById('selected-option').textContent = languageText;
            
            const flagElement = selectedButton.querySelector('.flag-icon');
            if (flagElement) {
                if (flagElement.tagName === 'IMG') {
                    document.getElementById('selected-language-flag').innerHTML = '';
                    const newFlag = document.createElement('img');
                    newFlag.src = flagElement.src;
                    newFlag.alt = flagElement.alt;
                    newFlag.className = 'flag-icon';
                    newFlag.style.width = '24px';
                    newFlag.style.height = '18px';
                    newFlag.style.borderRadius = '3px';
                    document.getElementById('selected-language-flag').appendChild(newFlag);
                } else {
                    document.getElementById('selected-language-flag').innerHTML = flagElement.innerHTML;
                }
            }
            
            // Get the normalized language version
            const normalizedLang = selectedButton.getAttribute('data-normalized') || selectedButton.getAttribute('data-language');
            
            // Apply filter
            filterHostsByLanguage(idioma, normalizedLang);
        }
    }
    
    function applyRegionFilter(region) {
        const regionButtons = document.querySelectorAll('.region-button');
        const selectedButton = Array.from(regionButtons).find(button => 
            button.getAttribute('data-region') === region
        );
        
        if (selectedButton) {
            // Update dropdown UI without opening it
            const regionText = selectedButton.querySelector('span:not(.region-icon)').textContent;
            document.getElementById('selected-option').textContent = regionText;
            
            const iconElement = selectedButton.querySelector('.region-icon');
            if (iconElement) {
                document.getElementById('selected-language-flag').textContent = iconElement.textContent;
            }
            
            // Apply filter
            filterHostsByRegion(region);
        }
    }
    
    function applyRoleFilter(role) {
        const roleButtons = document.querySelectorAll('.role-button');
        const selectedButton = Array.from(roleButtons).find(button => 
            button.getAttribute('data-role') === role
        );
        
        if (selectedButton) {
            // Update dropdown UI without opening it
            const roleText = selectedButton.querySelector('span:not(.role-icon)').textContent;
            document.getElementById('selected-option').textContent = roleText;
            
            const iconElement = selectedButton.querySelector('.role-icon');
            if (iconElement) {
                document.getElementById('selected-language-flag').textContent = iconElement.textContent;
            }
            
            // Apply filter
            filterHostsByRole(role);
        }
    }

    // Apply default category filter on page load
    setTimeout(() => {
        // Apply default filter (online) when page loads
        filterHostsByCategory('online');
        
        // Then check URL parameters for any specific filters
        parseURL();
        
        // Ensure all dropdowns are closed on page load
        document.querySelectorAll('.dropdown-content').forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }, 100);

    // TEST DROPDOWN IMPLEMENTATION - IMPROVED VERSION
    document.addEventListener('DOMContentLoaded', function() {
        const testDropdownBtn = document.getElementById('test-dropdown-btn');
        const testDropdownContent = document.getElementById('test-dropdown-content');
        const testOptions = document.querySelectorAll('.test-option');
        const testSelectedText = document.getElementById('test-selected-text');
        const testSelectedIcon = document.getElementById('test-selected-icon');
        const testSearch = document.getElementById('test-search');
        const testNoResults = document.getElementById('test-no-results');
        
        // First, make sure the dropdown is hidden on page load
        testDropdownContent.style.display = 'none';
        
        // Toggle dropdown on button click
        testDropdownBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Test dropdown button clicked');
            
            // Toggle the dropdown
            if (testDropdownContent.classList.contains('show')) {
                testDropdownContent.classList.remove('show');
                console.log('Test dropdown closed');
            } else {
                testDropdownContent.classList.add('show');
                testDropdownContent.style.display = 'block';
                console.log('Test dropdown opened');
                
                // Focus search input
                setTimeout(() => {
                    testSearch.focus();
                }, 100);
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            // Only if the dropdown is open and the click is outside the dropdown
            if (testDropdownContent.classList.contains('show') && !e.target.closest('.test-mobile-dropdown')) {
                testDropdownContent.classList.remove('show');
                console.log('Test dropdown closed by outside click');
            }
        });
        
        // Option selection
        testOptions.forEach(option => {
            option.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const value = this.getAttribute('data-value');
                const text = this.querySelector('span:not(.flag-icon)').textContent;
                const icon = this.querySelector('.flag-icon').textContent;
                
                // Update selected text and icon
                testSelectedText.textContent = text;
                testSelectedIcon.textContent = icon;
                
                // Close dropdown
                testDropdownContent.classList.remove('show');
                
                console.log(`Test dropdown: selected ${value} (${text})`);
            });
        });
        
        // Improved search functionality
        testSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            let resultsFound = false;
            
            testOptions.forEach(option => {
                const text = option.querySelector('span:not(.flag-icon)').textContent
                             .toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                
                if (text.includes(searchTerm)) {
                    option.style.display = 'flex';
                    resultsFound = true;
                } else {
                    option.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            if (resultsFound) {
                testNoResults.style.display = 'none';
            } else {
                testNoResults.style.display = 'block';
            }
        });
        
        // Style updates for dropdown
        function updateTestDropdownStyles() {
            // Apply CSS class to make it look and behave like the original
            if (testDropdownContent.classList.contains('show')) {
                testDropdownContent.style.display = 'block';
                // Apply animation
                testDropdownContent.style.animation = 'fadeInDown 0.3s ease-out';
            } else {
                testDropdownContent.style.display = 'none';
            }
        }
        
        // Observer for class changes
        const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                if (mutation.attributeName === 'class') {
                    updateTestDropdownStyles();
                }
            });
        });
        
        // Start observing class changes
        observer.observe(testDropdownContent, { attributes: true });
    });

    // Add event listeners to dropdown buttons
    document.querySelectorAll('.region-button').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const region = this.getAttribute('data-region');
            const regionText = this.querySelector('span:not(.region-icon)').textContent;
            const iconElement = this.querySelector('.region-icon');
            
            // Update selected text
            document.getElementById('selected-option').textContent = regionText;
            
            // Update selected icon
            if (iconElement) {
                document.getElementById('selected-language-flag').textContent = iconElement.textContent;
            }
            
            // Close dropdown
            document.querySelectorAll('.dropdown-content').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
            
            // Log for debugging
            console.log(`Selected region: ${region}`);
            console.log(`Available host regions: ${Array.from(document.querySelectorAll('.host-card')).map(card => card.getAttribute('data-region')).join(', ')}`);
            
            // Filter hosts by region
            filterHostsByRegion(region);
            
            // Update URL
            const urlParams = new URLSearchParams(window.location.search);
            if (region === 'all') {
                urlParams.delete('regiao');
            } else {
                urlParams.set('regiao', region);
            }
            
            // Maintain category parameter
            urlParams.set('categoria', 'presencial');
            
            history.replaceState(null, '', urlParams.toString() ? `?${urlParams.toString()}` : window.location.pathname);
        });
    });
    
    document.querySelectorAll('.role-button').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const role = this.getAttribute('data-role');
            const roleText = this.querySelector('span:not(.role-icon)').textContent;
            const iconElement = this.querySelector('.role-icon');
            
            // Update selected text
            document.getElementById('selected-option').textContent = roleText;
            
            // Update selected icon
            if (iconElement) {
                document.getElementById('selected-language-flag').textContent = iconElement.textContent;
            }
            
            // Close dropdown
            document.querySelectorAll('.dropdown-content').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
            
            // Log for debugging
            console.log(`Selected role: ${role}`);
            console.log(`Available host roles: ${Array.from(document.querySelectorAll('.host-card')).map(card => card.getAttribute('data-roles')).join(', ')}`);
            
            // Filter hosts by role
            filterHostsByRole(role);
            
            // Update URL
            const urlParams = new URLSearchParams(window.location.search);
            if (role === 'all') {
                urlParams.delete('papel');
            } else {
                urlParams.set('papel', role);
            }
            
            // Maintain category parameter
            urlParams.set('categoria', 'tecnica');
            
            history.replaceState(null, '', urlParams.toString() ? `?${urlParams.toString()}` : window.location.pathname);
        });
    });
    
    // Function to filter hosts by region
    function filterHostsByRegion(region) {
        console.log(`Filtering hosts by region: ${region}`);
        let visibleCount = 0;
        
        const hostCards = document.querySelectorAll('.host-card');
        hostCards.forEach(card => {
            const cardRegion = card.getAttribute('data-region');
            const hostName = card.querySelector('.host-name').textContent;
            
            // Check if card belongs to selected region or all regions
            const isMatch = region === 'all' || cardRegion === region;
            // Also match the "become a host" card for all regions
            const isSpecialCard = hostName.includes('Torne-se anfitrião');
            
            // Also check if card belongs to the selected category (Presencial)
            const isInCategory = card.getAttribute('data-categories').includes('presencial');
            
            console.log(`Host: ${hostName}, Region: ${cardRegion}, Filter: ${region}, Match: ${isMatch}, In Category: ${isInCategory}`);
            
            if ((isMatch || isSpecialCard) && isInCategory) {
                card.style.display = 'block';
                card.classList.add('visible');
                visibleCount++;
            } else {
                card.style.display = 'none';
                card.classList.remove('visible');
            }
        });
        
        // Show "no results" message if needed
        showNoResultsMessage(visibleCount, 'region');
    }
    
    // Function to filter hosts by role
    function filterHostsByRole(role) {
        console.log(`Filtering hosts by role: ${role}`);
        let visibleCount = 0;
        
        const hostCards = document.querySelectorAll('.host-card');
        hostCards.forEach(card => {
            const cardRoles = card.getAttribute('data-roles');
            const hostName = card.querySelector('.host-name').textContent;
            
            // Check if card has selected role
            const isMatch = role === 'all' || (cardRoles && cardRoles.includes(role));
            // Also match the "become a host" card for all roles
            const isSpecialCard = hostName.includes('Torne-se anfitrião') || role === 'fazer-parte';
            
            // Also check if card belongs to the selected category (Técnica)
            const isInCategory = card.getAttribute('data-categories').includes('tecnica');
            
            console.log(`Host: ${hostName}, Roles: ${cardRoles}, Filter: ${role}, Match: ${isMatch}, In Category: ${isInCategory}`);
            
            if ((isMatch || isSpecialCard) && isInCategory) {
                card.style.display = 'block';
                card.classList.add('visible');
                visibleCount++;
            } else {
                card.style.display = 'none';
                card.classList.remove('visible');
            }
        });
        
        // Show "no results" message if needed
        showNoResultsMessage(visibleCount, 'role');
    }
    
    // Helper function to show "no results" message
    function showNoResultsMessage(visibleCount, filterType) {
        if (visibleCount === 0) {
            let noResults = document.querySelector(`.no-hosts-${filterType}`);
            if (!noResults) {
                noResults = document.createElement('div');
                noResults.className = `no-hosts-message no-hosts-${filterType}`;
                
                let message = '';
                if (filterType === 'region') {
                    message = 'Não há anfitriões presenciais nesta região ainda.';
                } else if (filterType === 'role') {
                    message = 'Não há membros da equipe técnica neste papel ainda.';
                } else {
                    message = 'Nenhum resultado encontrado.';
                }
                
                noResults.innerHTML = `
                    <p>${message}</p>
                    <p>Que tal <a href="contato.php">se tornar um?</a></p>
                `;
                
                const hostGrid = document.querySelector('.hosts-grid');
                if (hostGrid) {
                    hostGrid.prepend(noResults);
                }
            }
        } else {
            // Remove no results message if it exists
            const noResults = document.querySelector(`.no-hosts-${filterType}`);
            if (noResults) {
                noResults.remove();
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?> 