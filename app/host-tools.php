<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

redirect_to('/app/host-tools-published.php');
