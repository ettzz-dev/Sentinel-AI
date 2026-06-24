<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$input = json_decode(file_get_contents("php://input"), true);
$action = $input['action'] ?? '';

// If accessed directly via browser (GET), default the action to run anyway
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = 'load_conversations';
}

if ($action === 'load_conversations') {
    try {
        // Fetch real history entries from database
        $stmt = $pdo->prepare("SELECT id, title, created_at FROM conversations ORDER BY created_at DESC");
        $stmt->execute();
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "success" => true,
            "conversations" => $conversations
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
    exit;
}