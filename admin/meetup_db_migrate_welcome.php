<?php
require_once '../config.php';

try {
    $conn = connectDB();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Iniciando migração de banco de dados para o sistema de boas-vindas...\n<br>";

    // 1. Criar tabela de intros
    $sql_intros = "CREATE TABLE IF NOT EXISTS community_welcome_intros (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        text_target TEXT NOT NULL COMMENT 'Texto no idioma alvo (pode ser qualquer idioma)',
        text_en     TEXT NOT NULL COMMENT 'Fallback em inglês',
        ativo       TINYINT(1) DEFAULT 1,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql_intros);
    echo "Tabela 'community_welcome_intros' verificada/criada.\n<br>";

    // 2. Criar tabela de perguntas
    $sql_questions = "CREATE TABLE IF NOT EXISTS community_welcome_questions (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        text_target TEXT NOT NULL COMMENT 'Pergunta no idioma alvo',
        text_en     TEXT NOT NULL COMMENT 'Pergunta em inglês (fallback)',
        ativo       TINYINT(1) DEFAULT 1,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql_questions);
    echo "Tabela 'community_welcome_questions' verificada/criada.\n<br>";

    // 3. Adicionar colunas na tabela de grupos
    try {
        $conn->exec("ALTER TABLE meetup_whatsapp_groups ADD COLUMN welcome_enabled TINYINT(1) DEFAULT 0");
        echo "Coluna 'welcome_enabled' adicionada em meetup_whatsapp_groups.\n<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
             echo "Coluna 'welcome_enabled' já existe em meetup_whatsapp_groups.\n<br>";
        } else {
             throw $e;
        }
    }

    try {
        $conn->exec("ALTER TABLE meetup_whatsapp_groups ADD COLUMN lang_code VARCHAR(10) DEFAULT 'en'");
        echo "Coluna 'lang_code' adicionada em meetup_whatsapp_groups.\n<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
             echo "Coluna 'lang_code' já existe em meetup_whatsapp_groups.\n<br>";
        } else {
             throw $e;
        }
    }

    // 4. Popular dados iniciais de intros (apenas se a tabela estiver vazia)
    $stmt = $conn->query("SELECT COUNT(*) FROM community_welcome_intros");
    if ($stmt->fetchColumn() == 0) {
        $intros = [
            ['Oba, gente nova na área! 🥳 Nossas boas-vindas, {mentions}!', 'Look who just joined! 🥳 A warm welcome to you, {mentions}!'],
            ['Temos visitas! Brincadeira, aqui todo mundo é de casa. Boas-vindas, {mentions}! 🍕', 'New faces in the house! Just kidding, everyone belongs here. Welcome, {mentions}! 🍕'],
            ['Agora sim estávamos esperando por vocês! Nossas boas-vindas, {mentions}! 🎈', 'We were waiting for you! So happy you\'re here, {mentions}! 🎈'],
            ['Olha quem chegou! Nossas boas-vindas, {mentions}! O grupo agora está mais completo. ✨', 'Look who\'s here! Welcome, {mentions}! The group just got better. ✨'],
            ['Seja(m) muito bem-vindo(a/os)! O grupo recebe {mentions} de braços abertos. 🎊', 'A huge welcome to {mentions}! We\'re so glad to have you here. 🎊']
        ];
        
        $insertIntro = $conn->prepare("INSERT INTO community_welcome_intros (text_target, text_en) VALUES (?, ?)");
        foreach ($intros as $intro) {
            $insertIntro->execute($intro);
        }
        echo "Saudações iniciais inseridas.\n<br>";
    } else {
        echo "Tabela de saudações já possui dados. Ignorando inserção.\n<br>";
    }

    // 5. Popular dados iniciais de perguntas (apenas se a tabela estiver vazia)
    $stmt = $conn->query("SELECT COUNT(*) FROM community_welcome_questions");
    if ($stmt->fetchColumn() == 0) {
        $questions = [
            ['Quais idiomas você já fala ou está aprendendo?', 'What languages do you speak or are you currently learning?'],
            ['Quais são seus hobbies favoritos?', 'What are your favorite hobbies?'],
            ['Se pudesse viajar amanhã com tudo pago, para onde iria?', 'If you could travel anywhere tomorrow, all expenses paid, where would you go?'],
            ['Qual foi a sua experiência de viagem mais interessante ou inusitada?', 'What was your most interesting or unusual travel experience?'],
            ['Qual é a sua comida favorita de todos os tempos?', 'What is your all-time favorite food?'],
            ['Recomenda um filme ou série? Que tipo de pessoa gostaria disso?', 'Can you recommend a movie or show? What kind of person would enjoy it?'],
            ['Você tem alguma habilidade secreta? Pode contar! 😄', 'Do you have a secret skill? Tell us! 😄'],
            ['Tem algum lugar do mundo cuja cultura você acha fascinante? Qual e por quê?', 'Is there a place in the world whose culture you find fascinating? Which one and why?']
        ];
        
        $insertQuestion = $conn->prepare("INSERT INTO community_welcome_questions (text_target, text_en) VALUES (?, ?)");
        foreach ($questions as $question) {
            $insertQuestion->execute($question);
        }
        echo "Perguntas iniciais inseridas.\n<br>";
    } else {
        echo "Tabela de perguntas já possui dados. Ignorando inserção.\n<br>";
    }

    // 6. Atualizar lang_code dos grupos existentes baseados no nome, para facilitar
    // Aqui fazemos um update bruto inicial se estiver como default.
    $conn->exec("UPDATE meetup_whatsapp_groups SET lang_code = 'es' WHERE nome LIKE '%espanhol%' OR nome LIKE '%spanish%'");
    $conn->exec("UPDATE meetup_whatsapp_groups SET lang_code = 'it' WHERE nome LIKE '%italiano%' OR nome LIKE '%italian%'");
    $conn->exec("UPDATE meetup_whatsapp_groups SET lang_code = 'de' WHERE nome LIKE '%alemão%' OR nome LIKE '%german%' OR nome LIKE '%deutsch%'");
    $conn->exec("UPDATE meetup_whatsapp_groups SET lang_code = 'ru' WHERE nome LIKE '%russo%' OR nome LIKE '%russian%'");
    $conn->exec("UPDATE meetup_whatsapp_groups SET lang_code = 'ja' WHERE nome LIKE '%japonês%' OR nome LIKE '%japanese%'");
    $conn->exec("UPDATE meetup_whatsapp_groups SET lang_code = 'zh' WHERE nome LIKE '%chinês%' OR nome LIKE '%chinese%'");
    $conn->exec("UPDATE meetup_whatsapp_groups SET lang_code = 'pt' WHERE nome LIKE '%português%' OR nome LIKE '%portuguese%'");
    $conn->exec("UPDATE meetup_whatsapp_groups SET lang_code = 'id' WHERE nome LIKE '%indonesio%' OR nome LIKE '%indonesian%' OR nome LIKE '%bahasa%'");
    $conn->exec("UPDATE meetup_whatsapp_groups SET lang_code = 'en' WHERE nome LIKE '%ingles%' OR nome LIKE '%english%'");

    echo "Migração concluída com sucesso!\n";

} catch (Exception $e) {
    echo "Erro na migração: " . $e->getMessage();
}
