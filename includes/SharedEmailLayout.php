<?php

namespace CobraAI;

/**
 * Shared email layout and footer used by all features (Register, EmailMarketing, etc.)
 * Stored in wp_options as cobra_ai_email_layout and cobra_ai_email_footer.
 */
class SharedEmailLayout
{
    const OPTION_LAYOUT = 'cobra_ai_email_layout';
    const OPTION_FOOTER = 'cobra_ai_email_footer';

    public static function get_layout(): string
    {
        $saved = get_option(self::OPTION_LAYOUT, '');
        return $saved !== '' ? $saved : self::default_layout();
    }

    public static function get_footer(): string
    {
        $saved = get_option(self::OPTION_FOOTER, '');
        return $saved !== '' ? $saved : self::default_footer();
    }

    public static function save_layout(string $html): void
    {
        update_option(self::OPTION_LAYOUT, $html, false);
    }

    public static function save_footer(string $html): void
    {
        update_option(self::OPTION_FOOTER, $html, false);
    }

    public static function default_layout(): string
    {
        return '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{subject}}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:20px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
      <!-- Header -->
      <tr><td style="background:#1a73e8;padding:30px 40px;text-align:center;">
        <h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:700;">{{site_name}}</h1>
      </td></tr>
      <!-- Content -->
      <tr><td style="padding:40px;color:#333333;font-size:15px;line-height:1.6;">
        {{content}}
      </td></tr>
      <!-- Footer -->
      <tr><td style="background:#f8f8f8;padding:20px 40px;border-top:1px solid #eeeeee;">
        {{footer}}
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>';
    }

    public static function default_footer(): string
    {
        $site_name = get_bloginfo('name');
        $site_url  = home_url();
        $year      = date('Y');

        return '<p style="margin:0 0 6px;font-size:12px;color:#888888;text-align:center;">
  Cet email a été envoyé par <strong>' . esc_html($site_name) . '</strong>.
</p>
<p style="margin:0 0 6px;font-size:12px;color:#888888;text-align:center;">
  <a href="' . esc_url($site_url) . '" style="color:#888888;">' . esc_html($site_url) . '</a>
</p>
<p style="margin:0 0 6px;font-size:12px;color:#888888;text-align:center;">
  <a href="{{unsubscribe_url}}" style="color:#888888;text-decoration:underline;">Se désinscrire</a>
</p>
<p style="margin:0;font-size:11px;color:#aaaaaa;text-align:center;">
  &copy; ' . $year . ' ' . esc_html($site_name) . '. Tous droits réservés.
</p>';
    }
}
