<?php
// scripts/send_welcome_email.php

// Set unlimited execution time for background process
set_time_limit(0);

// Load dependencies
require_once __DIR__ . '/../init/Services.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../libs/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer-master/src/SMTP.php';
require_once __DIR__ . '/../libs/PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($argc < 2) {
    exit("No user data provided");
}

try {
    // Decode user data
    $user = unserialize(base64_decode($argv[1]));

    if (!$user || !isset($user['email'])) {
        exit("Invalid user data");
    }

    // Create mailer instance (similar to your Services.php)
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'dmcarss23@gmail.com';
    $mail->Password   = 'nfww iaxw jmdp kwfv';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];

    $mail->setFrom('dmcarss23@gmail.com', 'Sismoya Water');
    $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['last_name']);
    $mail->isHTML(true);
    $mail->Subject = 'Welcome to Sismoya Water!';

    // Beautiful HTML email template
    $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
                .footer { background: #ddd; padding: 10px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to Sismoya Water!</h1>
                </div>
                <div class='content'>
                    <h2>Hello {$user['first_name']}!</h2>
                    <p>Thank you for registering with Sismoya Water. We're excited to have you on board!</p>
                    <p>Your account has been successfully created with the following details:</p>
                    <ul>
                        <li><strong>Name:</strong> {$user['first_name']} {$user['last_name']}</li>
                        <li><strong>Username:</strong> {$user['username']}</li>
                        <li><strong>Email:</strong> {$user['email']}</li>
                        <li><strong>Contact:</strong> {$user['contact_no']}</li>
                    </ul>
                    <p>You can now login to your account and start enjoying our services.</p>
                    <p>If you have any questions, please don't hesitate to contact our support team.</p>
                    <br>
                    <p>Best regards,<br>The Sismoya Water Team</p>
                </div>
                <div class='footer'>
                    <p>© 2024 Sismoya Water. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
    ";

    // Plain text version
    $mail->AltBody = "Welcome to Sismoya Water!\n\n" .
        "Hello {$user['first_name']}!\n\n" .
        "Thank you for registering with Sismoya Water. We're excited to have you on board!\n\n" .
        "Your account details:\n" .
        "Name: {$user['first_name']} {$user['last_name']}\n" .
        "Username: {$user['username']}\n" .
        "Email: {$user['email']}\n" .
        "Contact: {$user['contact_no']}\n\n" .
        "You can now login to your account.\n\n" .
        "Best regards,\nThe Sismoya Water Team";

    $mail->send();

    error_log("Welcome email sent successfully to: " . $user['email']);
} catch (Exception $e) {
    error_log("Failed to send welcome email: " . $e->getMessage());
}
