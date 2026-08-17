sed -i "s/DB_PASS.*/DB_PASS=#Nadier38/" /home/ubuntu/encontrodeidiomas/.env
sudo docker stop odysee-worker
sudo docker rm odysee-worker
sudo docker run -d --name odysee-worker --network host --restart always --env-file /home/ubuntu/encontrodeidiomas/.env -v /home/ubuntu/encontrodeidiomas/google_service_account.json:/app/google_service_account.json -v /home/ubuntu/encontrodeidiomas/odysee_worker/screenshots:/app/screenshots odysee-worker-img
