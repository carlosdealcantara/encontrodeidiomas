<?php
require_once __DIR__ . '/config.php';

$conn = connectDB();

echo "Iniciando migração de banco de dados para o Bot de Meetups...\n";

// 1. Adicionar greeting em languages
try {
    $conn->exec("ALTER TABLE languages ADD COLUMN greeting VARCHAR(100) DEFAULT 'Welcome!' AFTER name_en");
    echo "Coluna 'greeting' adicionada em 'languages' (ou já existia).\n";
} catch (PDOException $e) {
    echo "Coluna 'greeting' já existe.\n";
}

// 2. Tabela de grupos
$conn->exec("
    CREATE TABLE IF NOT EXISTS meetup_whatsapp_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        group_id VARCHAR(100) NOT NULL,
        categoria ENUM('multi_idioma', 'especifico') NOT NULL DEFAULT 'multi_idioma',
        language_id INT NULL,
        ativo TINYINT(1) DEFAULT 1,
        notas TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE SET NULL
    )
");
echo "Tabela 'meetup_whatsapp_groups' criada.\n";

// 3. Tabela de templates
$conn->exec("
    CREATE TABLE IF NOT EXISTS meetup_whatsapp_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cenario VARCHAR(100) NOT NULL,
        minutos_antes INT NOT NULL DEFAULT 0,
        template_texto TEXT NOT NULL,
        ativo TINYINT(1) DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");
echo "Tabela 'meetup_whatsapp_templates' criada.\n";

// Inserir templates padrão se não existirem
$stmt = $conn->query("SELECT COUNT(*) FROM meetup_whatsapp_templates");
if ($stmt->fetchColumn() == 0) {
    $defaultTemplate = "Estamos começando nosso encontro de {IDIOMA} neste exato momento!\n{EMOJI_FLAGS}\n{SAUDACAO}\n\n{EMOJI_FLAG} Encontro Online de {IDIOMA}\n{MEET_LINK}\n\n*Ao entrar na chamada, clique no botão CC e selecione o idioma para ativar legendas.* Digite seu Instagram no chat! Se chegou depois, tranquilo! Teremos o replay da chamada.\n\nQuer ficar por dentro? Página de {idioma} no Instagram {EMOJI_FLAG}\n{INSTAGRAM_LINK}";
    
    $reminderTemplate = "Faltam poucas horas para o nosso encontro de {IDIOMA}!\n{EMOJI_FLAGS}\nPrepare-se! O link será enviado aqui no grupo na hora exata do encontro.\n\nEnquanto isso, siga nossa página:\n{INSTAGRAM_LINK}";

    $ins = $conn->prepare("INSERT INTO meetup_whatsapp_templates (cenario, minutos_antes, template_texto) VALUES (?, ?, ?)");
    $ins->execute(['Aviso de Início (Hora Exata)', 0, $defaultTemplate]);
    $ins->execute(['Lembrete (2 horas antes)', 120, $reminderTemplate]);
    echo "Templates padrão inseridos.\n";
}

// 4. Tabela de logs
$conn->exec("
    CREATE TABLE IF NOT EXISTS meetup_whatsapp_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grupo_id INT NOT NULL,
        meeting_id INT NOT NULL,
        template_id INT NOT NULL,
        data_disparo DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");
echo "Tabela 'meetup_whatsapp_logs' criada.\n";

// 5. Configuração de senha dos hosts
$conn->exec("INSERT IGNORE INTO settings (setting_key, setting_value, label, category, type) VALUES ('hosts_app_password', 'meetup2026', 'Senha de Acesso dos Hosts', 'Segurança', 'text')");
echo "Senha padrão 'meetup2026' configurada na tabela settings.\n";

echo "Migração concluída com sucesso!\n";
