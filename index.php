<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Only POST requests are allowed'], JSON_PRETTY_PRINT);
    exit;
}

session_start();
if (!isset($_SESSION['last_request_time'])) {
    $_SESSION['last_request_time'] = time();
} else {
    $timeDiff = time() - $_SESSION['last_request_time'];
    if ($timeDiff < 60) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Too many requests. Please wait before trying again.'], JSON_PRETTY_PRINT);
        exit;
    }
    $_SESSION['last_request_time'] = time();
}

$data = file_get_contents('php://input');
$request = json_decode($data, true);

if (json_last_error() === JSON_ERROR_NONE) {
    if (!isset($request['purpose']) || !in_array($request['purpose'], [0, 1], true)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid purpose value. Use 0 for contact and 1 for appointment'], JSON_PRETTY_PRINT);
        exit;
    }
    
    $purpose = $request['purpose'];
    $firstName = htmlspecialchars(trim($request['firstName'] ?? ''));
    $lastName = htmlspecialchars(trim($request['lastName'] ?? ''));
    $email = htmlspecialchars(trim($request['email'] ?? ''));
    $phone = htmlspecialchars(trim($request['phone'] ?? ''));
    $subject = $request['subject'] ?? '';
    $message = htmlspecialchars(trim($request['message'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format'], JSON_PRETTY_PRINT);
        exit;
    }

    if (empty($firstName) || empty($lastName) || empty($email) || empty($subject) || empty($message)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'All fields are required'], JSON_PRETTY_PRINT);
        exit;
    }

    $subject = is_array($subject) ? implode(', ', $subject) : $subject;
    $purposeText = $purpose === 0 ? 'İletişim' : 'Randevu';
    $fullSubject = $purposeText . ': ' . $subject;
    $phoneMessage = isset($phone) && !empty($phone) ? "Telefon: $phone" : "Telefon: Belirtilmemiş";

    $fullMessage = "$firstName $lastName isimli kişi $purposeText için talepte bulundu.\nMesaj: $message\n$phoneMessage\nE-Posta: $email";

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $_ENV['EMAIL_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['EMAIL_USER'];
        $mail->Password = $_ENV['EMAIL_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $_ENV['EMAIL_PORT'];

        $mail->setFrom($_ENV['EMAIL_USER'], 'Web Site');
        $mail->addAddress($_ENV['EMAIL_USER']);
        $mail->addReplyTo($email, mb_convert_encoding("$firstName $lastName", 'UTF-8', 'auto'));

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = mb_convert_encoding($fullSubject, 'UTF-8', 'auto');
        $mail->Body    = nl2br(mb_convert_encoding($fullMessage, 'UTF-8', 'auto'));
        $mail->AltBody = mb_convert_encoding($fullMessage, 'UTF-8', 'auto');

        $mail->send();
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Email send successfully!'], JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => "Error sending email: {$mail->ErrorInfo}"], JSON_PRETTY_PRINT);
    }
} else {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON'], JSON_PRETTY_PRINT);
}
?>