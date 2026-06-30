<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('tly_api_key', 'sExaM2oyhcL6NfqykK3dMHbPbWM82LmptmJQLBXal5cP2ClhBgdATaN1Z6JB') ON DUPLICATE KEY UPDATE setting_value = 'sExaM2oyhcL6NfqykK3dMHbPbWM82LmptmJQLBXal5cP2ClhBgdATaN1Z6JB'");
$conn->query("UPDATE odysee_publish_queue SET odysee_url = 'https://t.ly/5Ic6e' WHERE language_id = 7 ORDER BY id DESC LIMIT 1");
echo "SUCCESS";
