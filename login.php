<?php
require_once __DIR__ . '/php/bootstrap.php';

if (current_user()) {
  redirect_to('/home.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = $_POST['email'] ?? '';
  $password = $_POST['password'] ?? '';
  if (login_user($email, $password)) {
    redirect_to('/home.php');
  }
  $error = 'Invalid credentials.';
}

include __DIR__ . '/pages/index.html';
