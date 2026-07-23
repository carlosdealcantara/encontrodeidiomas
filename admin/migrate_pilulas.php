<?php
require_once __DIR__ . '/../config.php';

try {
    $conn = connectDB();
    
    // 1. Tabela pilulas_conteudo
    $conn->exec("
        CREATE TABLE IF NOT EXISTS pilulas_conteudo (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo ENUM('audio','texto','enquete') NOT NULL,
            titulo VARCHAR(255) NOT NULL,
            audio_path VARCHAR(500) NULL,
            texto_corpo TEXT NULL,
            enquete_pergunta VARCHAR(500) NULL,
            enquete_opcoes JSON NULL,
            rodape_tipo ENUM('nenhum','assinatura','cta') NOT NULL DEFAULT 'assinatura',
            ativo TINYINT(1) NOT NULL DEFAULT 0,
            vezes_enviado INT NOT NULL DEFAULT 0,
            ultimo_envio DATE NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "Tabela pilulas_conteudo criada/verificada.<br>";

    // 2. Tabela pilulas_log
    $conn->exec("
        CREATE TABLE IF NOT EXISTS pilulas_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pilula_id INT NOT NULL,
            group_jid VARCHAR(100) NOT NULL,
            enviado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status ENUM('ok','erro') NOT NULL DEFAULT 'ok',
            detalhe TEXT NULL
        )
    ");
    echo "Tabela pilulas_log criada/verificada.<br>";

    // 3. Tabela pilulas_config
    $conn->exec("
        CREATE TABLE IF NOT EXISTS pilulas_config (
            chave VARCHAR(100) PRIMARY KEY,
            valor TEXT NOT NULL
        )
    ");
    echo "Tabela pilulas_config criada/verificada.<br>";

    // 4. Inserir configurações padrão se não existirem
    $check = $conn->query("SELECT COUNT(*) FROM pilulas_config")->fetchColumn();
    if ($check == 0) {
        $stmt = $conn->prepare("INSERT INTO pilulas_config (chave, valor) VALUES (?, ?)");
        
        $configs = [
            ['group_jid', ''],
            ['min_mensagens', '30'],
            ['cooldown_min_dias', '3'],
            ['cooldown_max_dias', '7'],
            ['horario_inicio', '8'],
            ['horario_fim', '21'],
            ['assinatura_texto', "⚡ _Dica oferecida pela Mentoria de Inglês_\nhttps://encontrodeidiomas.com.br/mentoria"],
            ['cta_texto', "🎯 *Quer acelerar seu inglês com aulas ao vivo?*\n👉 https://encontrodeidiomas.com.br/mentoria"]
        ];

        foreach ($configs as $cfg) {
            $stmt->execute($cfg);
        }
        echo "Configurações padrão inseridas.<br>";
    } else {
        echo "Configurações já existem.<br>";
    }

} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage();
}
?>
