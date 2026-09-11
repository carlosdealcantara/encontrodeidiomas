<?php
/**
 * Migração — Adição de traduções em Francês (fr)
 */
require_once '../config.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $conn = connectDB();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== Migração de Traduções em Francês (FR) ===\n\n";

    $fr_intros = [
        "Look who just joined! 🥳 A warm welcome to you, {mentions}!" => "Regardez qui vient d'arriver ! 🥳 Bienvenue à toi, {mentions} !",
        "New faces in the house! Just kidding, everyone belongs here. Welcome, {mentions}! 🍕" => "De nouvelles têtes parmi nous ! Je plaisante, tout le monde est chez soi ici. Bienvenue, {mentions} ! 🍕",
        "We were waiting for you! So happy you're here, {mentions}! 🎈" => "On t'attendait ! Tellement heureux que tu sois là, {mentions} ! 🎈",
        "Look who's here! Welcome, {mentions}! The group just got better. ✨" => "Regardez qui est là ! Bienvenue, {mentions} ! Le groupe est encore mieux avec toi. ✨",
        "A huge welcome to {mentions}! We're so glad to have you here. 🎊" => "Un grand bienvenue à {mentions} ! Nous sommes ravis de t'avoir parmi nous. 🎊"
    ];

    $fr_questions = [
        "What languages do you speak or are you currently learning?" => "Quelles langues parles-tu ou apprends-tu en ce moment ?",
        "Where are you from and where do you live now?" => "D'où viens-tu et où habites-tu maintenant ?",
        "What are your favorite hobbies?" => "Quels sont tes passe-temps préférés ?",
        "Why did you decide to learn this language?" => "Pourquoi as-tu décidé d'apprendre cette langue ?",
        "If you could travel anywhere tomorrow, where would you go?" => "Si tu pouvais voyager n'importe où demain, où irais-tu ?",
        "What is your favorite food?" => "Quelle est ta nourriture préférée ?",
        "What kind of music or movies do you like?" => "Quel genre de musique ou de films aimes-tu ?",
        "Tell us an interesting or funny fact about yourself." => "Raconte-nous un fait intéressant ou amusant sur toi.",
        
        "What's a slang word or expression from your native language that everyone should know?" => "Quelle est une argot ou une expression de ta langue maternelle que tout le monde devrait connaître ?",
        "What was your biggest \"lost in translation\" or embarrassing language mix-up moment?" => "Quel a été ton plus grand moment d'incompréhension ou de confusion embarrassante liée à la langue ?",
        "Which accent in your target language do you find the most challenging or the most charming?" => "Quel accent dans la langue que tu apprends trouves-tu le plus difficile ou le plus charmant ?",
        "What is the most unusual or surprising cultural difference you've experienced firsthand?" => "Quelle est la différence culturelle la plus inhabituelle ou surprenante que tu aies vécue en personne ?",
        "If you could live in any historical era or foreign city for just one month, where and when would it be?" => "Si tu pouvais vivre dans n'importe quelle époque historique ou ville étrangère pendant un mois, quand et où serait-ce ?",
        "What's a local dish from your hometown that visitors must try, but foreigners are usually afraid to?" => "Quel est un plat local de ta ville natale que les visiteurs doivent essayer, mais que les étrangers ont souvent peur de goûter ?",
        "Are you a slow traveler who likes to blend in, or do you try to see as many places as possible?" => "Es-tu un voyageur lent qui aime s'intégrer, ou essaies-tu de voir autant d'endroits que possible ?",
        "What's one item you always pack in your bag, no matter where you're traveling?" => "Quel est un objet que tu mets toujours dans ton sac, peu importe où tu voyages ?",
        "What's a movie, series, or podcast in your target language that helped you improve the most?" => "Quel est le film, la série ou le podcast dans la langue que tu apprends qui t'a le plus aidé à t'améliorer ?",
        "Do you prefer learning through structured study, like books and apps, or complete chaos through immersion and talking to strangers?" => "Préfères-tu apprendre par une étude structurée, avec des livres et des applications, ou dans le chaos total de l'immersion et des discussions avec des inconnus ?",
        "What's a song in a language you don't understand, but you still sing along to with total confidence?" => "Quelle est cette chanson dans une langue que tu ne comprends pas, mais que tu chantes avec une confiance absolue ?",
        "If you were invited to give a 10-minute TED Talk on a topic you love with zero preparation, what would it be?" => "Si on t'invitait à donner une conférence TED de 10 minutes sur un sujet que tu adores sans aucune préparation, de quoi parlerais-tu ?",
        "What is a small, everyday thing in your country that seems totally normal to you, but blows foreigners' minds?" => "Quelle est cette petite thing du quotidien dans ton pays qui te semble totalement normale, mais qui épate les étrangers ?",
        "Are you an early bird or a night owl, and how does that affect your language practice routine?" => "Es-tu un lève-tôt ou un couche-tard, et comment cela affecte-t-il ta routine d'apprentissage des langues ?",
        "What's a skill or hobby you've always wanted to learn, completely unrelated to languages?" => "Quelle compétence ou quel passe-temps as-tu toujours voulu apprendre, sans aucun rapport avec les langues ?",
        "If you could invite anyone in the world to dinner, who would it be?" => "Si tu pouvais inviter n'importe qui dans le monde à dîner, qui serait-ce ?",
        "Would you like to be famous? In what way?" => "Aimerais-tu être célèbre ? De quelle façon ?",
        "What would a perfect day look like to you?" => "À quoi ressemblerait une journée parfaite pour toi ?",
        "When was the last time you sang to yourself? What about to someone else?" => "À quand remonte la dernière fois que tu as chanté pour toi-même ? Et pour quelqu'un d'autre ?",
        "If you could change anything about how you were raised, what would it be?" => "Si tu pouvais changer quelque chose dans la façon dont tu as été élevé, qu'est-ce que ce serait ?",
        "If you could wake up tomorrow with any new skill or quality, what would it be?" => "Si tu pouvais te réveiller demain avec une nouvelle compétence ou qualité, quelle serait-elle ?",
        "If a crystal ball could tell you the truth about yourself, your life, the future, or anything else, what would you ask?" => "Si une boule de cristal pouvait te dire la vérité sur toi-même, ta vie, l'avenir ou n'importe quoi d'autre, que demanderais-tu ?",
        "What is the greatest achievement of your life so far?" => "Quel est le plus grand accomplissement de ta vie jusqu'à présent ?",
        "What do you value most in a friend?" => "Qu'est-ce que tu apprécies le plus chez un ami ?",
        "What is your most treasured memory?" => "Quel est ton souvenir le plus précieux ?",
        "If your house was on fire and you had time to save just one single item after your loved ones and pets were safe, what would you grab and why?" => "Si ta maison était en feu et que tu avais le temps de sauver un seul objet après que tes proches et animaux de compagnie soient en sécurité, que prendrais-tu et pourquoi ?"
    ];

    $insertT = $conn->prepare(
        "INSERT INTO community_welcome_translations (entity_type, entity_id, lang_code, text)
         VALUES (?, ?, 'fr', ?)
         ON DUPLICATE KEY UPDATE text = VALUES(text)"
    );

    $totalT = 0;

    $intros = $conn->query("SELECT id, text_en FROM community_welcome_intros")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($intros as $i) {
        $en = trim($i['text_en']);
        if (isset($fr_intros[$en])) {
            $insertT->execute(['intro', $i['id'], $fr_intros[$en]]);
            $totalT++;
            echo "Saudação ID {$i['id']} traduzida para FR.\n";
        }
    }

    $questions = $conn->query("SELECT id, text_en FROM community_welcome_questions")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($questions as $q) {
        $en = trim($q['text_en']);
        if (isset($fr_questions[$en])) {
            $insertT->execute(['question', $q['id'], $fr_questions[$en]]);
            $totalT++;
            echo "Pergunta ID {$q['id']} traduzida para FR.\n";
        } else {
            echo "Aviso: Nenhuma tradução em francês encontrada para ID {$q['id']}: '$en'\n";
        }
    }

    echo "\n=== Concluído! ===\n";
    echo "✅ Traduções inseridas/atualizadas em Francês: $totalT\n";

} catch (Exception $e) {
    echo "\n❌ Erro na migração: " . $e->getMessage() . "\n";
}
