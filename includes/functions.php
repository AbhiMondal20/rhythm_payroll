<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Kolkata');

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function currentPageName() {
    return basename($_SERVER['PHP_SELF'], '.php');
}

function isActivePage($page) {
    return currentPageName() === $page ? 'active' : '';
}