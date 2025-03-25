<?php
/**
 * Hello World shortcode template
 * 
 * @var string $message The hello message
 * @var string $choice The selected choice
 */

defined('ABSPATH') || exit;
?>

<div class="cobra-hello-world">
    <div class="message"><?php echo esc_html($message); ?></div>
    <div class="choice">
        <?php echo esc_html(sprintf(
            __('Selected option: %s', 'cobra-ai'),
            $choice
        )); ?>
    </div>
</div>