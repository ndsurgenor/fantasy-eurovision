<?php

// PHP built-in server router — passes all non-asset requests to public/index.php
$uri = $_SERVER['REQUEST_URI'];

// Serve static files (css, js, images, etc.) directly
$publicFile = __DIR__ . '/public' . parse_url($uri, PHP_URL_PATH);
if (is_file($publicFile)) {
    return false;
}

// Everything else goes through the front controller
require __DIR__ . '/public/index.php';
