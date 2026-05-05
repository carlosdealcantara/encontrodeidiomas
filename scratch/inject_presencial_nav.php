<?php
$files = ['hosts.php','index.php','meetings.php','languages.php','useful_links.php','settings.php','host_form.php','meeting_form.php'];
$presencialNav = '            <a href="presencial.php" class="nav-item"><i class="fas fa-map-marker-alt"></i> Presencial</a>';

foreach ($files as $f) {
    $path = __DIR__ . '/admin/' . $f;
    $content = file_get_contents($path);
    if (strpos($content, 'presencial.php') !== false) {
        echo $f . ": already has presencial\n";
        continue;
    }
    // Match the full meetings nav-item (could have class="nav-item" or class="nav-item active")
    $pattern = '/(<a href="meetings\.php" class="nav-item[^"]*">[^<]*<\/a>)/s';
    $count = 0;
    $new = preg_replace($pattern, '$1' . "\n" . $presencialNav, $content, 1, $count);
    if ($count > 0) {
        file_put_contents($path, $new);
        echo $f . ": updated OK\n";
    } else {
        echo $f . ": pattern not found\n";
    }
}
