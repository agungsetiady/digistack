<?php
require_once __DIR__ . '/config.php';

function check_admin_login() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: ' . admin_url('login'));
        exit;
    }
}