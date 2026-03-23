<?php
require_once 'db.php';
require_once 'config.php';

// ==================== HELPER FUNCTIONS ====================
function showLoadingAnimation($userId, $seconds = 5) {
    if (!LINE_CHANNEL_ACCESS_TOKEN || !$userId) return false;
    $url = 'https://api.line.me/v2/bot/chat/loading/start';
    $payload = ['chatId' => $userId, 'loadingSeconds' => min($seconds, 5)];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 202;
}

function sendFlexMessage($userId, $flexMessage) {
    if (!LINE_CHANNEL_ACCESS_TOKEN) return false;
    $url = 'https://api.line.me/v2/bot/message/push';
    $data = ['to' => $userId, 'messages' => [$flexMessage]];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200;
}

function createOrUpdateUserFromLine($userId, $source = 'bot') {
    $pdo = getDB();
    $now = date('Y-m-d H:i:s');
    // ดึงโปรไฟล์จาก LINE API
    $profile = null;
    $url = "https://api.line.me/v2/bot/profile/$userId";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
        $profile = json_decode($resp, true);
    }
    curl_close($ch);

    $displayName = $profile['displayName'] ?? '';
    $pictureUrl = $profile['pictureUrl'] ?? '';

    // ตรวจสอบว่ามีผู้ใช้อยู่แล้ว
    $stmt = $pdo->prepare("SELECT * FROM users WHERE line_user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $stmt = $pdo->prepare("UPDATE users SET display_name = ?, picture_url = ?, last_login = ?, updated_at = ?, last_interaction = ? WHERE line_user_id = ?");
        $stmt->execute([$displayName, $pictureUrl, $now, $now, $now, $userId]);
        $user['display_name'] = $displayName;
        $user['picture_url'] = $pictureUrl;
        return $user;
    } else {
        // ตรวจสอบว่าเป็นผู้ใช้คนแรกหรือไม่ (กำหนดเป็น admin)
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $count = $stmt->fetchColumn();
        $role = ($count == 0) ? 'admin' : 'user';
        $stmt = $pdo->prepare("INSERT INTO users (line_user_id, display_name, picture_url, role, last_login, created_at, updated_at, last_interaction, source, welcome_sent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$userId, $displayName, $pictureUrl, $role, $now, $now, $now, $now, $source]);
        return [
            'line_user_id' => $userId,
            'display_name' => $displayName,
            'picture_url' => $pictureUrl,
            'role' => $role
        ];
    }
}

// ==================== FLEX MESSAGE BUILDERS ====================
function createMainMenuFlex($userName) {
    return [
        'type' => 'flex',
        'altText' => '📋 เมนูหลัก',
        'contents' => [
            'type' => 'bubble',
            'header' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    ['type' => 'text', 'text' => '📋 เมนูหลัก', 'weight' => 'bold', 'size' => 'xl', 'color' => '#06c755'],
                    ['type' => 'text', 'text' => $userName ? "สวัสดีคุณ $userName" : 'ระบบจองห้องประชุม', 'size' => 'sm', 'color' => '#888888', 'margin' => 'md']
                ]
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    ['type' => 'text', 'text' => 'เลือกคำสั่งด้านล่าง', 'size' => 'md', 'color' => '#666666', 'weight' => 'bold'],
                    ['type' => 'separator', 'margin' => 'lg'],
                    ['type' => 'box', 'layout' => 'horizontal', 'margin' => 'lg', 'contents' => [
                        ['type' => 'text', 'text' => '📅', 'size' => 'xl', 'flex' => 1],
                        ['type' => 'text', 'text' => 'การจองของฉัน', 'size' => 'md', 'color' => '#06c755', 'weight' => 'bold', 'flex' => 4, 'wrap' => true],
                        ['type' => 'text', 'text' => 'พิมพ์ "การจอง"', 'size' => 'xs', 'color' => '#888888', 'flex' => 3, 'align' => 'end']
                    ]],
                    ['type' => 'box', 'layout' => 'horizontal', 'margin' => 'md', 'contents' => [
                        ['type' => 'text', 'text' => '🏢', 'size' => 'xl', 'flex' => 1],
                        ['type' => 'text', 'text' => 'ห้องว่างวันนี้', 'size' => 'md', 'color' => '#06c755', 'weight' => 'bold', 'flex' => 4, 'wrap' => true],
                        ['type' => 'text', 'text' => 'พิมพ์ "ห้องว่าง"', 'size' => 'xs', 'color' => '#888888', 'flex' => 3, 'align' => 'end']
                    ]],
                    ['type' => 'box', 'layout' => 'horizontal', 'margin' => 'md', 'contents' => [
                        ['type' => 'text', 'text' => '📊', 'size' => 'xl', 'flex' => 1],
                        ['type' => 'text', 'text' => 'สรุปการใช้ห้องวันนี้', 'size' => 'md', 'color' => '#06c755', 'weight' => 'bold', 'flex' => 4, 'wrap' => true],
                        ['type' => 'text', 'text' => 'พิมพ์ "สรุป"', 'size' => 'xs', 'color' => '#888888', 'flex' => 3, 'align' => 'end']
                    ]],
                    ['type' => 'box', 'layout' => 'horizontal', 'margin' => 'md', 'contents' => [
                        ['type' => 'text', 'text' => '📋', 'size' => 'xl', 'flex' => 1],
                        ['type' => 'text', 'text' => 'แสดงเมนู', 'size' => 'md', 'color' => '#06c755', 'weight' => 'bold', 'flex' => 4, 'wrap' => true],
                        ['type' => 'text', 'text' => 'พิมพ์ "เมนู"', 'size' => 'xs', 'color' => '#888888', 'flex' => 3, 'align' => 'end']
                    ]],
                    ['type' => 'box', 'layout' => 'horizontal', 'margin' => 'md', 'contents' => [
                        ['type' => 'text', 'text' => '❓', 'size' => 'xl', 'flex' => 1],
                        ['type' => 'text', 'text' => 'ช่วยเหลือ', 'size' => 'md', 'color' => '#06c755', 'weight' => 'bold', 'flex' => 4, 'wrap' => true],
                        ['type' => 'text', 'text' => 'พิมพ์ "help"', 'size' => 'xs', 'color' => '#888888', 'flex' => 3, 'align' => 'end']
                    ]]
                ]
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    ['type' => 'button', 'action' => ['type' => 'uri', 'label' => '📅 เปิดแอปจองห้อง', 'uri' => 'https://liff.line.me/' . LINE_LIFF_ID], 'style' => 'primary', 'color' => '#06c755']
                ],
                'paddingAll' => 'lg'
            ]
        ]
    ];
}

function createWelcomeFlex($user) {
    return [
        'type' => 'flex',
        'altText' => '🎉 ยินดีต้อนรับ',
        'contents' => [
            'type' => 'bubble',
            'header' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    ['type' => 'text', 'text' => '🎉 ยินดีต้อนรับ', 'weight' => 'bold', 'size' => 'xl', 'color' => '#06c755'],
                    ['type' => 'text', 'text' => $user['display_name'] ? "คุณ {$user['display_name']}" : 'สู่ระบบจองห้องประชุม', 'size' => 'md', 'color' => '#888888', 'margin' => 'md']
                ]
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    ['type' => 'text', 'text' => 'พิมพ์คำสั่งด้านล่างเพื่อใช้งาน', 'size' => 'md', 'color' => '#666666', 'wrap' => true],
                    ['type' => 'separator', 'margin' => 'lg'],
                    ['type' => 'box', 'layout' => 'horizontal', 'margin' => 'lg', 'contents' => [
                        ['type' => 'text', 'text' => '📅', 'size' => 'xl', 'flex' => 1],
                        ['type' => 'text', 'text' => 'ดูการจองของฉัน', 'size' => 'sm', 'color' => '#666666', 'flex' => 4, 'wrap' => true],
                        ['type' => 'text', 'text' => '"การจอง"', 'size' => 'xs', 'color' => '#06c755', 'flex' => 3, 'align' => 'end']
                    ]],
                    ['type' => 'box', 'layout' => 'horizontal', 'margin' => 'md', 'contents' => [
                        ['type' => 'text', 'text' => '🏢', 'size' => 'xl', 'flex' => 1],
                        ['type' => 'text', 'text' => 'ดูห้องว่างวันนี้', 'size' => 'sm', 'color' => '#666666', 'flex' => 4, 'wrap' => true],
                        ['type' => 'text', 'text' => '"ห้องว่าง"', 'size' => 'xs', 'color' => '#06c755', 'flex' => 3, 'align' => 'end']
                    ]],
                    ['type' => 'box', 'layout' => 'horizontal', 'margin' => 'md', 'contents' => [
                        ['type' => 'text', 'text' => '📊', 'size' => 'xl', 'flex' => 1],
                        ['type' => 'text', 'text' => 'สรุปการใช้ห้องวันนี้', 'size' => 'sm', 'color' => '#666666', 'flex' => 4, 'wrap' => true],
                        ['type' => 'text', 'text' => '"สรุป"', 'size' => 'xs', 'color' => '#06c755', 'flex' => 3, 'align' => 'end']
                    ]],
                    ['type' => 'box', 'layout' => 'horizontal', 'margin' => 'md', 'contents' => [
                        ['type' => 'text', 'text' => '📋', 'size' => 'xl', 'flex' => 1],
                        ['type' => 'text', 'text' => 'แสดงเมนู', 'size' => 'sm', 'color' => '#666666', 'flex' => 4, 'wrap' => true],
                        ['type' => 'text', 'text' => '"เมนู"', 'size' => 'xs', 'color' => '#06c755', 'flex' => 3, 'align' => 'end']
                    ]]
                ]
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    ['type' => 'button', 'action' => ['type' => 'uri', 'label' => '📅 เริ่มจองห้องประชุม', 'uri' => 'https://liff.line.me/' . LINE_LIFF_ID], 'style' => 'primary', 'color' => '#06c755']
                ],
                'paddingAll' => 'lg'
            ]
        ]
    ];
}

function createHelpFlex() {
    return [
        'type' => 'flex',
        'altText' => '❓ วิธีใช้',
        'contents' => [
            'type' => 'bubble',
            'header' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    ['type' => 'text', 'text' => '❓ วิธีใช้ระบบ', 'weight' => 'bold', 'size' => 'xl', 'color' => '#06c755']
                ]
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    ['type' => 'text', 'text' => '📋 คำสั่งพื้นฐาน', 'weight' => 'bold', 'size' => 'md', 'color' => '#666666'],
                    ['type' => 'separator', 'margin' => 'md'],
                    ['type' => 'box', 'layout' => 'horizontal', 'margin' => 'lg', 'contents' => [
                        ['type' => 'text', 'text' => '📅', 'size' => 'xl', 'flex' => 1],
                        ['type' => 'text', 'text' => 'ดูการจองของฉัน', 'size' => 'sm', 'color' => '#666666', 'flex' => 4, 'wrap' => true],
                        ['type' => 'text', 'text' => '"การจอง"', 'size' => 'xs', 'color' => '#06c755', 'flex' => 3, 'align' => 'end']
                    ]],
                    ['type' => 'box', 'layout' => 'horizontal', 'margin' => 'md', 'contents' => [
                        ['type' => 'text', 'text' => '🏢', 'size' => 'xl', 'flex' => 1],
                        ['type' => 'text', 'text' => 'ดูห้องว่างวันนี้', 'size' => 'sm', 'color' => '#666666', 'flex' => 4, 'wrap' => true],
                        ['type' => 'text', 'text' => '"ห้องว่าง"', 'size' => 'xs', 'color' => '#06c755', 'flex' => 3, 'align' => 'end']
                    ]],
                    ['type' => 'box', 'layout' => 'horizontal', 'margin' => 'md', 'contents' => [
                        ['type' => 'text', 'text' => '📊', 'size' => 'xl', 'flex' => 1],
                        ['type' => 'text', 'text' => 'สรุปการใช้ห้องวันนี้', 'size' => 'sm', 'color' => '#666666', 'flex' => 4, 'wrap' => true],
                        ['type' => 'text', 'text' => '"สรุป"', 'size' => 'xs', 'color' => '#06c755', 'flex' => 3, 'align' => 'end']
                    ]],
                    ['type' => 'box', 'layout' => 'horizontal', 'margin' => 'md', 'contents' => [
                        ['type' => 'text', 'text' => '📋', 'size' => 'xl', 'flex' => 1],
                        ['type' => 'text', 'text' => 'แสดงเมนู', 'size' => 'sm', 'color' => '#666666', 'flex' => 4, 'wrap' => true],
                        ['type' => 'text', 'text' => '"เมนู"', 'size' => 'xs', 'color' => '#06c755', 'flex' => 3, 'align' => 'end']
                    ]]
                ]
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    ['type' => 'button', 'action' => ['type' => 'uri', 'label' => '📅 เปิดแอปจองห้อง', 'uri' => 'https://liff.line.me/' . LINE_LIFF_ID], 'style' => 'primary', 'color' => '#06c755']
                ],
                'paddingAll' => 'lg'
            ]
        ]
    ];
}

function sendAvailableRoomsToday($userId) {
    $pdo = getDB();
    $now = date('Y-m-d H:i:s');
    $rooms = $pdo->query("SELECT * FROM rooms WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $bookings = $pdo->prepare("SELECT * FROM bookings WHERE status IN ('pending','confirmed') AND start_time <= ? AND end_time > ?");
    $bookings->execute([$now, $now]);
    $bookedRoomIds = [];
    while ($b = $bookings->fetch(PDO::FETCH_ASSOC)) {
        $bookedRoomIds[] = $b['room_id'];
    }
    $available = array_filter($rooms, function($r) use ($bookedRoomIds) {
        return !in_array($r['room_id'], $bookedRoomIds);
    });
    $available = array_values($available);
    $totalRooms = count($rooms);
    $timeStr = date('H:i');

    if (empty($available)) {
        $flex = [
            'type' => 'flex',
            'altText' => '😢 ไม่มีห้องว่าง',
            'contents' => [
                'type' => 'bubble',
                'header' => ['type' => 'box', 'layout' => 'vertical', 'contents' => [['type' => 'text', 'text' => '😢 ไม่มีห้องว่าง', 'weight' => 'bold', 'size' => 'xl', 'color' => '#ef4444']]],
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        ['type' => 'text', 'text' => "เวลา $timeStr น.", 'size' => 'sm', 'color' => '#888888', 'align' => 'center'],
                        ['type' => 'text', 'text' => 'ขณะนี้ห้องประชุมเต็มทั้งหมด', 'size' => 'md', 'color' => '#666666', 'wrap' => true, 'margin' => 'md'],
                        ['type' => 'text', 'text' => 'ลองจองช่วงเวลาอื่น หรือรอสักครู่', 'size' => 'sm', 'color' => '#888888', 'wrap' => true, 'margin' => 'md']
                    ]
                ],
                'footer' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        ['type' => 'button', 'action' => ['type' => 'uri', 'label' => '📅 ดูช่วงเวลาอื่น', 'uri' => 'https://liff.line.me/' . LINE_LIFF_ID], 'style' => 'primary', 'color' => '#06c755'],
                        ['type' => 'button', 'action' => ['type' => 'message', 'label' => '📋 แสดงเมนู', 'text' => 'เมนู'], 'style' => 'secondary', 'color' => '#888888', 'margin' => 'md']
                    ],
                    'paddingAll' => 'lg'
                ]
            ]
        ];
        sendFlexMessage($userId, $flex);
        return;
    }

    $roomList = [];
    foreach (array_slice($available, 0, 10) as $r) {
        $roomList[] = [
            'type' => 'box',
            'layout' => 'horizontal',
            'contents' => [
                ['type' => 'text', 'text' => "• {$r['name']}", 'size' => 'sm', 'color' => '#06c755', 'flex' => 3, 'wrap' => true],
                ['type' => 'text', 'text' => "{$r['capacity']} ที่นั่ง", 'size' => 'sm', 'color' => '#888888', 'flex' => 1, 'align' => 'end']
            ],
            'margin' => 'md'
        ];
    }
    if (count($available) > 10) {
        $roomList[] = ['type' => 'text', 'text' => "และอีก " . (count($available)-10) . " ห้อง...", 'size' => 'xs', 'color' => '#888888', 'align' => 'end', 'margin' => 'md'];
    }

    $flex = [
        'type' => 'flex',
        'altText' => "✅ ขณะนี้มี " . count($available) . " ห้องว่าง",
        'contents' => [
            'type' => 'bubble',
            'header' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    ['type' => 'text', 'text' => '✅ ห้องว่างขณะนี้', 'weight' => 'bold', 'size' => 'xl', 'color' => '#06c755'],
                    ['type' => 'text', 'text' => "เวลา $timeStr น.", 'size' => 'sm', 'color' => '#888888', 'margin' => 'md'],
                    ['type' => 'text', 'text' => "พบ " . count($available) . " ห้องว่าง จากทั้งหมด $totalRooms ห้อง", 'size' => 'sm', 'color' => '#666666', 'margin' => 'md']
                ]
            ],
            'body' => ['type' => 'box', 'layout' => 'vertical', 'contents' => $roomList],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    ['type' => 'button', 'action' => ['type' => 'uri', 'label' => '📅 จองเลย', 'uri' => 'https://liff.line.me/' . LINE_LIFF_ID], 'style' => 'primary', 'color' => '#06c755'],
                    ['type' => 'button', 'action' => ['type' => 'message', 'label' => '📋 แสดงเมนู', 'text' => 'เมนู'], 'style' => 'secondary', 'color' => '#888888', 'margin' => 'md']
                ],
                'paddingAll' => 'lg'
            ]
        ]
    ];
    sendFlexMessage($userId, $flex);
}

function sendMyBookings($userId) {
    $pdo = getDB();
    $primaryId = getPrimaryUserId($userId);  // ใช้ฟังก์ชันจาก db.php
    $linkedIds = getLinkedUserIds($primaryId);
    $placeholders = implode(',', array_fill(0, count($linkedIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE user_id IN ($placeholders) ORDER BY start_time DESC LIMIT 10");
    $stmt->execute($linkedIds);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($bookings)) {
        $flex = [
            'type' => 'flex',
            'altText' => '📅 ไม่มีการจอง',
            'contents' => [
                'type' => 'bubble',
                'header' => ['type' => 'box', 'layout' => 'vertical', 'contents' => [['type' => 'text', 'text' => '📅 ไม่มีการจอง', 'weight' => 'bold', 'size' => 'xl', 'color' => '#888888']]],
                'body' => ['type' => 'box', 'layout' => 'vertical', 'contents' => [['type' => 'text', 'text' => 'คุณยังไม่มีการจองห้องประชุม\nลองจองผ่านแอปได้เลยค่ะ', 'size' => 'md', 'color' => '#666666', 'wrap' => true]]],
                'footer' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        ['type' => 'button', 'action' => ['type' => 'uri', 'label' => '📅 จองเลย', 'uri' => 'https://liff.line.me/' . LINE_LIFF_ID], 'style' => 'primary', 'color' => '#06c755'],
                        ['type' => 'button', 'action' => ['type' => 'message', 'label' => '📋 แสดงเมนู', 'text' => 'เมนู'], 'style' => 'secondary', 'color' => '#888888', 'margin' => 'md']
                    ],
                    'paddingAll' => 'lg'
                ]
            ]
        ];
        sendFlexMessage($userId, $flex);
        return;
    }

    $bookingList = [];
    foreach ($bookings as $b) {
        $date = new DateTime($b['start_time']);
        $end = new DateTime($b['end_time']);
        $now = new DateTime();
        $dateStr = $date->format('d M Y');
        $timeStr = $date->format('H:i');
        $statusIcon = '⏳';
        $statusColor = '#f59e0b';
        if ($end < $now && $b['status'] === 'confirmed') {
            $statusIcon = '✅';
            $statusColor = '#888888';
        } elseif ($b['status'] === 'confirmed') {
            $statusIcon = '✅';
            $statusColor = '#06c755';
        } elseif ($b['status'] === 'rejected') {
            $statusIcon = '❌';
            $statusColor = '#ef4444';
        } elseif ($b['status'] === 'cancelled') {
            $statusIcon = '🚫';
            $statusColor = '#888888';
        } elseif ($b['status'] === 'auto_cancelled') {
            $statusIcon = '🤖';
            $statusColor = '#f59e0b';
        }
        $bookingList[] = [
            'type' => 'box',
            'layout' => 'horizontal',
            'contents' => [
                ['type' => 'text', 'text' => $statusIcon, 'size' => 'sm', 'flex' => 1, 'color' => $statusColor],
                ['type' => 'text', 'text' => $b['room_name'], 'size' => 'sm', 'color' => '#06c755', 'weight' => 'bold', 'flex' => 3, 'wrap' => true],
                ['type' => 'text', 'text' => "$dateStr $timeStr", 'size' => 'sm', 'color' => '#666666', 'flex' => 3, 'align' => 'end', 'wrap' => true]
            ],
            'margin' => 'md'
        ];
    }

    $confirmed = count(array_filter($bookings, fn($b) => $b['status'] === 'confirmed'));
    $pending = count(array_filter($bookings, fn($b) => $b['status'] === 'pending'));
    $rejected = count(array_filter($bookings, fn($b) => $b['status'] === 'rejected'));
    $cancelled = count(array_filter($bookings, fn($b) => $b['status'] === 'cancelled'));
    $auto = count(array_filter($bookings, fn($b) => $b['status'] === 'auto_cancelled'));

    $flex = [
        'type' => 'flex',
        'altText' => "📅 คุณมี " . count($bookings) . " รายการจอง",
        'contents' => [
            'type' => 'bubble',
            'header' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    ['type' => 'text', 'text' => '📅 การจองของฉัน', 'weight' => 'bold', 'size' => 'xl', 'color' => '#06c755'],
                    ['type' => 'text', 'text' => "ทั้งหมด " . count($bookings) . " รายการ", 'size' => 'sm', 'color' => '#888888', 'margin' => 'md'],
                    ['type' => 'box', 'layout' => 'horizontal', 'margin' => 'md', 'contents' => [
                        ['type' => 'text', 'text' => "✅ $confirmed", 'size' => 'xs', 'color' => '#06c755', 'flex' => 1],
                        ['type' => 'text', 'text' => "⏳ $pending", 'size' => 'xs', 'color' => '#f59e0b', 'flex' => 1],
                        ['type' => 'text', 'text' => "❌ $rejected", 'size' => 'xs', 'color' => '#ef4444', 'flex' => 1],
                        ['type' => 'text', 'text' => "🚫 $cancelled", 'size' => 'xs', 'color' => '#888888', 'flex' => 1],
                        ['type' => 'text', 'text' => "🤖 $auto", 'size' => 'xs', 'color' => '#f59e0b', 'flex' => 1]
                    ]]
                ]
            ],
            'body' => ['type' => 'box', 'layout' => 'vertical', 'contents' => $bookingList],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    ['type' => 'button', 'action' => ['type' => 'uri', 'label' => '📋 ดูทั้งหมดในแอป', 'uri' => 'https://liff.line.me/' . LINE_LIFF_ID], 'style' => 'primary', 'color' => '#06c755'],
                    ['type' => 'button', 'action' => ['type' => 'message', 'label' => '📋 แสดงเมนู', 'text' => 'เมนู'], 'style' => 'secondary', 'color' => '#888888', 'margin' => 'md']
                ],
                'paddingAll' => 'lg'
            ]
        ]
    ];
    sendFlexMessage($userId, $flex);
}

function sendDailySummary($userId, $date = null) {
    $pdo = getDB();
    $targetDate = $date ? new DateTime($date) : new DateTime();
    $dateStr = $targetDate->format('Y-m-d');
    $thaiDate = $targetDate->format('d/m/Y');  // ใช้วันที่ไทยแบบง่าย

    $bookings = $pdo->prepare("SELECT * FROM bookings WHERE DATE(start_time) = ?");
    $bookings->execute([$dateStr]);
    $allBookings = $bookings->fetchAll(PDO::FETCH_ASSOC);

    $rooms = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'active'")->fetchColumn();
    $totalRooms = $rooms ?: 0;
    $usedRooms = count(array_unique(array_column($allBookings, 'room_id')));
    $totalBookings = count($allBookings);
    $pending = count(array_filter($allBookings, fn($b) => $b['status'] === 'pending'));
    $confirmed = count(array_filter($allBookings, fn($b) => $b['status'] === 'confirmed'));
    $completed = count(array_filter($allBookings, fn($b) => $b['status'] === 'confirmed' && new DateTime($b['end_time']) < new DateTime()));

    $bookingItems = [];
    foreach (array_slice($allBookings, 0, 10) as $b) {
        $start = new DateTime($b['start_time']);
        $end = new DateTime($b['end_time']);
        $timeStr = $start->format('H:i') . ' - ' . $end->format('H:i');
        $statusIcon = '⏳';
        $statusColor = '#f59e0b';
        if ($b['status'] === 'confirmed') {
            $statusIcon = '✅';
            $statusColor = '#06c755';
        } elseif ($b['status'] === 'pending') {
            $statusIcon = '⏳';
            $statusColor = '#f59e0b';
        } elseif ($b['status'] === 'cancelled') {
            $statusIcon = '❌';
            $statusColor = '#ef4444';
        }
        $bookingItems[] = [
            'type' => 'box',
            'layout' => 'horizontal',
            'contents' => [
                ['type' => 'text', 'text' => $statusIcon, 'size' => 'sm', 'flex' => 1, 'color' => $statusColor],
                ['type' => 'text', 'text' => $timeStr, 'size' => 'sm', 'color' => '#666666', 'flex' => 2],
                ['type' => 'text', 'text' => $b['room_name'], 'size' => 'sm', 'color' => '#06c755', 'flex' => 3, 'wrap' => true]
            ],
            'margin' => 'md'
        ];
    }
    if (count($allBookings) > 10) {
        $bookingItems[] = ['type' => 'text', 'text' => "และอีก " . (count($allBookings)-10) . " รายการ...", 'size' => 'xs', 'color' => '#888888', 'align' => 'end', 'margin' => 'md'];
    }

    $flex = [
        'type' => 'flex',
        'altText' => "📊 สรุปการใช้ห้องวันที่ $thaiDate",
        'contents' => [
            'type' => 'bubble',
            'header' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    ['type' => 'text', 'text' => '📊 สรุปการใช้ห้องรายวัน', 'weight' => 'bold', 'size' => 'xl', 'color' => '#06c755'],
                    ['type' => 'text', 'text' => $thaiDate, 'size' => 'sm', 'color' => '#888888', 'margin' => 'md']
                ]
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => array_merge([
                    ['type' => 'box', 'layout' => 'horizontal', 'contents' => [
                        ['type' => 'text', 'text' => '🏢 ห้องทั้งหมด:', 'size' => 'sm', 'color' => '#666666', 'flex' => 3],
                        ['type' => 'text', 'text' => "$totalRooms ห้อง", 'size' => 'sm', 'color' => '#06c755', 'weight' => 'bold', 'flex' => 2, 'align' => 'end']
                    ]],
                    ['type' => 'box', 'layout' => 'horizontal', 'margin' => 'md', 'contents' => [
                        ['type' => 'text', 'text' => '📊 การจองทั้งหมด:', 'size' => 'sm', 'color' => '#666666', 'flex' => 3],
                        ['type' => 'text', 'text' => "$totalBookings รายการ", 'size' => 'sm', 'color' => '#06c755', 'weight' => 'bold', 'flex' => 2, 'align' => 'end']
                    ]],
                    ['type' => 'box', 'layout' => 'horizontal', 'margin' => 'md', 'contents' => [
                        ['type' => 'text', 'text' => '✅ อนุมัติแล้ว:', 'size' => 'sm', 'color' => '#666666', 'flex' => 3],
                        ['type' => 'text', 'text' => "$confirmed รายการ", 'size' => 'sm', 'color' => '#06c755', 'flex' => 2, 'align' => 'end']
                    ]],
                    ['type' => 'box', 'layout' => 'horizontal', 'margin' => 'md', 'contents' => [
                        ['type' => 'text', 'text' => '⏳ รออนุมัติ:', 'size' => 'sm', 'color' => '#666666', 'flex' => 3],
                        ['type' => 'text', 'text' => "$pending รายการ", 'size' => 'sm', 'color' => '#f59e0b', 'flex' => 2, 'align' => 'end']
                    ]],
                    ['type' => 'box', 'layout' => 'horizontal', 'margin' => 'md', 'contents' => [
                        ['type' => 'text', 'text' => '✅ ผ่านไปแล้ว:', 'size' => 'sm', 'color' => '#666666', 'flex' => 3],
                        ['type' => 'text', 'text' => "$completed รายการ", 'size' => 'sm', 'color' => '#888888', 'flex' => 2, 'align' => 'end']
                    ]],
                    ['type' => 'separator', 'margin' => 'lg'],
                    ['type' => 'text', 'text' => '📋 รายการจองทั้งหมด', 'size' => 'md', 'color' => '#666666', 'weight' => 'bold', 'margin' => 'lg']
                ], $bookingItems)
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    ['type' => 'button', 'action' => ['type' => 'uri', 'label' => '📅 จองห้องประชุม', 'uri' => 'https://liff.line.me/' . LINE_LIFF_ID], 'style' => 'primary', 'color' => '#06c755'],
                    ['type' => 'button', 'action' => ['type' => 'message', 'label' => '📋 แสดงเมนู', 'text' => 'เมนู'], 'style' => 'secondary', 'color' => '#888888', 'margin' => 'md']
                ],
                'paddingAll' => 'lg'
            ]
        ]
    ];
    sendFlexMessage($userId, $flex);
}

// ==================== MAIN WEBHOOK HANDLER ====================
$rawInput = file_get_contents('php://input');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rawInput) {
    $body = json_decode($rawInput, true);
    if (isset($body['events'])) {
        foreach ($body['events'] as $event) {
            if ($event['type'] === 'follow') {
                $userId = $event['source']['userId'];
                showLoadingAnimation($userId, 5);
                $user = createOrUpdateUserFromLine($userId, 'bot_follow');
                $welcomeFlex = createWelcomeFlex($user);
                sendFlexMessage($userId, $welcomeFlex);
                $pdo = getDB();
                $stmt = $pdo->prepare("UPDATE users SET welcome_sent = TRUE WHERE line_user_id = ?");
                $stmt->execute([$userId]);
            }
            if ($event['type'] === 'message' && $event['message']['type'] === 'text') {
                $text = trim($event['message']['text']);
                $userId = $event['source']['userId'];
                showLoadingAnimation($userId, 5);
                $user = createOrUpdateUserFromLine($userId, 'bot_message');
                $pdo = getDB();
                $stmt = $pdo->prepare("UPDATE users SET last_interaction = NOW() WHERE line_user_id = ?");
                $stmt->execute([$userId]);

                $lowerText = mb_strtolower($text);
                if ($lowerText === 'เมนู' || $lowerText === 'menu') {
                    sendFlexMessage($userId, createMainMenuFlex($user['display_name']));
                } elseif ($lowerText === 'help' || $lowerText === 'ช่วยเหลือ') {
                    sendFlexMessage($userId, createHelpFlex());
                } elseif ($lowerText === 'สรุป') {
                    sendDailySummary($userId);
                } elseif (strpos($lowerText, 'ห้องว่าง') !== false) {
                    sendAvailableRoomsToday($userId);
                } elseif (strpos($lowerText, 'การจอง') !== false) {
                    sendMyBookings($userId);
                } else {
                    sendFlexMessage($userId, createMainMenuFlex($user['display_name']));
                }
            }
            if ($event['type'] === 'unfollow') {
                $userId = $event['source']['userId'];
                $pdo = getDB();
                $stmt = $pdo->prepare("UPDATE users SET status = 'inactive', unfollowed_at = NOW() WHERE line_user_id = ?");
                $stmt->execute([$userId]);
            }
        }
    }
    http_response_code(200);
    echo 'OK';
    exit;
}
http_response_code(200);
echo 'OK';
