#!/bin/bash
UNHEALTHY=$(sudo docker ps -q -f "name=odysee-worker" -f "health=unhealthy")

if [ ! -z "$UNHEALTHY" ]; then
    echo "$(date): Odysee Worker Unhealthy. Restarting..."
    sudo docker restart odysee-worker
    mysql -h 77.37.127.146 -u u879045076_ei '-p#Nadier38' u879045076_ei -e "INSERT INTO odysee_worker_restarts (reason) VALUES ('Docker Healthcheck Failed');"
fi
