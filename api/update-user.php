<?php
header('Content-Type: application/json');

include '../../includes/db.php';

// Verify webhook secret
$headers = getallheaders();
$secret = $headers['X-Webhook-Secret'] ?? '';

if ($secret !== 'ALAEHSCAPE_SECRET') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Get JSON payload
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate required fields
if (!isset($data['user_id']) || !isset($data['source'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

// Only process if coming from Aleahscape (prevent loops)
if ($data['source'] !== 'aleahscape') {
    echo json_encode(['status' => 'ignored', 'message' => 'Invalid source']);
    exit;
}

// Aleahscape sends "user_id" which maps to Webit's "user_id" column
$user_id = $data['user_id'];
$name = $data['name'] ?? '';
$email = $data['email'] ?? '';
$phone = $data['phone'] ?? '';

// Check if user exists in Webit (using user_id column)
$checkStmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
$checkStmt->bind_param("i", $user_id);
$checkStmt->execute();
$exists = $checkStmt->get_result()->num_rows > 0;

if ($exists) {
    // Update existing user (using user_id column)
    $stmt = $conn->prepare(
        "UPDATE users SET name = ?, email = ?, phone = ? WHERE user_id = ?"
    );
    $stmt->bind_param("sssi", $name, $email, $phone, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'User updated in Webit',
            'user_id' => $user_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to update user'
        ]);
    }
} else {
    // User doesn't exist - might need to create
    echo json_encode([
        'status' => 'warning',
        'message' => 'User not found in Webit database',
        'user_id' => $user_id
    ]);
}
?>