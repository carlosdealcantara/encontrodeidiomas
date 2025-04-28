<?php
$title = "Links Úteis";

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

.links-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.link-category {
    background-color: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.link-category:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
}

.category-header {
    background-color: var(--primary-color);
    color: var(--white);
    padding: 20px;
    text-align: center;
}

.category-icon {
    font-size: 2.5rem;
    margin-bottom: 15px;
}

.category-title {
    font-size: 1.5rem;
    margin-bottom: 5px;
}

.category-description {
    font-size: 0.9rem;
    opacity: 0.8;
}

.link-list {
    padding: 20px;
}

.resource-link {
    display: block;
    padding: 12px 15px;
    border-bottom: 1px solid #f0f0f0;
    color: var(--text-color);
    text-decoration: none;
    transition: all 0.2s ease;
}

.resource-link:last-child {
    border-bottom: none;
}

.resource-link:hover {
    background-color: #f9f9f9;
    padding-left: 20px;
}

.resource-link-title {
    font-weight: 500;
    margin-bottom: 5px;
}

.resource-link-description {
    font-size: 0.9rem;
    color: #666;
}

.link-category:nth-child(1) .category-header {
    background-color: var(--accent-red);
}

.link-category:nth-child(2) .category-header {
    background-color: var(--accent-blue);
}

.link-category:nth-child(3) .category-header {
    background-color: #009688;
}

.link-category:nth-child(4) .category-header {
    background-color: #9C27B0;
}

.link-category:nth-child(5) .category-header {
    background-color: #FF9800;
}

.link-category:nth-child(6) .category-header {
    background-color: #607D8B;
}
EOT;

include 'includes/header.php';
?>

<div class="main-content">
    <div class="container">
        <div class="page-title">
            <h1>Links Úteis</h1>
            <p>Uma coleção de recursos úteis para aprender e praticar idiomas</p>
        </div>
        
        <div class="links-grid">
            <div class="link-category">
                <div class="category-header">
                    <div class="category-icon">
                        <i class="fas fa-headphones"></i>
                    </div>
                    <h2 class="category-title">Podcasts</h2>
                    <p class="category-description">Ouça e aprenda em qualquer lugar</p>
                </div>
                <div class="link-list">
                    <a href="https://www.duolingo.com/podcast" target="_blank" class="resource-link">
                        <div class="resource-link-title">Duolingo Podcasts</div>
                        <div class="resource-link-description">Histórias narradas em inglês, espanhol, francês e mais.</div>
                    </a>
                    <a href="https://www.newsinslowfrench.com/" target="_blank" class="resource-link">
                        <div class="resource-link-title">News in Slow French</div>
                        <div class="resource-link-description">Notícias em francês em um ritmo mais lento para facilitar a compreensão.</div>
                    </a>
                    <a href="https://www.japanesewithnoriko.com/" target="_blank" class="resource-link">
                        <div class="resource-link-title">Learn Japanese with Noriko</div>
                        <div class="resource-link-description">Podcast para estudantes de japonês de todos os níveis.</div>
                    </a>
                </div>
            </div>
            
            <div class="link-category">
                <div class="category-header">
                    <div class="category-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <h2 class="category-title">Aplicativos</h2>
                    <p class="category-description">Aprendizado no seu bolso</p>
                </div>
                <div class="link-list">
                    <a href="https://www.duolingo.com/" target="_blank" class="resource-link">
                        <div class="resource-link-title">Duolingo</div>
                        <div class="resource-link-description">Aprenda mais de 30 idiomas com lições gamificadas.</div>
                    </a>
                    <a href="https://www.memrise.com/" target="_blank" class="resource-link">
                        <div class="resource-link-title">Memrise</div>
                        <div class="resource-link-description">Flashcards e vídeos de falantes nativos.</div>
                    </a>
                    <a href="https://www.hellotalk.com/" target="_blank" class="resource-link">
                        <div class="resource-link-title">HelloTalk</div>
                        <div class="resource-link-description">Pratique com falantes nativos em uma troca de idiomas.</div>
                    </a>
                    <a href="https://www.tandem.net/" target="_blank" class="resource-link">
                        <div class="resource-link-title">Tandem</div>
                        <div class="resource-link-description">Encontre parceiros para troca de idiomas.</div>
                    </a>
                </div>
            </div>
            
            <div class="link-category">
                <div class="category-header">
                    <div class="category-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h2 class="category-title">Gramática</h2>
                    <p class="category-description">Recursos para aperfeiçoar sua gramática</p>
                </div>
                <div class="link-list">
                    <a href="https://www.grammarly.com/" target="_blank" class="resource-link">
                        <div class="resource-link-title">Grammarly</div>
                        <div class="resource-link-description">Correção gramatical em inglês para seus textos.</div>
                    </a>
                    <a href="https://www.lawlessfrench.com/" target="_blank" class="resource-link">
                        <div class="resource-link-title">Lawless French</div>
                        <div class="resource-link-description">Recursos para aprender gramática francesa.</div>
                    </a>
                    <a href="https://www.german-grammar.de/" target="_blank" class="resource-link">
                        <div class="resource-link-title">German Grammar</div>
                        <div class="resource-link-description">Explicações detalhadas da gramática alemã.</div>
                    </a>
                </div>
            </div>
            
            <div class="link-category">
                <div class="category-header">
                    <div class="category-icon">
                        <i class="fas fa-video"></i>
                    </div>
                    <h2 class="category-title">YouTube</h2>
                    <p class="category-description">Canais para aprender idiomas</p>
                </div>
                <div class="link-list">
                    <a href="https://www.youtube.com/@encontrodeidiomasingles" target="_blank" class="resource-link">
                        <div class="resource-link-title">Encontro de Idiomas - Inglês</div>
                        <div class="resource-link-description">Nosso canal com encontros de inglês gravados.</div>
                    </a>
                    <a href="https://www.youtube.com/@encontrodeidiomasespanhol" target="_blank" class="resource-link">
                        <div class="resource-link-title">Encontro de Idiomas - Espanhol</div>
                        <div class="resource-link-description">Nosso canal com encontros de espanhol gravados.</div>
                    </a>
                    <a href="https://www.youtube.com/@EasyGerman" target="_blank" class="resource-link">
                        <div class="resource-link-title">Easy German</div>
                        <div class="resource-link-description">Entrevistas de rua e explicações em alemão.</div>
                    </a>
                    <a href="https://www.youtube.com/@EasyJapanese" target="_blank" class="resource-link">
                        <div class="resource-link-title">Easy Japanese</div>
                        <div class="resource-link-description">Aprenda japonês com situações reais de conversação.</div>
                    </a>
                </div>
            </div>
            
            <div class="link-category">
                <div class="category-header">
                    <div class="category-icon">
                        <i class="fas fa-film"></i>
                    </div>
                    <h2 class="category-title">Filmes e Séries</h2>
                    <p class="category-description">Entretenimento para aprender</p>
                </div>
                <div class="link-list">
                    <a href="https://www.netflix.com/" target="_blank" class="resource-link">
                        <div class="resource-link-title">Netflix</div>
                        <div class="resource-link-description">Séries e filmes em diversos idiomas com legendas.</div>
                    </a>
                    <a href="https://www.languagelearningwithnetflix.com/" target="_blank" class="resource-link">
                        <div class="resource-link-title">Language Learning with Netflix</div>
                        <div class="resource-link-description">Extensão para Chrome que facilita o aprendizado com Netflix.</div>
                    </a>
                    <a href="https://www.fluentu.com/" target="_blank" class="resource-link">
                        <div class="resource-link-title">FluentU</div>
                        <div class="resource-link-description">Vídeos autênticos com legendas interativas.</div>
                    </a>
                </div>
            </div>
            
            <div class="link-category">
                <div class="category-header">
                    <div class="category-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h2 class="category-title">Comunidades</h2>
                    <p class="category-description">Conecte-se com outros estudantes</p>
                </div>
                <div class="link-list">
                    <a href="https://www.reddit.com/r/languagelearning/" target="_blank" class="resource-link">
                        <div class="resource-link-title">r/languagelearning</div>
                        <div class="resource-link-description">Comunidade no Reddit para estudantes de idiomas.</div>
                    </a>
                    <a href="https://www.italki.com/" target="_blank" class="resource-link">
                        <div class="resource-link-title">iTalki</div>
                        <div class="resource-link-description">Encontre professores particulares ou parceiros de troca.</div>
                    </a>
                    <a href="https://www.lingq.com/" target="_blank" class="resource-link">
                        <div class="resource-link-title">LingQ</div>
                        <div class="resource-link-description">Plataforma com conteúdo para leitura e comunidade.</div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 