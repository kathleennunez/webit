<?php
require_once __DIR__ . '/php/bootstrap.php';
logout_user();
redirect_to('/login.php');
