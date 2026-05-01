<?php
require_once 'config.php';

function normalizeForOdysee($name) {
    // Especial para Servo-Croata
    if ($name === 'Sérvio' || $name === 'Servo-Croata') return 'ServoCroata';
    
    $map = [
        'á'=>'a', 'à'=>'a', 'ã'=>'a', 'â'=>'a', 'é'=>'e', 'ê'=>'e', 'í'=>'i', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ú'=>'u', 'ç'=>'c',
        'Á'=>'A', 'À'=>'A', 'Ã'=>'A', 'Â'=>'A', 'É'=>'E', 'Ê'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ú'=>'U', 'Ç'=>'C'
    ];
    $normalized = strtr($name, $map);
    return preg_replace('/[^A-Za-z0-0]/', '', $normalized);
}

try {
    $conn = connectDB();
    
    // 1. Atualizar nome do idioma Sérvio para Servo-Croata
    $stmt = $conn->prepare("UPDATE languages SET name = 'Servo-Croata' WHERE name = 'Sérvio'");
    $stmt->execute();
    echo "Idioma atualizado para Servo-Croata.<br>";

    // 2. Buscar todos os idiomas
    $stmt = $conn->query("SELECT id, name FROM languages");
    $languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Atualizar links de todos os eventos baseados no idioma
    $updateStmt = $conn->prepare("UPDATE events SET youtube_link = :link WHERE language_id = :lang_id");
    
    foreach ($languages as $lang) {
        $odyseeName = normalizeForOdysee($lang['name']);
        $newLink = "https://odysee.com/@EncontrodeIdiomas" . $odyseeName;
        
        $updateStmt->execute([
            ':link' => $newLink,
            ':lang_id' => $lang['id']
        ]);
        echo "Eventos de {$lang['name']} atualizados para: {$newLink}<br>";
    }

    echo "<strong>Sucesso! Todos os links foram migrados para o Odysee.</strong>";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
