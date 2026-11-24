<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

final class Mailer
{
    public static function deliver(string $toEmail, string $toName, string $subject, string $html, ?string $text = null): array
    {
        $toEmail = trim($toEmail);
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'status' => 'fallido', 'error' => 'Destinatario no válido.'];
        }

        $host = trim((string) Settings::get('mail_host', ''));
        $fromEmail = trim((string) Settings::get('mail_from_email', ''));
        if ($host === '' || $fromEmail === '') {
            $error = 'El servidor SMTP no está configurado.';
            self::log($toEmail, $subject, 'omitido', $error);
            return ['ok' => false, 'status' => 'omitido', 'error' => $error];
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = (int) Settings::get('mail_port', '587');
            $username = (string) Settings::get('mail_username', '');
            $mail->SMTPAuth = $username !== '';
            if ($username !== '') {
                $mail->Username = $username;
                $mail->Password = Settings::decryptSecret((string) Settings::get('mail_password', ''));
            }
            $encryption = (string) Settings::get('mail_encryption', 'tls');
            if ($encryption === 'tls' || $encryption === 'ssl') {
                $mail->SMTPSecure = $encryption;
                $mail->SMTPAutoTLS = true;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            $mail->Timeout = 8;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($fromEmail, (string) Settings::get('mail_from_name', 'GandiaTech CRM'));
            $mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text ?? trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $html)));
            $mail->send();

            self::log($toEmail, $subject, 'enviado');
            return ['ok' => true, 'status' => 'enviado', 'error' => ''];
        } catch (Throwable $e) {
            $error = $e->getMessage();
            self::log($toEmail, $subject, 'fallido', $error);
            return ['ok' => false, 'status' => 'fallido', 'error' => $error];
        }
    }

    public static function send(string $toEmail, string $toName, string $subject, string $html, ?string $text = null): bool
    {
        return self::deliver($toEmail, $toName, $subject, $html, $text)['ok'];
    }

    public static function template(string $title, string $message, string $ctaLabel = '', string $ctaUrl = ''): string
    {
        $safeTitle = e($title);
        $safeMessage = nl2br(e($message));
        $button = '';
        if ($ctaLabel !== '' && $ctaUrl !== '') {
            $button = '<p style="margin:28px 0 8px;"><a href="' . e($ctaUrl) . '" style="background:#FF5A36;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:999px;font-weight:600;display:inline-block;">' . e($ctaLabel) . '</a></p>';
        }

        return '<!doctype html><html lang="es"><body style="margin:0;background:#FBF9F5;padding:32px 16px;font-family:Helvetica,Arial,sans-serif;color:#1E2230;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #E9E4DA;border-radius:24px;overflow:hidden;">'
            . '<tr><td style="padding:28px 32px 8px;">'
            . '<div style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#C7381A;font-weight:700;">GandiaTech CRM</div>'
            . '<h1 style="font-size:24px;line-height:1.2;margin:10px 0 16px;color:#1E2230;">' . $safeTitle . '</h1>'
            . '<div style="font-size:15px;line-height:1.6;color:#5E6472;">' . $safeMessage . '</div>'
            . $button
            . '</td></tr>'
            . '<tr><td style="padding:18px 32px 28px;border-top:1px solid #E9E4DA;font-size:12px;color:#5E6472;">'
            . 'Este correo se ha enviado automáticamente desde el CRM de GandiaTech.'
            . '</td></tr></table></body></html>';
    }

    private static function log(string $recipient, string $subject, string $status, string $error = ''): void
    {
        try {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO email_log (recipient, subject, status, error, created_at) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$recipient, $subject, $status, $error !== '' ? $error : null, now_utc()]);
        } catch (Throwable) {
            // El registro de correo nunca debe romper la operación principal.
        }
    }
}
