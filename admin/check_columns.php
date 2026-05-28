<?php require 'config.php'; \ = \->query('SHOW COLUMNS FROM meetup_whatsapp_templates'); print_r(\->fetchAll(PDO::FETCH_ASSOC)); ?>
