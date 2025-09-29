<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set("display_errors", 1);

$input = json_decode(file_get_contents("php://input"), true);
$userMessage = $input["message"] ?? "";

$apiKey = "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"; // Your "meta" unlimited key (usage: $0)

if (empty($userMessage)) {
    echo json_encode(["reply" => "⚠️ No message received."]);
    exit;
}

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
    "messages" => [
        ["role" => "system", "content" => "You are a helpful AI Meta Shark Staff. Keep responses concise, fun, and relevant to shopping, gaming, or site queries."],
        ["role" => "user", "content" => $userMessage]
    ],
    "temperature" => 0.7, 
    "max_tokens" => 500   // Cap length 
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $error = "⚠️ cURL Error (" . curl_errno($ch) . "): " . curl_error($ch);
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
    $errorMsg = " API Error: " . $data["error"]["message"];
    error_log("Meta Shark AI Error: " . $errorMsg);
    echo json_encode(["reply" => $errorMsg]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode(["reply" => " HTTP $httpCode: Try again later."]);
    exit;
}

$reply = $data["choices"][0]["message"]["content"] ?? " No response from AI.";
echo json_encode(["reply" => trim($reply)]);
?>