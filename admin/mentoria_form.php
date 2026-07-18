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

// Lógica de Salvar (Create ou Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $status_aluno = $_POST['status_aluno'] ?? 'Ativo';
    $valor_mensalidade = str_replace(',', '.', $_POST['valor_mensalidade'] ?? '0');
    $total_investido = str_replace(',', '.', $_POST['total_investido'] ?? '0');
    $dia_vencimento = (int)($_POST['dia_vencimento'] ?? 1);
    $proximo_vencimento = $_POST['proximo_vencimento'] ?? date('Y-m-d');
    
    // Tratamento das datas (podem ser null)
    $data_inicio = !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : null;
    $data_nascimento = !empty($_POST['data_nascimento']) ? $_POST['data_nascimento'] : null;
    
    $grupo_atual = $_POST['grupo_atual'] ?? 'Our Meetups';
    $observacoes = $_POST['observacoes'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    // Remove tudo que não for número do telefone
    $telefone_limpo = preg_replace('/\D/', '', $telefone);

    if ($id > 0) {
        // UPDATE
        $sql = "UPDATE mentoria_alunos SET 
                nome = :nome, telefone = :telefone, status_aluno = :status_aluno, 
                valor_mensalidade = :valor_mensalidade, total_investido = :total_investido,
                dia_vencimento = :dia_vencimento, proximo_vencimento = :proximo_vencimento, 
                data_inicio = :data_inicio, data_nascimento = :data_nascimento, grupo_atual = :grupo_atual, 
                observacoes = :observacoes 
                WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'nome' => $nome, 'telefone' => $telefone_limpo, 'status_aluno' => $status_aluno,
            'valor_mensalidade' => $valor_mensalidade, 'total_investido' => $total_investido, 
            'dia_vencimento' => $dia_vencimento, 'proximo_vencimento' => $proximo_vencimento, 
            'data_inicio' => $data_inicio, 'data_nascimento' => $data_nascimento, 'grupo_atual' => $grupo_atual,
            'observacoes' => $observacoes, 'id' => $id
        ]);
        header('Location: mentoria.php?msg=Aluno atualizado com sucesso');
        exit;
    } else {
        // INSERT
        $sql = "INSERT INTO mentoria_alunos (nome, telefone, status_aluno, valor_mensalidade, total_investido, dia_vencimento, proximo_vencimento, data_inicio, data_nascimento, grupo_atual, observacoes) 
                VALUES (:nome, :telefone, :status_aluno, :valor_mensalidade, :total_investido, :dia_vencimento, :proximo_vencimento, :data_inicio, :data_nascimento, :grupo_atual, :observacoes)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'nome' => $nome, 'telefone' => $telefone_limpo, 'status_aluno' => $status_aluno,
            'valor_mensalidade' => $valor_mensalidade, 'total_investido' => $total_investido, 
            'dia_vencimento' => $dia_vencimento, 'proximo_vencimento' => $proximo_vencimento, 
            'data_inicio' => $data_inicio, 'data_nascimento' => $data_nascimento, 'grupo_atual' => $grupo_atual,
            'observacoes' => $observacoes
        ]);
        header('Location: mentoria.php?msg=Novo aluno cadastrado com sucesso');
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
    <style>
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

                <div class="form-grid">
                    <div class="form-group full">
                        <label>Nome Completo do Aluno</label>
                        <input type="text" name="nome" required value="<?= htmlspecialchars($aluno['nome'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Telefone WhatsApp (Com DDD)</label>
                        <input type="text" name="telefone" required value="<?= htmlspecialchars($aluno['telefone'] ?? '') ?>" placeholder="Brasil: 11999998888 | Internacional: 818030606423">
                    </div>

                    <div class="form-group">
                        <label>Status do Aluno</label>
                        <select name="status_aluno" required>
                            <option value="Ativo" <?= ($aluno['status_aluno']??'') === 'Ativo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="Inativo" <?= ($aluno['status_aluno']??'') === 'Inativo' ? 'selected' : '' ?>>Inativo</option>
                            <option value="Vitalício" <?= ($aluno['status_aluno']??'') === 'Vitalício' ? 'selected' : '' ?>>Vitalício</option>
                        </select>
                    </div>

                    <div class="form-group">
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

                    <div class="form-group">
                        <label>Data Exata do Próximo Vencimento</label>
                        <input type="date" name="proximo_vencimento" required value="<?= htmlspecialchars($aluno['proximo_vencimento'] ?? date('Y-m-d')) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Dia Fixo de Vencimento (1 a 31)</label>
                        <input type="number" name="dia_vencimento" min="1" max="31" required value="<?= htmlspecialchars($aluno['dia_vencimento'] ?? '') ?>">
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
</body>
</html>
