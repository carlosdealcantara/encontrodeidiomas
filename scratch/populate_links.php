<?php
require_once __DIR__ . '/../config.php';

function populateLinks() {
    $conn = connectDB();
    
    $links = [
        ['Online - Grupo com os Encontros de Todos os Idiomas', 'https://chat.whatsapp.com/KJl1q7Uy9w1314gkFSdV42', 'fab fa-whatsapp', 1],
        ['Online - Agenda dos Encontros', 'https://www.instagram.com/p/DBXWzhEMtat/', 'far fa-calendar-alt', 2],
        ['Inglês - Comunidade', 'https://chat.whatsapp.com/LSHuFIfFO7TF1AmI80gIhf', 'fas fa-comment-dots', 3],
        ['Todos os Outros Idiomas - Comunidade', 'https://chat.whatsapp.com/Bx7SarMQzscADqcvg05Fk5', 'fas fa-globe-americas', 4],
        ['Presencial - Vídeo de Apresentação', 'https://www.instagram.com/p/Crl2SMSgn8y/', 'fas fa-video', 5],
        ['Presencial na Sua Cidade - Comunidade', 'https://chat.whatsapp.com/EvCdZw4MZ7GBsLiy0MkPFi', 'fas fa-map-marker-alt', 6]
    ];

    foreach ($links as $l) {
        $stmt = $conn->prepare("INSERT INTO useful_links (title, url, icon, order_index) VALUES (?, ?, ?, ?)");
        $stmt->execute($l);
        echo "Adicionado: {$l[0]}\n";
    }
}

populateLinks();
