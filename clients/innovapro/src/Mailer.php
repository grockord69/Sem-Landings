<?php
declare(strict_types=1);
namespace Innova;
final class Mailer {
    public function send(array $lead): void {
        if (!cfg('smtp.host') || !cfg('smtp.username') || !cfg('smtp.password') || !cfg('smtp.from_email') || !cfg('smtp.recipients')) {
            throw new \RuntimeException('SMTP_NOT_CONFIGURED');
        }
        require_once ROOT . '/vendor/autoload.php';
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string)cfg('smtp.host');
        $mail->Port = (int)cfg('smtp.port');
        $mail->SMTPAuth = true;
        $mail->Username = (string)cfg('smtp.username');
        $mail->Password = (string)cfg('smtp.password');
        $mail->SMTPSecure = cfg('smtp.encryption') === 'ssl' ? $mail::ENCRYPTION_SMTPS : $mail::ENCRYPTION_STARTTLS;
        $mail->Timeout = max(1, min(8, (int)cfg('smtp.timeout_seconds', 5)));
        $mail->Timelimit = $mail->Timeout;
        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->setFrom((string)cfg('smtp.from_email'), (string)cfg('smtp.from_name'));
        foreach (cfg('smtp.recipients') as $recipient) { $mail->addAddress($recipient); }
        $mail->addReplyTo($lead['email'], $lead['nombre']);
        $mail->Subject = 'Nueva solicitud · InnovaPro · Lead #' . $lead['id'];
        $mail->isHTML(true);
        ob_start();
        try { require ROOT . '/templates/emails/new-lead.php'; $mail->Body = (string)ob_get_contents(); }
        finally { ob_end_clean(); }
        $mail->AltBody = html_entity_decode(strip_tags(str_replace(['</p>', '</tr>', '<br>'], "\n", $mail->Body)), ENT_QUOTES, 'UTF-8');
        $mail->send();
    }
}
