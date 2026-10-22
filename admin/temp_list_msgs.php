<?php
require_once __DIR__ . '/../config.php';
 = connectDB();
 = ->query( SELECT id cenario dias_antes lang_id ativo FROM mentoria_mensagens);
echo json_encode(->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
