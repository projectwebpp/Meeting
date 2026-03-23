<?php
require_once 'config.php';

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }
    }
    return $pdo;
}

// ฟังก์ชันที่ใช้ร่วมกัน
function getPrimaryUserId($lineUserId) {
    if (!$lineUserId) return null;
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT primary_user_id FROM user_links WHERE secondary_user_id = ? AND active = TRUE");
    $stmt->execute([$lineUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['primary_user_id'] : $lineUserId;
}

function getLinkedUserIds($primaryUserId) {
    $pdo = getDB();
    $ids = [$primaryUserId];
    $stmt = $pdo->prepare("SELECT secondary_user_id FROM user_links WHERE primary_user_id = ? AND active = TRUE");
    $stmt->execute([$primaryUserId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ids[] = $row['secondary_user_id'];
    }
    return $ids;
}

function linkUserIds($primary, $secondary, $source) {
    if ($primary === $secondary) return false;
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO user_links (primary_user_id, secondary_user_id, source, linked_at) VALUES (?, ?, ?, NOW()) ON CONFLICT (primary_user_id, secondary_user_id) DO NOTHING");
    return $stmt->execute([$primary, $secondary, $source]);
}
?>
