#!/bin/bash
mysql -u root -p'SenhaMeetups2026' encontrodeidiomas -e "SELECT id, language_id, status, titulo_final, created_at, updated_at FROM odysee_publish_queue ORDER BY id DESC LIMIT 5;"
