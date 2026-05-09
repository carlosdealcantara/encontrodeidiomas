<?php
require_once 'config.php';

try {
    $conn = connectDB();
    
    // 1. Add columns to hosts table
    $conn->exec("ALTER TABLE hosts ADD COLUMN IF NOT EXISTS online_description_en TEXT");
    $conn->exec("ALTER TABLE hosts ADD COLUMN IF NOT EXISTS inperson_description_en TEXT");
    $conn->exec("ALTER TABLE hosts ADD COLUMN IF NOT EXISTS technical_description_en TEXT");
    
    // 2. Prepare translations
    $translations = [
        "Carlos de Alcântara" => [
            "online" => "Carlos de Alcântara is an English expert and experienced host. His meetups are dynamic and focused on conversational practice, creating a welcoming environment for all levels.",
            "inperson" => "Carlos is an enthusiastic host who brings unique energy to in-person meetups. His ability to create a welcoming and engaging environment makes participants feel comfortable.",
            "technical" => "Carlos is the lead developer of Encontro de Idiomas, responsible for the site's architecture and the implementation of CMS features."
        ],
        "Daniel" => [
            "online" => "Daniel is passionate about Slavic languages and shares his knowledge of Russian and Polish in an accessible and engaging way. His meetups are an excellent introduction.",
            "inperson" => "Daniel shares his Slavic language knowledge in in-person meetups combining theory and conversation. His structured approach helps participants develop authentic pronunciation."
        ],
        "Isaac" => [
            "online" => "Isaac is a Chinese language enthusiast who shares his knowledge in an accessible and fun way. His meetups include cultural elements and everyday expressions.",
            "inperson" => "Isaac transforms Mandarin learning into a complete in-person experience. With physical materials and calligraphy exercises, he provides an authentic immersion into Chinese culture."
        ],
        "Paula" => [
            "online" => "Paula coordinates in-person meetups in São Paulo. With her dynamism and city knowledge, she organizes meetups in interesting and accessible places for language practice.",
            "inperson" => "Paula brings a vibrant energy that unites cultural rhythm and well-being. In her meetups, she facilitates real connections and transforms language practice into a dynamic experience."
        ],
        "Ricardo" => [
            "online" => "Ricardo brings a serene presence and impeccable courtesy to the meetups. Always positive, he creates an environment of respect, turning language practice into a fluid exchange."
        ],
        "Társis" => [
            "online" => "Társis conducts meetups focused on improving communication and expression in Portuguese. He creates a practice environment where participants of all levels can communicate with confidence.",
            "inperson" => "Társis facilitates in-person meetups focused on improving pronunciation and fluency. His method includes practical exercises that help participants develop public speaking confidence."
        ],
        "Thiago" => [
            "online" => "Thiago brings expressiveness and welcoming theatricality to the meetups. With gentle gestures and vibrant intonation, he transforms language practice into a light and playful moment."
        ]
    ];
    
    // 3. Update hosts
    $stmt = $conn->prepare("UPDATE hosts SET online_description_en = :online, inperson_description_en = :inperson, technical_description_en = :tech WHERE full_name LIKE :name");
    
    foreach ($translations as $name => $descs) {
        $stmt->execute([
            'online'  => $descs['online'] ?? null,
            'inperson' => $descs['inperson'] ?? null,
            'tech'    => $descs['technical'] ?? null,
            'name'    => "%$name%"
        ]);
    }
    
    echo "Database migrated and hosts updated successfully.";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
