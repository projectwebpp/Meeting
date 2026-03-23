<?php
// Database configuration for PostgreSQL (Neon)
define('DB_DSN', 'pgsql:host=ep-morning-river-ani6mh5p-pooler.c-6.us-east-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require');
define('DB_USER', 'neondb_owner');
define('DB_PASS', 'npg_2u1rVjFdsNxX');

// LINE configuration
define('LINE_CHANNEL_ACCESS_TOKEN', 'gHB3k1CSgDBib+x4Xg9KAefpfBCM9KpB4geg8wA2mldyiJob7NUga7HkN/EmTwq976HRLEmjMCBpLBTqdhBVMs95OM+/HehMBdV0UIlDSlxCnBeOGJweMclWdbzJ8lYLIVsldOLaxaW6mvs/3PpVbAdB04t89/1O/w1cDnyilFU=');
define('LINE_LIFF_ID', '2001506774-XQoe74Ua');

// Image upload directory (relative to script)
define('UPLOAD_DIR', __DIR__ . '/uploads/');
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);

// Base URL for images (CHANGE THIS to your actual domain)
define('BASE_URL', 'https://your-domain.com/api/');
