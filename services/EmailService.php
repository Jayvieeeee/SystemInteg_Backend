<?php
// services/EmailService.php

require_once __DIR__ . '/../libs/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer-master/src/SMTP.php';
require_once __DIR__ . '/../libs/PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private $mailer;

    public function __construct(PHPMailer $mailer)
    {
        $this->mailer = $mailer;
    }

    public function sendWelcomeEmail($user)
    {
        try {
            $mail = $this->mailer;
            $mail->clearAddresses();

            $mail->setFrom('dmcarss23@gmail.com', 'Sismoya Water');
            $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['last_name']);
            $mail->isHTML(true);
            $mail->Subject = 'Welcome to Sismoya Water!';

            $mail->Body = "
                <h2>Hello {$user['first_name']}!</h2>
                <p>Thank you for registering with Sismoya Water!</p>
                <p><strong>Your account details:</strong></p>
                <ul>
                    <li>Name: {$user['first_name']} {$user['last_name']}</li>
                    <li>Username: {$user['username']}</li>
                    <li>Email: {$user['email']}</li>
                    <li>Contact: {$user['contact_no']}</li>
                </ul>
                <p>You can now login to your account.</p>
            ";

            $mail->AltBody = "Welcome to Sismoya Water!\n\nHello {$user['first_name']}! Thank you for registering.";

            return $mail->send();
        } catch (Exception $e) {
            error_log("Email failed: " . $e->getMessage());
            return false;
        }
    }

    public function sendPasswordResetEmail($user, $code)
    {
        try {
            $mail = $this->mailer;
            $mail->clearAddresses();

            $mail->setFrom('dmcarss23@gmail.com', 'Sismoya Water');
            $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['last_name']);
            $mail->isHTML(true);
            $mail->Subject = 'Reset Your Password';

            $mail->Body = "
            <h2>Password Reset Request</h2>
            <p>Hello {$user['first_name']}!</p>
            <p>Your password reset code is: <b>{$code}</b></p>
            <p>This code will expire in 15 minutes.</p>
            <p>If you didn't request this, please ignore this email.</p>
        ";

            $mail->AltBody = "Password Reset Code: {$code}\nThis code expires in 15 minutes.";

            return $mail->send();
        } catch (Exception $e) {
            error_log("Password reset email failed: " . $e->getMessage());
            return false;
        }
    }
}
