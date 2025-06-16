<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// Configuration SMTP
// En production, utilisez des variables d'environnement
// Exemple avec .env : SMTP_HOST=smtp.gmail.com
$smtp_config = [
    'host' => 'smtp.gmail.com',
    'username' => 'sunbaker27@gmail.com',
    'password' => 'adyn ojvg dfev qzmw',
    'port' => 587,
    'from_email' => 'sunbaker27@gmail.com',
    'from_name' => 'Formulaire de Contact',
    'to_email' => 'sunbaker27@gmail.com'
];

// Configuration reCAPTCHA
$recaptcha_secret = "6Lcfj2IrAAAAAHUD6Wykz7JQvZOKiEyKZl8XE8Ru"; // Clé secrète reCAPTCHA
$recaptcha_site_key = "6Lcfj2IrAAAAALvlteAl3IWtwekcEwAuifudeoXh"; // Clé site reCAPTCHA

// Vérification du reCAPTCHA
function verifyRecaptcha($recaptcha_response) {
    global $recaptcha_secret;
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => $recaptcha_secret,
        'response' => $recaptcha_response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    $result = json_decode($response);

    // Log de la réponse complète de reCAPTCHA
    // error_log("reCAPTCHA Response: " . print_r($result, true));

    return $result->success;
}

// Vérification de la limite d'envois
function checkRateLimit($ip, $email) {
    $cache_file = 'rate_limit.json';
    $time_window = 3600; // 1 heure
    $max_attempts = 5; // Maximum 5 tentatives par heure

    if (file_exists($cache_file)) {
        $data = json_decode(file_get_contents($cache_file), true);
    } else {
        $data = [];
    }

    $now = time();
    $ip_key = 'ip_' . $ip;
    $email_key = 'email_' . $email;

    // Nettoyage des anciennes entrées
    foreach ($data as $key => $value) {
        if ($now - $value['time'] > $time_window) {
            unset($data[$key]);
        }
    }

    // Vérification des limites
    if (isset($data[$ip_key]) && $data[$ip_key]['count'] >= $max_attempts) {
        return false;
    }
    if (isset($data[$email_key]) && $data[$email_key]['count'] >= $max_attempts) {
        return false;
    }

    // Mise à jour des compteurs
    if (!isset($data[$ip_key])) {
        $data[$ip_key] = ['count' => 1, 'time' => $now];
    } else {
        $data[$ip_key]['count']++;
    }
    if (!isset($data[$email_key])) {
        $data[$email_key] = ['count' => 1, 'time' => $now];
    } else {
        $data[$email_key]['count']++;
    }

    file_put_contents($cache_file, json_encode($data));
    return true;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Vérification du reCAPTCHA
    if (!isset($_POST['g-recaptcha-response']) || !verifyRecaptcha($_POST['g-recaptcha-response'])) {
        echo json_encode(['status' => 'error', 'message' => "Veuillez vérifier que vous n'êtes pas un robot."]);
        exit();
    }

    // Récupération des données du formulaire
    $name = strip_tags(trim($_POST["name"]));
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $message = strip_tags(trim($_POST["message"]));

    // Vérification des données
    if (empty($name) OR empty($message) OR !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Veuillez compléter le formulaire et réessayer."]);
        exit();
    }

    // Vérification de la limite d'envois
    if (!checkRateLimit($_SERVER['REMOTE_ADDR'], $email)) {
        echo json_encode(['status' => 'error', 'message' => "Trop de tentatives d'envoi. Veuillez réessayer plus tard."]);
        exit();
    }

    $mail = new PHPMailer(true);

    try {
        // Configuration du serveur
        $mail->isSMTP();
        $mail->Host = $smtp_config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_config['username'];
        $mail->Password = $smtp_config['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtp_config['port'];
        $mail->CharSet = 'UTF-8';

        // Destinataires
        $mail->setFrom($smtp_config['from_email'], $smtp_config['from_name']);
        $mail->addReplyTo($email, $name);
        $mail->addAddress($smtp_config['to_email']);

        // Contenu
        $mail->isHTML(true);
        $mail->Subject = "Nouveau message de $name";
        $mail->Body = "
            <h2>Nouveau message du formulaire de contact</h2>
            <p><strong>Nom:</strong> $name</p>
            <p><strong>Email:</strong> $email</p>
            <p><strong>Message:</strong></p>
            <p>" . nl2br(htmlspecialchars($message)) . "</p>
        ";

        $mail->send();
        echo json_encode(['status' => 'success', 'message' => "Merci ! Votre message a été envoyé avec succès."]);
        exit();
    } catch (Exception $e) {
        error_log("Erreur d'envoi d'email : " . $mail->ErrorInfo);
        echo json_encode(['status' => 'error', 'message' => "Oups ! Une erreur s'est produite lors de l'envoi : " . $mail->ErrorInfo]);
        exit();
    }
} else {
    http_response_code(405); // Méthode non autorisée
    echo json_encode(['status' => 'error', 'message' => "Méthode de requête non autorisée."]);
    exit();
}
?> 