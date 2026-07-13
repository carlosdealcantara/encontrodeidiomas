<?php
session_start();
require_once '../config.php';
require_once '../includes/whatsapp_helper.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();
$msg = null;

// Lidar com o salvamento das flags
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_flags'])) {
    $odysee_modo = $_POST['wpp_odysee_modo'] ?? '0';
    if (!in_array($odysee_modo, ['0', 'hosts', 'full', '1'])) {
        $odysee_modo = '0';
    }
    if ($odysee_modo === '1') $odysee_modo = 'full';

    $flags = [
        'wpp_meetups_hourly_ativo' => isset($_POST['wpp_meetups_hourly_ativo']) ? '1' : '0',
        'wpp_meetups_daily_ativo'  => isset($_POST['wpp_meetups_daily_ativo']) ? '1' : '0',
        'wpp_mentoria_ativo'       => isset($_POST['wpp_mentoria_ativo']) ? '1' : '0',
        'wpp_odysee_ativo'         => $odysee_modo
    ];

    try {
        $stmt = $conn->prepare("UPDATE system_settings SET valor = ? WHERE chave = ?");
        foreach ($flags as $key => $value) {
            $stmt->execute([$value, $key]);
        }
        $msg = "Configurações atualizadas com sucesso!";
    } catch (PDOException $e) {
        $msg = "Erro ao atualizar configurações: " . $e->getMessage();
    }
}

// Buscar valores atuais
$meetups_hourly = getSystemSetting($conn, 'wpp_meetups_hourly_ativo', '0');
$meetups_daily  = getSystemSetting($conn, 'wpp_meetups_daily_ativo', '0');
$mentoria_ativo = getSystemSetting($conn, 'wpp_mentoria_ativo', '1');
$odysee_ativo   = getSystemSetting($conn, 'wpp_odysee_ativo', '0');

// Normalize legacy '1'
if ($odysee_ativo === '1') $odysee_ativo = 'full';

// Buscar status do bot
$bot_status = statusWhatsApp();
$is_connected = $bot_status['connected'] ?? false;

// Determinar estado de contenção global
$modo_contencao_ativo = ($meetups_hourly === '0' || $meetups_daily === '0' || $odysee_ativo === '0' || $odysee_ativo === 'hosts');

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modo de Contenção WhatsApp | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-bg: #0f172a;
            --sidebar-bg: #1e293b;
            --accent-red: #e31d1c;
            --text-main: #f1f5f9;
            --text-dim: #94a3b8;
            --card-bg: #1e293b;
            --success: #10b981;
            --warning: #f59e0b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: bold; }
        .alert.success { background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert.danger { background: rgba(227, 29, 28, 0.1); color: var(--accent-red); border: 1px solid rgba(227, 29, 28, 0.2); }
        .alert.warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); border: 1px solid rgba(245, 158, 11, 0.2); }
        
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; text-decoration: none; border: none; color: white; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: var(--accent-red); }
        .btn-secondary { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); }
        .btn-success { background: var(--success); }
        
        .card { background: var(--card-bg); padding: 25px; border-radius: 15px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.05); }
        
        /* Toggle Switch CSS */
        .switch { position: relative; display: inline-block; width: 60px; height: 34px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(255,255,255,0.1); transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--success); }
        input:checked + .slider:before { transform: translateX(26px); }

        .toggle-row { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .toggle-row:last-child { border-bottom: none; }
        .toggle-info h3 { margin-bottom: 5px; }
        .toggle-info p { color: var(--text-dim); font-size: 0.9rem; }
        
        .radio-group { display: flex; flex-direction: column; gap: 10px; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; }
        .radio-option { display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s; }
        .radio-option:hover { background: rgba(255,255,255,0.05); }
        .radio-option input[type="radio"] { cursor: pointer; width: 18px; height: 18px; accent-color: var(--success); }
        .radio-option.danger-opt input[type="radio"] { accent-color: var(--accent-red); }
        
        #btn-revelar-danger {
            background: rgba(227, 29, 28, 0.1);
            color: var(--accent-red);
            border: 1px dashed var(--accent-red);
            padding: 10px;
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
            font-weight: bold;
            transition: all 0.3s;
            margin-top: 5px;
        }
        #btn-revelar-danger:hover {
            background: var(--accent-red);
            color: white;
        }
        .danger-zone {
            display: none;
            border-top: 1px solid rgba(227, 29, 28, 0.3);
            padding-top: 10px;
            margin-top: 5px;
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Sub-Nav -->
        <?php include __DIR__ . '/includes/whatsapp_subnav.php'; ?>

        <header class="header">
            <div>
                <h2>Modo de Contenção (Anti-Ban)</h2>
                <p style="color: var(--text-dim);">Controle quais automações podem enviar mensagens para evitar suspensão do número.</p>
            </div>
            <div>
                <?php if ($is_connected): ?>
                    <div class="alert success" style="margin:0; padding: 10px 15px;"><i class="fas fa-check-circle"></i> Bot Conectado</div>
                <?php else: ?>
                    <div class="alert danger" style="margin:0; padding: 10px 15px;"><i class="fas fa-exclamation-triangle"></i> Bot Desconectado</div>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($msg): ?>
            <div class="alert success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <?php if ($modo_contencao_ativo): ?>
            <div class="alert warning">
                <i class="fas fa-shield-alt"></i> <strong>Modo de Contenção Ativo!</strong> Algumas automações de alto risco estão desligadas. O sistema está enviando apenas o mínimo essencial.
            </div>
        <?php else: ?>
            <div class="alert success">
                <i class="fas fa-rocket"></i> <strong>Operação Normal!</strong> Todas as automações estão autorizadas a disparar mensagens.
            </div>
        <?php endif; ?>

        <form method="POST" onsubmit="return confirmarSalvar(event)">
            <div class="card">
                <h3><i class="fas fa-cogs"></i> Controle de Automações</h4>
                <p style="color: var(--text-dim); margin-bottom: 20px; margin-top: 5px;">
                    Desligue as chaves abaixo para impedir que o sistema envie mensagens dessas categorias.
                </p>

                <div class="toggle-row">
                    <div class="toggle-info">
                        <h3>Avisos de Início de Meetup (Hora em Hora)</h3>
                        <p>🔴 <strong>ALTO RISCO:</strong> Envia mensagens simultâneas para dezenas de grupos. Recomendado manter desligado enquanto o número for novo.</p>
                    </div>
                    <div>
                        <label class="switch">
                            <input type="checkbox" name="wpp_meetups_hourly_ativo" <?= $meetups_hourly === '1' ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <div class="toggle-row">
                    <div class="toggle-info">
                        <h3>Resumo Diário de Meetups (09:00 AM)</h3>
                        <p>🟡 <strong>MÉDIO RISCO:</strong> Envia a agenda do dia para vários grupos uma vez por dia.</p>
                    </div>
                    <div>
                        <label class="switch">
                            <input type="checkbox" name="wpp_meetups_daily_ativo" <?= $meetups_daily === '1' ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <div class="toggle-row" style="align-items: flex-start;">
                    <div class="toggle-info">
                        <h3>Disparos de Vídeos do Odysee</h3>
                        <p>Controle hierárquico para o envio das gravações semanais.</p>
                    </div>
                    <div style="min-width: 300px;">
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="wpp_odysee_modo" value="0" <?= $odysee_ativo === '0' ? 'checked' : '' ?>>
                                <span>⚪ <strong>Desligado</strong><br><small style="color:var(--text-dim)">Não envia para ninguém.</small></span>
                            </label>
                            
                            <label class="radio-option">
                                <input type="radio" name="wpp_odysee_modo" value="hosts" <?= $odysee_ativo === 'hosts' ? 'checked' : '' ?>>
                                <span>🟡 <strong>Apenas Hosts</strong> <span style="color:var(--success); font-size:0.8em;">(Seguro)</span><br><small style="color:var(--text-dim)">Envia uma vez para o grupo interno dos hosts repassarem.</small></span>
                            </label>

                            <div id="btn-revelar-danger" <?= $odysee_ativo === 'full' ? 'style="display:none;"' : '' ?> onclick="revelarDanger()">
                                ⚠️ Mostrar opção de alto risco
                            </div>

                            <div class="danger-zone" id="danger-zone" <?= $odysee_ativo === 'full' ? 'style="display:block;"' : '' ?>>
                                <label class="radio-option danger-opt">
                                    <input type="radio" name="wpp_odysee_modo" value="full" <?= $odysee_ativo === 'full' ? 'checked' : '' ?>>
                                    <span style="color:var(--accent-red)">🔴 <strong>DISPARO TOTAL</strong><br><small style="color:var(--text-dim)">Envia para todos os grupos do idioma simultaneamente. <strong>(Risco de Ban)</strong></small></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="toggle-row">
                    <div class="toggle-info">
                        <h3>Automações da Mentoria (Aulas, Ranking, Desafio)</h3>
                        <p>🟢 <strong>BAIXO RISCO:</strong> Mensagens mais pontuais (abertura de sala, kicks, pontuação diária).</p>
                    </div>
                    <div>
                        <label class="switch">
                            <input type="checkbox" name="wpp_mentoria_ativo" <?= $mentoria_ativo === '1' ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" name="save_flags" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Configurações</button>
                </div>
            </div>
        </form>
        
        <div class="card">
            <h3><i class="fas fa-info-circle"></i> Dica sobre Novos Números</h3>
            <p style="color: var(--text-dim); margin-top: 10px; line-height: 1.6;">
                O WhatsApp possui um sistema rigoroso de detecção de spam. Números novos (com menos de 1 a 3 meses) que enviam mensagens iguais para muitos grupos rapidamente sofrem punições automáticas.<br><br>
                <strong>Estratégia recomendada:</strong> Mantenha os <em>Avisos de Início de Meetup</em> desligados por enquanto. Deixe apenas as automações essenciais da Mentoria rodando. Com o tempo (após algumas semanas), reative os meetups gradualmente.
            </p>
        </div>
    </main>

    <script>
        function revelarDanger() {
            document.getElementById('danger-zone').style.display = 'block';
            document.getElementById('btn-revelar-danger').style.display = 'none';
        }

        function confirmarSalvar(e) {
            const modos = document.getElementsByName('wpp_odysee_modo');
            let isFull = false;
            for(let i=0; i<modos.length; i++){
                if(modos[i].checked && modos[i].value === 'full'){
                    isFull = true;
                    break;
                }
            }
            if(isFull) {
                if(!confirm('⚠️ ATENÇÃO EXTREMA!\n\nVocê selecionou o DISPARO TOTAL para o Odysee.\nIsso vai enviar mensagens em massa e pode resultar no BANIMENTO IMEDIATO do número do bot se ele for muito novo.\n\nTem certeza absoluta que deseja ativar o disparo em massa?')) {
                    e.preventDefault();
                    return false;
                }
            }
            return true;
        }
    </script>
</body>
</html>
