#!/bin/bash
sshpass -p '#Nadier38' ssh -o StrictHostKeyChecking=no -p 65002 u879045076@77.37.127.146 'cd domains/viaei.com/public_html/dev && git pull origin dev'
