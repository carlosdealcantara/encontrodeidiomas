<?php
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $content = file_get_contents($envFile);
    
    // Adiciona as credenciais se não existirem
    if (strpos($content, 'ADMIN_USER') === false) {
        $content .= "\n# Credenciais do Painel Administrativo\nADMIN_USER=admin\nADMIN_PASS=encontro2023\n";
        file_put_contents($envFile, $content);
        echo "<h3>✅ Sucesso! Credenciais de segurança adicionadas ao servidor.</h3>";
    } else {
        echo "<h3>ℹ️ As credenciais já existem no servidor.</h3>";
    }
} else {
    echo "<h3>❌ Erro: Arquivo .env não encontrado no servidor.</h3>";
}
?>
