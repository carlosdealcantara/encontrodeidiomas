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
$error = null;

// Lógica de Sincronização Manual (Push config to Baileys)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_global_config'])) {
    // 1. Pega config atual da mentoria para não perder o resto
    $config = getMentoriaConfig();
    
    // 2. Busca grupos globais no banco
    try {
        $stmtGlob = $conn->query("SELECT group_id as jid, nome FROM meetup_whatsapp_groups WHERE comunidade = 'global' AND ativo = 1");
        $globalGroups = $stmtGlob->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($globalGroups as $gg) {
            $key = 'global_' . preg_replace('/[^a-z0-9]/', '', strtolower($gg['nome']));
            $config['groups'][$key] = [
                'jid' => $gg['jid'],
                'name' => $gg['nome'],
                'is_community_group' => true,
                'ranking_enabled' => true
            ];
        }

        if (isset($_POST['tpl_messenger'])) {
            $config['templates']['community_ranking_messenger'] = trim($_POST['tpl_messenger']);
        }
        if (isset($_POST['tpl_reactor'])) {
            $config['templates']['community_ranking_reactor'] = trim($_POST['tpl_reactor']);
        }
        
        // 3. Envia para o Baileys
        $res = sendBaileysRequest('/mentoria-config', $config, 'POST');
        if ($res['success']) {
            $msg = "Os grupos globais foram sincronizados com sucesso no servidor do robô!";
        } else {
            $error = "Erro ao sincronizar com o robô: " . ($res['error'] ?? 'Desconhecido');
        }
    } catch (Exception $e) {
        $error = "Erro ao buscar grupos no banco de dados.";
    }
}

// Pegar config atual para exibição
$config = getMentoriaConfig();

$tpl_messenger = $config['templates']['community_ranking_messenger'] ?? "📊 *DAILY RANKING — {group_name}*\n📅 _{date}_\n━━━━━━━━━━━━━━━━━━━━━━\n\n💬 *TOP TALKERS*\n_Who sent the most messages today?_\n\n{msg_ranking_list}\n\n━━━━━━━━━━━━━━━━━━━━━━\n✨ _Keep the conversation going! Tomorrow's ranking starts now._ 🚀";
$tpl_reactor = $config['templates']['community_ranking_reactor'] ?? "❤️ *REACTION STARS — {group_name}*\n📅 _{date}_\n━━━━━━━━━━━━━━━━━━━━━━\n\n_Who spread the most love today?_\n\n{react_ranking_list}\n\n━━━━━━━━━━━━━━━━━━━━━━\n_React to others and climb the ranking! 🙌_";

$title = 'Comunidade Global - Admin';
$current_page = 'comunidade_global.php';
include 'includes/header.php';
?>

<style>
    .page-title { color: #fff; font-size: 24px; font-weight: 600; margin-bottom: 20px; }
    .card { background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); margin-bottom: 20px; }
    .btn-primary { background: #38bdf8; color: #fff; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
    .btn-primary:hover { background: #0284c7; }
    .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
    .alert-success { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
    .alert-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
    .alert-warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
</style>

<?php include 'includes/sidebar.php'; ?>

<main style="flex:1; padding:30px; overflow-y:auto;">
    <h1 class="page-title"><i class="fas fa-globe" style="color: #38bdf8; margin-right: 10px;"></i> Comunidade Global</h1>
    
    <?php if ($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <div>
                <h3 style="margin-top:0; margin-bottom:0; color:#fff;">Monitoramento de Atividade (Hoje)</h3>
                <p style="color: var(--text-dim); margin-top:5px; margin-bottom:0; font-size: 14px;">Abaixo você pode acompanhar quantas mensagens e reações cada participante já enviou hoje (<b><?= date('d/m/Y') ?></b>) nos grupos globais.</p>
            </div>
            <a href="comunidade_global.php" class="btn-primary" style="font-size: 13px; padding: 8px 12px; background: #10b981;">
                <i class="fas fa-sync-alt"></i> Atualizar Placar
            </a>
        </div>

        <?php
        $hoje = date('Y-m-d');
        $activityToday = fetchBaileysActivity($hoje);

        if (empty($activityToday)) {
            echo "<div class='alert alert-warning'>Nenhuma atividade registrada ainda para o dia de hoje ($hoje). Se o robô acabou de ser configurado, aguarde os alunos começarem a enviar mensagens.</div>";
        } else {
            $globalGroupNames = [];
            foreach (($config['groups'] ?? []) as $g) {
                if (!empty($g['jid']) && !empty($g['is_community_group'])) {
                    $globalGroupNames[$g['jid']] = $g['name'] ?? 'Grupo Global Desconhecido';
                }
            }

            echo "<div style='display:flex; flex-wrap:wrap; gap:20px; margin-top:20px;'>";
            $hasGlobalActivity = false;
            foreach ($activityToday as $groupJid => $members) {
                if (!isset($globalGroupNames[$groupJid])) continue;
                
                $gName = $globalGroupNames[$groupJid];
                if (empty($members)) continue;
                $hasGlobalActivity = true;
                
                uasort($members, function($a, $b) {
                    $totalA = ($a['messages'] ?? 0) + ($a['reactions_given'] ?? 0) + ($a['images_sent'] ?? 0) + ($a['audios_sent'] ?? 0);
                    $totalB = ($b['messages'] ?? 0) + ($b['reactions_given'] ?? 0) + ($b['images_sent'] ?? 0) + ($b['audios_sent'] ?? 0);
                    return $totalB <=> $totalA;
                });

                echo "<div style='background:#1e1e1e; padding:15px; border-radius:8px; border:1px solid #333; flex: 1 1 300px;'>";
                echo "<h3 style='margin-top:0; color:#38bdf8; font-size:16px;'><i class='fas fa-comments' style='margin-right:8px;'></i> {$gName}</h3>";
                
                echo "<table style='width:100%; border-collapse:collapse; margin-top:10px;'>";
                echo "<tr style='border-bottom:1px solid #444; color:#aaa; font-size:12px;'>
                        <th style='text-align:left; padding:5px 0;'>Participante</th>
                        <th style='text-align:center; padding:5px 0;'>💬 Msgs</th>
                        <th style='text-align:center; padding:5px 0;'>❤️ Reacts</th>
                      </tr>";
                      
                foreach ($members as $jid => $data) {
                    $nome = $data['name'] ?? 'Desconhecido';
                    $msgs = ($data['messages'] ?? 0) + ($data['images_sent'] ?? 0) + ($data['audios_sent'] ?? 0);
                    $reacts = $data['reactions_given'] ?? 0;
                    
                    $isAdminMarker = '';
                    if (strpos($jid, preg_replace('/:\d+@/', '@', $config['admin_jid'] ?? '')) !== false) {
                        $isAdminMarker = ' <span style="font-size:10px; background:#444; padding:2px 4px; border-radius:4px;">Admin</span>';
                    }

                    echo "<tr style='border-bottom:1px solid #2a2a2a;'>";
                    echo "<td style='padding:8px 0; font-size:14px;'>" . htmlspecialchars($nome) . $isAdminMarker . "</td>";
                    echo "<td style='padding:8px 0; font-size:14px; text-align:center; color:#10b981; font-weight:bold;'>{$msgs}</td>";
                    echo "<td style='padding:8px 0; font-size:14px; text-align:center; color:#f43f5e; font-weight:bold;'>{$reacts}</td>";
                    echo "</tr>";
                }
                echo "</table>";
                echo "</div>";
            }
            if (!$hasGlobalActivity) {
                echo "<div class='alert alert-warning' style='width: 100%;'>Nenhuma atividade registrada ainda nos grupos globais para hoje.</div>";
            }
            echo "</div>";
        }
        ?>
    </div>

    <div class="card">
        <h3 style="margin-top:0; color:#fff;">Configurações e Sincronização</h3>
        <p style="color: var(--text-dim); margin-top:5px; font-size: 14px;">Ao adicionar ou editar um grupo na aba WhatsApp (Meetups), salve aqui para forçar o robô a sincronizar. Abaixo você também pode editar as mensagens do ranking.</p>
        
        <form method="POST" style="margin-top: 20px;">
            <div style="margin-bottom: 15px;">
                <label style="display:block; margin-bottom: 5px; font-weight:600;">Template: Top Mensagens</label>
                <textarea name="tpl_messenger" style="width:100%; min-height:150px; background:var(--input-bg); color:#fff; border:1px solid #333; border-radius:6px; padding:10px;"><?= htmlspecialchars($tpl_messenger) ?></textarea>
                <small style="color:var(--text-dim);">Variáveis: <code>{group_name}</code>, <code>{date}</code>, <code>{msg_ranking_list}</code></small>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display:block; margin-bottom: 5px; font-weight:600;">Template: Top Reações</label>
                <textarea name="tpl_reactor" style="width:100%; min-height:150px; background:var(--input-bg); color:#fff; border:1px solid #333; border-radius:6px; padding:10px;"><?= htmlspecialchars($tpl_reactor) ?></textarea>
                <small style="color:var(--text-dim);">Variáveis: <code>{group_name}</code>, <code>{date}</code>, <code>{react_ranking_list}</code></small>
            </div>

            <button type="submit" name="sync_global_config" class="btn-primary">
                <i class="fas fa-save"></i> Salvar e Sincronizar Robô
            </button>
        </form>
    </div>
</main>
</body>
</html>
