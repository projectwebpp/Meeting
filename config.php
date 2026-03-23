<?php
// Database configuration for PostgreSQL (Neon)
define('DB_DSN', 'pgsql:host=ep-morning-river-ani6mh5p-pooler.c-6.us-east-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require');
define('DB_USER', 'neondb_owner');
define('DB_PASS', 'npg_2u1rVjFdsNxX');

// LINE configuration
define('LINE_CHANNEL_ACCESS_TOKEN', '7aZdqWC4uaxtKyOq2PbyjusqU9rK5Lr7u5MvXRVYy5gMsMgYCBAJFzPV8kzORfHb0j7eWzwhbH7gFy6LDWKZjr81UUMdH97A5BmFCEyefzojforqdWuRWL+y34k9HBwormb7leCQAyqkU/Doia9LaQdB04t89/1O/w1cDnyilFU=');
define('LINE_LIFF_ID', '2001506774-XQoe74Ua');

// Image upload directory (relative to script)
define('UPLOAD_DIR', __DIR__ . '/uploads/');
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);

// Base URL for images (CHANGE THIS to your actual domain)
define('BASE_URL', 'https://meeting-uuup.onrender.com/api.php');
