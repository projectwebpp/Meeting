<?php
// Database configuration for PostgreSQL (Neon)
define('DB_DSN', 'pgsql:host=ep-morning-river-ani6mh5p-pooler.c-6.us-east-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require');
define('DB_USER', 'neondb_owner');
define('DB_PASS', 'npg_2u1rVjFdsNxX');

// LINE configuration
define('LINE_CHANNEL_ACCESS_TOKEN', 'aPAvFs9wjzMKfX6pug8zBVpEoOdtDOaQ1nP060YodHilNRw/Y+/b8v8TqQ7uFGZiqErGn3G+b/2xHiCFK+p3e7IPIX5/l5+eMzYkUtyQvJuXZmoGW990hq22Ei7ljXHjFIwYBfTgJ8QUyxr/ze7aQgdB04t89/1O/w1cDnyilFU=');
define('LINE_LIFF_ID', '2009198981-GsqxkwIK');

// Image upload directory (relative to script)
define('UPLOAD_DIR', __DIR__ . '/uploads/');
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);

// Base URL for images (adjust to your domain)
define('BASE_URL', 'https://your-domain.com/api/'); // CHANGE THIS
