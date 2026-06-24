<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$conversationId = $_GET['conversation_id'] ?? null;

if (!$conversationId) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT role, content
    FROM messages
    WHERE conversation_id = ?
    ORDER BY created_at ASC
");

$stmt->execute([$conversationId]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
