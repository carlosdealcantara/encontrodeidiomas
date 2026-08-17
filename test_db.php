<?php
$c = mysqli_connect('77.37.127.146', 'u879045076_carlos', '#Nadier38', 'u879045076_central');
if ($c) {
    echo "OK\n";
} else {
    echo "FAIL: " . mysqli_connect_error() . "\n";
}
