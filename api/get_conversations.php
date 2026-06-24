<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$userId = 1;

$stmt = $pdo->prepare("
    SELECT id, title, created_at
    FROM conversations
    WHERE user_id = ?
    ORDER BY created_at DESC
");

$stmt->execute([$userId]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
