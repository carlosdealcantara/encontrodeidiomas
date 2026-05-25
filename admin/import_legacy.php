<?php
require_once '../config.php';

try {
    $conn = connectDB();
    
    $data = <<<EOD
Naldo - 1 março
Pietro - 2 outubro
Rosângela - 2 março
Maria Clara - 2 de maio
Rodrigo - 3 dezembro
Jaqueline - 3 março
Gui - 3 dezembro
Everton - 3 maio
Jhonatan - 3 janeiro
Wilton - 3 agosto 250
Cléo - 4 dezembro
Yngwie - 4 de fevereiro
Eva - 4 agosto
Igor - 5 março 60
Sophia - 5 fevereiro 800
Eric - 5 maio
Maria Antônia - 5 março
Jheny - 5 julho
Adélia - 5 outubro
Mallu - 5 novembro
Antônio - 5 novembro
Andreia - 5 de fevereiro
Gabriel - 5 agosto 250
Felipe Quaresma - 6 agosto
Paula Rocha - 6 março
Ayle - 6 março
Delvia - 7 março
Isly - 7 janeiro
Ualisson - 7 março
Fernanda Venancio - 7 março
Edmilson - 9 agosto
Lucas - 9 fevereiro 250
Carol - 10 março
Thaís - 10 junho
Fernando - 10 julho
Bia - 10 julho 110
Henrique - 11 janeiro 400
Nat - 11 janeiro
Paula - 11 fevereiro 215
Maikel - 12 agosto 250
Franklin - 12 outubro 2160
Alan Gabriel - 13 março
Alessio - 14 março
Mari Alves - 14 janeiro 300
Daiane - 15 novembro 500
Juliana - 15 fevereiro
Marisa - 15 maio
Karina - 15 março
Crys - 16 de novembro
Denilson - 17 abril 110
May - 18 agosto
Paula - 19 fevereiro 
Heryka - 19 novembro
Vanderlucia - 19 novembro
Joyce - 20 novembro
Allan - 20 julho
Ricardo Marques - 20 março
Paula Cristina - 21 julho
Nilva - 22 fevereiro
Nakiely - 22 março
Fabrício - 22 setembro 200
Leonardo - 22 março 250
Caminaj - 24 novembro
Pry - 24 novembro
Aparecida - 25 julho
Wellington - 25 novembro
Natane - 25 novembro
Marília - 26 novembro
Janaina - 26 novembro 200
Poliana - 27 julho 250
Luciana - 28 fevereiro 60
Veronica - 28 novembro 600
Rosana - 28 julho 200
Hugo - 28 julho 150
Nádia - 28 janeiro
Sádia - 28 janeiro
Petrus - 28 janeiro
Ivan - 28 setembro
Thiago - 28 julho
Fred - 28 julho 500
Helenice - 28 novembro 200
Mitsuo - 31 agosto
Regiane - 31 setembro
Aída - 31 setembro
Luiz - 31 setembro
EOD;

    $lines = explode("\n", trim($data));
    $meses = [
        'janeiro' => '01', 'fevereiro' => '02', 'março' => '03', 'abril' => '04',
        'maio' => '05', 'junho' => '06', 'julho' => '07', 'agosto' => '08',
        'setembro' => '09', 'outubro' => '10', 'novembro' => '11', 'dezembro' => '12'
    ];

    $sucesso = 0;
    foreach($lines as $line) {
        if(empty(trim($line))) continue;
        
        $parts = explode('-', $line);
        $nome = trim($parts[0]);
        $resto = trim($parts[1] ?? '');
        
        $resto = str_replace('de ', '', $resto);
        
        $tokens = explode(' ', trim($resto));
        $dia = str_pad($tokens[0], 2, '0', STR_PAD_LEFT);
        
        $mesNome = mb_strtolower($tokens[1] ?? 'janeiro', 'UTF-8');
        $mes = $meses[$mesNome] ?? '01';
        
        $valor = 0;
        if(isset($tokens[2])) {
            $valor = (float)$tokens[2];
        }
        
        // Data simbolica (ano 1900)
        // Se o dia for 31 e o mês não tiver 31 dias (ex: setembro), forçamos pro último dia válido para não dar erro de SQL
        $data_vencimento = "1900-{$mes}-{$dia}";
        if (!strtotime($data_vencimento)) {
            $data_vencimento = "1900-{$mes}-28"; // Fallback de segurança
        }
        
        $telefone = "Em branco"; // Placeholder obrigatório
        
        // Inserimos todos como 'Inativo' por padrão. O pagamento também fica como 'Isento' para não disparar nenhuma cobrança maluca sem querer.
        $stmt = $conn->prepare("INSERT INTO mentoria_alunos (nome, telefone, status_aluno, valor_mensalidade, dia_vencimento, proximo_vencimento, status_pagamento) VALUES (?, ?, 'Inativo', ?, ?, ?, 'Isento')");
        $stmt->execute([$nome, $telefone, $valor, (int)$dia, $data_vencimento]);
        $sucesso++;
    }

    echo "<h2>Importação Concluída!</h2>";
    echo "<p>{$sucesso} alunos importados com sucesso para o banco de dados.</p>";
    echo "<p>Eles foram cadastrados com o status 'Inativo', pagamento 'Isento' e ano '1900'. Você pode ir no painel da Mentoria para ver a lista.</p>";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
