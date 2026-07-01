#!/bin/bash
sshpass -p '#Nadier38' ssh -o StrictHostKeyChecking=no -p 65002 u879045076_carlos@77.37.127.146 'cd domains/dev.encontrodeidiomas.com.br/public_html && git pull origin dev'
