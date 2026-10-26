<?php
require_once __DIR__ . '/vendor/autoload.php';

use Gemini\Data\GoogleSearch;
use Gemini\Data\Tool;

// 1. Handle API Key setup (Replace with your actual key string if not using a system .env)
$apiKey = getenv('GEMINI_API_KEY') ?: 'YOUR_API_KEY_HERE'; 

if ($apiKey === 'YOUR_API_KEY_HERE') {
    die("Error: Please set your Gemini API key inside the script or environment.");
}

$responseText = "";
$sources = [];
$query = isset($_POST['query']) ? trim($_POST['query']) : "";

// 2. Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($query)) {
    try {
        // Initialize the Gemini client
        $client = Gemini::client($apiKey);
        
        // Use gemini-2.0-flash (fast, supports search grounding natively)
        $model = $client->generativeModel(model: 'gemini-3.6-flash');

        // Execute prompt while passing GoogleSearch as a native model tool
        $response = $model
            ->withTool(new Tool(googleSearch: GoogleSearch::from()))
            ->generateContent($query);

        $responseText = $response->text();

        // 3. Extract ground-truth search citations via property array index
        $candidates = $response->candidates;
        if (!empty($candidates) && isset($candidates[0]->groundingMetadata)) {
            $metadata = $candidates[0]->groundingMetadata;
            if (!empty($metadata) && !empty($metadata->groundingChunks)) {
                foreach ($metadata->groundingChunks as $chunk) {
                    if (isset($chunk->web)) {
                        $sources[] = [
                            'title' => $chunk->web->title,
                            'url' => $chunk->web->uri
                        ];
                    }
                }
            }
        }
    } catch (\Exception $e) {
        $responseText = "API Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gemini Search Grounding Assistant</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 700px; margin: 40px auto; padding: 0 20px; line-height: 1.6; }
        input[type="text"] { width: 80%; padding: 10px; font-size: 16px; }
        button { padding: 10px 20px; font-size: 16px; cursor: pointer; }
        .box { background: #f4f4f9; padding: 20px; border-radius: 8px; margin-top: 20px; }
        .sources { background: #eef9f1; padding: 15px; border-radius: 8px; margin-top: 15px; font-size: 14px; }
    </style>
</head>
<body>

    <h2>Ask Gemini (with Google Search enabled)</h2>
    <form method="POST">
        <input type="text" name="query" placeholder="Ask something current (e.g., SpaceX launch schedule)..." value="<?php echo htmlspecialchars($query); ?>" required>
        <button type="submit">Ask AI</button>
    </form>

    <?php if (!empty($responseText)): ?>
        <div class="box">
            <strong>Response:</strong>
            <p><?php echo nl2br(htmlspecialchars($responseText)); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($sources)): ?>
        <div class="sources">
            <strong>Google Search Sources Visited:</strong>
            <ul>
                <?php foreach ($sources as $source): ?>
                    <li><a href="<?php echo htmlspecialchars($source['url']); ?>" target="_blank"><?php echo htmlspecialchars($source['title']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

</body>
</html>
