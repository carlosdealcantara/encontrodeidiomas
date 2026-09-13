<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: text/html; charset=utf-8');

try {
    $conn = connectDB();
    $rows = $conn->query("
        SELECT m.id, l.name as idioma, m.title, m.comunidade, m.active, m.day_of_week, m.time_hour
        FROM meetings m
        JOIN languages l ON m.language_id = l.id
        ORDER BY m.comunidade DESC, l.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "<h1>Encontros e suas Comunidades</h1>";
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Idioma</th><th>Título</th><th>Comunidade</th><th>Ativo</th><th>Dia</th><th>Hora</th></tr>";
    foreach ($rows as $r) {
        echo "<tr>";
        echo "<td>{$r['id']}</td>";
        echo "<td>{$r['idioma']}</td>";
        echo "<td>" . htmlspecialchars($r['title'] ?? '') . "</td>";
        echo "<td><b>{$r['comunidade']}</b></td>";
        echo "<td>" . ($r['active'] ? 'Sim' : 'Não') . "</td>";
        echo "<td>{$r['day_of_week']}</td>";
        echo "<td>{$r['time_hour']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "Erro: " . htmlspecialchars($e->getMessage());
}
