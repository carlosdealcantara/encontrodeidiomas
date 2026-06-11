<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/whatsapp_helper.php';

$conn = connectDB();
$config = getMentoriaConfig();
$group_jid = $config['groups']['our_meetups']['jid'] ?? '120363294371457065@g.us'; 

$days = [1, 2, 3, 4]; // Seg a Qui
$times = ['08:00', '20:00'];
$link = 'meet.google.com/abc-defg-hij';

foreach ($days as $day) {
    foreach ($times as $time) {
        $stmt = $conn->prepare("INSERT INTO meetup_schedule (group_jid, day_of_week, start_time, meet_link, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$group_jid, $day, $time, $link]);
    }
}
echo "Schedules seeded!";
?>
