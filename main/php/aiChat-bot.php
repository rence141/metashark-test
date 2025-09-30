<?php
// aiChat-bot.php
// Updated to use secure config, professional responses, and conversation history

header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set("display_errors", 1);

// Include the secure config file and database
require_once 'aiConfig.php'; // Adjust path if needed (e.g., '../config/aiConfig.php')
include("db.php");

// Start session for user context
session_start();

$input = json_decode(file_get_contents("php://input"), true);

// Extract user message safely
$user_message_temp = isset($input["message"]) ? $input["message"] : "";
$user_id_temp = $_SESSION['user_id'] ?? (isset($input["user_id"]) ? $input["user_id"] : null); // Use session or input
$role_temp = isset($input["role"]) ? $input["role"] : "user"; // For saving

$apiKey = OPENROUTER_API_KEY;

if (empty($user_message_temp) || !$user_id_temp) {
    echo json_encode(["reply" => "No message or user ID received."]);
    exit;
}

// Fetch recent conversation history (last 10 messages, alternating user/AI)
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

// Build messages array: reverse history to chronological, add system, add current user message
$messages = [
    ["role" => "system", "content" => "You are a professional and polite staff member of Meta Shark. Always respond courteously, using phrases like 'please', 'thank you', and 'you're welcome' where appropriate. Keep responses concise, informative, and relevant to shopping, gaming, or site queries. Do not use emojis or Markdown formatting such as **bold** or *italics*."]
];

// Add history in chronological order (oldest first)
for ($i = count($history) - 1; $i >= 0; $i--) {
    $messages[] = ["role" => $history[$i]['role'], "content" => $history[$i]['message']];
}

// Add current user message
$messages[] = ["role" => "user", "content" => $user_message_temp];

// OpenRouter API endpoint
$url = "https://openrouter.ai/api/v1/chat/completions";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Prevent hangs
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $apiKey",
    "Content-Type: application/json",
    "HTTP-Referer: https://your-site.com",  // Replace with your domain
    "X-Title: Meta Shark AI Chatbot"         // For usage tracking
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "model" => "deepseek/deepseek-r1:free", // Free, high-performance DeepSeek R1 (o1-level reasoning, 2025 standard)
    "messages" => $messages,
    "temperature" => 0.7, 
    "max_tokens" => 500   // Cap length 
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $error = "cURL Error (" . curl_errno($ch) . "): " . curl_error($ch);
    error_log("Meta Shark AI Debug: " . $error);
    echo json_encode(["reply" => $error]);
    curl_close($ch);
    exit;
}

curl_close($ch);

error_log("Meta Shark AI HTTP: $httpCode");
error_log("Meta Shark AI Response: $response");

$data = json_decode($response, true);

if (isset($data["error"])) {
    $errorMsg = "API Error: " . $data["error"]["message"];
    if (strpos($errorMsg, "User not found") !== false) {
        $errorMsg .= " Please generate a new API key at https://openrouter.ai/keys and update aiConfig.php.";
    }
    error_log("Meta Shark AI Error: " . $errorMsg);
    echo json_encode(["reply" => $errorMsg]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode(["reply" => "HTTP $httpCode: Try again later."]);
    exit;
}

// Extract reply safely without ?? in expression
$reply_content_temp = isset($data["choices"][0]["message"]["content"]) ? $data["choices"][0]["message"]["content"] : "No response from AI.";
$reply_temp = $reply_content_temp;

// Save user message and AI reply to database
$save_user = $conn->prepare("INSERT INTO ai_chats (user_id, role, message, timestamp) VALUES (?, ?, ?, NOW())");
$user_role = "user";
$save_user->bind_param("iss", $user_id_temp, $user_role, $user_message_temp);
$save_user->execute();

$save_ai = $conn->prepare("INSERT INTO ai_chats (user_id, role, message, timestamp) VALUES (?, ?, ?, NOW())");
$ai_role = "ai";
$save_ai->bind_param("iss", $user_id_temp, $ai_role, $reply_temp);
$save_ai->execute();

$save_user->close();
$save_ai->close();

echo json_encode(["reply" => trim($reply_temp)]);
?>