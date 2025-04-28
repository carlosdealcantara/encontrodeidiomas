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

/* Filter controls */
.filter-controls {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.filter-button {
    padding: 8px 15px;
    background-color: #f0f2f5;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.filter-button:hover {
    background-color: #e6e9ec;
}

.filter-button.active {
    background-color: var(--accent-red);
    color: var(--white);
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
            <div class="filter-controls">
                <button class="filter-button active" data-filter="all">Todos</button>
                <?php foreach ($languages as $language): ?>
                    <button class="filter-button" data-filter="<?= strtolower($language['name']) ?>"><?= $language['name'] ?></button>
                <?php endforeach; ?>
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
                            
                            <img src="<?= !empty($host['profile_picture']) ? $host['profile_picture'] : 'assets/images/HostSemFoto.png' ?>" 
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
    // Filter functionality
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
            hostCards.forEach(card => {
                if (filter === 'all') {
                    card.style.display = 'block';
                } else {
                    const languages = card.getAttribute('data-languages');
                    if (languages && languages.includes(filter)) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?> 