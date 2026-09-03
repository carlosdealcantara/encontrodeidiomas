<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_template'])) {
        $cenario    = trim($_POST['cenario']);
        $minutos    = (int)$_POST['minutos_antes'];
        $texto      = trim($_POST['template_texto']);
        $ativo      = isset($_POST['ativo']) ? 1 : 0;
        $frequencia = $_POST['frequencia'] ?? 'diario';
        $escopo     = $_POST['escopo']     ?? 'por_encontro';
        
        try {
            try {
                if (!empty($_POST['id'])) {
                    $stmt = $conn->prepare("UPDATE meetup_whatsapp_templates SET cenario = ?, minutos_antes = ?, template_texto = ?, ativo = ?, frequencia = ?, escopo = ?, comunidade_alvo = ? WHERE id = ?");
                    $stmt->execute([$cenario, $minutos, $texto, $ativo, $frequencia, $escopo, $_POST['comunidade_alvo'] ?? 'brasil', $_POST['id']]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO meetup_whatsapp_templates (cenario, minutos_antes, template_texto, ativo, frequencia, escopo, comunidade_alvo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$cenario, $minutos, $texto, $ativo, $frequencia, $escopo, $_POST['comunidade_alvo'] ?? 'brasil']);
                }
            } catch (PDOException $e) {
                // Fallback caso colunas frequencia/escopo/comunidade_alvo ainda não existam (migração pendente)
                if (strpos($e->getMessage(), "Unknown column") !== false) {
                    if (!empty($_POST['id'])) {
                        $stmt = $conn->prepare("UPDATE meetup_whatsapp_templates SET cenario = ?, minutos_antes = ?, template_texto = ?, ativo = ? WHERE id = ?");
                        $stmt->execute([$cenario, $minutos, $texto, $ativo, $_POST['id']]);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO meetup_whatsapp_templates (cenario, minutos_antes, template_texto, ativo) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$cenario, $minutos, $texto, $ativo]);
                    }
                } else {
                    throw $e;
                }
            }
            header('Location: meetup_templates.php?msg=Template salvo com sucesso!');
            exit;
        } catch (PDOException $e) {
            $msg = "Erro ao salvar: " . $e->getMessage();
            $_GET['msg'] = $msg;
        }
    }
    
    if (isset($_POST['test_template'])) {
        $telefone = preg_replace('/\D/', '', $_POST['test_number']);
        if (strlen($telefone) < 10) {
            $_GET['msg'] = "Por favor, insira um número válido com DDD.";
        } else {
            // Adiciona código do Brasil se não tiver
            if (!str_starts_with($telefone, '55')) {
                $telefone = '55' . $telefone;
            }
            
            $textoBruto = $_POST['template_texto'];
            
            // Mock Data
            $textoFinal = str_replace('{IDIOMA}', 'INGLÊS (TESTE)', $textoBruto);
            $textoFinal = str_replace('{idioma}', 'Inglês', $textoFinal);
            $textoFinal = str_replace('{EMOJI_FLAG}', '🇺🇸', $textoFinal);
            $textoFinal = str_replace('{EMOJI_REPETIDO_5X}', '🇺🇸🇺🇸🇺🇸🇺🇸🇺🇸', $textoFinal);
            $textoFinal = str_replace('{SAUDACAO}', 'Hello!', $textoFinal);
            $textoFinal = str_replace('{BOAS_VINDAS_NATIVAS}', 'Welcome! (nativo mock)', $textoFinal);
            $textoFinal = str_replace('{SITE_LINK}', 'viaEi.com/online', $textoFinal);
            $textoFinal = str_replace('{MEET_LINK}', 'meet.google.com/abc-defg-hij', $textoFinal);
            $textoFinal = str_replace('{INSTAGRAM_LINK}', 'instagram.com/encontrodeidiomasingles', $textoFinal);
            $textoFinal = str_replace('{HOST_LINK}', 'viaEi.com/equipe/', $textoFinal);
            $textoFinal = str_replace('{TODAS_BANDEIRAS_HOJE}', '🇺🇸🇫🇷🇯🇵', $textoFinal); // Mock com 3 idiomas
            
            // Mock Data para Resumo Diário
            $mockLista = "🇺🇸 English\n🇩🇪 Deutsch";
            $textoFinal = str_replace('{LISTA_ENCONTROS}', $mockLista, $textoFinal);
            
            require_once '../includes/whatsapp_helper.php';
            
            $result = enviarWhatsApp($telefone, $textoFinal, 'template_teste');
            $httpcode = $result['httpCode'];
            $response = json_encode($result);
            
            if ($httpcode >= 200 && $httpcode < 300) {
                $_GET['msg'] = "🚀 Mensagem de teste enviada com sucesso para $telefone!";
            } else {
                $_GET['msg'] = "❌ Erro ao enviar teste ($httpcode). Resposta: " . htmlspecialchars($response);
            }
        }
    }
}

if (isset($_GET['delete'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM meetup_whatsapp_templates WHERE id = ?");
        $stmt->execute([(int)$_GET['delete']]);
        header('Location: meetup_templates.php?msg=Template excluido');
        exit;
    } catch (PDOException $e) {
        $_GET['msg'] = "Erro ao excluir: " . $e->getMessage();
    }
}

$templates = [];
try {
    $templates = $conn->query("SELECT * FROM meetup_whatsapp_templates ORDER BY minutos_antes DESC")->fetchAll();
} catch (PDOException $e) {
    $_GET['msg'] = "Erro no banco: " . $e->getMessage() . ". As tabelas provavelmente ainda não foram criadas.";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Templates WhatsApp (Meetups) | Admin</title>
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
            --input-bg: #0f172a;
            --success: #10b981;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { margin-bottom: 30px; }
        
        .alert { padding: 15px; background: rgba(16, 185, 129, 0.1); color: var(--success); border-radius: 12px; margin-bottom: 20px; }
        
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        
        .card { background: var(--card-bg); padding: 25px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.05); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 8px; color: var(--text-dim); }
        input[type="text"], input[type="number"], textarea { width: 100%; padding: 12px; background: var(--input-bg); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 8px; font-family: 'Outfit', sans-serif; }
        textarea { resize: vertical; min-height: 200px; }
        
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; border: none; color: white; display: inline-block; text-decoration: none; }
        .btn-primary { background: var(--accent-red); }
        .btn-secondary { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); }
        
        .variables { margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px; }
        .var-chip { background: rgba(56, 189, 248, 0.1); color: #38bdf8; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; cursor: pointer; border: 1px solid rgba(56, 189, 248, 0.2); }
        .var-chip:hover { background: rgba(56, 189, 248, 0.2); }
        
        .template-list { margin-top: 30px; }
        .template-item { background: var(--input-bg); padding: 15px; border-radius: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; border: 1px solid rgba(255,255,255,0.05); }
        .template-status { font-size: 0.8rem; padding: 3px 8px; border-radius: 4px; }
        .status-on { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .status-off { background: rgba(255, 255, 255, 0.1); color: var(--text-dim); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Sub-Nav -->
        <?php include __DIR__ . '/includes/whatsapp_subnav.php'; ?>

        <header class="header">
            <h2>Templates de Mensagem (Meetups)</h2>
            <p style="color: var(--text-dim);">Configure as mensagens que serão disparadas (ex: hora exata, lembrete).</p>
        </header>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert"><?= htmlspecialchars($_GET['msg']) ?></div>
        <?php endif; ?>

        <div class="grid">
            <div class="card">
                <h3 id="form-title" style="margin-bottom: 20px;">Adicionar Template</h3>
                <form method="POST">
                    <input type="hidden" name="id" id="template_id">
                    
                    <div class="form-group">
                        <label>Nome do Template (Cenário) <small style="color:var(--text-dim); font-weight:normal;">Ex: "Lembrete 30 min", "Resumo do Dia"</small></label>
                        <input type="text" name="cenario" id="cenario" class="form-control" required placeholder="Ex: Lembrete 30 min">
                    </div>
                    
                    <div class="form-group">
                        <label>Escopo do Disparo</label>
                        <select name="escopo" id="escopo" class="form-control" required>
                            <option value="por_encontro">Por Encontro — dispara para cada meeting × grupo (padrão)</option>
                            <option value="diario">Diário — dispara 1x/dia por grupo, X min antes do 1º encontro</option>
                        </select>
                        <small style="color: var(--text-dim);">Use "Diário" para o Convite para Host. Use "Por Encontro" para lembretes normais.</small>
                    </div>

                    <div class="form-group">
                        <label>Frequência do Disparo</label>
                        <select name="frequencia" id="frequencia" class="form-control" required>
                            <option value="diario">Diário (Pode disparar todos os dias)</option>
                            <option value="semanal">Semanal (Máximo 1 vez por semana por grupo/idioma)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Comunidade Alvo</label>
                        <select name="comunidade_alvo" id="comunidade_alvo" class="form-control" required>
                            <option value="brasil">🇧🇷 Brasil — apenas grupos Brasil</option>
                            <option value="global">🌐 Global — apenas grupos Global</option>
                        </select>
                        <small style="color: var(--text-dim);">Define para qual comunidade este template será disparado.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Minutos de Antecedência (0 = Na hora exata; para escopo Diário: relativo ao 1º encontro)</label>
                        <input type="number" name="minutos_antes" id="minutos_antes" required value="0">
                        <small style="color: var(--text-dim);">Use 120 para 2 horas antes, 60 para 1 hora, 30 para meia hora, etc.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Texto do WhatsApp</label>
                        <textarea name="template_texto" id="template_texto" required></textarea>
                        
                        <div class="variables">
                            <span class="var-chip" onclick="insertVar('{IDIOMA}')">{IDIOMA}</span>
                            <span class="var-chip" onclick="insertVar('{EMOJI_FLAG}')">{EMOJI_FLAG}</span>
                            <span class="var-chip" onclick="insertVar('{EMOJI_REPETIDO_5X}')" title="Ex: 🇺🇸🇺🇸🇺🇸🇺🇸🇺🇸">{EMOJI_REPETIDO_5X}</span>
                            <span class="var-chip" onclick="insertVar('{SAUDACAO}')" title="Puxa o campo 'Saudação (EN)' da aba idiomas">{SAUDACAO}</span>
                            <span class="var-chip" onclick="insertVar('{BOAS_VINDAS_NATIVAS}')" title="Puxa as boas-vindas no idioma-alvo (Ex: 欢迎!)">{BOAS_VINDAS_NATIVAS} 🌐</span>
                            <span class="var-chip" onclick="insertVar('{SITE_LINK}')" title="Muda sozinho: viaEi.com/online (Brasil) ou viaEi.com/en/online (Global)">{SITE_LINK} 🔗</span>
                            <span class="var-chip" onclick="insertVar('{IDIOMA_BASE}')" title="Se encontro Global: 🗣️ 🇺🇸 EN. Se Brasil: 🗣️ 🇧🇷 PT-BR.">{IDIOMA_BASE} 🗣️</span>
                            <span class="var-chip" onclick="insertVar('{MEET_LINK}')">{MEET_LINK}</span>
                            <span class="var-chip" onclick="insertVar('{INSTAGRAM_LINK}')">{INSTAGRAM_LINK}</span>
                            <span class="var-chip" onclick="insertVar('{HOST_LINK}')">{HOST_LINK}</span>
                            <span class="var-chip" onclick="insertVar('{TODAS_BANDEIRAS_HOJE}')" title="Lista as bandeiras dos encontros de hoje. Somente para o template 'Resumo do Dia'">{TODAS_BANDEIRAS_HOJE} 🗓️</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><input type="checkbox" name="ativo" id="ativo" checked> Template Ativo</label>
                    </div>
                    
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button type="submit" name="save_template" class="btn btn-primary">Salvar Template</button>
                        <button type="button" class="btn btn-secondary" id="btn_cancel" style="display:none;" onclick="resetForm()">Cancelar</button>
                    </div>

                    <hr style="border-color: rgba(255,255,255,0.05); margin: 25px 0;">
                    
                    <h4 style="margin-bottom: 10px;">Testar Mensagem no Privado</h4>
                    <p style="color: var(--text-dim); font-size: 0.9rem; margin-bottom: 15px;">Quer ver como a mensagem acima vai ficar no WhatsApp antes de salvar? Digite seu número abaixo e receba um teste com dados fictícios (ex: Inglês).</p>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" name="test_number" placeholder="Seu Zap (Ex: 31988887777)" style="flex: 1;">
                        <button type="submit" name="test_template" class="btn btn-secondary"><i class="fas fa-paper-plane"></i> Disparar Teste</button>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <h3>Templates Cadastrados</h3>
                <div class="template-list">
                    <?php if (empty($templates)): ?>
                        <p style="color: var(--text-dim);">Nenhum template cadastrado.</p>
                    <?php endif; ?>
                    
                    <?php foreach ($templates as $t): ?>
                        <div class="template-item">
                            <div>
                                <strong><?= htmlspecialchars($t['cenario']) ?></strong>
                                <div style="color: var(--text-dim); font-size: 0.85rem; margin-top: 4px;">
                                    Tempo: <?= $t['minutos_antes'] == 0 ? 'Exato' : $t['minutos_antes'] . ' min antes' ?>
                                    <span class="template-status <?= $t['ativo'] ? 'status-on' : 'status-off' ?>" style="margin-left: 10px;">
                                        <?= $t['ativo'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                    <?php
                                        $com = $t['comunidade_alvo'] ?? 'brasil';
                                        $comLabel = match($com) {
                                            'global' => '<img src="https://flagcdn.com/w20/us.png" style="width:16px; margin-right:5px; border-radius:2px;" alt="US"> Global',
                                            default  => '<img src="https://flagcdn.com/w20/br.png" style="width:16px; margin-right:5px; border-radius:2px;" alt="BR"> Brasil',
                                        };
                                        $comColor = match($com) {
                                            'global' => '#38bdf8',
                                            'ambos'  => '#a78bfa',
                                            default  => '#10b981',
                                        };
                                    ?>
                                    <span class="badge" style="background:<?= $comColor ?>20;color:<?= $comColor ?>; display: inline-flex; align-items: center;"><?= $comLabel ?></span>
                                </div>
                            </div>
                            <div style="display:flex; gap: 5px;">
                                <button class="btn btn-secondary" style="padding: 5px 10px;" onclick="editTemplate(<?= $t['id'] ?>, '<?= addslashes($t['cenario']) ?>', '<?= $t['escopo'] ?? 'por_encontro' ?>', '<?= $t['frequencia'] ?? 'diario' ?>', <?= $t['minutos_antes'] ?>, `<?= addslashes($t['template_texto']) ?>`, <?= $t['ativo'] ?>, '<?= $t['comunidade_alvo'] ?? 'brasil' ?>')"><i class="fas fa-edit"></i></button>
                                <a href="?delete=<?= $t['id'] ?>" class="btn btn-secondary" style="padding: 5px 10px; color: var(--accent-red);" onclick="return confirm('Excluir?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        function insertVar(variable) {
            const txt = document.getElementById('template_texto');
            const start = txt.selectionStart;
            const end = txt.selectionEnd;
            txt.value = txt.value.substring(0, start) + variable + txt.value.substring(end);
            txt.focus();
            txt.selectionStart = start + variable.length;
            txt.selectionEnd = start + variable.length;
        }
        
        function editTemplate(id, cenario, escopo, frequencia, minutos, texto, ativo, comunidade_alvo) {
            document.getElementById('form-title').textContent = 'Editar Template';
            document.getElementById('template_id').value = id;
            document.getElementById('cenario').value = cenario;
            document.getElementById('escopo').value = escopo || 'por_encontro';
            document.getElementById('frequencia').value = frequencia || 'diario';
            document.getElementById('minutos_antes').value = minutos;
            document.getElementById('template_texto').value = texto;
            document.getElementById('ativo').checked = (ativo == 1);
            document.getElementById('comunidade_alvo').value = comunidade_alvo || 'brasil';
            document.getElementById('btn_cancel').style.display = 'inline-block';
        }
        
        function resetForm() {
            document.getElementById('form-title').textContent = 'Adicionar Template';
            document.getElementById('template_id').value = '';
            document.getElementById('cenario').value = '';
            document.getElementById('escopo').value = 'por_encontro';
            document.getElementById('frequencia').value = 'diario';
            document.getElementById('minutos_antes').value = '0';
            document.getElementById('template_texto').value = '';
            document.getElementById('ativo').checked = true;
            document.getElementById('comunidade_alvo').value = 'brasil';
            document.getElementById('btn_cancel').style.display = 'none';
        }
    </script>
</body>
</html>

