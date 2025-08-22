<?php
require_once __DIR__ . '/../models/UserModel.php';

require_once __DIR__ . '/../libs/php-jwt-main/src/JWT.php';
require_once __DIR__ . '/../libs/php-jwt-main/src/Key.php';
require_once __DIR__ . '/../libs/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer-master/src/SMTP.php';
require_once __DIR__ . '/../libs/PHPMailer-master/src/Exception.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AuthController
{

    private $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function login($data)
    {
        $identifier = $data['identifier'] ?? '';

        if (empty($identifier)) {
            throw new AuthException("Username or email is required", 422);
        }

        $user = $this->userModel->findByEmailUsername($identifier);
        if (!$user) {
            throw new AuthException("User not found", 401);
        }

        if (!password_verify($data['password'] ?? '', $user['password'])) {
            throw new AuthException("Incorrect password", 401);
        }

        unset($user['password']);

        $payload = [
            'iss' => 'my-app',
            'sub' => $user['user_id'],
            'iat' => time(),
            'exp' => time() + 3600 //1 hour expiry
        ];

        $jwt = JWT::encode($payload, JWT_SECRET_KEY, 'HS256');

        return [
            "error" => false,
            "message" => "Login successful",
            "user" => $user,
            "token" => $jwt
        ];
    }

    public function register($data)
    {
        if ($this->userModel->exists($data['email'], $data['username'])) {
            throw new ConflictException("Email or username already exists", 409);
        }

        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['role'] = $data['role'] ?? 'customer';
        $data['contact_no'] = $data['contact_no'] ?? '';

        $user = $this->userModel->create($data);
        return ["error" => false, "message" => "Registration successful", "user" => $user];
    }

    public function forgotPassword($data)
    {
        $email = $data['email'] ?? '';
        if (empty($email)) {
            throw new ValidationException("Email is required", 422);
        }

        $user = $this->userModel->findByEmailUsername($email);
        if (!$user) {
            throw new NotFoundException("User not found", 404);
        }

        // generate 6-digit numeric code
        $code = random_int(100000, 999999);
        $exp = time() + 900; // 15 mins expiration

        // save code and expiration to DB
        $this->userModel->saveResetCode($user['user_id'], $code, $exp);

        // PHPMailer setup
        $mail = new PHPMailer(true);
        try {
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
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Reset Your Password';
            $mail->Body    = "Your password reset code is: <b>$code</b>. It will expire in 15 minutes.";

            $mail->send();
        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/../logs/debug.log', "Mailer Error: " . $mail->ErrorInfo . PHP_EOL, FILE_APPEND);
            throw new Exception("Failed to send email");
        }

        return [
            'error' => false,
            'message' => 'Reset code sent to your email'
        ];
    }

    public function verifyResetCode($data)
    {
        $code = $data['code'] ?? '';
        if (empty($code)) {
            throw new ValidationException("Code is required", 422);
        }

        $user = $this->userModel->findByResetCode($code);
        if (!$user) {
            throw new AuthException("Invalid or expired code", 401);
        }

        $this->userModel->markResetVerified($user['user_id']);

        return [
            'error' => false,
            'message' => 'Code verified successfully'
        ];
    }

    public function resetPassword($data)
    {
        $newPassword = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';

        if (empty($newPassword) || empty($confirmPassword)) {
            throw new ValidationException("New password and confirm password are required", 422);
        }

        if ($newPassword !== $confirmPassword) {
            throw new ValidationException("Passwords do not match", 422);
        }

        $user = $this->userModel->findVerifiedResetUser();
        if (!$user) {
            throw new AuthException("No verified reset request found", 401);
        }

        $this->userModel->updatePassword($user['user_id'], password_hash($newPassword, PASSWORD_DEFAULT));
        $this->userModel->deleteResetCode($user['user_id']);

        return [
            'error' => false,
            'message' => 'Password successfully updated'
        ];
    }
}
