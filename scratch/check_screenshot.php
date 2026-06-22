<?php
$file = '/home/ubuntu/encontrodeidiomas/scratch/screenshot.png';
if (file_exists($file)) {
    echo "Screenshot exists. Size: " . filesize($file) . " bytes.";
} else {
    echo "Screenshot does NOT exist.";
}
