<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

function autoAssociate() {
    $conn = connectDB();
    
    // 1. Busca todos os encontros que estão sem host
    $stmt = $conn->query("
        SELECT m.id, m.language_id, l.name as lang_name 
        FROM meetings m 
        JOIN languages l ON m.language_id = l.id 
        WHERE m.host_id IS NULL
    ");
    $meetings = $stmt->fetchAll();
    
    echo "Iniciando associação automática...\n";
    $count = 0;

    foreach ($meetings as $m) {
        // Limpa o nome do idioma para a busca (remove parênteses)
        $cleanLang = trim(preg_replace('/\s*\(.*?\)\s*/', '', $m['lang_name']));
        
        // 2. Busca hosts que atuam online e possuem esse idioma
        $stmtHost = $conn->prepare("
            SELECT id, full_name 
            FROM hosts 
            WHERE category LIKE '%Online%' 
            AND languages LIKE :lang
            AND status = 'ativo'
        ");
        $stmtHost->execute(['lang' => '%' . $cleanLang . '%']);
        $possibleHosts = $stmtHost->fetchAll();

        // 3. Se houver EXATAMENTE um host, associa
        if (count($possibleHosts) === 1) {
            $host = $possibleHosts[0];
            $update = $conn->prepare("UPDATE meetings SET host_id = ? WHERE id = ?");
            $update->execute([$host['id'], $m['id']]);
            echo "Encontro ID {$m['id']} ({$m['lang_name']}) -> Associado a {$host['full_name']}\n";
            $count++;
        } else if (count($possibleHosts) > 1) {
            echo "Encontro ID {$m['id']} ({$m['lang_name']}) -> Pulado (Múltiplos hosts encontrados)\n";
        } else {
            echo "Encontro ID {$m['id']} ({$m['lang_name']}) -> Nenhum host específico encontrado.\n";
        }
    }

    echo "Fim do processo. $count associações realizadas.\n";
}

autoAssociate();
