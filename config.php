<?php
$dbHost = getenv('DB_HOST') ?: 'ep-morning-river-ani6mh5p-pooler.c-6.us-east-1.aws.neon.tech';
$dbPort = getenv('DB_PORT') ?: '5432';
$dbName = getenv('DB_NAME') ?: 'neondb';
$dbUser = getenv('DB_USER') ?: 'neondb_owner';
$dbPass = getenv('DB_PASS') ?: 'npg_2u1rVjFdsNxX';
$dbSslMode = getenv('DB_SSLMODE') ?: 'require';

define('DB_DSN', "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName;sslmode=$dbSslMode");
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);

define('LINE_CHANNEL_ACCESS_TOKEN', getenv('LINE_CHANNEL_ACCESS_TOKEN') ?: 'aPAvFs9wjzMKfX6pug8zBVpEoOdtDOaQ1nP060YodHilNRw/Y+/b8v8TqQ7uFGZiqErGn3G+b/2xHiCFK+p3e7IPIX5/l5+eMzYkUtyQvJuXZmoGW990hq22Ei7ljXHjFIwYBfTgJ8QUyxr/ze7aQgdB04t89/1O/w1cDnyilFU=');
define('LINE_LIFF_ID', getenv('LINE_LIFF_ID') ?: '2009198981-GsqxkwIK');

define('UPLOAD_DIR', __DIR__ . '/uploads/');
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);

define('BASE_URL', getenv('BASE_URL') ?: 'https://meeting-uuup.onrender.com/api/');
