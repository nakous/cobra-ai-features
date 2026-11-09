<?php

namespace CobraAI\Features\StripePayments;

/**
 * Email notifications for Stripe Payments orders.
 */
class Emails
{
    private Feature $feature;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
    }

    /**
     * Register hooks.
     */
    public function init(): void
    {
        add_action('cobra_ai_order_paid', [$this, 'send_confirmation'], 20, 2);
        add_action('cobra_ai_order_refunded', [$this, 'send_refund_notice'], 20, 2);
    }

    /**
     * Send a purchase confirmation email to the buyer.
     */
    public function send_confirmation(int $order_id, array $order_data): void
    {
        $settings = $this->feature->get_settings();
        if (empty($settings['email_notifications'])) {
            return;
        }

        $user = get_userdata((int) ($order_data['user_id'] ?? 0));
        if (!$user || !$user->user_email) {
            return;
        }

        $product    = get_post((int) ($order_data['product_id'] ?? 0));
        $product_name = $product ? $product->post_title : '#' . ($order_data['product_id'] ?? '?');
        $amount     = number_format((float) ($order_data['amount'] ?? 0), 2);
        $currency   = strtoupper($order_data['currency'] ?? 'USD');
        $site_name  = get_bloginfo('name');
        $site_url   = home_url();

        $subject = sprintf(
            /* translators: %s: site name */
            __('Purchase confirmation — %s', 'cobra-ai'),
            $site_name
        );

        $body = $this->build_confirmation_html([
            'user_name'    => $user->display_name ?: $user->user_login,
            'user_email'   => $user->user_email,
            'order_id'     => $order_id,
            'product_name' => $product_name,
            'amount'       => $amount,
            'currency'     => $currency,
            'site_name'    => $site_name,
            'site_url'     => $site_url,
        ]);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $site_name, get_option('admin_email')),
        ];

        $sent = wp_mail($user->user_email, $subject, $body, $headers);

        if (!$sent) {
            $this->feature->log('warning', sprintf(
                'Emails: failed to send confirmation for order #%d to %s',
                $order_id,
                $user->user_email
            ));
        }

        // Notify admin too
        $admin_email = get_option('admin_email');
        $admin_subject = sprintf(
            /* translators: %1$s: site name, %2$d: order ID, %3$s: amount, %4$s: currency */
            __('[%1$s] New order #%2$d — %3$s %4$s', 'cobra-ai'),
            $site_name,
            $order_id,
            $amount,
            $currency
        );
        $admin_body = sprintf(
            __("<p>A new order has been paid.</p>
<p><strong>Order:</strong> #%d<br>
<strong>Customer:</strong> %s (%s)<br>
<strong>Product:</strong> %s<br>
<strong>Amount:</strong> %s %s</p>", 'cobra-ai'),
            $order_id,
            esc_html($user->display_name),
            esc_html($user->user_email),
            esc_html($product_name),
            $amount,
            $currency
        );
        wp_mail($admin_email, $admin_subject, $admin_body, $headers);
    }

    /**
     * Send a refund notice to the buyer.
     */
    public function send_refund_notice(int $order_id, array $order_data): void
    {
        $settings = $this->feature->get_settings();
        if (empty($settings['email_notifications'])) {
            return;
        }

        $user = get_userdata((int) ($order_data['user_id'] ?? 0));
        if (!$user || !$user->user_email) {
            return;
        }

        $amount    = number_format((float) ($order_data['amount'] ?? 0), 2);
        $currency  = strtoupper($order_data['currency'] ?? 'USD');
        $site_name = get_bloginfo('name');

        $subject = sprintf(
            /* translators: %1$d: order ID, %2$s: site name */
            __('Refund for your order #%1$d — %2$s', 'cobra-ai'),
            $order_id,
            $site_name
        );

        $body = sprintf(
            __('<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
<h2 style="color:#0c5460;">Refund processed</h2>
<p>Hello %1$s,</p>
<p>Your order <strong>#%2$d</strong> for <strong>%3$s %4$s</strong> has been refunded.</p>
<p>The amount will be credited to your payment method within a few business days.</p>
<p>Best regards,<br>%5$s</p>
</div>', 'cobra-ai'),
            esc_html($user->display_name),
            $order_id,
            $amount,
            $currency,
            esc_html($site_name)
        );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $site_name, get_option('admin_email')),
        ];

        wp_mail($user->user_email, $subject, $body, $headers);
    }

    /**
     * Build a styled HTML confirmation email.
     */
    private function build_confirmation_html(array $vars): string
    {
        $v = (object) $vars;

        $lbl_confirmation = esc_html__('Payment confirmation', 'cobra-ai');
        $lbl_hello        = esc_html__('Hello', 'cobra-ai');
        $lbl_thanks       = esc_html__('Thank you for your purchase! Your payment has been recorded.', 'cobra-ai');
        $lbl_order        = esc_html__('Order', 'cobra-ai');
        $lbl_product      = esc_html__('Product', 'cobra-ai');
        $lbl_amount       = esc_html__('Amount', 'cobra-ai');
        $lbl_contact      = esc_html__('If you have any questions, contact us at', 'cobra-ai');

        return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;">
    <div style="background:#635bff;color:#fff;padding:24px 30px;border-radius:6px 6px 0 0;">
        <h1 style="margin:0;font-size:22px;">{$v->site_name}</h1>
        <p style="margin:6px 0 0;font-size:14px;opacity:.85;">{$lbl_confirmation}</p>
    </div>

    <div style="padding:30px;border:1px solid #e8e8e8;border-top:0;border-radius:0 0 6px 6px;">
        <p style="font-size:16px;">{$lbl_hello} <strong>{$v->user_name}</strong>,</p>
        <p>{$lbl_thanks}</p>

        <table style="width:100%;border-collapse:collapse;margin:20px 0;" cellpadding="8">
            <tr style="background:#f8f9fa;">
                <td style="font-weight:600;border-bottom:1px solid #e8e8e8;">{$lbl_order}</td>
                <td style="border-bottom:1px solid #e8e8e8;">#{$v->order_id}</td>
            </tr>
            <tr>
                <td style="font-weight:600;border-bottom:1px solid #e8e8e8;">{$lbl_product}</td>
                <td style="border-bottom:1px solid #e8e8e8;">{$v->product_name}</td>
            </tr>
            <tr style="background:#f8f9fa;">
                <td style="font-weight:600;border-bottom:1px solid #e8e8e8;">{$lbl_amount}</td>
                <td style="border-bottom:1px solid #e8e8e8;font-size:18px;font-weight:700;color:#635bff;">{$v->amount} {$v->currency}</td>
            </tr>
        </table>

        <p style="color:#666;font-size:13px;">
            {$lbl_contact} <a href="mailto:{$v->user_email}" style="color:#635bff;">{$v->site_name}</a>.
        </p>
    </div>

    <p style="text-align:center;color:#999;font-size:12px;margin-top:16px;">
        {$v->site_name} &mdash; <a href="{$v->site_url}" style="color:#999;">{$v->site_url}</a>
    </p>
</div>
HTML;
    }
}
