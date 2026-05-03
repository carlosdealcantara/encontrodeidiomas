<?php
require_once '../config.php';
$conn = connectDB();

// Ajuste fino para os ícones baseados no título (para recuperar a diversidade)
$updates = [
    'Grupo com Encontros de Múltiplos Idiomas' => 'fas fa-globe-americas',
    'Encontro Presencial na Sua Cidade'      => 'fas fa-map-marker-alt',
    'Inglês'                                  => 'fas fa-language',
    'Todos os Outros Idiomas'                => 'fas fa-earth-americas',
    'Agenda dos Encontros Online'            => 'fas fa-calendar-days'
];

foreach ($updates as $title => $icon) {
    $stmt = $conn->prepare("UPDATE useful_links SET icon = ? WHERE title = ?");
    $stmt->execute([$icon, $title]);
    echo "Atualizado: $title -> $icon\n";
}
echo "Concluído!";
