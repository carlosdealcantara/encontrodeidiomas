<?php
/**
 * Script de Seed / Atualização dos Templates da Régua de Inadimplência
 * Pode ser executado via navegador por admin autenticado ou com token secreto.
 */
session_start();
require_once __DIR__ . '/../config.php';

$token_secreto = '83x9aZ2pLQw1';
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$hasToken = (isset($_GET['token']) && $_GET['token'] === $token_secreto);

if (!$isAdmin && !$hasToken) {
    die("Acesso negado.");
}

$conn = connectDB();

echo "<h2>Atualizando Templates de Cobrança e Régua Pós-Vencimento</h2><hr>";

$novosTemplates = [
    // --- INGLÊS ('en') ---
    [
        'lang_id'    => 'en',
        'cenario'    => 'Suspensão de Acesso (Kick Our Classes)',
        'dias_antes' => -1,
        'texto'      => "Olá, {nome}! Notamos que o ciclo da sua mentoria de {idioma} encerrou e não identificamos a renovação até o prazo limite. Por conta disso, seu acesso aos grupos de aulas foi pausado temporariamente.\n\n📄 *Já enviou o comprovante?* Tranquilo! Como a nossa verificação financeira é manual, se você realizou o pagamento dentro do prazo, assim que conferirmos seu comprovante o acesso será restabelecido normalmente, mantendo 100% dos seus benefícios e o valor original da sua mensalidade, sem nenhuma cobrança adicional.\n\n⚠️ *Perdeu o prazo de renovação?*\nCaso o prazo tenha expirado, você tem duas opções para regularizar sua situação e retornar às aulas:\n\n🔹 *Opção 1 (Previsão Contratual):* Atualizar sua mensalidade para o novo valor com o reajuste por perda de pontualidade: *{valor_reajustado}/mês*, sem taxa adicional para reativação hoje.\n\n🔹 *Opção 2 (Alternativa para manter sua oferta):* Como alternativa flexível, você pode optar por manter o valor da sua mensalidade atual (*{valor_antigo}/mês*) mediante a quitação da taxa de regularização por atraso (a partir de *{multa_base}* + R$ 1,00 por dia decorrido).\n\nSe você já pagou, basta aguardar nossa confirmação por aqui. Caso deseje regularizar por alguma das opções acima, responda nos avisando se prefere a *Opção 1* ou a *Opção 2* para enviarmos os dados de liberação."
    ],
    [
        'lang_id'    => 'en',
        'cenario'    => 'Lembrete de Vaga (+2 dias de atraso)',
        'dias_antes' => -2,
        'texto'      => "Olá, {nome}! Passando para lembrar que seu acesso às aulas de {idioma} está pausado temporariamente aguardando a renovação do seu ciclo.\n\nCaso queira retornar mantendo a sua mensalidade original de *{valor_antigo}/mês*, a taxa de regularização hoje está em *{multa_total}* ({dias_atraso} dias de atraso).\n\nSe preferir a *Opção 1* de reajuste para *{valor_reajustado}/mês*, você não paga nenhuma taxa de atraso hoje!\n\nBasta nos avisar por aqui qual opção prefere para liberarmos seu retorno."
    ],
    [
        'lang_id'    => 'en',
        'cenario'    => 'Última Chamada de Regularização (+5 dias de atraso)',
        'dias_antes' => -5,
        'texto'      => "Olá, {nome}! Esta é a nossa última chamada referente à sua vaga na mentoria de {idioma}.\n\nPara reativar suas aulas mantendo a condição especial de *{valor_antigo}/mês*, o valor da taxa de regularização hoje é de *{multa_total}* ({dias_atraso} dias de atraso).\n\nOu, se preferir, você pode reativar pela *Opção 1* de reajuste (*{valor_reajustado}/mês*) sem taxa adicional hoje.\n\nCaso decida dar uma pausa nos estudos neste momento, você continuará como membro da nossa comunidade nos grupos abertos. Se desejar retornar, me dê um retorno por aqui até o final do dia!"
    ],

    // --- ESPANHOL ('es') ---
    [
        'lang_id'    => 'es',
        'cenario'    => 'Suspensão de Acesso (Kick Our Classes)',
        'dias_antes' => -1,
        'texto'      => "Olá, {nome}! Notamos que o ciclo da sua mentoria de {idioma} encerrou e não identificamos a renovação até o prazo limite. Por conta disso, seu acesso aos grupos de aulas foi pausado temporariamente.\n\n📄 *Já enviou o comprovante?* Tranquilo! Como a nossa verificação financeira é manual, se você realizou o pagamento dentro do prazo, assim que conferirmos seu comprovante o acesso será restabelecido normalmente, mantendo 100% dos seus benefícios e o valor original da sua mensalidade, sem nenhuma cobrança adicional.\n\n⚠️ *Perdeu o prazo de renovação?*\nCaso o prazo tenha expirado, você tem duas opções para regularizar sua situação e retornar às aulas:\n\n🔹 *Opção 1 (Previsão Contratual):* Atualizar sua mensalidade para o novo valor com o reajuste por perda de pontualidade: *{valor_reajustado}/mês*, sem taxa adicional para reativação hoje.\n\n🔹 *Opção 2 (Alternativa para manter sua oferta):* Como alternativa flexível, você pode optar por manter o valor da sua mensalidade atual (*{valor_antigo}/mês*) mediante a quitação da taxa de regularização por atraso (a partir de *{multa_base}* + R$ 1,00 por dia decorrido).\n\nSe você já pagou, basta aguardar nossa confirmação por aqui. Caso deseje regularizar por alguma das opções acima, responda nos avisando se prefere a *Opção 1* ou a *Opção 2* para enviarmos os dados de liberação."
    ],
    [
        'lang_id'    => 'es',
        'cenario'    => 'Lembrete de Vaga (+2 dias de atraso)',
        'dias_antes' => -2,
        'texto'      => "Olá, {nome}! Passando para lembrar que seu acesso às aulas de {idioma} está pausado temporariamente aguardando a renovação do seu ciclo.\n\nCaso queira retornar mantendo a sua mensalidade original de *{valor_antigo}/mês*, a taxa de regularização hoje está em *{multa_total}* ({dias_atraso} dias de atraso).\n\nSe preferir a *Opção 1* de reajuste para *{valor_reajustado}/mês*, você não paga nenhuma taxa de atraso hoje!\n\nBasta nos avisar por aqui qual opção prefere para liberarmos seu retorno."
    ],
    [
        'lang_id'    => 'es',
        'cenario'    => 'Última Chamada de Regularização (+5 dias de atraso)',
        'dias_antes' => -5,
        'texto'      => "Olá, {nome}! Esta é a nossa última chamada referente à sua vaga na mentoria de {idioma}.\n\nPara reativar suas aulas mantendo a condição especial de *{valor_antigo}/mês*, o valor da taxa de regularização hoje é de *{multa_total}* ({dias_atraso} dias de atraso).\n\nOu, se preferir, você pode reativar pela *Opção 1* de reajuste (*{valor_reajustado}/mês*) sem taxa adicional hoje.\n\nCaso decida dar uma pausa nos estudos neste momento, você continuará como membro da nossa comunidade nos grupos abertos. Se desejar retornar, me dê um retorno por aqui até o final do dia!"
    ],
];

foreach ($novosTemplates as $tpl) {
    // Verifica se já existe para este idioma e dias_antes
    $stmtCheck = $conn->prepare("SELECT id FROM mentoria_mensagens WHERE lang_id = ? AND dias_antes = ?");
    $stmtCheck->execute([$tpl['lang_id'], $tpl['dias_antes']]);
    $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        $stmtUp = $conn->prepare("UPDATE mentoria_mensagens SET cenario = ?, texto = ?, ativo = 1 WHERE id = ?");
        $stmtUp->execute([$tpl['cenario'], $tpl['texto'], $existente['id']]);
        echo "<p>✅ Template atualizado: [{$tpl['lang_id']}] {$tpl['cenario']} (dias_antes: {$tpl['dias_antes']})</p>";
    } else {
        $stmtIn = $conn->prepare("INSERT INTO mentoria_mensagens (cenario, texto, dias_antes, ativo, ativo_telegram, lang_id) VALUES (?, ?, ?, 1, 1, ?)");
        $stmtIn->execute([$tpl['cenario'], $tpl['texto'], $tpl['dias_antes'], $tpl['lang_id']]);
        echo "<p>✨ Template criado: [{$tpl['lang_id']}] {$tpl['cenario']} (dias_antes: {$tpl['dias_antes']})</p>";
    }
}

echo "<br><h3>Todos os templates foram sincronizados com sucesso no banco de dados!</h3>";
