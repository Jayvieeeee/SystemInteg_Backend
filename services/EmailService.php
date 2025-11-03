<?php

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
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'Welcome to Sismoya Water! Let\'s Get You Started';

            $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
            </head>
            <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
                <table role="presentation" style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 40px 20px;">
                            <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff;">
                                <!-- Logo -->
                                <tr>
                                    <td style="padding: 40px 40px 30px 40px; text-align: center; background: linear-gradient(135deg, #2D8CBB 0%, #2D8CBB 100%);">
                                        <h1 style="color: #ffffff; margin: 0; font-size: 32px; font-weight: bold;">🌊 Sismoya Water</h1>
                                        <p style="color: #e0e7ff; margin: 10px 0 0 0; font-size: 14px;">Pure. Fresh. Delivered.</p>
                                    </td>
                                </tr>
                                
                                <!-- Content -->
                                <tr>
                                    <td style="padding: 40px; font-size: 16px; line-height: 1.6;">
                                           <h2 style="color: #2D8CBB; margin: 0 0 20px 0; font-size: 20px; font-weight: 500;">Welcome to Sismoya Water! We are thrilled to have you on board.</h2>
                                        
                                        <p style="color: #000000; margin: 0 0 15px 0;">Hi ' . htmlspecialchars($user['first_name']) . ',</p>
                                        
                                        <p style="color: #000000; margin: 0 0 15px 0;">You\'ve successfully registered, and we\'re delighted to inform you that you\'re now ready to enjoy fresh, clean water delivered right to your doorstep!</p>
                                        
                                           <p style="color: #000000; margin: 0 0 25px 0;">Below, you can place your first order and start experiencing our premium water delivery service. </p>
                                        
                                        <!-- CTA Button -->
                                        <table role="presentation" style="margin: 30px 0;">
                                            <tr>
                                                <td style="text-align: center;">
                                                    <a href="https://sismoya.bsit3b.site" style="display: inline-block; padding: 14px 32px; background-color: #2D8CBB; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 16px;">Place Your First Order</a>
                                                </td>
                                            </tr>
                                        </table>
                                                          <p style="color: #000000; margin: 0 0 20px 0;">Additionally, if you have any questions about our products, delivery process, or pricing, please don\'t hesitate to ask.</p>
                                        
                                        <p style="color: #000000; margin: 25px 0 0 0;">We\'re looking forward to serving you and providing the refreshing hydration you and your family deserve.</p>
                                        
                                        <p style="color: #000000; margin: 20px 0 0 0;">Warm regards,</p>
                                        <p style="color: #000000; margin: 5px 0 0 0;">Sismoya Water Team</p>
                                    </td>
                                </tr>
                                
                                <!-- Footer -->
                                <tr>
                                    <td style="padding: 30px 40px; text-align: center; background-color: #f9f9f9; border-top: 1px solid #e5e5e5;">
                                        <p style="color: #2D8CBB; font-size: 16px; margin: 0 0 5px 0; font-weight: bold;">
                                            CONTACT US
                                        </p>
                                        <p style="color: #000000; font-size: 14px; margin: 5px 0;">
                                            09422617079
                                        </p>
                                        <p style="color: #000000; font-size: 14px; margin: 5px 0;">
                                            sismoya@gmail.com
                                        </p>
                                        <p style="color: #999999; font-size: 12px; margin: 15px 0 0 0;">
                                            © 2024 Sismoya Water. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            ';

            $mail->AltBody = 'Welcome to Sismoya Water! We are thrilled to have you on board. Hi ' . $user['first_name'] . ', You\'ve successfully registered, and we\'re delighted to inform you that you\'re now ready to enjoy fresh, clean water delivered right to your doorstep! Visit https://sismoyawater.com/order to place your first order. Please keep in mind that delivery slots are limited each day, so we encourage you to schedule as soon as possible. If you prefer, our customer service team can assist you. Contact us: 09422617079 or sismoya@gmail.com. We\'re looking forward to serving you!';

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $mail->ErrorInfo);
            return false;
        }
    }

    public function sendPasswordResetEmail($user, $resetCode, $expiryMinutes = 15)
    {
        try {
            $mail = $this->mailer;
            $mail->clearAddresses();

            $mail->setFrom('dmcarss23@gmail.com', 'Sismoya Water');
            $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['last_name']);
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'Password Reset Request - Sismoya Water';

            $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
            </head>
            <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
                <table role="presentation" style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 40px 20px;">
                            <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff;">
                                <!-- Logo -->
                                <tr>
                                    <td style="padding: 40px 40px 30px 40px; text-align: center; background: linear-gradient(135deg, #2D8CBB 0%, #2D8CBB 100%);">
                                        <h1 style="color: #ffffff; margin: 0; font-size: 32px; font-weight: bold;">🌊 Sismoya Water</h1>
                                        <p style="color: #e0e7ff; margin: 10px 0 0 0; font-size: 14px;">Pure. Fresh. Delivered.</p>
                                    </td>
                                </tr>
                                
                                <!-- Content -->
                                <tr>
                                    <td style="padding: 40px; font-size: 16px; line-height: 1.6;">
                                        <h2 style="color: #2D8CBB; margin: 0 0 20px 0; font-size: 22px;">Password Reset Request</h2>
                                        
                                        <p style="color: #000000; margin: 0 0 15px 0;">Hello ' . htmlspecialchars($user['first_name']) . '!</p>
                                        
                                        <p style="color: #000000; margin: 0 0 25px 0;">We received a request to reset your password. Use the code below:</p>
                                        
                                        <!-- Reset Code -->
                                        <div style="background-color: #f5f5f5; padding: 30px; text-align: center; margin: 0 0 25px 0;">
                                            <div style="font-size: 36px; font-weight: bold; color: #1f2937; letter-spacing: 8px;">
                                                ' . htmlspecialchars($resetCode) . '
                                            </div>
                                        </div>
                                        
                                        <p style="color: #ef4444; font-size: 15px; margin: 0 0 20px 0; font-weight: bold;">
                                            This code will expire in ' . $expiryMinutes . ' minutes.
                                        </p>
                                        
                                        <p style="color: #000000; margin: 0; font-size: 14px;">
                                            If you didn\'t request this password reset, please ignore this email or contact support if you have concerns.
                                        </p>
                                    </td>
                                </tr>
                                
                                <!-- Footer -->
                                <tr>
                                    <td style="padding: 30px 40px; text-align: center; background-color: #f9f9f9; border-top: 1px solid #e5e5e5;">
                                        <p style="color: #2D8CBB; font-size: 16px; margin: 0 0 5px 0; font-weight: bold;">
                                            CONTACT US
                                        </p>
                                        <p style="color: #000000; font-size: 14px; margin: 5px 0;">
                                            09422617079
                                        </p>
                                        <p style="color: #000000; font-size: 14px; margin: 5px 0;">
                                            sismoya@gmail.com
                                        </p>
                                        <p style="color: #999999; font-size: 12px; margin: 15px 0 0 0;">
                                            © 2024 Sismoya Water. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            ';

            $mail->AltBody = 'Password Reset Request - Hello ' . $user['first_name'] . '! We received a request to reset your password. Use this code: ' . $resetCode . '. This code will expire in ' . $expiryMinutes . ' minutes. If you didn\'t request this password reset, please ignore this email. Contact us: 09422617079 or sismoya@gmail.com.';

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Password reset email sending failed: " . $mail->ErrorInfo);
            return false;
        }
    }

    // EmailService.php

    public function sendVerificationEmail($user)
    {
        try {
            $mail = $this->mailer;
            $mail->clearAddresses();

            $verificationLink = $_ENV['APP_URL'] . "/verify-email?token=" . $user['verification_token'];

            $mail->setFrom('dmcarss23@gmail.com', 'Sismoya Water');
            $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['last_name']);
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'Verify Your Email Address - Sismoya Water';

            $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
            <table role="presentation" style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 40px 20px;">
                        <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff;">
                            <!-- Logo -->
                            <tr>
                                <td style="padding: 40px 40px 30px 40px; text-align: center; background: linear-gradient(135deg, #2D8CBB 0%, #2D8CBB 100%);">
                                    <h1 style="color: #ffffff; margin: 0; font-size: 32px; font-weight: bold;">🌊 Sismoya Water</h1>
                                    <p style="color: #e0e7ff; margin: 10px 0 0 0; font-size: 14px;">Pure. Fresh. Delivered.</p>
                                </td>
                            </tr>
                            
                            <!-- Content -->
                            <tr>
                                <td style="padding: 40px; font-size: 16px; line-height: 1.6;">
                                    <h2 style="color: #2D8CBB; margin: 0 0 20px 0; font-size: 22px;">Verify Your Email Address</h2>
                                    
                                    <p style="color: #000000; margin: 0 0 15px 0;">Hi ' . htmlspecialchars($user['first_name']) . ',</p>
                                    
                                    <p style="color: #000000; margin: 0 0 25px 0;">Thank you for registering with Sismoya Water! To complete your registration, please verify your email address by clicking the button below:</p>
                                    
                                    <!-- CTA Button -->
                                    <table role="presentation" style="margin: 30px 0;">
                                        <tr>
                                            <td style="text-align: center;">
                                                <a href="' . $verificationLink . '" style="display: inline-block; padding: 14px 32px; background-color: #2D8CBB; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 16px;">Verify Email Address</a>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <p style="color: #000000; margin: 25px 0 0 0; font-size: 14px;">Or copy and paste this link into your browser:</p>
                                    <p style="color: #2D8CBB; margin: 5px 0 25px 0; font-size: 14px; word-break: break-all;">' . $verificationLink . '</p>
                                    
                                    <p style="color: #ef4444; font-size: 15px; margin: 0 0 20px 0; font-weight: bold;">
                                        This link will expire in 24 hours.
                                    </p>
                                    
                                    <p style="color: #000000; margin: 0; font-size: 14px;">
                                        If you didn\'t create an account, please ignore this email.
                                    </p>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style="padding: 30px 40px; text-align: center; background-color: #f9f9f9; border-top: 1px solid #e5e5e5;">
                                    <p style="color: #2D8CBB; font-size: 16px; margin: 0 0 5px 0; font-weight: bold;">
                                        CONTACT US
                                    </p>
                                    <p style="color: #000000; font-size: 14px; margin: 5px 0;">
                                        09422617079
                                    </p>
                                    <p style="color: #000000; font-size: 14px; margin: 5px 0;">
                                        sismoya@gmail.com
                                    </p>
                                    <p style="color: #999999; font-size: 12px; margin: 15px 0 0 0;">
                                        © 2024 Sismoya Water. All rights reserved.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ';

            $mail->AltBody = 'Verify Your Email Address - Hi ' . $user['first_name'] . '! Thank you for registering with Sismoya Water. Please verify your email by visiting: ' . $verificationLink . '. This link will expire in 24 hours. If you didn\'t create an account, please ignore this email. Contact us: 09422617079 or sismoya@gmail.com.';

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Verification email sending failed: " . $mail->ErrorInfo);
            return false;
        }
    }
}
