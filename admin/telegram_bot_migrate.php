<?php
require_once __DIR__ . '/../config.php';

try {
    $conn = connectDB();
    
    // 1. Tabela de slots (quais meetings ativam notificação)
    $conn->exec("
        CREATE TABLE IF NOT EXISTS telegram_bot_slots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            ativo TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_meeting (meeting_id),
            FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE
        )
    ");
    echo "Tabela telegram_bot_slots criada/verificada com sucesso.<br>";

    // 2. Tabela de logs (anti-duplicidade)
    $conn->exec("
        CREATE TABLE IF NOT EXISTS telegram_bot_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            data_disparo DATE NOT NULL,
            tipo ENUM('inicio','5min','10min','20min') NOT NULL,
            enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY no_duplicate (meeting_id, data_disparo, tipo)
        )
    ");
    echo "Tabela telegram_bot_logs criada/verificada com sucesso.<br>";

    // 3. Tabela de templates editáveis
    $conn->exec("
        CREATE TABLE IF NOT EXISTS telegram_bot_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo ENUM('inicio','5min','10min','20min') NOT NULL UNIQUE,
            texto TEXT NOT NULL,
            ativo TINYINT(1) DEFAULT 1
        )
    ");
    
    // 4. Inserir templates padrão se não existirem
    $check = $conn->query("SELECT COUNT(*) FROM telegram_bot_templates")->fetchColumn();
    if ($check == 0) {
        $stmt = $conn->prepare("INSERT INTO telegram_bot_templates (tipo, texto) VALUES (?, ?)");
        
        $stmt->execute(['inicio', "🔴 *ENCONTRO AO VIVO*\n\n{EMOJI_FLAG} *{IDIOMA}* acabou de começar!\n⏰ {DIA} às {HORA}h\n\n🔗 {MEET_LINK}"]);
        $stmt->execute(['5min', "⏳ *5 minutos de {IDIOMA}*\n\nEncontro já está no ar. Vai que alguém está esperando! 👀\n\n🔗 {MEET_LINK}"]);
        $stmt->execute(['10min', "📍 *10 minutos de {IDIOMA}*\n\nAinda dá tempo de entrar e dar uma conferida. 😉\n\n🔗 {MEET_LINK}"]);
        $stmt->execute(['20min', "🌟 *20 minutos de {IDIOMA}*\n\nO encontro está agitado há 20 minutos!\n\n🔗 {MEET_LINK}"]);
        
        echo "Templates padrão inseridos com sucesso.<br>";
    } else {
        echo "Templates já existem, pulando inserção.<br>";
    }

} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage();
}
?>
