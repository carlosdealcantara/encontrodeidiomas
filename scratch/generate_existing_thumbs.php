<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

echo "Gerando miniaturas para hosts existentes...\n";

$stmt = $conn->query("SELECT profile_picture FROM hosts WHERE profile_picture != '' AND profile_picture != 'HostSemFoto.png'");
$hosts = $stmt->fetchAll();

foreach ($hosts as $h) {
    $fileName = $h['profile_picture'];
    $targetPath = __DIR__ . '/../assets/images/' . $fileName;
    $thumbName = str_replace('.', '_thumb.', $fileName);
    $thumbPath = __DIR__ . '/../assets/images/' . $thumbName;

    if (file_exists($targetPath)) {
        $ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        $img = null;
        if ($ext === 'jpg' || $ext === 'jpeg') $img = @imagecreatefromjpeg($targetPath);
        elseif ($ext === 'png') $img = @imagecreatefrompng($targetPath);
        elseif ($ext === 'webp') $img = @imagecreatefromwebp($targetPath);

        if ($img) {
            $width = imagesx($img);
            $height = imagesy($img);
            $size = min($width, $height);
            $thumb = imagecreatetruecolor(80, 80);
            imagecopyresampled($thumb, $img, 0, 0, ($width-$size)/2, ($height-$size)/2, 80, 80, $size, $size);
            imagejpeg($thumb, $thumbPath, 80);
            echo "Gerado: $thumbName\n";
            imagedestroy($img);
            imagedestroy($thumb);
        }
    }
}
echo "Concluído!\n";
