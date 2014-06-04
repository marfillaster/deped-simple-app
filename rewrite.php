<?php

// forward all request to the front controller

if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js|ico)$/', $_SERVER["REQUEST_URI"])) {
    return false;
} else {
    include __DIR__ . '/index.php';
}
