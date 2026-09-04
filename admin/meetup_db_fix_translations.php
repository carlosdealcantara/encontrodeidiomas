<?php
require_once '../config.php';
$conn = connectDB();
header('Content-Type: text/plain; charset=utf-8');

$updates = [
    // --- ESPANHOL (es) ---
    ['es', 0, "¡Oba, gente nueva por aquí! 🥳 Nuestras bienvenidas, {mentions}! Estamos muy felices de tenerte con nosotros."],
    ['es', 1, "¡Qué alegría, gente nueva! 🥳 Nuestras bienvenidas, {mentions}! Estábamos esperándote."],
    ['es', 2, "¡Mira quién acaba de llegar! 🥳 Nuestras bienvenidas, {mentions}! Siéntete en casa, que aquí siempre hay buena conversación."],
    ['es', 3, "¡La familia sigue creciendo! 🥳 {mentions}, ¡qué gusto tenerte aquí! Nuestras bienvenidas."],
    ['es', 4, "¡Yey, más amigos para practicar el idioma! 🥳 Nuestras bienvenidas, {mentions}! Estamos emocionados de charlar contigo."],

    // --- ALEMÃO (de) ---
    ['de', 0, "Oh, neue Leute hier! 🥳 Herzlich willkommen, {mentions}! Wir freuen uns sehr, dich bei uns zu haben."],
    ['de', 1, "Welche Freude, neue Gesichter! 🥳 Willkommen, {mentions}! Wir haben auf dich gewartet."],
    ['de', 2, "Schau mal, wer da kommt! 🥳 Herzlich willkommen, {mentions}! Mach es dir bequem, hier gibt es immer gute Gespräche."],
    ['de', 3, "Die Familie wächst weiter! 🥳 {mentions}, wie schön, dich hier zu haben! Willkommen."],
    ['de', 4, "Yeah, mehr Freunde zum Sprachüben! 🥳 Willkommen, {mentions}! Wir freuen uns darauf, mit dir zu plaudern."],

    // --- RUSSO (ru) ---
    ['ru', 0, "О, новенькие! 🥳 Добро пожаловать, {mentions}! Мы очень рады видеть тебя здесь."],
    ['ru', 1, "Как здорово, новички! 🥳 Добро пожаловать, {mentions}! Мы тебя ждали."],
    ['ru', 2, "Посмотри, кто пришёл! 🥳 Добро пожаловать, {mentions}! Располагайся, у нас всегда интересно."],
    ['ru', 3, "Наша семья растёт! 🥳 {mentions}, как здорово, что ты здесь! Добро пожаловать."],
    ['ru', 4, "Ура, больше друзей для языковой практики! 🥳 Добро пожаловать, {mentions}! Не терпится поговорить с тобой."],

    // --- ITALIANO (it) ---
    ['it', 0, "Oh, nuovi membri! 🥳 Un grande benvenuto, {mentions}! Siamo felicissimi di averti qui con noi."],
    ['it', 1, "Che gioia, nuovi amici! 🥳 Un grande benvenuto, {mentions}! Ti stavamo aspettando."],
    ['it', 2, "Guarda chi è arrivato! 🥳 Un grande benvenuto, {mentions}! Mettiti a tuo agio, qui c'è sempre buona conversazione."],
    ['it', 3, "La famiglia continua a crescere! 🥳 {mentions}, che bello averti qui! Un grande benvenuto."],
    ['it', 4, "Yay, altri amici per praticare la lingua! 🥳 Un grande benvenuto, {mentions}! Non vediamo l'ora di chiacchierare con te."],

    // --- CHINÊS (zh) ---
    ['zh', 0, "哦哦，有新朋友加入！ 🥳 欢迎你，{mentions}！很高兴你能来！"],
    ['zh', 1, "太好了，新成员来了！ 🥳 欢迎你，{mentions}！我们一直在等你。"],
    ['zh', 2, "看看谁来了！ 🥳 欢迎加入，{mentions}！放轻松，这里总有精彩的对话。"],
    ['zh', 3, "大家庭又壮大了！ 🥳 {mentions}，很高兴你在这里！欢迎。"],
    ['zh', 4, "太棒了，又多了练习伙伴！ 🥳 欢迎你，{mentions}！我们都很期待和你交流。"],

    // --- INDONÉSIO (id) ---
    ['id', 0, "Wah, ada anggota baru! 🥳 Selamat datang, {mentions}! Kami sangat senang kamu bergabung."],
    ['id', 1, "Asyik, ada anggota baru! 🥳 Selamat datang, {mentions}! Kami menunggu kamu."],
    ['id', 2, "Lihat siapa yang datang! 🥳 Selamat datang, {mentions}! Santai saja, di sini selalu ada obrolan seru."],
    ['id', 3, "Keluarga terus bertambah! 🥳 {mentions}, senang sekali kamu ada di sini! Selamat datang."],
    ['id', 4, "Yeay, lebih banyak teman untuk berlatih bahasa! 🥳 Selamat datang, {mentions}! Kami tidak sabar untuk mengobrol dengan kamu."],
];

// O script anterior (seed) gerou IDs sequenciais para as intros.
// Vamos pegar os IDs das intros em ordem para atualizar corretamente, assumindo que as 5 primeiras são as que queremos.
$intros = $conn->query("SELECT id FROM community_welcome_intros ORDER BY id LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);

if (count($intros) < 5) {
    die("Erro: menos de 5 intros encontradas.");
}

$stmt = $conn->prepare("UPDATE community_welcome_translations SET text = ? WHERE entity_type = 'intro' AND lang_code = ? AND entity_id = ?");

$count = 0;
foreach ($updates as $upd) {
    $lang = $upd[0];
    $idx = $upd[1];
    $text = $upd[2];
    
    if (!isset($intros[$idx])) continue;
    $entity_id = $intros[$idx];
    
    // Debug
    echo "Updating intro $idx (ID: $entity_id) for lang $lang...\n";
    
    $stmt->execute([$text, $lang, $entity_id]);
    $count += $stmt->rowCount();
}

echo "\nCorreções de gênero e número aplicadas! Linhas afetadas: $count\n";
