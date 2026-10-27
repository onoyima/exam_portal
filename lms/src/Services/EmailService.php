<?php
namespace Lms\Services;

use Lms\Core\Database;
use Lms\Core\Logger;

class EmailService
{
    private static array $config = [];

    public static function init(): void
    {
        self::$config = [
            'driver' => lms_config('mail.driver', 'log'),
            'from_address' => lms_config('mail.from_address', 'noreply@veritas.edu.ng'),
            'from_name' => lms_config('mail.from_name', 'Next Gen LMS'),
            'smtp' => lms_config('mail.smtp', []),
            'sendmail' => lms_config('mail.sendmail', ['path' => '/usr/sbin/sendmail -bs']),
        ];
        Logger::info('Mail service initialized', ['driver' => self::$config['driver']]);
    }

    public static function send(string $to, string $subject, string $body, ?string $altBody = null, array $attachments = []): bool
    {
        $driver = self::$config['driver'] ?? 'log';
        $fromAddress = self::$config['from_address'] ?? 'noreply@veritas.edu.ng';
        $fromName = self::$config['from_name'] ?? 'Next Gen LMS';

        try {
            return match ($driver) {
                'smtp' => self::sendSmtp($to, $subject, $body, $altBody, $fromAddress, $fromName, $attachments),
                'sendmail' => self::sendSendmail($to, $subject, $body, $altBody, $fromAddress, $fromName, $attachments),
                'mail' => self::sendMailFunction($to, $subject, $body, $altBody, $fromAddress, $fromName, $attachments),
                default => self::sendLog($to, $subject, $body, $altBody),
            };
        } catch (\Throwable $e) {
            Logger::error('Mail send failed: ' . $e->getMessage(), ['to' => $to, 'subject' => $subject]);
            return false;
        }
    }

    public static function sendTemplate(string $to, string $subject, string $template, array $data = []): bool
    {
        $body = self::renderTemplate($template, $data);
        $altBody = strip_tags($body);
        return self::send($to, $subject, $body, $altBody);
    }

    public static function sendBulk(array $recipients, string $subject, string $template, array $data = []): int
    {
        $sent = 0;
        foreach ($recipients as $recipient) {
            $email = is_string($recipient) ? $recipient : ($recipient['email'] ?? '');
            if (empty($email)) continue;

            $mergedData = array_merge($data, is_array($recipient) ? $recipient : []);
            if (self::sendTemplate($email, $subject, $template, $mergedData)) {
                $sent++;
            }
        }
        return $sent;
    }

    public static function queue(string $to, string $subject, string $template, array $data = []): bool
    {
        try {
            $db = Database::connection('lms');
            $stmt = $db->prepare("
                INSERT INTO lms_mail_queue (to_email, subject, body, alt_body, data, attachments, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
            ");
            $stmt->execute([
                $to,
                $subject,
                $template,
                null,
                json_encode($data),
                null,
            ]);
            return true;
        } catch (\Throwable $e) {
            Logger::error('Mail queue failed: ' . $e->getMessage(), ['to' => $to, 'subject' => $subject]);
            return false;
        }
    }

    public static function processQueue(int $limit = 10): int
    {
        $processed = 0;

        try {
            $db = Database::connection('lms');
            $stmt = $db->prepare("
                SELECT * FROM lms_mail_queue
                WHERE status = 'pending' AND (scheduled_at IS NULL OR scheduled_at <= NOW()) AND attempts < max_attempts
                ORDER BY created_at ASC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $items = $stmt->fetchAll();

            foreach ($items as $item) {
                try {
                    $data = json_decode($item['data'] ?? '{}', true) ?: [];
                    $body = self::renderTemplate($item['body'], $data);
                    $altBody = strip_tags($body);

                    $success = self::send($item['to_email'], $item['subject'], $body, $altBody);

                    if ($success) {
                        $db->prepare("UPDATE lms_mail_queue SET status = 'sent', sent_at = NOW(), updated_at = NOW() WHERE id = ?")
                            ->execute([$item['id']]);
                    } else {
                        $db->prepare("UPDATE lms_mail_queue SET attempts = attempts + 1, updated_at = NOW() WHERE id = ?")
                            ->execute([$item['id']]);
                    }
                } catch (\Throwable $e) {
                    $db->prepare("UPDATE lms_mail_queue SET attempts = attempts + 1, error_message = ?, updated_at = NOW() WHERE id = ?")
                        ->execute([$e->getMessage(), $item['id']]);

                    if ($item['attempts'] + 1 >= $item['max_attempts']) {
                        $db->prepare("UPDATE lms_mail_queue SET status = 'failed', updated_at = NOW() WHERE id = ?")
                            ->execute([$item['id']]);
                    }
                }

                $processed++;
            }
        } catch (\Throwable $e) {
            Logger::error('Mail queue processing failed: ' . $e->getMessage());
        }

        return $processed;
    }

    public static function available(): bool
    {
        $driver = self::$config['driver'] ?? lms_config('mail.driver', 'log');
        return match ($driver) {
            'smtp' => !empty(self::$config['smtp']['host']),
            'sendmail' => !empty(self::$config['sendmail']['path']),
            'mail' => function_exists('mail'),
            default => true,
        };
    }

    private static function sendSmtp(string $to, string $subject, string $body, ?string $altBody, string $fromAddress, string $fromName, array $attachments): bool
    {
        if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            return self::sendPhpMailer($to, $subject, $body, $altBody, $fromAddress, $fromName, $attachments);
        }
        if (class_exists('\Swift_Mailer')) {
            return self::sendSwiftMailer($to, $subject, $body, $altBody, $fromAddress, $fromName, $attachments);
        }
        Logger::warning('No SMTP library available, falling back to log');
        return self::sendLog($to, $subject, $body, $altBody);
    }

    private static function sendPhpMailer(string $to, string $subject, string $body, ?string $altBody, string $fromAddress, string $fromName, array $attachments): bool
    {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = self::$config['smtp']['host'] ?? '';
        $mail->Port = (int) (self::$config['smtp']['port'] ?? 587);
        $mail->SMTPAuth = !empty(self::$config['smtp']['username']);
        $mail->Username = self::$config['smtp']['username'] ?? '';
        $mail->Password = self::$config['smtp']['password'] ?? '';
        $mail->SMTPSecure = self::$config['smtp']['encryption'] ?? 'tls';
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->msgHTML($body);
        if ($altBody) $mail->AltBody = $altBody;
        foreach ($attachments as $attachment) {
            if (is_string($attachment)) {
                $mail->addAttachment($attachment);
            } elseif (isset($attachment['path'])) {
                $mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
            }
        }
        return $mail->send();
    }

    private static function sendSwiftMailer(string $to, string $subject, string $body, ?string $altBody, string $fromAddress, string $fromName, array $attachments): bool
    {
        $transport = (new \Swift_SmtpTransport(
            self::$config['smtp']['host'] ?? '',
            (int) (self::$config['smtp']['port'] ?? 587),
            self::$config['smtp']['encryption'] ?? 'tls'
        ))
            ->setUsername(self::$config['smtp']['username'] ?? '')
            ->setPassword(self::$config['smtp']['password'] ?? '');

        $mailer = new \Swift_Mailer($transport);
        $message = (new \Swift_Message($subject))
            ->setFrom([$fromAddress => $fromName])
            ->setTo([$to])
            ->setBody($body, 'text/html');

        if ($altBody) {
            $message->addPart($altBody, 'text/plain');
        }

        foreach ($attachments as $attachment) {
            if (is_string($attachment)) {
                $message->attach(\Swift_Attachment::fromPath($attachment));
            } elseif (isset($attachment['path'])) {
                $message->attach(\Swift_Attachment::fromPath($attachment['path'])->setFilename($attachment['name'] ?? ''));
            }
        }

        return $mailer->send($message) > 0;
    }

    private static function sendSendmail(string $to, string $subject, string $body, ?string $altBody, string $fromAddress, string $fromName, array $attachments): bool
    {
        $path = self::$config['sendmail']['path'] ?? '/usr/sbin/sendmail -bs';
        ini_set('sendmail_path', $path);
        return self::sendMailFunction($to, $subject, $body, $altBody, $fromAddress, $fromName, $attachments);
    }

    private static function sendMailFunction(string $to, string $subject, string $body, ?string $altBody, string $fromAddress, string $fromName, array $attachments): bool
    {
        $headers = [
            'MIME-Version: 1.0',
            "From: {$fromName} <{$fromAddress}>",
            'Content-Type: text/html; charset=UTF-8',
        ];

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    private static function sendLog(string $to, string $subject, string $body, ?string $altBody): bool
    {
        $logDir = LMS_STORAGE;
        $logFile = $logDir . '/mail.log';
        $entry = sprintf(
            "[%s] To: %s | Subject: %s | Body: %s\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $body
        );
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        Logger::info('Mail logged', ['to' => $to, 'subject' => $subject]);
        return true;
    }

    private static function renderTemplate(string $template, array $data = []): string
    {
        $body = file_get_contents($template);
        if ($body === false) {
            $body = $template;
        }
        foreach ($data as $key => $value) {
            $body = str_replace('{{' . $key . '}}', (string) $value, $body);
        }
        return $body;
    }
}
