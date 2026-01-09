<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$user = current_user();

include __DIR__ . '/../pages/subscription.html';
