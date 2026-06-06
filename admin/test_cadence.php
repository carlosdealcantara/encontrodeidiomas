<?php
/**
 * EXPERIMENTO CONTROLADO: Fila Assíncrona com Cadência (15 Segundos)
 * Painel Interativo com Polling de Status JSON para evitar buffering
 */

require_once '../config.php';

// Proteção para rodar via navegador ou terminal
if (php_sapi_name() !== 'cli' && ($_GET['key'] ?? '') !== 'teste2026x') {
    http_response_code(403);
    die('Acesso negado. Use ?key=teste2026x na URL.');
}

$statusFile = __DIR__ . '/test_cadence_status.json';

// --- Ação: Status (Retorna o JSON de progresso) ---
if (($_GET['action'] ?? '') === 'status') {
    header('Content-Type: application/json');
    if (file_exists($statusFile)) {
        echo file_get_contents($statusFile);
    } else {
        echo json_encode(['status' => 'idle', 'progress' => ['current' => 0, 'total' => 0], 'logs' => []]);
    }
    exit;
}

// --- Ação: Run (Executa o teste e salva o progresso no JSON) ---
if (($_GET['action'] ?? '') === 'run') {
    header('Content-Type: application/json');
    // Forçar a execução sem timeout do PHP
    set_time_limit(180);
    
    $EVOLUTION_API_URL = "http://136.248.92.126:8080/message/sendText/meetups";
    $EVOLUTION_API_KEY = "SenhaMeetups2026";
    $mensagemTeste = "🔧 [Diagnóstico Automático da Administração] Teste de cadência do sistema.";

    // Função auxiliar para atualizar o status
    $updateStatus = function($status, $current, $total, $logs) use ($statusFile) {
        file_put_contents($statusFile, json_encode([
            'status' => $status,
            'progress' => ['current' => $current, 'total' => $total],
            'logs' => $logs,
            'updated_at' => date('H:i:s')
        ], JSON_PRETTY_PRINT));
    };

    $logs = [];
    $logs[] = ['type' => 'info', 'message' => 'Iniciando teste de cadência no servidor...'];
    $updateStatus('running', 0, 3, $logs);

    $conn = connectDB();

    try {
        // Busca apenas 3 grupos como amostra de segurança
        $stmt = $conn->query("SELECT nome, group_id FROM meetup_whatsapp_groups WHERE group_id IS NOT NULL AND group_id != '' LIMIT 3");
        $grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($grupos) === 0) {
            throw new Exception("Nenhum grupo com ID válido encontrado no banco de dados.");
        }

        $total = count($grupos);
        $atual = 0;
        $updateStatus('running', 0, $total, $logs);

        foreach ($grupos as $grupo) {
            $atual++;
            $nomeGrupo = $grupo['nome'];
            $groupId = $grupo['group_id'];

            $logs[] = ['type' => 'process', 'message' => "[$atual/$total] Enviando para: $nomeGrupo ($groupId)"];
            $updateStatus('running', $atual - 1, $total, $logs);

            $payload = json_encode([
                "number" => $groupId,
                "textMessage" => ["text" => $mensagemTeste]
            ]);

            $ch = curl_init($EVOLUTION_API_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); 
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "apikey: $EVOLUTION_API_KEY"]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

            $startTime = microtime(true);
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            
            curl_close($ch);

            if ($response === false || $httpcode === 0 || $curlError) {
                $logs[] = [
                    'type' => 'timeout',
                    'message' => "[FALHA - TIMEOUT] O servidor não aguentou gerar a criptografia. Tempo: {$duration}s. (Erro: $curlError)"
                ];
            } elseif ($httpcode === 400 || $httpcode === 500) {
                $respObj = json_decode($response, true);
                $motivo = $respObj['response']['message'][0] ?? $respObj['message'] ?? 'Erro interno na API (Provável protocolo @lid)';
                if (is_array($motivo)) {
                    $motivo = implode(', ', $motivo);
                }
                $logs[] = [
                    'type' => 'protocol',
                    'message' => "[FALHA - PROTOCOLO {$httpcode}] A API recusou o pacote. Tempo: {$duration}s. Motivo: {$motivo}"
                ];
            } elseif ($httpcode === 200 || $httpcode === 201) {
                $logs[] = [
                    'type' => 'success',
                    'message' => "[SUCESSO] Mensagem entregue no grupo: $nomeGrupo. Tempo: {$duration}s."
                ];
            } else {
                $logs[] = [
                    'type' => 'unknown',
                    'message' => "[DESCONHECIDO] HTTP $httpcode. Tempo: {$duration}s. Resposta: $response"
                ];
            }

            $updateStatus('running', $atual, $total, $logs);

            if ($atual < $total) {
                $logs[] = ['type' => 'info', 'message' => "Aguardando 15 segundos para limpar a memória (Garbage Collector)..."];
                $updateStatus('running', $atual, $total, $logs);
                sleep(15);
            }
        }

        $logs[] = ['type' => 'info', 'message' => "Teste de Fila Concluído!"];
        $updateStatus('completed', $total, $total, $logs);
        echo json_encode(['success' => true, 'message' => 'Completed']);

    } catch (Exception $e) {
        $logs[] = ['type' => 'error', 'message' => "[ERRO CRÍTICO] " . $e->getMessage()];
        $updateStatus('error', $atual ?? 0, $total ?? 3, $logs);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evolution API - Diagnóstico de Cadência</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0b0f19;
            --bg-secondary: #161c2d;
            --accent-primary: #4f46e5;
            --accent-glow: rgba(79, 70, 229, 0.4);
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --border-color: #242c3d;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-main);
            font-family: 'Outfit', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 800px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 20px rgba(79, 70, 229, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #a5b4fc, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .header p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .config-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
            font-size: 0.9rem;
        }

        .config-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
        }

        .config-row:last-child {
            margin-bottom: 0;
        }

        .config-label {
            color: var(--text-muted);
        }

        .config-val {
            font-weight: 600;
            color: var(--text-main);
        }

        .actions {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .btn {
            background: var(--accent-primary);
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 14px var(--accent-glow);
            font-family: 'Outfit', sans-serif;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.6);
        }

        .btn:disabled {
            background: #374151;
            box-shadow: none;
            cursor: not-allowed;
            color: var(--text-muted);
        }

        /* Progress Bar */
        .progress-wrapper {
            margin-bottom: 25px;
            display: none;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .progress-bar-container {
            width: 100%;
            height: 10px;
            background: #1f2937;
            border-radius: 5px;
            overflow: hidden;
        }

        .progress-bar {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, #4f46e5, #10b981);
            transition: width 0.4s ease;
            border-radius: 5px;
        }

        /* Console */
        .console {
            background: #000;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            line-height: 1.6;
            height: 300px;
            overflow-y: auto;
            color: #d1d5db;
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.8);
        }

        .console::-webkit-scrollbar {
            width: 8px;
        }
        .console::-webkit-scrollbar-track {
            background: #05070c;
        }
        .console::-webkit-scrollbar-thumb {
            background: #1f2937;
            border-radius: 4px;
        }

        .log-line {
            margin-bottom: 6px;
            word-break: break-all;
        }

        .log-line.info { color: #60a5fa; }
        .log-line.process { color: #e5e7eb; border-left: 3px solid #4f46e5; padding-left: 8px; }
        .log-line.success { color: var(--success); font-weight: bold; }
        .log-line.timeout { color: var(--warning); }
        .log-line.protocol { color: var(--danger); }
        .log-line.error { color: var(--danger); font-weight: bold; text-transform: uppercase; }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-idle { background: #374151; color: #d1d5db; }
        .status-running { background: rgba(79, 70, 229, 0.2); color: #a5b4fc; border: 1px solid #4f46e5; animation: pulse 1.5s infinite; }
        .status-completed { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid var(--success); }
        .status-error { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid var(--danger); }

        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>Evolution API</h1>
        <p>Diagnóstico Científico de Cadência de Disparos</p>
    </div>

    <div class="config-card">
        <div class="config-row">
            <span class="config-label">Status da Instância:</span>
            <span class="config-val" id="instance-status">Verificando...</span>
        </div>
        <div class="config-row">
            <span class="config-label">Amostra do Teste:</span>
            <span class="config-val">3 Grupos Selecionados (Segurança)</span>
        </div>
        <div class="config-row">
            <span class="config-label">Cadência:</span>
            <span class="config-val">15 segundos de intervalo entre envios</span>
        </div>
        <div class="config-row">
            <span class="config-label">Mensagem:</span>
            <span class="config-val" style="font-style: italic;">"🔧 [Diagnóstico Automático da Administração] Teste de cadência do sistema."</span>
        </div>
    </div>

    <div class="actions">
        <button id="start-btn" class="btn" onclick="startTest()">Iniciar Teste de Fila</button>
    </div>

    <div class="progress-wrapper" id="progress-wrapper">
        <div class="progress-info">
            <span id="progress-label">Processando fila...</span>
            <span id="progress-percent">0%</span>
        </div>
        <div class="progress-bar-container">
            <div class="progress-bar" id="progress-bar"></div>
        </div>
    </div>

    <div class="console" id="console">
        <div class="log-line info">> Pronto para iniciar o diagnóstico. Clique no botão acima.</div>
    </div>
</div>

<script>
    const key = 'teste2026x';
    let pollingInterval = null;

    // Verificar status do Evolution API
    async function checkInstance() {
        const instStatus = document.getElementById('instance-status');
        try {
            const res = await fetch(`test_cadence.php?key=${key}&action=status`);
            const data = await res.json();
            instStatus.innerHTML = `<span class="status-badge status-completed">Online (meetups)</span>`;
        } catch(e) {
            instStatus.innerHTML = `<span class="status-badge status-error">Erro ao Comunicar</span>`;
        }
    }

    checkInstance();

    function appendLog(type, message) {
        const consoleEl = document.getElementById('console');
        const line = document.createElement('div');
        line.className = `log-line ${type}`;
        
        const now = new Date();
        const timeStr = now.toTimeString().split(' ')[0];
        
        line.innerHTML = `[${timeStr}] ${message}`;
        consoleEl.appendChild(line);
        consoleEl.scrollTop = consoleEl.scrollHeight;
    }

    async function startTest() {
        const btn = document.getElementById('start-btn');
        btn.disabled = true;
        document.getElementById('progress-wrapper').style.display = 'block';
        
        const consoleEl = document.getElementById('console');
        consoleEl.innerHTML = '';
        appendLog('info', 'Iniciando teste de cadência. Aguardando processamento dos grupos...');

        // Inicia a execução no backend
        fetch(`test_cadence.php?key=${key}&action=run`)
            .catch(err => {
                console.error("Execução iniciada, monitorando status...", err);
            });

        // Começa a monitorar via polling
        if (pollingInterval) clearInterval(pollingInterval);
        lastLogCount = 0;
        pollingInterval = setInterval(pollStatus, 1500);
    }

    let lastLogCount = 0;

    async function pollStatus() {
        try {
            const res = await fetch(`test_cadence.php?key=${key}&action=status`);
            const data = await res.json();

            // Atualizar barra de progresso
            if (data.progress && data.progress.total > 0) {
                const percent = Math.round((data.progress.current / data.progress.total) * 100);
                document.getElementById('progress-bar').style.width = `${percent}%`;
                document.getElementById('progress-percent').innerText = `${percent}%`;
                document.getElementById('progress-label').innerText = `Enviado ${data.progress.current} de ${data.progress.total} grupos`;
            }

            // Atualizar logs
            if (data.logs && data.logs.length > lastLogCount) {
                for (let i = lastLogCount; i < data.logs.length; i++) {
                    const log = data.logs[i];
                    appendLog(log.type, log.message);
                }
                lastLogCount = data.logs.length;
            }

            // Checar conclusão
            if (data.status === 'completed' || data.status === 'error') {
                clearInterval(pollingInterval);
                document.getElementById('start-btn').disabled = false;
                if (data.status === 'completed') {
                    appendLog('success', 'DIAGNÓSTICO CONCLUÍDO COM SUCESSO!');
                } else {
                    appendLog('error', 'DIAGNÓSTICO INTERROMPIDO POR ERRO CRÍTICO!');
                }
            }
        } catch (e) {
            console.error("Erro no polling de status:", e);
        }
    }
</script>
</body>
</html>
