<?php
// payment_gcash.php
// Initiates a real GCash payment via PayMongo

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
include("db.php");

require_once __DIR__ . '/../../vendor/autoload.php'; // Guzzle

if (!isset($_SESSION['user_id'])) { header("Location: login_users.php"); exit(); }

$orderId = intval($_GET['order_id'] ?? ($_SESSION['pending_payment_order_id'] ?? 0));
$method = $_GET['method'] ?? ($_SESSION['pending_payment_method'] ?? '');

if ($orderId <= 0 || $method !== 'gcash') {
    header("Location: shop.php");
    exit();
}

// Verify order belongs to user and is pending
$stmt = $conn->prepare("SELECT id, total, status FROM orders WHERE id = ? AND user_id = ?");
if ($stmt) {
    $stmt->bind_param("ii", $orderId, $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows !== 1) { header("Location: shop.php"); exit(); }
    $order = $res->fetch_assoc();
    if ($order['status'] !== 'pending') { header("Location: shop.php"); exit(); }
}

// PayMongo API credentials (store securely in production!)
$secretKey = 'sk_test_xxx'; // Replace with your PayMongo secret key

use GuzzleHttp\Client;

try {
    $client = new Client([
        'base_uri' => 'https://api.paymongo.com/v1/',
        'auth' => [$secretKey, ''],
    ]);

    // 1. Create Payment Intent
    $response = $client->post('payment_intents', [
        'json' => [
            'data' => [
                'attributes' => [
                    'amount' => intval($order['total'] * 100), // PHP to centavos
                    'payment_method_allowed' => ['gcash'],
                    'payment_method_options' => ['gcash' => new stdClass()],
                    'currency' => 'PHP'
                ]
            ]
        ]
    ]);
    $body = json_decode($response->getBody(), true);
    $intentId = $body['data']['id'] ?? null;
    $clientKey = $body['data']['attributes']['client_key'] ?? null;

    if (!$intentId || !$clientKey) {
        echo "<pre>Payment Intent Error:\n";
        var_dump($body);
        echo "</pre>";
        exit();
    }

    // 2. Create Payment Method for GCash
    $gcashNumber = $_POST['gcash_number'] ?? '';
    $pmResponse = $client->post('payment_methods', [
        'json' => [
            'data' => [
                'attributes' => [
                    'type' => 'gcash',
                    'details' => [ 'phone' => $gcashNumber ]
                ]
            ]
        ]
    ]);
    $pmBody = json_decode($pmResponse->getBody(), true);
    $pmId = $pmBody['data']['id'] ?? null;

    if (!$pmId) {
        echo "<pre>Payment Method Error:\n";
        var_dump($pmBody);
        echo "</pre>";
        exit();
    }

    // 3. Attach Payment Method to Intent
    $attachResponse = $client->post("payment_intents/$intentId/attach", [
        'json' => [
            'data' => [
                'attributes' => [
                    'payment_method' => $pmId,
                    'client_key' => $clientKey
                ]
            ]
        ]
    ]);
    $attachBody = json_decode($attachResponse->getBody(), true);
    $redirectUrl = $attachBody['data']['attributes']['next_action']['redirect']['url'] ?? '';

    if ($redirectUrl) {
        header("Location: $redirectUrl");
        exit();
    } else {
        echo "<pre>Attach Error:\n";
        var_dump($attachBody);
        echo "</pre>";
        exit();
    }

} catch (Exception $e) {
    echo "<pre>Exception:\n" . $e->getMessage() . "</pre>";
    exit();
}
