<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

if (!isset($_POST['email'])) {
    die("Email required.");
}

$email = $_POST['email'];
$otp = rand(100000, 999999);

// save OTP in session
$_SESSION['otp'] = $otp;
$_SESSION['email'] = $email;

$mail = new PHPMailer(true);

try {
    // SMTP SETTINGS
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;

    // ⚠️ USE GMAIL APP PASSWORD
    $mail->Username = 'familaranhannahyvonnemonsanto@gmail.com';
    $mail->Password = 'qzlqxudiqpxkbvtx';

    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    // EMAIL CONTENT
    $mail->setFrom('no-reply@pupstb.com', 'COMSOC');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Your OTP Verification Code';
    $mail->Body = "
        <h2>COMSOC Registration</h2>
        <p>Your OTP code is:</p>
        <h1 style='color:maroon;'>$otp</h1>
        <p>This code is for account verification.</p>
    ";

    $mail->send();

    header("Location: verify.php");
    exit();

} catch (Exception $e) {
    echo "Email failed: {$mail->ErrorInfo}";
}
?>
