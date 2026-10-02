<?php
session_start();
require_once '../config.php';

// Prevenir cache agressivo da Hostinger/LiteSpeed
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Proteção da página
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();
$aluno = null;
$msg = '';
$ltv_vitalicios = (float)getSetting('ltv_vitalicios', '5000');

// Se for Edição, busca os dados
if (isset($_GET['id'])) {
    $stmt = $conn->prepare("SELECT * FROM mentoria_alunos WHERE id = :id");
    $stmt->execute(['id' => (int)$_GET['id']]);
    $aluno = $stmt->fetch();
}

$available_langs = [];
try {
    $available_langs = $conn->query("SELECT lang_id, nome, bandeira FROM mentoria_langs WHERE ativo = 1 ORDER BY lang_id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
if (empty($available_langs)) {
    $available_langs = [
        ['lang_id' => 'en', 'nome' => 'Inglês', 'bandeira' => '🇺🇸'],
        ['lang_id' => 'es', 'nome' => 'Espanhol', 'bandeira' => '🇪🇸']
    ];
}

$selected_lang = $aluno['lang_id'] ?? $_GET['lang'] ?? 'en';

// Carrega lista de alunos para o campo "Responsável Financeiro"
$alunos_para_responsavel = [];
try {
    $stmtResp = $conn->query("SELECT id, nome, lang_id FROM mentoria_alunos WHERE responsavel_financeiro_id IS NULL ORDER BY nome ASC");
    $alunos_para_responsavel = $stmtResp->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Lógica de Salvar (Create ou Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $responsavel_financeiro_id = !empty($_POST['responsavel_financeiro_id']) ? (int)$_POST['responsavel_financeiro_id'] : null;
    $status_aluno = $_POST['status_aluno'] ?? 'Ativo';
    $valor_mensalidade = str_replace(',', '.', $_POST['valor_mensalidade'] ?? '0');
    $total_investido = str_replace(',', '.', $_POST['total_investido'] ?? '0');
    $proximo_vencimento = !empty($_POST['proximo_vencimento']) ? $_POST['proximo_vencimento'] : date('Y-m-d');
    
    // Tratamento das datas (podem ser null)
    $data_inicio = !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : null;
    $data_nascimento = !empty($_POST['data_nascimento']) ? $_POST['data_nascimento'] : null;
    
    $grupo_atual = $_POST['grupo_atual'] ?? 'Our Meetups';
    $observacoes = $_POST['observacoes'] ?? '';
    $lang_id = $_POST['lang_id'] ?? 'en';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    // Remove tudo que não for número do telefone
    $telefone_limpo = preg_replace('/\D/', '', $telefone);

    $status_pagamento = $_POST['status_pagamento'] ?? 'Pago';

    // Se tem responsável financeiro, herda o status de pagamento e data do responsável
    if ($responsavel_financeiro_id) {
        try {
            $stmtRespData = $conn->prepare("SELECT status_pagamento, proximo_vencimento FROM mentoria_alunos WHERE id = ?");
            $stmtRespData->execute([$responsavel_financeiro_id]);
            $respData = $stmtRespData->fetch();
            if ($respData) {
                $status_pagamento = $respData['status_pagamento'];
                $proximo_vencimento = $respData['proximo_vencimento'];
            }
        } catch (Exception $e) {}
    }

    if ($id > 0) {
        // UPDATE
        $sql = "UPDATE mentoria_alunos SET 
                nome = :nome, telefone = :telefone, cpf = :cpf, email = :email,
                responsavel_financeiro_id = :responsavel_financeiro_id,
                status_aluno = :status_aluno, status_pagamento = :status_pagamento,
                valor_mensalidade = :valor_mensalidade, total_investido = :total_investido,
                proximo_vencimento = :proximo_vencimento, 
                data_inicio = :data_inicio, data_nascimento = :data_nascimento, grupo_atual = :grupo_atual, 
                observacoes = :observacoes, lang_id = :lang_id 
                WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'nome' => $nome, 'telefone' => $telefone_limpo, 'cpf' => $cpf ?: null, 'email' => $email ?: null,
            'responsavel_financeiro_id' => $responsavel_financeiro_id,
            'status_aluno' => $status_aluno, 'status_pagamento' => $status_pagamento,
            'valor_mensalidade' => $valor_mensalidade, 'total_investido' => $total_investido, 
            'proximo_vencimento' => $proximo_vencimento, 
            'data_inicio' => $data_inicio, 'data_nascimento' => $data_nascimento, 'grupo_atual' => $grupo_atual,
            'observacoes' => $observacoes, 'lang_id' => $lang_id, 'id' => $id
        ]);
        header('Location: mentoria.php?lang=' . urlencode($lang_id) . '&msg=Aluno atualizado com sucesso');
        exit;
    } else {
        // INSERT
        $sql = "INSERT INTO mentoria_alunos (nome, telefone, cpf, email, responsavel_financeiro_id, status_aluno, status_pagamento, valor_mensalidade, total_investido, proximo_vencimento, data_inicio, data_nascimento, grupo_atual, observacoes, lang_id) 
                VALUES (:nome, :telefone, :cpf, :email, :responsavel_financeiro_id, :status_aluno, :status_pagamento, :valor_mensalidade, :total_investido, :proximo_vencimento, :data_inicio, :data_nascimento, :grupo_atual, :observacoes, :lang_id)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'nome' => $nome, 'telefone' => $telefone_limpo, 'cpf' => $cpf ?: null, 'email' => $email ?: null,
            'responsavel_financeiro_id' => $responsavel_financeiro_id,
            'status_aluno' => $status_aluno, 'status_pagamento' => $status_pagamento,
            'valor_mensalidade' => $valor_mensalidade, 'total_investido' => $total_investido, 
            'proximo_vencimento' => $proximo_vencimento, 
            'data_inicio' => $data_inicio, 'data_nascimento' => $data_nascimento, 'grupo_atual' => $grupo_atual,
            'observacoes' => $observacoes, 'lang_id' => $lang_id
        ]);
        header('Location: mentoria.php?lang=' . urlencode($lang_id) . '&msg=Novo aluno cadastrado com sucesso');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $aluno ? 'Editar Aluno' : 'Novo Aluno' ?> | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        /* Select2 dark theme override */
        .select2-container--default .select2-selection--single { background-color: #0f172a; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; height: 46px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { color: #f1f5f9; line-height: 46px; padding-left: 15px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 46px; }
        .select2-dropdown { background-color: #1e293b; border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; color: #f1f5f9; z-index: 99999; }
        .select2-search--dropdown .select2-search__field { background-color: #0f172a; color: #f1f5f9; border: 1px solid rgba(255,255,255,0.2); border-radius: 6px; padding: 8px 12px; }
        .select2-results__option { padding: 10px 14px; color: #f1f5f9; font-size: 0.9rem; }
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable { background-color: #e31d1c !important; color: white !important; }
        .select2-container--default .select2-results__option[aria-selected="true"] { background-color: rgba(255,255,255,0.1) !important; }
        .select2-container { width: 100% !important; }
        .resp-flag { width: 18px; height: 12px; object-fit: cover; border-radius: 2px; vertical-align: middle; margin-right: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.4); }
        :root {
            --primary-bg: #0f172a;
            --sidebar-bg: #1e293b;
            --accent-red: #e31d1c;
            --accent-blue: #38bdf8;
            --text-main: #f1f5f9;
            --text-dim: #94a3b8;
            --card-bg: #1e293b;
            --input-bg: #0f172a;
            --success: #10b981;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--primary-bg); color: var(--text-main); display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .header-title h2 { font-size: 1.8rem; font-weight: 700; }
        .btn-back { color: var(--text-dim); text-decoration: none; font-weight: 600; transition: 0.3s; }
        .btn-back:hover { color: white; }

        .form-card { background: var(--card-bg); padding: 30px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); max-width: 800px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; }
        .form-group.full { grid-column: 1 / -1; }
        
        label { font-size: 0.9rem; color: var(--text-dim); font-weight: 600; }
        input, select, textarea { 
            background: var(--input-bg); 
            border: 1px solid rgba(255,255,255,0.1); 
            color: white; 
            padding: 12px 15px; 
            border-radius: 10px; 
            font-size: 1rem;
            outline: none;
            transition: 0.3s;
        }
        input:focus, select:focus, textarea:focus { border-color: var(--accent-red); }
        
        .btn-submit { 
            background: var(--success); 
            color: white; 
            border: none; 
            padding: 15px 30px; 
            border-radius: 12px; 
            font-size: 1rem; 
            font-weight: 700; 
            cursor: pointer; 
            transition: 0.3s; 
            width: 100%; 
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2); }
        
        .obs-hint { font-size: 0.75rem; color: var(--text-dim); font-weight: 400; margin-top: -5px; }

        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: stretch; gap: 15px; }
            .form-card { padding: 20px 15px; border-radius: 16px; width: 100%; }
            .form-grid { grid-template-columns: 1fr; gap: 15px; }
            .form-group.full { grid-column: span 1; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <div class="header-title">
                <h2><?= $aluno ? 'Editar Aluno' : 'Novo Aluno da Mentoria' ?></h2>
            </div>
            <a href="mentoria.php" class="btn-back"><i class="fas fa-arrow-left"></i> Voltar</a>
        </header>

        <div class="form-card">
            <form method="POST" action="">
                <?php if($aluno): ?>
                    <input type="hidden" name="id" value="<?= $aluno['id'] ?>">
                <?php endif; ?>

                <?php if ($msg): ?>
                    <div class="msg-box <?= strpos($msg, 'Erro') !== false ? 'error' : '' ?>"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>
                
                <?php if ($aluno && $aluno['status_aluno'] === 'Vitalício'): ?>
                    <div class="msg-box" style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--success); color: var(--text-main); margin-bottom: 20px;">
                        <i class="fas fa-gem" style="color: var(--success); margin-right: 8px;"></i>
                        <strong>Aluno Vitalício</strong>: O sistema não cobrará este aluno. O Status Financeiro recomendado é "Isento".
                    </div>
                <?php endif; ?>
                
                <?php if ($aluno && $aluno['status_aluno'] === 'Comunidade'): ?>
                    <div class="msg-box" style="background: rgba(56, 189, 248, 0.1); border-left: 4px solid var(--accent-blue); color: var(--text-main); margin-bottom: 20px;">
                        <i class="fas fa-users" style="color: var(--accent-blue); margin-right: 8px;"></i>
                        <strong>Aluno da Comunidade</strong>: Acesso apenas à comunidade (e-book, ex-alunos). Não haverá cobrança.
                    </div>
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group full">
                        <label>Nome Completo do Aluno</label>
                        <input type="text" name="nome" required value="<?= htmlspecialchars($aluno['nome'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Idioma da Mentoria</label>
                        <select name="lang_id" required style="font-weight: 600;">
                            <?php foreach ($available_langs as $l): ?>
                                <option value="<?= htmlspecialchars($l['lang_id']) ?>" <?= $selected_lang === $l['lang_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($l['bandeira'] . ' ' . $l['nome']) ?> (<?= htmlspecialchars($l['lang_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="obs-hint">Define em qual turma, relatórios e templates o aluno será alocado.</div>
                    </div>

                    <div class="form-group">
                        <label>Telefone WhatsApp (Com DDD)</label>
                        <input type="text" name="telefone" required value="<?= htmlspecialchars($aluno['telefone'] ?? '') ?>" placeholder="Brasil: 11999998888 | Internacional: 818030606423">
                    </div>

                    <div class="form-group">
                        <label>CPF</label>
                        <input type="text" name="cpf" value="<?= htmlspecialchars($aluno['cpf'] ?? '') ?>" placeholder="Somente números: 12345678901" maxlength="14">
                        <div class="obs-hint">Opcional. Usado para identificação e controle financeiro.</div>
                    </div>

                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($aluno['email'] ?? '') ?>" placeholder="aluno@email.com">
                        <div class="obs-hint">Opcional. Para comunicações fora do WhatsApp.</div>
                    </div>

                    <div class="form-group full" id="grupo_responsavel_financeiro">
                        <label>💳 Responsável Financeiro</label>
                        <div class="obs-hint" style="margin-bottom: 8px;">Deixe em branco se o próprio aluno é o pagador. Se este aluno é <strong>dependente</strong> de outra pessoa (ex: esposa/marido, filho), selecione o <strong>pagador titular</strong> abaixo. O sistema não cobrará este aluno nem o removerá caso o responsável esteja em dia.</div>
                        <?php
                        $flagMapForm = ['en' => 'us', 'es' => 'es', 'fr' => 'fr', 'de' => 'de', 'it' => 'it', 'pt' => 'br'];
                        ?>
                        <select name="responsavel_financeiro_id" id="select_responsavel" class="select2-responsavel">
                            <option value="">— Sem responsável (aluno paga por conta própria) —</option>
                            <?php foreach ($alunos_para_responsavel as $ar): ?>
                                <?php if ((int)$ar['id'] === (int)($aluno['id'] ?? 0)) continue; ?>
                                <?php
                                    $arFlag = $flagMapForm[$ar['lang_id']] ?? strtolower($ar['lang_id']);
                                    $arFlagUrl = 'https://flagcdn.com/w20/' . $arFlag . '.png';
                                    $arLangLabel = strtoupper($ar['lang_id']);
                                ?>
                                <option value="<?= (int)$ar['id'] ?>"
                                    data-flag="<?= htmlspecialchars($arFlagUrl) ?>"
                                    <?= ((int)($aluno['responsavel_financeiro_id'] ?? 0)) === (int)$ar['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ar['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status do Aluno</label>
                        <select name="status_aluno" required>
                            <option value="Ativo" <?= ($aluno['status_aluno']??'') === 'Ativo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="Inativo" <?= ($aluno['status_aluno']??'') === 'Inativo' ? 'selected' : '' ?>>Inativo</option>
                            <option value="Comunidade" <?= ($aluno['status_aluno']??'') === 'Comunidade' ? 'selected' : '' ?>>Comunidade</option>
                            <option value="Vitalício" <?= ($aluno['status_aluno']??'') === 'Vitalício' ? 'selected' : '' ?>>Vitalício</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status do Pagamento</label>
                        <select name="status_pagamento" required>
                            <option value="Pago" <?= ($aluno['status_pagamento']??'Pago') === 'Pago' ? 'selected' : '' ?>>Pago</option>
                            <option value="Pendente" <?= ($aluno['status_pagamento']??'') === 'Pendente' ? 'selected' : '' ?>>Pendente</option>
                            <option value="Comprovante Enviado" <?= ($aluno['status_pagamento']??'') === 'Comprovante Enviado' ? 'selected' : '' ?>>Comprovante Enviado</option>
                            <option value="Suspenso" <?= ($aluno['status_pagamento']??'') === 'Suspenso' ? 'selected' : '' ?>>Suspenso</option>
                            <option value="Isento" <?= ($aluno['status_pagamento']??'') === 'Isento' ? 'selected' : '' ?>>Isento</option>
                        </select>
                    </div>

                    <div class="form-group" id="grupo_valor_mensalidade">
                        <label>Valor da Mensalidade (R$)</label>
                        <input type="text" name="valor_mensalidade" value="<?= htmlspecialchars($aluno['valor_mensalidade'] ?? '0.00') ?>">
                    </div>

                    <div class="form-group">
                        <label>Total Já Investido / LTV (R$)</label>
                        <input type="text" name="total_investido" value="<?= htmlspecialchars($aluno['total_investido'] ?? '0.00') ?>">
                        <div class="obs-hint">Ao bater R$ <?= number_format($ltv_vitalicios, 0, ',', '.') ?>, vira Vitalício.</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Data de Início (Para estatísticas)</label>
                        <input type="date" name="data_inicio" value="<?= htmlspecialchars($aluno['data_inicio'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Data de Nascimento</label>
                        <input type="date" name="data_nascimento" value="<?= htmlspecialchars($aluno['data_nascimento'] ?? '') ?>">
                        <div class="obs-hint">Usada pelo bot para enviar parabéns no aniversário. 🎂</div>
                    </div>

                    <div class="form-group" id="grupo_proximo_vencimento">
                        <label>Data Exata do Próximo Vencimento</label>
                        <input type="date" name="proximo_vencimento" id="proximo_vencimento" value="<?= htmlspecialchars($aluno['proximo_vencimento'] ?? date('Y-m-d')) ?>">
                    </div>

                    <div class="form-group full">
                        <label>Observações</label>
                        <div class="obs-hint">Ex: "Paga apenas R$ 150 por acordo antigo", "Aluno do exterior", etc.</div>
                        <textarea name="observacoes" rows="3"><?= htmlspecialchars($aluno['observacoes'] ?? '') ?></textarea>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Salvar Cadastro
                </button>
            </form>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const statusSelect = document.querySelector("select[name='status_aluno']");
        const proximoVencimento = document.getElementById("grupo_proximo_vencimento");
        const valorMensalidade = document.getElementById("grupo_valor_mensalidade");
        const inputProx = document.getElementById("proximo_vencimento");

        function toggleFields() {
            const v = statusSelect.value;
            const isSpecial = (v === 'Vitalício' || v === 'Comunidade');
            proximoVencimento.style.display = isSpecial ? 'none' : 'block';
            valorMensalidade.style.display  = isSpecial ? 'none' : 'block';
            isSpecial ? inputProx.removeAttribute("required") : inputProx.setAttribute("required", "required");
        }
        statusSelect.addEventListener("change", toggleFields);
        toggleFields();
    });

    // Select2: Responsável Financeiro com bandeirinha
    $(document).ready(function() {
        function formatRespOption(option) {
            if (!option.id) {
                return $('<span style="color:#94a3b8;">' + option.text + '</span>');
            }
            var flagUrl = $(option.element).data('flag') || '';
            var name    = option.text.trim();
            var $el = $('<span></span>');
            if (flagUrl) {
                $el.append('<img src="' + flagUrl + '" class="resp-flag" onerror="this.style.display=\'none\'"> ');
            }
            $el.append('<strong>' + name + '</strong>');
            return $el;
        }
        function formatRespSelection(option) {
            return option.text.trim();
        }

        $('#select_responsavel').select2({
            placeholder: 'Digite o nome para buscar...',
            allowClear: true,
            width: '100%',
            dropdownParent: $('body'),
            templateResult:    formatRespOption,
            templateSelection: formatRespSelection,
            language: { noResults: function() { return 'Nenhum aluno encontrado'; } }
        });
    });
    </script>
</body>
</html>
