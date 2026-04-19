<?php
declare(strict_types=1);

// PHPMailer is required for SMTP support.
// Installation: run `composer require phpmailer/phpmailer` in the project root,
// then ensure `vendor/autoload.php` is loaded in public/index.php.
// Composer: https://getcomposer.org
//
// On shared hosting without Composer: download PHPMailer manually from
// https://github.com/PHPMailer/PHPMailer and place the src/ directory
// under vendor/phpmailer/phpmailer/src/, then require the files manually.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class Mailer
{
    public function send(string $to, string $subject, string $body): bool
    {
        try {
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            
            // Development & Testing (Mailpit)
            if (MAIL_PORT == 1025) {
                $mail->SMTPAuth   = false;
                $mail->SMTPSecure = ''; 
            } else {
                // Live server
                $mail->SMTPAuth   = true;
                $mail->Username   = MAIL_USER;
                $mail->Password   = MAIL_PASS;
                $mail->SMTPSecure = MAIL_PORT === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            $mail->Port = MAIL_PORT;

            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            error_log('Mailer error: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            error_log('Mailer unexpected error: ' . $e->getMessage());
            return false;
        }
    }
}
