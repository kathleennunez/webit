<?php
require_once __DIR__ . '/../php/bootstrap.php';
$includeIntlTelInput = true;

if (current_user()) {
  $role = current_user()['role'] ?? '';
  redirect_to($role === 'admin' ? '/app/admin.php' : '/app/home.php');
}

$message = '';
$error = '';
$firstName = '';
$lastName = '';
$email = '';
$timezone = 'UTC';
$timezoneDisplay = '';
$phoneDisplay = '';
$smsOptIn = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $firstName = trim($_POST['first_name'] ?? '');
  $lastName = trim($_POST['last_name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $emailNormalized = strtolower($email);
  $password = $_POST['password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';
  $timezone = $_POST['timezone'] ?? 'UTC';
  $phoneDisplay = $_POST['phone'] ?? ($_POST['phone_display'] ?? '');
  $phone = normalize_phone_ph($phoneDisplay);
  $smsOptIn = !empty($_POST['sms_opt_in']);

  if (!$firstName || !$lastName || !$email || !$password) {
    $error = 'Please fill out all required fields.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please provide a valid email address.';
  } elseif (strlen($password) < 8) {
    $error = 'Password must be at least 8 characters.';
  } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
    $error = 'Password must include at least one uppercase letter and one number.';
  } elseif ($password !== $confirm) {
    $error = 'Passwords do not match.';
  } else {
    $users = read_json('users.json');
    
    // Check if email already exists
    $emailExists = false;
    foreach ($users as $user) {
      if (strtolower($user['email'] ?? '') === $emailNormalized) {
        $emailExists = true;
        break;
      }
    }
    
    if ($emailExists) {
      $error = 'Email already exists.';
    } else {
      // Generate unique ID
      $userId = uniqid('user_', true);
      
      // Ensure ID is truly unique
      $idExists = true;
      while ($idExists) {
        $idExists = false;
        foreach ($users as $user) {
          if (($user['user_id'] ?? '') === $userId) {
            $idExists = true;
            $userId = uniqid('user_', true);
            break;
          }
        }
      }
      
      $newUser = [
        'user_id' => $userId,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $emailNormalized,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'member',
        'interests' => [],
        'api_token' => bin2hex(random_bytes(12)),
        'avatar' => '/assets/images/avatar-default.svg',
        'timezone' => $timezone,
        'phone' => $phone,
        'sms_opt_in' => $smsOptIn,
        'alaehscape_user_id' => null // Will be updated after sync
      ];
      
      $users[] = $newUser;
      
      // Try to write to database/JSON
      try {
        write_json('users.json', $users);
        
        /* ===============================
           SYNC TO ALAEHSCAPE
        ================================ */
        $fullName = trim($firstName . ' ' . $lastName);
        $syncPayload = json_encode([
          "name" => $fullName,
          "email" => $email,
          "phone" => $phone,
          "password" => $password,
          "webit_user_id" => $userId // Send WebIT user ID
        ]);

        $ch = curl_init("http://172.20.10.3/Ala_Eh_scape/php/api/register.php"); // Update with AlaehScape IP
        curl_setopt_array($ch, [
          CURLOPT_POST => true,
          CURLOPT_POSTFIELDS => $syncPayload,
          CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-KEY: WEBIT_SECRET'
          ],
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_TIMEOUT => 5
        ]);

        $syncResponse = curl_exec($ch);
        $syncHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // If sync successful, update alaehscape_user_id
        if ($syncHttpCode === 200) {
          $responseData = json_decode($syncResponse, true);
          if (isset($responseData['user_id'])) {
            // Update the user with AlaehScape ID
            $users = read_json('users.json');
            foreach ($users as &$user) {
              if ($user['user_id'] === $userId) {
                $user['alaehscape_user_id'] = $responseData['user_id'];
                break;
              }
            }
            write_json('users.json', $users);
          }
        } else {
          error_log("Failed to sync registration to AlaehScape: " . $syncResponse);
        }
        
        $message = 'Account created. You can now sign in.';
        $firstName = '';
        $lastName = '';
        $email = '';
        $timezone = 'UTC';
        $timezoneDisplay = '';
        $phoneDisplay = '';
        $smsOptIn = false;
        
      } catch (Exception $e) {
        // Remove the user from array if database insert failed
        array_pop($users);
        $error = 'Registration failed. Please try again.';
        error_log("Registration error for {$email}: " . $e->getMessage());
      }
    }
  }
}

$timezones = get_timezones();
if (!$timezones) {
  $timezones = ['UTC'];
}
$timezoneDisplay = $timezone === 'UTC' ? '' : $timezone;

include __DIR__ . '/../pages/signup.html';
