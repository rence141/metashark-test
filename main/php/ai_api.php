<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set("display_errors", 1);

$input = json_decode(file_get_contents("php://input"), true);
$userMessage = $input["message"] ?? "";

$apiKey = "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"; // Get a new one: https://openrouter.ai/keys
// For production, move this to a secure config file, e.g., require_once 'aiConfig.php'; $apiKey = OPENROUTER_API_KEY;

if (empty($userMessage)) {
    echo json_encode(["reply" => "No message received."]);
    exit;
}

// OpenRouter API endpoint
$url = "https://openrouter.ai/api/v1/chat/completions";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Add timeout to prevent hangs
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $apiKey",
    "Content-Type: application/json",
    "HTTP-Referer: https://your-site.com",  // Replace with your domain for attribution
    "X-Title: Meta Shark Chatbot"           // Helps OpenRouter track usage
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "model" => "deepseek/deepseek-r1:free", // Free, reliable DeepSeek chat model (2025 verified)
    "messages" => [
        ["role" => "system", "content" => "You are Verna, the Meta AI Attendant and staff member of Meta Shark. You specialize in assisting buyers and providing customer service for shopping, gaming, and site-related queries. Always introduce yourself as Verna if it's the first interaction in the conversation. Respond professionally and politely, using courteous language like 'please', 'thank you', and 'you're welcome' where appropriate. Keep responses concise, informative, and strictly relevant to customer needs. Never disclose internal site information, operational details, pricing structures, backend processes, or any non-public content—focus only on buyer-facing guidance and customer support. Do not use emojis or Markdown formatting such as **bold** or *italics*."],
        ["role" => "user", "content" => $userMessage]
    ],
    "temperature" => 0.7, // Optional: Tune for creativity
    "max_tokens" => 500   // Limit response length to save credits
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $error = "cURL Error (" . curl_errno($ch) . "): " . curl_error($ch);
    error_log("Meta Shark Debug: " . $error); // Check server logs
    echo json_encode(["reply" => $error]);
    curl_close($ch);
    exit;
}

curl_close($ch);

error_log("Meta Shark HTTP Code: $httpCode"); // Log for debugging
error_log("Meta Shark Raw Response: $response");

$data = json_decode($response, true);

if (isset($data["error"])) {
    $errorMsg = "API Error (" . ($data["error"]["code"] ?? "unknown") . "): " . $data["error"]["message"];
    if (strpos($errorMsg, "User not found") !== false) {
        $errorMsg .= " Please generate a new API key at https://openrouter.ai/keys and update your configuration.";
    }
    error_log("Meta Shark API Debug: " . $errorMsg);
    echo json_encode(["reply" => $errorMsg]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode(["reply" => "HTTP Error ($httpCode): Server issue—try again soon."]);
    exit;
}

$reply = $data["choices"][0]["message"]["content"] ?? "No reply from AI—unexpected format.";
echo json_encode(["reply" => trim($reply)]); // Trim whitespace
?>