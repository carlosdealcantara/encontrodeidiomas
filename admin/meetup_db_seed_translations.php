<?php
require_once '../config.php';
$conn = connectDB();

header('Content-Type: text/plain; charset=utf-8');

echo "=== Populando traduções completas ===\n\n";

// Busca IDs das intros e perguntas
$intros = $conn->query("SELECT id, text_en FROM community_welcome_intros ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$questions = $conn->query("SELECT id, text_en FROM community_welcome_questions ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

echo "Intros encontradas: " . count($intros) . "\n";
echo "Perguntas encontradas: " . count($questions) . "\n\n";

// MAPEAMENTO COMPLETO DE TRADUÇÕES DAS INTROS
// Cada intro tem tradução para: es, pt, de, ru, ja, zh, id, it
// As IDs são sequenciais a partir do que existe no banco.
// IMPORTANTE: usamos array indexado pela posição (0-based) porque não sabemos os IDs exatos.

$intro_translations = [
    // intro 0 (primeira intro)
    [
        'es' => "¡Oba, gente nueva por aquí! 🥳 Nuestras bienvenidas, {mentions}! Estamos muy felices de tenerlos con nosotros.",
        'pt' => "Oba, gente nova na área! 🥳 Nossas boas-vindas, {mentions}! Que ótimo ter vocês aqui.",
        'de' => "Oh, neue Leute hier! 🥳 Herzlich willkommen, {mentions}! Wir freuen uns sehr, euch bei uns zu haben.",
        'ru' => "О, новенькие! 🥳 Добро пожаловать, {mentions}! Мы очень рады видеть вас здесь.",
        'ja' => "おお、新しい仲間が来た！ 🥳 ようこそ、{mentions}！一緒に楽しみましょう！",
        'zh' => "哦哦，有新朋友加入！ 🥳 欢迎你们，{mentions}！很高兴你们能来！",
        'id' => "Wah, ada anggota baru! 🥳 Selamat datang, {mentions}! Kami sangat senang kalian bergabung.",
        'it' => "Oh, nuovi membri! 🥳 Benvenuti, {mentions}! Siamo felicissimi di avervi qui con noi.",
    ],
    // intro 1
    [
        'es' => "¡Qué alegría, gente nueva! 🥳 Sean bienvenidos, {mentions}! Estábamos esperándolos.",
        'pt' => "Que alegria, gente nova! 🥳 Bem-vindos, {mentions}! Estávamos esperando por vocês.",
        'de' => "Welche Freude, neue Gesichter! 🥳 Willkommen, {mentions}! Wir haben auf euch gewartet.",
        'ru' => "Как здорово, новички! 🥳 Добро пожаловать, {mentions}! Мы вас ждали.",
        'ja' => "やったー、新しい仲間！ 🥳 {mentions}さん、よく来てくれました！お待ちしてましたよ。",
        'zh' => "太好了，新成员来了！ 🥳 欢迎{mentions}！我们一直在等你们。",
        'id' => "Asyik, ada anggota baru! 🥳 Selamat datang, {mentions}! Kami menunggu kalian.",
        'it' => "Che gioia, nuovi amici! 🥳 Benvenuti, {mentions}! Vi stavamo aspettando.",
    ],
    // intro 2
    [
        'es' => "¡Miren quiénes acaban de llegar! 🥳 ¡Nuestras bienvenidas, {mentions}! Pónganse cómodos, que aquí siempre hay buena conversación.",
        'pt' => "Olha quem chegou! 🥳 Nossas boas-vindas, {mentions}! Fiquem à vontade, aqui sempre tem boa conversa.",
        'de' => "Schaut mal, wer da kommt! 🥳 Herzlich willkommen, {mentions}! Macht es euch bequem, hier gibt es immer gute Gespräche.",
        'ru' => "Посмотрите, кто пришёл! 🥳 Добро пожаловать, {mentions}! Располагайтесь, у нас всегда интересно.",
        'ja' => "見て見て、新しい人が来た！ 🥳 {mentions}さん、ようこそ！ゆっくりしていってね。",
        'zh' => "看看谁来了！ 🥳 欢迎加入，{mentions}！放轻松，这里总有精彩的对话。",
        'id' => "Lihat siapa yang datang! 🥳 Selamat datang, {mentions}! Santai saja, di sini selalu ada obrolan seru.",
        'it' => "Guardate chi è arrivato! 🥳 Benvenuti, {mentions}! Mettetevi comodi, qui c'è sempre buona conversazione.",
    ],
    // intro 3
    [
        'es' => "¡La familia sigue creciendo! 🥳 {mentions}, ¡cuánto gusto de tenerlos aquí! Bienvenidos.",
        'pt' => "A família continua crescendo! 🥳 {mentions}, que bom ter vocês aqui! Bem-vindos.",
        'de' => "Die Familie wächst weiter! 🥳 {mentions}, wie schön, euch hier zu haben! Willkommen.",
        'ru' => "Наша семья растёт! 🥳 {mentions}, как здорово, что вы здесь! Добро пожаловать.",
        'ja' => "仲間がどんどん増えてる！ 🥳 {mentions}さん、来てくれてよかった！ようこそ。",
        'zh' => "大家庭又壮大了！ 🥳 {mentions}，很高兴你们在这里！欢迎。",
        'id' => "Keluarga terus bertambah! 🥳 {mentions}, senang sekali kalian ada di sini! Selamat datang.",
        'it' => "La famiglia continua a crescere! 🥳 {mentions}, che bello avervi qui! Benvenuti.",
    ],
    // intro 4
    [
        'es' => "¡Yey, más amigos para practicar el idioma! 🥳 Bienvenidos, {mentions}! Estamos emocionados de charlar con ustedes.",
        'pt' => "Ebaaa, mais amigos para praticar o idioma! 🥳 Bem-vindos, {mentions}! Mal podemos esperar para conversar com vocês.",
        'de' => "Yeah, mehr Freunde zum Sprachüben! 🥳 Willkommen, {mentions}! Wir freuen uns darauf, mit euch zu plaudern.",
        'ru' => "Ура, больше друзей для языковой практики! 🥳 Добро пожаловать, {mentions}! Не терпится поговорить с вами.",
        'ja' => "やったー！また言語練習の仲間が増えた！ 🥳 {mentions}さん、ようこそ！一緒に話しましょう。",
        'zh' => "太棒了，又多了练习伙伴！ 🥳 欢迎你们，{mentions}！我们都很期待和你们交流。",
        'id' => "Yeay, lebih banyak teman untuk berlatih bahasa! 🥳 Selamat datang, {mentions}! Kami tidak sabar untuk mengobrol dengan kalian.",
        'it' => "Yay, altri amici per praticare la lingua! 🥳 Benvenuti, {mentions}! Non vediamo l'ora di chiacchierare con voi.",
    ],
];

// MAPEAMENTO COMPLETO DAS PERGUNTAS
// 8 perguntas, cada uma com tradução para os 8 idiomas
$question_translations = [
    // pergunta 0: "Que idiomas você fala ou está aprendendo?"
    [
        'es' => "¿Qué idiomas hablas o estás aprendiendo?",
        'pt' => "Que idiomas você fala ou está aprendendo?",
        'de' => "Welche Sprachen sprichst du oder lernst du gerade?",
        'ru' => "На каких языках вы говорите или что учите?",
        'ja' => "どんな言語を話せますか？または今学んでいますか？",
        'zh' => "你会说哪些语言？或者你正在学习什么语言？",
        'id' => "Bahasa apa yang kamu bisa atau sedang kamu pelajari?",
        'it' => "Quali lingue parli o stai imparando?",
    ],
    // pergunta 1: "De onde você é e onde mora agora?"
    [
        'es' => "¿De dónde eres y dónde vives ahora?",
        'pt' => "De onde você é e onde mora agora?",
        'de' => "Woher kommst du und wo wohnst du gerade?",
        'ru' => "Откуда вы родом и где живёте сейчас?",
        'ja' => "どちらのご出身で、今はどこに住んでいますか？",
        'zh' => "你来自哪里？现在住在哪里？",
        'id' => "Kamu berasal dari mana dan sekarang tinggal di mana?",
        'it' => "Da dove vieni e dove vivi adesso?",
    ],
    // pergunta 2: "Quais são os seus hobbies favoritos?"
    [
        'es' => "¿Cuáles son tus pasatiempos favoritos?",
        'pt' => "Quais são os seus hobbies favoritos?",
        'de' => "Was sind deine liebsten Hobbys?",
        'ru' => "Каковы ваши любимые увлечения?",
        'ja' => "好きな趣味は何ですか？",
        'zh' => "你最喜欢的爱好是什么？",
        'id' => "Apa hobi favoritmu?",
        'it' => "Quali sono i tuoi hobby preferiti?",
    ],
    // pergunta 3: "Por que você decidiu aprender esse idioma?"
    [
        'es' => "¿Por qué decidiste aprender este idioma?",
        'pt' => "Por que você decidiu aprender esse idioma?",
        'de' => "Warum hast du dich entschieden, diese Sprache zu lernen?",
        'ru' => "Почему вы решили учить этот язык?",
        'ja' => "なぜこの言語を勉強しようと思いましたか？",
        'zh' => "你为什么决定学习这门语言？",
        'id' => "Kenapa kamu memutuskan untuk belajar bahasa ini?",
        'it' => "Perché hai deciso di imparare questa lingua?",
    ],
    // pergunta 4: "Para onde você viajaria se pudesse ir amanhã?"
    [
        'es' => "¿A dónde viajarías si pudieras ir mañana?",
        'pt' => "Para onde você viajaria se pudesse ir amanhã?",
        'de' => "Wohin würdest du reisen, wenn du morgen aufbrechen könntest?",
        'ru' => "Куда бы вы поехали, если бы могли отправиться завтра?",
        'ja' => "明日どこへでも行けるとしたら、どこへ旅行しますか？",
        'zh' => "如果你明天可以去任何地方，你会去哪里旅行？",
        'id' => "Ke mana kamu mau pergi kalau bisa pergi besok?",
        'it' => "Dove viaggeresti se potessi partire domani?",
    ],
    // pergunta 5: "Qual é a sua comida favorita?"
    [
        'es' => "¿Cuál es tu comida favorita?",
        'pt' => "Qual é a sua comida favorita?",
        'de' => "Was ist dein Lieblingsessen?",
        'ru' => "Какая ваша любимая еда?",
        'ja' => "好きな食べ物は何ですか？",
        'zh' => "你最喜欢的食物是什么？",
        'id' => "Apa makanan favoritmu?",
        'it' => "Qual è il tuo cibo preferito?",
    ],
    // pergunta 6: "Que tipo de música ou filmes você gosta?"
    [
        'es' => "¿Qué tipo de música o películas te gustan?",
        'pt' => "Que tipo de música ou filmes você gosta?",
        'de' => "Welche Art von Musik oder Filmen magst du?",
        'ru' => "Какую музыку или фильмы вы любите?",
        'ja' => "どんな音楽や映画が好きですか？",
        'zh' => "你喜欢什么类型的音乐或电影？",
        'id' => "Kamu suka musik atau film jenis apa?",
        'it' => "Che tipo di musica o film ti piacciono?",
    ],
    // pergunta 7: "Conta um fato curioso ou engraçado sobre você."
    [
        'es' => "Cuéntanos un dato curioso o divertido sobre ti.",
        'pt' => "Conta um fato curioso ou engraçado sobre você.",
        'de' => "Erzähl uns eine interessante oder lustige Tatsache über dich.",
        'ru' => "Расскажите нам интересный или забавный факт о себе.",
        'ja' => "あなたについて面白いことや珍しいことを教えてください。",
        'zh' => "跟我们说一个关于你的有趣或有意思的事实吧。",
        'id' => "Ceritakan satu fakta menarik atau lucu tentang dirimu.",
        'it' => "Dicci un fatto curioso o divertente su di te.",
    ],
];

$stmt = $conn->prepare("INSERT INTO community_welcome_translations (entity_type, entity_id, lang_code, text) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE text = VALUES(text)");

$insertedIntros = 0;
foreach ($intros as $idx => $intro) {
    if (!isset($intro_translations[$idx])) continue;
    foreach ($intro_translations[$idx] as $lc => $text) {
        $stmt->execute(['intro', $intro['id'], $lc, $text]);
        $insertedIntros++;
    }
}

$insertedQs = 0;
foreach ($questions as $idx => $q) {
    if (!isset($question_translations[$idx])) continue;
    foreach ($question_translations[$idx] as $lc => $text) {
        $stmt->execute(['question', $q['id'], $lc, $text]);
        $insertedQs++;
    }
}

echo "✅ Traduções de intros inseridas/atualizadas: $insertedIntros\n";
echo "✅ Traduções de perguntas inseridas/atualizadas: $insertedQs\n";
echo "\nFeito! Todas as traduções estão completas.\n";
