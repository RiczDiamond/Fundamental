<?php

/**
 * Generate an HTML email template.
 *
 * @param string $subject  The email subject (used in header and title).
 * @param string $message  The main message body (can contain HTML).
 * @param array  $options  Optional data:
 *                         - logo_url: URL for a logo image
 *                         - footer:  HTML footer text
 *                         - preheader: short preview text
 *
 * @return string HTML string.
 */
function email_render_template(string $subject, string $message, array $options = []): string
{
    // Use an absolute URL for images so email clients can load them.
    $logoUrl = $options['logo_url'] ?? '';
    if ($logoUrl === '') {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $logoUrl = $scheme . '://' . $host . '/molwebsites-brand.svg';
    }

    $footer = $options['footer'] ?? '<p style="margin:0;font-size:12px;color:#888;">&copy; ' . date('Y') . ' Fundamenteel. Alle rechten voorbehouden.</p>';
    $preheader = $options['preheader'] ?? 'Je ontvangt dit bericht omdat je een wachtwoordreset hebt aangevraagd.';

    // Sanitize minimal for safe email output
    $safeSubject = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safePreheader = htmlspecialchars($preheader, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{$safeSubject}</title>
  <style>
    body { margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background:#f5f5f7; }
    .email-wrapper { width:100%; background:#f5f5f7; padding:20px 0; }
    .email-content { max-width:600px; margin:0 auto; background:#ffffff; border-radius:10px; overflow:hidden; }
    .email-header { padding:20px 30px; background:#1f2937; color:#fff; }
    .email-body { padding:30px; color:#1f2937; }
    .button { display:inline-block; padding:12px 20px; background:#2563eb; color:#fff; text-decoration:none; border-radius:6px; }
    .footer { padding:20px 30px; font-size:12px; color:#777; }
    .preheader { display:none; visibility:hidden; opacity:0; height:0; width:0; }
  </style>
</head>
<body>
  <span class="preheader">{$safePreheader}</span>
  <div class="email-wrapper">
    <div class="email-content">
      <div class="email-header">
        <img src="{$logoUrl}" alt="Logo" style="max-height:36px;" />
        <h1 style="margin:16px 0 0; font-size:18px;">{$safeSubject}</h1>
      </div>
      <div class="email-body">
        {$message}
      </div>
      <div class="footer">
        {$footer}
      </div>
    </div>
  </div>
</body>
</html>
HTML;
}
