<?php
require_once "config.php";
$conn = connectDB();
$stmt = $conn->prepare("UPDATE mentoria_desafio_streaks SET current_streak = 18, longest_streak = 18, last_completed_date = '2026-06-30', total_completions = 18 WHERE id = 1");
$stmt->execute();
echo "Flavia updated successfully!";
