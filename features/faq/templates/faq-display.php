<?php
// faq/templates/faq-display.php

$settings = $this->get_settings();
$categories = get_terms([
    'taxonomy' => $this->taxonomy,
    'hide_empty' => true,
]); 
?>

<div class="cobra-faq-container" data-theme="<?php echo esc_attr($settings['styling']['theme']); ?>">
    <?php if (!empty($settings['display']['title'])): ?>
        <h2 class="cobra-faq-title"><?php echo esc_html($settings['display']['title']); ?></h2>
    <?php endif; ?>

    <?php if ($settings['search']['enable_search']): ?>
        <div class="cobra-faq-search">
            <input type="text" 
                   class="cobra-faq-search-input" 
                   placeholder="<?php esc_attr_e('Search FAQs...', 'cobra-ai'); ?>"
                   data-autocomplete="<?php echo $settings['search']['enable_autocomplete'] ? 'true' : 'false'; ?>">
            
            <?php if ($settings['search']['show_category_filter'] && !empty($categories)): ?>
                <select class="cobra-faq-category-filter">
                    <option value=""><?php esc_html_e('All Categories', 'cobra-ai'); ?></option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo esc_attr($category->slug); ?>">
                            <?php echo esc_html($category->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>

        <div class="cobra-faq-search-results" style="display: none;"></div>
    <?php endif; ?>

    <div class="cobra-faq-list">
        <?php foreach ($faqs as $faq): ?>
            <?php include $this->path . 'templates/faq-item.php'; ?>
        <?php endforeach; ?>
    </div>

    <?php if ($settings['display']['load_more_type'] === 'pagination'): ?>
        <div class="cobra-faq-pagination">
            <?php
            echo paginate_links([
                'total' => ceil(wp_count_posts($this->post_type)->publish / $atts['limit']),
                'current' => get_query_var('paged') ? get_query_var('paged') : 1,
            ]);
            ?>
        </div>
    <?php else: ?>
        <button class="cobra-faq-load-more" style="display: <?php echo count($faqs) >= $atts['limit'] ? 'block' : 'none'; ?>">
            <?php esc_html_e('Load More', 'cobra-ai'); ?>
        </button>
    <?php endif; ?>

    <?php if (!empty($settings['display']['footer_text'])): ?>
        <div class="cobra-faq-footer">
            <p>
                <?php if (!empty($settings['display']['footer_link'])): ?>
                    <a href="<?php echo esc_url($settings['display']['footer_link']); ?>">
                        <?php echo esc_html($settings['display']['footer_text']); ?>
                    </a>
                <?php else: ?>
                    <?php echo esc_html($settings['display']['footer_text']); ?>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($faqs)): ?>
<script type="application/ld+json">
<?php
$schema_items = [];
foreach ($faqs as $faq) {
    $answer_text = wp_strip_all_tags(apply_filters('the_content', $faq->post_content));
    $answer_text = preg_replace('/\s+/', ' ', trim($answer_text));
    $schema_items[] = [
        '@type'          => 'Question',
        'name'           => $faq->post_title,
        'acceptedAnswer' => [
            '@type' => 'Answer',
            'text'  => $answer_text,
        ],
    ];
}
echo wp_json_encode([
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => $schema_items,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
?>
</script>
<?php endif; ?>