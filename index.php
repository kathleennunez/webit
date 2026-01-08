<?php
require_once __DIR__ . '/php/bootstrap.php';

if (current_user()) {
  redirect_to('/home.php');
}

include __DIR__ . '/pages/landing.html';
