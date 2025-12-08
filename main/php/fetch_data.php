<?php
if ($action === 'top_products') {
    // 1. Clean the output buffer to prevent JSON syntax errors
    // (This fixes issues where headers or whitespace break the AJAX)
    if (ob_get_length()) ob_clean(); 
    header('Content-Type: application/json');

    // 2. The Query
    // Ensure 'stock_quantity' matches your database column exactly.
    // If your DB uses 'stock' or 'quantity', change it below.
    $sql = "SELECT id, name, category, stock_quantity, price 
            FROM products 
            ORDER BY id DESC 
            LIMIT 5";

    $result = $conn->query($sql);
    
    $data = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
    } else {
        // 3. Send SQL Error if query fails
        echo json_encode(['error' => 'SQL Error: ' . $conn->error]);
    }
    
    exit;
}

?>