<?php
require_once 'db.php';
require_once 'config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$path = $_REQUEST['path'] ?? '';

function jsonResponse($success, $data = null, $message = '') {
    echo json_encode(['success' => $success, 'data' => $data, 'message' => $message]);
    exit;
}

try {
    switch ($path) {
        case 'user/profile':         handleUserProfile(); break;
        case 'user/update':          handleUserUpdate(); break;
        case 'user/bookings':        handleUserBookings(); break;
        case 'rooms':                handleRooms(); break;
        case 'room':                 handleRoomDetail(); break;
        case 'room/create':          handleRoomCreate(); break;
        case 'room/update':          handleRoomUpdate(); break;
        case 'room/delete':          handleRoomDelete(); break;
        case 'room/upload-image':    handleRoomUploadImage(); break;
        case 'bookings':             handleBookings(); break;
        case 'booking':              handleBookingDetail(); break;
        case 'booking/create':       handleBookingCreate(); break;
        case 'booking/update':       handleBookingUpdate(); break;
        case 'booking/cancel':       handleBookingCancel(); break;
        case 'booking/approve':      handleBookingApprove(); break;
        case 'booking/reject':       handleBookingReject(); break;
        case 'booking/admin-cancel': handleBookingAdminCancel(); break;
        case 'booking/check-availability': handleCheckAvailability(); break;
        case 'admin/stats':          handleAdminStats(); break;
        case 'admin/users':          handleAdminUsers(); break;
        case 'admin/user/role':      handleAdminUserRole(); break;
        case 'admin/user/delete':    handleAdminUserDelete(); break;
        case 'admin/all-bookings':   handleAdminAllBookings(); break;
        case 'admin/pending-bookings': handleAdminPendingBookings(); break;
        case 'admin/settings/update': handleAdminSettingsUpdate(); break;
        case 'admin/settings/get':   handleAdminSettingsGet(); break;
        case 'admin/auto-cancel-overdue': handleAutoCancelOverdue(); break;
        case 'notifications':        handleNotifications(); break;
        case 'notification/read':    handleNotificationRead(); break;
        case 'notifications/read-all': handleNotificationsReadAll(); break;
        case 'uploadImage':          handleUploadImage(); break;
        case 'user/link':            handleUserLink(); break;
        case 'user/linked-ids':      handleUserLinkedIds(); break;
        default: jsonResponse(false, null, 'Invalid path');
    }
} catch (Exception $e) {
    jsonResponse(false, null, 'Server error: ' . $e->getMessage());
}

// ========== Helper functions ==========
function generateId($prefix) { return $prefix . substr(strtoupper(uniqid()), -8); }

function isAdmin($lineUserId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT role FROM users WHERE line_user_id = ?");
    $stmt->execute([$lineUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row && $row['role'] === 'admin';
}

function isManager($lineUserId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT role FROM users WHERE line_user_id = ?");
    $stmt->execute([$lineUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row && ($row['role'] === 'admin' || $row['role'] === 'manager');
}

function createOrUpdateUser($lineUserId, $displayName, $pictureUrl, $email, $source = 'api') {
    $pdo = getDB();
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("SELECT * FROM users WHERE line_user_id = ?");
    $stmt->execute([$lineUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $stmt = $pdo->prepare("UPDATE users SET display_name = ?, picture_url = ?, email = ?, last_login = ?, updated_at = ?, last_interaction = ? WHERE line_user_id = ?");
        $stmt->execute([$displayName, $pictureUrl, $email, $now, $now, $now, $lineUserId]);
        return $user;
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $cnt = $stmt->fetchColumn();
        $role = ($cnt == 0) ? 'admin' : 'user';
        $stmt = $pdo->prepare("INSERT INTO users (line_user_id, display_name, picture_url, email, role, last_login, created_at, updated_at, last_interaction, source, welcome_sent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$lineUserId, $displayName, $pictureUrl, $email, $role, $now, $now, $now, $now, $source]);
        return ['line_user_id' => $lineUserId, 'display_name' => $displayName, 'picture_url' => $pictureUrl, 'email' => $email, 'role' => $role];
    }
}

function checkAvailability($roomId, $startTime, $endTime, $excludeBookingId = null) {
    $pdo = getDB();
    $sql = "SELECT * FROM bookings WHERE room_id = ? AND status IN ('pending','confirmed') 
            AND (start_time < ? AND end_time > ? OR start_time < ? AND end_time > ? OR start_time >= ? AND start_time < ?)";
    $params = [$roomId, $endTime, $startTime, $endTime, $startTime, $startTime, $endTime];
    if ($excludeBookingId) { $sql .= " AND booking_id != ?"; $params[] = $excludeBookingId; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['available' => count($conflicts) === 0, 'conflicting' => $conflicts];
}

function sendFlexMessage($userId, $flexMessage) {
    if (!LINE_CHANNEL_ACCESS_TOKEN) return false;
    $url = 'https://api.line.me/v2/bot/message/push';
    $data = ['to' => $userId, 'messages' => [$flexMessage]];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200;
}

function notifyAdminsNewBooking($booking) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT line_user_id FROM users WHERE role IN ('admin','manager') AND status = 'active'");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $flexMsg = createBookingFlexMessage($booking, 'new');
    foreach ($admins as $adminId) sendFlexMessage($adminId, $flexMsg);
}

function createBookingFlexMessage($booking, $type = 'new') {
    // Minimal version – you can expand later
    return [
        'type' => 'flex',
        'altText' => '📅 การจอง: ' . ($booking['title'] ?? ''),
        'contents' => [
            'type' => 'bubble',
            'header' => ['type' => 'box', 'layout' => 'vertical', 'contents' => [['type' => 'text', 'text' => '📌 มีการจองใหม่', 'weight' => 'bold', 'size' => 'xl', 'color' => '#06c755']]],
            'body' => ['type' => 'box', 'layout' => 'vertical', 'contents' => [
                ['type' => 'text', 'text' => $booking['room_name'] ?? '', 'weight' => 'bold', 'size' => 'lg', 'color' => '#06c755'],
                ['type' => 'text', 'text' => $booking['title'] ?? '', 'size' => 'md', 'color' => '#666666', 'wrap' => true],
                ['type' => 'separator', 'margin' => 'lg'],
                ['type' => 'text', 'text' => '👤 ผู้จอง: ' . ($booking['user_name'] ?? ''), 'size' => 'sm', 'color' => '#888888']
            ]]
        ]
    ];
}

// ========== Endpoint handlers ==========

function handleUserProfile() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    if (!$lineUserId) jsonResponse(false, null, 'Missing lineUserId');
    $displayName = $_REQUEST['displayName'] ?? '';
    $pictureUrl = $_REQUEST['pictureUrl'] ?? '';
    $email = $_REQUEST['email'] ?? '';
    $source = $_REQUEST['source'] ?? 'api';
    $user = createOrUpdateUser($lineUserId, $displayName, $pictureUrl, $email, $source);
    jsonResponse(true, $user);
}

function handleUserUpdate() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    if (!$lineUserId) jsonResponse(false, null, 'Missing lineUserId');
    $pdo = getDB();
    $fields = []; $params = [];
    if (isset($_REQUEST['phone'])) { $fields[] = "phone = ?"; $params[] = $_REQUEST['phone']; }
    if (isset($_REQUEST['department'])) { $fields[] = "department = ?"; $params[] = $_REQUEST['department']; }
    if (isset($_REQUEST['displayName'])) { $fields[] = "display_name = ?"; $params[] = $_REQUEST['displayName']; }
    if (isset($_REQUEST['email'])) { $fields[] = "email = ?"; $params[] = $_REQUEST['email']; }
    if (empty($fields)) jsonResponse(false, null, 'No fields to update');
    $fields[] = "updated_at = NOW()";
    $params[] = $lineUserId;
    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE line_user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, null, 'Updated');
}

function handleUserBookings() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    if (!$lineUserId) jsonResponse(false, null, 'Missing lineUserId');
    $primaryId = getPrimaryUserId($lineUserId);
    $linkedIds = getLinkedUserIds($primaryId);
    $placeholders = implode(',', array_fill(0, count($linkedIds), '?'));
    $pdo = getDB();
    $sql = "SELECT * FROM bookings WHERE user_id IN ($placeholders) ORDER BY created_at DESC LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($linkedIds);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(true, $bookings);
}

function handleRooms() {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT * FROM rooms WHERE status = 'active' ORDER BY name");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(true, $rooms);
}

function handleRoomDetail() {
    $roomId = $_REQUEST['roomId'] ?? null;
    if (!$roomId) jsonResponse(false, null, 'Missing roomId');
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE room_id = ?");
    $stmt->execute([$roomId]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$room) jsonResponse(false, null, 'Room not found');
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE room_id = ? AND status IN ('pending','confirmed') AND start_time <= ? AND end_time > ?");
    $stmt->execute([$roomId, $now, $now]);
    $currentBooking = $stmt->fetch(PDO::FETCH_ASSOC);
    $room['isAvailableNow'] = ($currentBooking === false);
    $room['currentBooking'] = $currentBooking;
    jsonResponse(true, $room);
}

function handleRoomCreate() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    if (!isAdmin($lineUserId)) jsonResponse(false, null, 'Unauthorized');
    $pdo = getDB();
    $roomId = generateId('RM');
    $stmt = $pdo->prepare("INSERT INTO rooms (room_id, name, capacity, location, description, facilities, image_url, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())");
    $stmt->execute([
        $roomId, $_REQUEST['name'] ?? '', intval($_REQUEST['capacity'] ?? 0), $_REQUEST['location'] ?? '',
        $_REQUEST['description'] ?? '', $_REQUEST['facilities'] ?? '', $_REQUEST['imageUrl'] ?? ''
    ]);
    jsonResponse(true, ['roomId' => $roomId]);
}

function handleRoomUpdate() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    if (!isAdmin($lineUserId)) jsonResponse(false, null, 'Unauthorized');
    $roomId = $_REQUEST['roomId'] ?? null;
    if (!$roomId) jsonResponse(false, null, 'Missing roomId');
    $pdo = getDB();
    $fields = []; $params = [];
    if (isset($_REQUEST['name'])) { $fields[] = "name = ?"; $params[] = $_REQUEST['name']; }
    if (isset($_REQUEST['capacity'])) { $fields[] = "capacity = ?"; $params[] = intval($_REQUEST['capacity']); }
    if (isset($_REQUEST['location'])) { $fields[] = "location = ?"; $params[] = $_REQUEST['location']; }
    if (isset($_REQUEST['description'])) { $fields[] = "description = ?"; $params[] = $_REQUEST['description']; }
    if (isset($_REQUEST['facilities'])) { $fields[] = "facilities = ?"; $params[] = $_REQUEST['facilities']; }
    if (isset($_REQUEST['imageUrl'])) { $fields[] = "image_url = ?"; $params[] = $_REQUEST['imageUrl']; }
    if (isset($_REQUEST['status'])) { $fields[] = "status = ?"; $params[] = $_REQUEST['status']; }
    if (empty($fields)) jsonResponse(false, null, 'No fields to update');
    $fields[] = "updated_at = NOW()";
    $params[] = $roomId;
    $sql = "UPDATE rooms SET " . implode(', ', $fields) . " WHERE room_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, null, 'Updated');
}

function handleRoomDelete() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    if (!isAdmin($lineUserId)) jsonResponse(false, null, 'Unauthorized');
    $roomId = $_REQUEST['roomId'] ?? null;
    if (!$roomId) jsonResponse(false, null, 'Missing roomId');
    $pdo = getDB();
    $stmt = $pdo->prepare("DELETE FROM rooms WHERE room_id = ?");
    $stmt->execute([$roomId]);
    jsonResponse(true, null, 'Deleted');
}

function handleRoomUploadImage() {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) jsonResponse(false, null, 'No file uploaded');
    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $destination = UPLOAD_DIR . $filename;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
        $fileUrl = BASE_URL . 'uploads/' . $filename;
        jsonResponse(true, ['fileUrl' => $fileUrl]);
    } else {
        jsonResponse(false, null, 'Upload failed');
    }
}

function handleBookings() {
    $pdo = getDB();
    $sql = "SELECT * FROM bookings WHERE 1=1";
    $params = [];
    if (isset($_REQUEST['roomId'])) { $sql .= " AND room_id = ?"; $params[] = $_REQUEST['roomId']; }
    if (isset($_REQUEST['date'])) { $sql .= " AND DATE(start_time) = ?"; $params[] = $_REQUEST['date']; }
    if (isset($_REQUEST['status'])) { $sql .= " AND status = ?"; $params[] = $_REQUEST['status']; }
    if (!isset($_REQUEST['showPast']) || $_REQUEST['showPast'] !== 'true') $sql .= " AND end_time >= NOW()";
    $sql .= " ORDER BY start_time";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(true, ['bookings' => $bookings]);
}

function handleBookingDetail() {
    $bookingId = $_REQUEST['bookingId'] ?? null;
    if (!$bookingId) jsonResponse(false, null, 'Missing bookingId');
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking) jsonResponse(false, null, 'Booking not found');
    jsonResponse(true, $booking);
}

function handleBookingCreate() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    if (!$lineUserId) jsonResponse(false, null, 'Missing lineUserId');
    $roomId = $_REQUEST['roomId'] ?? null;
    $startTime = $_REQUEST['startTime'] ?? null;
    $endTime = $_REQUEST['endTime'] ?? null;
    if (!$roomId || !$startTime || !$endTime) jsonResponse(false, null, 'Missing required fields');
    $avail = checkAvailability($roomId, $startTime, $endTime);
    if (!$avail['available']) jsonResponse(false, null, 'Room not available at selected time');
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT name FROM rooms WHERE room_id = ?");
    $stmt->execute([$roomId]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$room) jsonResponse(false, null, 'Room not found');
    $roomName = $room['name'];
    $primaryId = getPrimaryUserId($lineUserId);
    $bookingId = generateId('BK');
    $stmt = $pdo->prepare("INSERT INTO bookings (booking_id, room_id, room_name, user_id, original_user_id, user_name, title, description, start_time, end_time, attendees, meeting_link, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())");
    $stmt->execute([
        $bookingId, $roomId, $roomName, $primaryId, $lineUserId,
        $_REQUEST['userName'] ?? '', $_REQUEST['title'] ?? 'การจองห้องประชุม', $_REQUEST['description'] ?? '',
        $startTime, $endTime, intval($_REQUEST['attendees'] ?? 1), $_REQUEST['meetingLink'] ?? ''
    ]);
    if ($primaryId !== $lineUserId) linkUserIds($primaryId, $lineUserId, 'booking_create');
    $bookingData = [
        'booking_id' => $bookingId, 'room_name' => $roomName, 'title' => $_REQUEST['title'] ?? '',
        'start_time' => $startTime, 'end_time' => $endTime, 'user_name' => $_REQUEST['userName'] ?? '',
        'attendees' => $_REQUEST['attendees'] ?? 1
    ];
    notifyAdminsNewBooking($bookingData);
    jsonResponse(true, ['bookingId' => $bookingId, 'bookingData' => $bookingData]);
}

function handleBookingUpdate() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    $bookingId = $_REQUEST['bookingId'] ?? null;
    if (!$lineUserId || !$bookingId) jsonResponse(false, null, 'Missing lineUserId or bookingId');
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking) jsonResponse(false, null, 'Booking not found');
    $primaryId = getPrimaryUserId($lineUserId);
    if ($booking['user_id'] !== $primaryId && !isAdmin($primaryId)) jsonResponse(false, null, 'Unauthorized');
    if ($booking['status'] !== 'pending') jsonResponse(false, null, 'Cannot update confirmed/rejected/cancelled booking');
    if (isset($_REQUEST['startTime']) || isset($_REQUEST['endTime']) || isset($_REQUEST['roomId'])) {
        $newRoomId = $_REQUEST['roomId'] ?? $booking['room_id'];
        $newStart = $_REQUEST['startTime'] ?? $booking['start_time'];
        $newEnd = $_REQUEST['endTime'] ?? $booking['end_time'];
        $avail = checkAvailability($newRoomId, $newStart, $newEnd, $bookingId);
        if (!$avail['available']) jsonResponse(false, null, 'Selected time is not available');
    }
    $fields = []; $params = [];
    if (isset($_REQUEST['title'])) { $fields[] = "title = ?"; $params[] = $_REQUEST['title']; }
    if (isset($_REQUEST['description'])) { $fields[] = "description = ?"; $params[] = $_REQUEST['description']; }
    if (isset($_REQUEST['meetingLink'])) { $fields[] = "meeting_link = ?"; $params[] = $_REQUEST['meetingLink']; }
    if (isset($_REQUEST['attendees'])) { $fields[] = "attendees = ?"; $params[] = intval($_REQUEST['attendees']); }
    if (isset($_REQUEST['roomId'])) {
        $fields[] = "room_id = ?"; $params[] = $_REQUEST['roomId'];
        $stmt2 = $pdo->prepare("SELECT name FROM rooms WHERE room_id = ?");
        $stmt2->execute([$_REQUEST['roomId']]);
        $newRoom = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($newRoom) { $fields[] = "room_name = ?"; $params[] = $newRoom['name']; }
    }
    if (isset($_REQUEST['startTime'])) { $fields[] = "start_time = ?"; $params[] = $_REQUEST['startTime']; }
    if (isset($_REQUEST['endTime'])) { $fields[] = "end_time = ?"; $params[] = $_REQUEST['endTime']; }
    if (empty($fields)) jsonResponse(false, null, 'No fields to update');
    $fields[] = "updated_at = NOW()";
    $params[] = $bookingId;
    $sql = "UPDATE bookings SET " . implode(', ', $fields) . " WHERE booking_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, null, 'Booking updated');
}

function handleBookingCancel() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    $bookingId = $_REQUEST['bookingId'] ?? null;
    if (!$lineUserId || !$bookingId) jsonResponse(false, null, 'Missing lineUserId or bookingId');
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking) jsonResponse(false, null, 'Booking not found');
    $primaryId = getPrimaryUserId($lineUserId);
    if ($booking['user_id'] !== $primaryId && !isAdmin($primaryId)) jsonResponse(false, null, 'Unauthorized');
    $now = date('Y-m-d H:i:s');
    if ($booking['start_time'] < $now && !isAdmin($primaryId)) jsonResponse(false, null, 'Cannot cancel past booking');
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ?, updated_at = NOW() WHERE booking_id = ?");
    $stmt->execute([$primaryId, $bookingId]);
    jsonResponse(true, null, 'Booking cancelled');
}

function handleBookingApprove() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    $bookingId = $_REQUEST['bookingId'] ?? null;
    if (!$lineUserId || !$bookingId) jsonResponse(false, null, 'Missing lineUserId or bookingId');
    if (!isManager($lineUserId)) jsonResponse(false, null, 'Unauthorized');
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking) jsonResponse(false, null, 'Booking not found');
    if ($booking['status'] !== 'pending') jsonResponse(false, null, 'Booking already processed');
    $avail = checkAvailability($booking['room_id'], $booking['start_time'], $booking['end_time'], $bookingId);
    if (!$avail['available']) jsonResponse(false, null, 'Room not available (conflict detected)');
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed', approved_at = NOW(), approved_by = ?, updated_at = NOW() WHERE booking_id = ?");
    $stmt->execute([$lineUserId, $bookingId]);
    $flexMsg = createBookingFlexMessage($booking, 'approved');
    sendFlexMessage($booking['user_id'], $flexMsg);
    jsonResponse(true, null, 'Approved');
}

function handleBookingReject() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    $bookingId = $_REQUEST['bookingId'] ?? null;
    if (!$lineUserId || !$bookingId) jsonResponse(false, null, 'Missing lineUserId or bookingId');
    if (!isManager($lineUserId)) jsonResponse(false, null, 'Unauthorized');
    $reason = $_REQUEST['reason'] ?? 'No reason provided';
    $skipNotification = ($_REQUEST['skipNotification'] ?? 'false') === 'true';
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking) jsonResponse(false, null, 'Booking not found');
    if ($booking['status'] !== 'pending') jsonResponse(false, null, 'Booking already processed');
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'rejected', reject_reason = ?, rejected_at = NOW(), rejected_by = ?, updated_at = NOW() WHERE booking_id = ?");
    $stmt->execute([$reason, $lineUserId, $bookingId]);
    if (!$skipNotification) {
        $flexMsg = createBookingFlexMessage($booking, 'rejected');
        sendFlexMessage($booking['user_id'], $flexMsg);
    }
    jsonResponse(true, null, 'Rejected');
}

function handleBookingAdminCancel() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    $bookingId = $_REQUEST['bookingId'] ?? null;
    if (!$lineUserId || !$bookingId) jsonResponse(false, null, 'Missing lineUserId or bookingId');
    if (!isManager($lineUserId)) jsonResponse(false, null, 'Unauthorized');
    $skipNotification = ($_REQUEST['skipNotification'] ?? 'false') === 'true';
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking) jsonResponse(false, null, 'Booking not found');
    if (!in_array($booking['status'], ['pending','confirmed'])) jsonResponse(false, null, 'Cannot cancel this booking');
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = 'admin', updated_at = NOW() WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    if (!$skipNotification) {
        $flexMsg = createBookingFlexMessage($booking, 'admin_cancelled');
        sendFlexMessage($booking['user_id'], $flexMsg);
    }
    jsonResponse(true, null, 'Booking cancelled by admin');
}

function handleCheckAvailability() {
    $roomId = $_REQUEST['roomId'] ?? null;
    $startTime = $_REQUEST['startTime'] ?? null;
    $endTime = $_REQUEST['endTime'] ?? null;
    $exclude = $_REQUEST['bookingId'] ?? null;
    if (!$roomId || !$startTime || !$endTime) jsonResponse(false, null, 'Missing parameters');
    $avail = checkAvailability($roomId, $startTime, $endTime, $exclude);
    jsonResponse(true, $avail);
}

function handleAdminStats() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    if (!isAdmin($lineUserId)) jsonResponse(false, null, 'Unauthorized');
    $pdo = getDB();
    $stats = [];
    $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['rooms'] = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status='active'")->fetchColumn();
    $stats['bookings'] = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $stats['pending'] = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn();
    $stats['today'] = $pdo->query("SELECT COUNT(*) FROM bookings WHERE DATE(start_time) = CURRENT_DATE")->fetchColumn();
    jsonResponse(true, $stats);
}

function handleAdminUsers() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    if (!isAdmin($lineUserId)) jsonResponse(false, null, 'Unauthorized');
    $pdo = getDB();
    $stmt = $pdo->query("SELECT line_user_id, display_name, picture_url, email, role, status, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(true, $users);
}

function handleAdminUserRole() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    if (!isAdmin($lineUserId)) jsonResponse(false, null, 'Unauthorized');
    $targetUserId = $_REQUEST['targetUserId'] ?? null;
    $newRole = $_REQUEST['role'] ?? null;
    if (!$targetUserId || !$newRole) jsonResponse(false, null, 'Missing parameters');
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE line_user_id = ?");
    $stmt->execute([$newRole, $targetUserId]);
    jsonResponse(true, null, 'Role updated');
}

function handleAdminUserDelete() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    if (!isAdmin($lineUserId)) jsonResponse(false, null, 'Unauthorized');
    $targetUserId = $_REQUEST['targetUserId'] ?? null;
    if (!$targetUserId) jsonResponse(false, null, 'Missing targetUserId');
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE line_user_id = ?");
    $stmt->execute([$targetUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $stmt = $pdo->prepare("INSERT INTO deleted_users (original_user_id, user_data, deleted_by, deleted_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$targetUserId, json_encode($user), $lineUserId]);
        $pdo->prepare("DELETE FROM users WHERE line_user_id = ?")->execute([$targetUserId]);
        $pdo->prepare("DELETE FROM bookings WHERE user_id = ? OR original_user_id = ?")->execute([$targetUserId, $targetUserId]);
        $pdo->prepare("DELETE FROM user_links WHERE primary_user_id = ? OR secondary_user_id = ?")->execute([$targetUserId, $targetUserId]);
        $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$targetUserId]);
        jsonResponse(true, null, 'User deleted');
    } else {
        jsonResponse(false, null, 'User not found');
    }
}

function handleAdminAllBookings() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    if (!isManager($lineUserId)) jsonResponse(false, null, 'Unauthorized');
    $pdo = getDB();
    $stmt = $pdo->query("SELECT * FROM bookings ORDER BY created_at DESC");
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(true, $bookings);
}

function handleAdminPendingBookings() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    if (!isManager($lineUserId)) jsonResponse(false, null, 'Unauthorized');
    $pdo = getDB();
    $stmt = $pdo->query("SELECT * FROM bookings WHERE status = 'pending' ORDER BY start_time");
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(true, $bookings);
}

function handleAdminSettingsGet() {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        $pdo->exec("INSERT INTO settings (id, updated_at) VALUES (1, NOW()) ON CONFLICT (id) DO NOTHING");
        $settings = ['id' => 1, 'app_name' => 'Meeting Room', 'require_approval' => 1, 'reminder_minutes' => 30, 'max_booking_days' => 30, 'min_booking_minutes' => 30, 'max_booking_hours' => 4];
    }
    jsonResponse(true, $settings);
}

function handleAdminSettingsUpdate() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    if (!isAdmin($lineUserId)) jsonResponse(false, null, 'Unauthorized');
    $pdo = getDB();
    $fields = []; $params = [];
    if (isset($_REQUEST['appName'])) { $fields[] = "app_name = ?"; $params[] = $_REQUEST['appName']; }
    if (isset($_REQUEST['requireApproval'])) { $fields[] = "require_approval = ?"; $params[] = ($_REQUEST['requireApproval'] === 'true' ? 1 : 0); }
    if (isset($_REQUEST['reminderMinutes'])) { $fields[] = "reminder_minutes = ?"; $params[] = intval($_REQUEST['reminderMinutes']); }
    if (empty($fields)) jsonResponse(false, null, 'No fields to update');
    $fields[] = "updated_at = NOW()";
    $sql = "UPDATE settings SET " . implode(', ', $fields) . " WHERE id = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, null, 'Settings updated');
}

function handleAutoCancelOverdue() {
    $pdo = getDB();
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'auto_cancelled', auto_cancelled_at = NOW(), updated_at = NOW() WHERE status = 'confirmed' AND end_time < ?");
    $stmt->execute([$now]);
    $affected = $stmt->rowCount();
    jsonResponse(true, ['cancelledCount' => $affected]);
}

function handleNotifications() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    if (!$lineUserId) jsonResponse(false, null, 'Missing lineUserId');
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$lineUserId]);
    $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $unread = 0;
    foreach ($notifs as $n) if (!$n['is_read']) $unread++;
    jsonResponse(true, ['notifications' => $notifs, 'unreadCount' => $unread]);
}

function handleNotificationRead() {
    $notifId = $_REQUEST['notifId'] ?? null;
    if (!$notifId) jsonResponse(false, null, 'Missing notifId');
    $pdo = getDB();
    $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE notif_id = ?")->execute([$notifId]);
    jsonResponse(true);
}

function handleNotificationsReadAll() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    if (!$lineUserId) jsonResponse(false, null, 'Missing lineUserId');
    $pdo = getDB();
    $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?")->execute([$lineUserId]);
    jsonResponse(true);
}

function handleUploadImage() {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) jsonResponse(false, null, 'No file uploaded');
    $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $destination = UPLOAD_DIR . $filename;
    if (move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {
        $fileUrl = BASE_URL . 'uploads/' . $filename;
        jsonResponse(true, ['fileUrl' => $fileUrl, 'fileId' => $filename]);
    } else {
        jsonResponse(false, null, 'Upload failed');
    }
}

function handleUserLink() {
    $primary = $_REQUEST['lineUserId'] ?? null;
    $secondary = $_REQUEST['secondaryUserId'] ?? null;
    $source = $_REQUEST['source'] ?? 'api';
    if (!$primary || !$secondary) jsonResponse(false, null, 'Missing user IDs');
    $result = linkUserIds($primary, $secondary, $source);
    jsonResponse($result, null, $result ? 'Linked' : 'Already linked');
}

function handleUserLinkedIds() {
    $lineUserId = $_REQUEST['lineUserId'] ?? null;
    if (!$lineUserId) jsonResponse(false, null, 'Missing lineUserId');
    $primary = getPrimaryUserId($lineUserId);
    $linked = getLinkedUserIds($primary);
    jsonResponse(true, $linked);
}
