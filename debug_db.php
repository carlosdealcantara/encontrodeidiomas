<?php
require_once 'config.php';

echo "--- LISTA DE IDIOMAS ---\n";
try {
    $conn = connectDB();
    $stmt = $conn->query("SELECT id, name, active FROM languages");
    while ($row = $stmt->fetch()) {
        echo "ID: {$row['id']} | Nome: {$row['name']} | Ativo: {$row['active']}\n";
    }
} catch (Exception $e) {
    echo "Erro Idiomas: " . $e->getMessage() . "\n";
}

echo "\n--- EVENTOS DE JAPONÊS (OU SIMILARES) ---\n";
try {
    $stmt = $conn->query("SELECT e.id, e.day_of_week, e.time_hour, e.active, l.name as lang 
                          FROM events e 
                          JOIN languages l ON e.language_id = l.id 
                          WHERE l.name LIKE '%apon%'");
    while ($row = $stmt->fetch()) {
        echo "ID: {$row['id']} | Dia: {$row['day_of_week']} | Hora: {$row['time_hour']} | Ativo: {$row['active']} | Idioma: {$row['lang']}\n";
    }
} catch (Exception $e) {
    echo "Erro Eventos: " . $e->getMessage() . "\n";
}
?>
