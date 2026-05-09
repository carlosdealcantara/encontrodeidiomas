<?php
require_once 'config.php';

echo "<h2>Verificação de Migração de Banco de Dados</h2>";

try {
    $conn = connectDB();
    
    // Verificar se a coluna existe
    $stmt = $conn->query("SHOW COLUMNS FROM meetings LIKE 'description_en'");
    $columnExists = $stmt->fetch();
    
    if ($columnExists) {
        echo "<p style='color: green;'>✅ Coluna 'description_en' existe!</p>";
    } else {
        echo "<p style='color: red;'>❌ Coluna 'description_en' NÃO encontrada. Tentando criar...</p>";
        $conn->exec("ALTER TABLE meetings ADD COLUMN description_en TEXT AFTER description");
        echo "<p style='color: green;'>✅ Coluna criada com sucesso.</p>";
    }

    // Verificar se há dados preenchidos
    $stmt = $conn->query("SELECT COUNT(*) FROM meetings WHERE description_en IS NOT NULL AND description_en != ''");
    $count = $stmt->fetchColumn();
    
    echo "<p>Registros com descrição em inglês: $count</p>";

    if ($count == 0) {
        echo "<p>Injetando traduções padrão...</p>";
        $local_translations = [
            "Alemão" => "Practice German in our online meetups. Open for all levels.",
            "Chinês" => "Come learn and practice Mandarin. Communicative and cultural approach.",
            "Coreano" => "Learn elements of Korean language and culture in a dynamic meetup.",
            "Espanhol" => "Practice Spanish with natives and enthusiasts. Cultural topics.",
            "Francês" => "Come practice French in a welcoming environment for everyone.",
            "Inglês" => "Improve your English with practical conversation. All levels.",
            "Italiano" => "Learn Italian in a relaxed environment. Open for all levels.",
            "Japonês" => "Immerse in Japanese culture by practicing the language. All levels.",
            "Libras" => "Learn and practice Libras (Brazilian Sign Language) with our instructors.",
            "Polonês" => "Discover the Polish language and culture with our weekly meetups.",
            "Português" => "Help foreigners with Brazilian Portuguese. Cultural exchange.",
            "Russo" => "Learn the basics of Russian language and interesting cultural aspects.",
            "Servo-Croata" => "Explore Balkan culture by practicing the language. Open to all."
        ];

        $updateStmt = $conn->prepare("UPDATE meetings m JOIN languages l ON m.language_id = l.id SET m.description_en = :desc WHERE l.name LIKE :lang");
        foreach ($local_translations as $lang => $desc) {
            $updateStmt->execute(['desc' => $desc, 'lang' => "%$lang%"]);
        }
        echo "<p style='color: green;'>✅ Traduções injetadas.</p>";
    } else {
        echo "<p style='color: green;'>✅ As traduções já parecem estar no banco.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}

echo "<br><a href='online'>Voltar para o site</a>";
