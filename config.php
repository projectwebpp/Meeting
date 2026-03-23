<?php
// Database configuration (from environment variables)
$dbHost = getenv('DB_HOST') ?: 'ep-morning-river-ani6mh5p-pooler.c-6.us-east-1.aws.neon.tech';
$dbPort = getenv('DB_PORT') ?: '5432';
$dbName = getenv('DB_NAME') ?: 'neondb';
$dbUser = getenv('DB_USER') ?: 'neondb_owner';
$dbPass = getenv('DB_PASS') ?: 'npg_2u1rVjFdsNxX';
$dbSslMode = getenv('DB_SSLMODE') ?: 'require';

define('DB_DSN', "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName;sslmode=$dbSslMode");
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);

// LINE configuration
define('LINE_CHANNEL_ACCESS_TOKEN', getenv('LINE_CHANNEL_ACCESS_TOKEN') ?: '7aZdqWC4uaxtKyOq2PbyjusqU9rK5Lr7u5MvXRVYy5gMsMgYCBAJFzPV8kzORfHb0j7eWzwhbH7gFy6LDWKZjr81UUMdH97A5BmFCEyefzojforqdWuRWL+y34k9HBwormb7leCQAyqkU/Doia9LaQdB04t89/1O/w1cDnyilFU=');
define('LINE_LIFF_ID', getenv('LINE_LIFF_ID') ?: '2001506774-XQoe74Ua');

// Image upload directory
define('UPLOAD_DIR', __DIR__ . '/uploads/');
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);

// Base URL for images (must end with /)
define('BASE_URL', getenv('BASE_URL') ?: 'https://meeting-uuup.onrender.com/api/');
