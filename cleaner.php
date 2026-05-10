<?php
$files = ['git-fix.php', 'cleaner.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        unlink($file);
        echo "Arquivo $file removido com sucesso.<br>";
    }
}
?>
