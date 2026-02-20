<?php
/**
 * FloxWatch Unified Mailer — Gmail SMTP Edition
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

function sendFloxEmail($to, $subject, $message, $html = false) {
    $mail = new PHPMailer(true);

    try {
        // --- GMAIL SMTP CONFIG ---
        $mail->isSMTP();
        $mail->SMTPDebug  = 2; // Enable debug output
        $mail->Debugoutput = function($str, $level) {
            $logDir = __DIR__ . '/logs';
            if (!is_dir($logDir)) mkdir($logDir, 0777, true);
            file_put_contents($logDir . '/smtp_debug.log', "[" . date('Y-m-d H:i:s') . "] $str\n", FILE_APPEND);
        };

        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'floxxteam@gmail.com';
        $mail->Password   = 'ykwbfxgpbwsnviyn'; // New App Password provided by user

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        
        $mail->Timeout    = 20;

        // SSL options for local environments (MAMP might need this)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom('floxxteam@gmail.com', 'FloxWatch');
        $mail->addAddress($to);
        $mail->addReplyTo('floxxteam@gmail.com', 'FloxWatch Support');

        // Content
        $mail->isHTML($html);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        
        if (!$html) {
            $mail->AltBody = strip_tags($message);
        }

        $mail->send();
        return true;

    } catch (Exception $e) {
        // Local logging for debug
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) mkdir($logDir, 0777, true);
        
        $errorLog = $logDir . '/mailer_errors.log';
        $errorMsg = "[" . date('Y-m-d H:i:s') . "] Mail Error: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage() . " (Target: $to)\n";
        file_put_contents($errorLog, $errorMsg, FILE_APPEND);
        
        // Also log to the general PHP error log
        error_log("PHPMailer Error: " . $mail->ErrorInfo . " (Target: $to)");
        
        return false;
    }
}
