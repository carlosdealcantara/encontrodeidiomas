<?php
require_once '../config.php';
$conn = connectDB();

echo "<pre>\n";
echo "Iniciando migração de banco de dados para traduções multilíngues de boas-vindas...\n";

try {
    // 1. Criar a nova tabela de traduções
    $conn->exec("
        CREATE TABLE IF NOT EXISTS community_welcome_translations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entity_type ENUM('intro', 'question') NOT NULL,
            entity_id INT NOT NULL,
            lang_code VARCHAR(10) NOT NULL,
            text TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_entity_lang (entity_type, entity_id, lang_code),
            INDEX idx_entity (entity_type, entity_id)
        )
    ");
    echo "Tabela 'community_welcome_translations' verificada/criada.\n";

    // 2. Adicionar o template geral de boas-vindas na tabela mentoria_config se não existir
    // Mas mentoria_config está no JSON no Baileys. Então, vou adicionar isso no meetup_whatsapp_templates ou apenas deixar que o painel admin cuide disso enviando o JSON.
    // O painel admin atualiza o config do Baileys, então o 'community_welcome_template' pode ficar lá, como o 'community_ranking_messenger'.

    // 3. Inserir algumas traduções de exemplo baseadas nas perguntas e intros da fase 1.
    // Para simplificar a demonstração (já que IA vai traduzir), vou usar INSERT IGNORE
    // para não dar erro se já existirem.
    
    // Intros (ID 1 ao 5)
    $introsES = [
        1 => "¡Oba, gente nueva por aquí! 🥳 Nuestras bienvenidas, {mentions}! Estamos muy felices de tenerlos con nosotros.",
        2 => "¡Qué alegría ver gente nueva! 🥳 Bienvenidos, {mentions}! Siéntanse como en casa.",
        3 => "¡Nuevas caras en la comunidad! 🥳 Un saludo especial para {mentions}! Pónganse cómodos.",
        4 => "¡La familia sigue creciendo! 🥳 {mentions}, ¡qué bueno tenerlos aquí! Bienvenidos.",
        5 => "¡Yey, más amigos para practicar! 🥳 Bienvenidos, {mentions}! Estamos emocionados de hablar con ustedes."
    ];
    $introsDE = [
        1 => "Oh, neue Leute hier! 🥳 Herzlich willkommen, {mentions}! Wir freuen uns sehr, euch bei uns zu haben.",
        2 => "Was für eine Freude, neue Leute zu sehen! 🥳 Willkommen, {mentions}! Fühlt euch wie zu Hause."
    ];
    // Adicionaremos mais idiomas manualmente depois se for o caso.

    $stmtInsertIntro = $conn->prepare("INSERT IGNORE INTO community_welcome_translations (entity_type, entity_id, lang_code, text) VALUES ('intro', ?, ?, ?)");
    foreach ($introsES as $id => $text) {
        $stmtInsertIntro->execute([$id, 'es', $text]);
    }
    foreach ($introsDE as $id => $text) {
        $stmtInsertIntro->execute([$id, 'de', $text]);
    }

    // Perguntas (ID 1 ao 8)
    $qsES = [
        1 => "¿Qué idiomas hablas o estás aprendiendo?",
        2 => "¿De dónde eres y dónde vives ahora?",
        3 => "¿Cuáles son tus pasatiempos favoritos?",
        4 => "¿Por qué decidiste aprender este idioma?",
        5 => "¿A dónde viajarías si pudieras ir mañana?",
        6 => "¿Cuál es tu comida favorita?",
        7 => "¿Qué tipo de música o películas te gustan?",
        8 => "Cuéntanos un dato curioso o divertido sobre ti."
    ];
    $stmtInsertQ = $conn->prepare("INSERT IGNORE INTO community_welcome_translations (entity_type, entity_id, lang_code, text) VALUES ('question', ?, ?, ?)");
    foreach ($qsES as $id => $text) {
        $stmtInsertQ->execute([$id, 'es', $text]);
    }

    echo "Traduções iniciais (Espanhol e Alemão) populadas com sucesso.\n";
    echo "Migração concluída com sucesso!\n";

} catch (PDOException $e) {
    echo "Erro na migração: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
