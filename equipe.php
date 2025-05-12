<?php
$title = "Nossa Equipe";

// Get all hosts
require_once 'config.php';
$hosts = getHosts();
$languages = getLanguages();

// Create a map of language IDs to names for easy lookup
$languageMap = [];
foreach ($languages as $language) {
    $languageMap[$language['id']] = $language['name'];
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
}

.host-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
}

.host-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background-color: var(--accent-blue);
    color: var(--white);
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    z-index: 10;
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
    gap: 8px;
    margin-bottom: 15px;
}

.language-tag {
    display: inline-block;
    background-color: #f0f2f5;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    color: var(--text-color);
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

/* Responsive adjustments */
@media (max-width: 768px) {
    .hosts-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    }
}
EOT;

include 'includes/header.php';
?>

<div class="main-content">
    <div class="container">
        <div class="page-title">
            <h1>Nossa Equipe</h1>
            <p>Conheça os anfitriões e organizadores que fazem acontecer os encontros de idiomas.</p>
        </div>
        
        <div class="team-section">
            <!-- Original filter controls (hidden) -->
            <div class="filter-controls">
                <button class="filter-button active" data-filter="all">Todos</button>
                <?php foreach ($languages as $language): ?>
                    <button class="filter-button" data-filter="<?= strtolower($language['name']) ?>"><?= $language['name'] ?></button>
                <?php endforeach; ?>
            </div>
            
            <!-- Language Dropdown -->
            <div class="mobile-dropdown">
                <p style="text-align: center; margin-bottom: 10px; font-size: 0.9rem; font-weight: 500;">Selecione um idioma:</p>
                <button class="dropdown-button">
                    <div class="dropdown-flag-container">
                        <span id="selected-language-flag" class="flag-icon" style="font-size: 1.2rem; width: 24px; height: 24px; display: inline-block; text-align: center; box-shadow: none;">🌍</span>
                        <span id="selected-language">Todos os idiomas</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="dropdown-content">
                    <div class="search-filter">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input" id="language-search" placeholder="Buscar idioma...">
                    </div>
                    <div class="no-results" id="no-results">
                        Nenhum idioma encontrado.
                    </div>
                    <button class="language-button" data-language="all">
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
                    <button class="language-button" data-language="<?= strtolower($language['name']) ?>">
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
                    <button class="language-button" data-language="outros">
                        <div class="language-info">
                            <span class="flag-icon" style="font-size: 1.2rem; width: 24px; height: 24px; display: inline-block; text-align: center; box-shadow: none;">🚩</span>
                            <span>Seu idioma aqui!</span>
                        </div>
                    </button>
                </div>
            </div>
            
            <div class="hosts-container">
                <div class="hosts-grid">
                    <?php foreach ($hosts as $host): ?>
                        <?php 
                        // Get host languages
                        $hostLanguages = [];
                        if (!empty($host['languages'])) {
                            $languageIds = explode(',', $host['languages']);
                            foreach ($languageIds as $langId) {
                                if (isset($languageMap[trim($langId)])) {
                                    $hostLanguages[] = $languageMap[trim($langId)];
                                }
                            }
                        }
                        
                        // Get first language for badge
                        $primaryLanguage = !empty($hostLanguages) ? $hostLanguages[0] : '';
                        
                        // Get social media links
                        $socialLinks = !empty($host['social_media_links']) ? json_decode($host['social_media_links'], true) : [];
                        ?>
                        
                        <div class="host-card" data-languages="<?= strtolower(implode(' ', $hostLanguages)) ?>">
                            <?php if (!empty($primaryLanguage)): ?>
                                <div class="host-badge"><?= $primaryLanguage ?></div>
                            <?php endif; ?>
                            
                            <img src="<?= !empty($host['profile_picture']) ? (strpos($host['profile_picture'], 'assets/') === 0 ? $host['profile_picture'] : 'assets/images/' . $host['profile_picture']) : 'assets/images/HostSemFoto.png' ?>" 
                                 alt="<?= $host['full_name'] ?>" class="host-image">
                                 
                            <div class="host-info">
                                <h3 class="host-name"><?= $host['full_name'] ?></h3>
                                
                                <div class="host-languages">
                                    <?php foreach ($hostLanguages as $lang): ?>
                                        <span class="language-tag"><?= $lang ?></span>
                                    <?php endforeach; ?>
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
    // Original filter functionality (keep for compatibility)
    const filterButtons = document.querySelectorAll('.filter-button');
    const hostCards = document.querySelectorAll('.host-card');
    
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
    const dropdownContent = document.querySelector('.dropdown-content');
    const languageButtons = document.querySelectorAll('.language-button');
    const selectedLanguageText = document.getElementById('selected-language');
    const selectedLanguageFlag = document.getElementById('selected-language-flag');
    const searchInput = document.getElementById('language-search');
    const noResults = document.getElementById('no-results');
    
    // Toggle dropdown when button is clicked
    dropdownButton.addEventListener('click', function() {
        dropdownContent.classList.toggle('show');
    });
    
    // Close dropdown when clicking outside
    window.addEventListener('click', function(event) {
        if (!event.target.closest('.mobile-dropdown')) {
            dropdownContent.classList.remove('show');
        }
    });
    
    // Search functionality
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        let resultsFound = false;
        
        languageButtons.forEach(button => {
            const languageName = button.querySelector('span:not(.flag-icon)').textContent.toLowerCase();
            if (languageName.includes(searchTerm)) {
                button.style.display = 'flex';
                resultsFound = true;
            } else {
                button.style.display = 'none';
            }
        });
        
        // Show/hide no results message
        if (resultsFound) {
            noResults.style.display = 'none';
        } else {
            noResults.style.display = 'block';
        }
    });
    
    // Language selection
    languageButtons.forEach(button => {
        button.addEventListener('click', function() {
            const language = this.getAttribute('data-language');
            const languageText = this.querySelector('span:not(.flag-icon)').textContent;
            
            // Update selected language text
            selectedLanguageText.textContent = languageText;
            
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
            dropdownContent.classList.remove('show');
            
            // Filter hosts
            filterHostsByLanguage(language);
            
            // Update the original filter buttons UI (for compatibility)
            filterButtons.forEach(btn => {
                if (btn.getAttribute('data-filter') === language) {
                    btn.click();
                }
            });
        });
    });
    
    // Function to filter hosts by language
    function filterHostsByLanguage(language) {
        hostCards.forEach(card => {
            if (language === 'all') {
                card.style.display = 'block';
            } else {
                const languages = card.getAttribute('data-languages');
                if (languages && languages.includes(language)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?> 