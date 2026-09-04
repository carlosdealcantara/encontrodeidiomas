<?php
require_once __DIR__.'/../config.php';
$conn = connectDB();

$questions = $conn->query("SELECT id FROM community_welcome_questions ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$correct_texts = [
    ['Que idiomas você fala ou está aprendendo?', 'What languages do you speak or are you currently learning?'],
    ['De onde você é e onde mora agora?', 'Where are you from and where do you live now?'],
    ['Quais são os seus hobbies favoritos?', 'What are your favorite hobbies?'],
    ['Por que você decidiu aprender esse idioma?', 'Why did you decide to learn this language?'],
    ['Para onde você viajaria se pudesse ir amanhã?', 'If you could travel anywhere tomorrow, where would you go?'],
    ['Qual é a sua comida favorita?', 'What is your favorite food?'],
    ['Que tipo de música ou filmes você gosta?', 'What kind of music or movies do you like?'],
    ['Conta um fato curioso ou engraçado sobre você.', 'Tell us an interesting or funny fact about yourself.']
];

$stmt = $conn->prepare("UPDATE community_welcome_questions SET text_target = ?, text_en = ? WHERE id = ?");

foreach ($questions as $index => $q) {
    if (isset($correct_texts[$index])) {
        $stmt->execute([$correct_texts[$index][0], $correct_texts[$index][1], $q['id']]);
        echo "Updated ID {$q['id']} to index {$index}\n";
    }
}
echo "Correção no banco de dados aplicada com sucesso!";
