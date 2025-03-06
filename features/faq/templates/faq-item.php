<?php
// templates/faq-item.php

$faq_categories = get_the_terms($faq->ID, $this->taxonomy);
$category_names = [];
if ($faq_categories && !is_wp_error($faq_categories)) {
    foreach ($faq_categories as $cat) {
        $category_names[] = $cat->name;
    }
}

// Get meta information
$views = get_post_meta($faq->ID, 'cobra_faq_views', true) ?: 0;
$last_updated = get_the_modified_date('Y-m-d', $faq->ID);
?>

<div class="cobra-faq-item" data-id="<?php echo esc_attr($faq->ID); ?>">
    <div class="cobra-faq-question">
        <div class="cobra-faq-header">
            <h3><?php echo esc_html($faq->post_title); ?></h3>
            
            <?php if ($settings['display']['show_categories'] && !empty($category_names)): ?>
                <div class="cobra-faq-categories">
                    <?php foreach ($category_names as $category): ?>
                        <span class="cobra-faq-category"><?php echo esc_html($category); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <button class="cobra-faq-toggle" aria-expanded="false">
                <span class="screen-reader-text">
                    <?php esc_html_e('Toggle answer', 'cobra-ai'); ?>
                </span>
                <svg class="cobra-faq-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <line x1="12" y1="5" x2="12" y2="19" class="vertical"></line>
                    <line x1="5" y1="12" x2="19" y2="12" class="horizontal"></line>
                </svg>
            </button>
        </div>

        <?php if ($settings['display']['show_meta']): ?>
            <div class="cobra-faq-meta">
                <?php if ($settings['display']['show_views']): ?>
                    <span class="cobra-faq-views">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M12 4.5c-5 0-9.3 3-11 7.5 1.7 4.5 6 7.5 11 7.5s9.3-3 11-7.5c-1.7-4.5-6-7.5-11-7.5z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <?php echo esc_html(number_format($views)); ?>
                    </span>
                <?php endif; ?>

                <?php if ($settings['display']['show_last_updated']): ?>
                    <span class="cobra-faq-updated">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <?php
                        printf(
                            esc_html__('Updated: %s', 'cobra-ai'),
                            esc_html($last_updated)
                        );
                        ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="cobra-faq-answer" hidden>
        <div class="cobra-faq-content">
            <?php 
            // Apply content filters for proper formatting
            echo apply_filters('the_content', $faq->post_content); 
            ?>
        </div>

        <?php if ($settings['display']['show_helpful']): ?>
            <div class="cobra-faq-helpful">
                <p><?php esc_html_e('Was this answer helpful?', 'cobra-ai'); ?></p>
                <div class="cobra-faq-helpful-buttons">
                    <button class="cobra-faq-helpful-yes" data-faq="<?php echo esc_attr($faq->ID); ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                        </svg>
                        <?php esc_html_e('Yes', 'cobra-ai'); ?>
                    </button>
                    <button class="cobra-faq-helpful-no" data-faq="<?php echo esc_attr($faq->ID); ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path>
                        </svg>
                        <?php esc_html_e('No', 'cobra-ai'); ?>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($settings['display']['show_share']): ?>
            <div class="cobra-faq-share">
                <button class="cobra-faq-share-button" data-url="<?php echo esc_url(get_permalink($faq->ID)); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="18" cy="5" r="3"></circle>
                        <circle cx="6" cy="12" r="3"></circle>
                        <circle cx="18" cy="19" r="3"></circle>
                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                    </svg>
                    <?php esc_html_e('Share', 'cobra-ai'); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>