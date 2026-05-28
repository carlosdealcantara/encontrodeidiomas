<?php require 'config.php'; \ = \->query('SELECT nome, group_id FROM meetup_whatsapp_groups LIMIT 3'); echo json_encode(\->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT); ?>
