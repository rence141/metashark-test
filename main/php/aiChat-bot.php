<?php
// aiChat-bot.php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once 'aiConfig.php'; 
include("db.php");

session_start();

$input = json_decode(file_get_contents("php://input"), true);

// Extract user message safely
$user_message_temp = isset($input["message"]) ? $input["message"] : "";
$user_id_temp = $_SESSION['user_id'] ?? (isset($input["user_id"]) ? $input["user_id"] : null); 
$apiKey = OPENROUTER_API_KEY;

if (empty($user_message_temp) || !$user_id_temp) {
    echo json_encode(["reply" => "No message or user ID received."]);
    exit;
}

// Fetch recent conversation history
$history_sql = "SELECT role, message FROM ai_chats WHERE user_id = ? ORDER BY timestamp DESC LIMIT 10";
$history_stmt = $conn->prepare($history_sql);
$history_stmt->bind_param("i", $user_id_temp);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
$history = [];
while ($row = $history_result->fetch_assoc()) {
    $history[] = $row;
}
$history_stmt->close();

// --- FIX 1: IMPROVED SYSTEM PROMPT ---
// We give the AI generic knowledge about the site so it doesn't refuse to answer.
$system_prompt = "You are Verna, the friendly and professional AI Attendant for Meta Shark.
CONTEXT:
- Meta Shark is an online marketplace for gaming gear, electronics, and accessories.
- You specialize in helping buyers choose products and answering general site queries.

GUIDELINES:
1. If this is the start of the conversation, introduce yourself briefly as Verna.
2. Be polite and professional (use 'please', 'thank you').
3. YOU DO NOT have access to the user's live database, order history, or real-time inventory. 
   - If a user asks about a specific order status, say: 'I don't have access to your account details. Please check your Dashboard > Orders page for the latest status.'
   - If a user asks for specific prices, say: 'Please check the product page for the most up-to-date pricing.'
4. You CAN answer general questions about gaming, technology, and how to navigate an e-commerce site.
5. Do not use emojis or Markdown (no bold/italics).
6. Keep answers concise (under 3 sentences usually).";

// Build messages array
$messages = [
    ["role" => "system", "content" => $system_prompt]
];

// Add history (reverse to chronological)
for ($i = count($history) - 1; $i >= 0; $i--) {
    // Strip old <think> tags from history so they don't confuse the AI
    $clean_content = preg_replace('/<think>[\s\S]*?<\/think>/', '', $history[$i]['message']);
    $messages[] = ["role" => $history[$i]['role'], "content" => trim($clean_content)];
}

// Add current user message
$messages[] = ["role" => "user", "content" => $user_message_temp];

// OpenRouter API endpoint
$url = "https://openrouter.ai/api/v1/chat/completions";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 45); // Increased timeout for R1 models
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $apiKey",
    "Content-Type: application/json",
    "HTTP-Referer: https://your-site.com",
    "X-Title: Meta Shark AI Chatbot"
]);

// --- FIX 2: MODEL CHOICE ---
// Deepseek R1 is great, but sometimes over-thinks simple support tasks.
// If this still fails, try changing the model to "google/gemini-2.0-flash-lite-preview-02-05:free"
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "model" => "deepseek/deepseek-r1:free", 
    "messages" => $messages,
    "temperature" => 0.7, 
    "max_tokens" => 1000 // R1 needs more tokens for "thinking"
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo json_encode(["reply" => "Connection Error: " . curl_error($ch)]);
    curl_close($ch);
    exit;
}
curl_close($ch);

$data = json_decode($response, true);

// Error Handling
if (isset($data["error"])) {
    error_log("AI Error: " . $data["error"]["message"]);
    echo json_encode(["reply" => "I'm having trouble connecting right now. Please try again."]);
    exit;
}

// Extract reply
$reply_content_temp = isset($data["choices"][0]["message"]["content"]) ? $data["choices"][0]["message"]["content"] : "I'm sorry, I couldn't process that.";

// --- FIX 3: STRIP <THINK> TAGS ---
// DeepSeek R1 outputs its internal thought process in <think> tags. We must remove them.
$clean_reply = preg_replace('/<think>[\s\S]*?<\/think>/', '', $reply_content_temp);
$clean_reply = trim($clean_reply);

// Fallback if the cleaning results in empty text (rare, but happens if AI only thinks)
if (empty($clean_reply)) {
    $clean_reply = "I'm sorry, could you please rephrase that?";
}

// Save to Database
$save_user = $conn->prepare("INSERT INTO ai_chats (user_id, role, message, timestamp) VALUES (?, 'user', ?, NOW())");
$save_user->bind_param("is", $user_id_temp, $user_message_temp);
$save_user->execute();

// We save the CLEAN reply to the DB, not the one with <think> tags
$save_ai = $conn->prepare("INSERT INTO ai_chats (user_id, role, message, timestamp) VALUES (?, 'ai', ?, NOW())");
$save_ai->bind_param("is", $user_id_temp, $clean_reply);
$save_ai->execute();

$save_user->close();
$save_ai->close();

echo json_encode(["reply" => $clean_reply]);
?>