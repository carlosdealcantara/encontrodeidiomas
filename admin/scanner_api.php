<?php
require_once '../config.php';
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Acesso Negado.");
}

$conn = connectDB();
$stmt = $conn->query("SELECT id, nome, group_id FROM meetup_whatsapp_groups WHERE ativo = 1 ORDER BY nome ASC");
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Varredura Diagnóstica de Grupos</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #f1f5f9; font-family: 'Outfit', sans-serif; padding: 40px; }
        .card { background: #1e293b; padding: 25px; border-radius: 15px; max-width: 900px; margin: 0 auto; }
        .btn { padding: 12px 24px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .btn:hover { background: #2563eb; }
        .log { margin-top: 20px; max-height: 500px; overflow-y: auto; background: #000; padding: 15px; border-radius: 8px; font-family: monospace; }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
        .info { color: #3b82f6; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Varredura Profunda: Evolution API</h2>
        <p>Este sistema testará o disparo real para <b><?= count($grupos) ?> grupos</b>, um por um. Como o teste roda no seu navegador, não há risco do servidor derrubar o processo por tempo limite.</p>
        <p>O teste enviará a mensagem: <i>"🔧 Teste de Diagnóstico do Sistema (Pode Ignorar)"</i>.</p>
        <button class="btn" onclick="iniciarVarredura()">Iniciar Varredura Completa</button>
        <button class="btn" style="background:#ef4444; margin-left:10px;" onclick="pararVarredura()">Parar</button>
        
        <div class="log" id="logBox">
            <div style="color: #64748b;">Aguardando início...</div>
        </div>
    </div>

    <script>
        const grupos = <?= json_encode($grupos) ?>;
        let index = 0;
        let rodando = false;

        function logar(msg, classe = '') {
            const box = document.getElementById('logBox');
            box.innerHTML += `<div class="${classe}">${msg}</div>`;
            box.scrollTop = box.scrollHeight;
        }

        function iniciarVarredura() {
            if (rodando) return;
            rodando = true;
            index = 0;
            document.getElementById('logBox').innerHTML = '';
            logar('Iniciando varredura...', 'info');
            processarProximo();
        }

        function pararVarredura() {
            rodando = false;
            logar('Varredura interrompida pelo usuário.', 'warning');
        }

        async function processarProximo() {
            if (!rodando) return;
            if (index >= grupos.length) {
                logar('✅ Varredura Concluída!', 'success');
                rodando = false;
                return;
            }

            const grupo = grupos[index];
            logar(`[${index + 1}/${grupos.length}] Testando '${grupo.nome}' (${grupo.group_id})...`, 'info');
            
            try {
                const tempoInicio = Date.now();
                const res = await fetch(`scanner_worker.php?group_id=${encodeURIComponent(grupo.group_id)}`);
                const tempoTotal = ((Date.now() - tempoInicio) / 1000).toFixed(2);
                
                if (res.ok) {
                    const data = await res.json();
                    if (data.status === 200 || data.status === 201) {
                        logar(`&nbsp;&nbsp;-> ✅ SUCESSO! Tempo: ${tempoTotal}s`, 'success');
                    } else if (data.status === 400) {
                        logar(`&nbsp;&nbsp;-> ❌ REJEITADO (HTTP 400). Tempo: ${tempoTotal}s. Motivo: WhatsApp bloqueou o ID.`, 'error');
                    } else if (data.status === 0) {
                        logar(`&nbsp;&nbsp;-> ⚠️ TIMEOUT (Congelamento). Tempo: ${tempoTotal}s. A API não conseguiu processar este grupo.`, 'warning');
                    } else {
                        logar(`&nbsp;&nbsp;-> ❌ ERRO DESCONHECIDO (HTTP ${data.status}). Tempo: ${tempoTotal}s`, 'error');
                    }
                } else {
                    logar(`&nbsp;&nbsp;-> ❌ Erro de rede ou servidor ao testar.`, 'error');
                }
            } catch (e) {
                logar(`&nbsp;&nbsp;-> ❌ Erro no Javascript: ${e.message}`, 'error');
            }

            index++;
            // Pausa de 2 segundos para não sobrecarregar
            setTimeout(processarProximo, 2000);
        }
    </script>
</body>
</html>
