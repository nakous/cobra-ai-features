<?php
// faq/views/settings.php

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings with defaults merged
$settings = $this->get_settings();

// Display any settings errors
$this->display_settings_errors();
?>

<div class="wrap">
    <h1><?php echo esc_html__('FAQ Settings', 'cobra-ai'); ?></h1>

    <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Settings saved successfully.', 'cobra-ai'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('cobra_ai_feature_settings_' . $this->get_feature_id()); ?>
        <input type="hidden" name="action" value="cobra_ai_save_feature_settings">
        <input type="hidden" name="feature_id" value="<?php echo esc_attr($this->get_feature_id()); ?>">

        <!-- Display Settings -->
        <div class="cobra-settings-section">
            <h2><?php echo esc_html__('Display Settings', 'cobra-ai'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="faq_title"><?php echo esc_html__('FAQ Title', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="faq_title" 
                               name="settings[display][title]" 
                               value="<?php echo esc_attr($settings['display']['title']); ?>" 
                               class="regular-text">
                        <p class="description">
                            <?php echo esc_html__('The title displayed above your FAQs', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="items_per_page"><?php echo esc_html__('Items Per Page', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="items_per_page" 
                               name="settings[display][items_per_page]" 
                               value="<?php echo esc_attr($settings['display']['items_per_page']); ?>" 
                               min="1" 
                               max="50">
                        <p class="description">
                            <?php echo esc_html__('Number of FAQs to display per page', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="show_categories"><?php echo esc_html__('Show Categories', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="show_categories" 
                                   name="settings[display][show_categories]" 
                                   value="1" 
                                   <?php checked($settings['display']['show_categories']); ?>>
                            <?php echo esc_html__('Display FAQ categories', 'cobra-ai'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="order_by"><?php echo esc_html__('Order By', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <select id="order_by" name="settings[display][order_by]">
                            <option value="date" <?php selected($settings['display']['order_by'], 'date'); ?>>
                                <?php echo esc_html__('Date', 'cobra-ai'); ?>
                            </option>
                            <option value="title" <?php selected($settings['display']['order_by'], 'title'); ?>>
                                <?php echo esc_html__('Title', 'cobra-ai'); ?>
                            </option>
                            <option value="views" <?php selected($settings['display']['order_by'], 'views'); ?>>
                                <?php echo esc_html__('Most Viewed', 'cobra-ai'); ?>
                            </option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="order"><?php echo esc_html__('Order', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <select id="order" name="settings[display][order]">
                            <option value="DESC" <?php selected($settings['display']['order'], 'DESC'); ?>>
                                <?php echo esc_html__('Descending', 'cobra-ai'); ?>
                            </option>
                            <option value="ASC" <?php selected($settings['display']['order'], 'ASC'); ?>>
                                <?php echo esc_html__('Ascending', 'cobra-ai'); ?>
                            </option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="footer_text"><?php echo esc_html__('Footer Text', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="footer_text" 
                               name="settings[display][footer_text]" 
                               value="<?php echo esc_attr($settings['display']['footer_text']); ?>" 
                               class="regular-text">
                        <p class="description">
                            <?php echo esc_html__('Text displayed at the bottom of the FAQ section', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="footer_link"><?php echo esc_html__('Footer Link', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <input type="url" 
                               id="footer_link" 
                               name="settings[display][footer_link]" 
                               value="<?php echo esc_url($settings['display']['footer_link']); ?>" 
                               class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="load_more_type"><?php echo esc_html__('Load More Type', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <select id="load_more_type" name="settings[display][load_more_type]">
                            <option value="pagination" <?php selected($settings['display']['load_more_type'], 'pagination'); ?>>
                                <?php echo esc_html__('Pagination', 'cobra-ai'); ?>
                            </option>
                            <option value="button" <?php selected($settings['display']['load_more_type'], 'button'); ?>>
                                <?php echo esc_html__('Load More Button', 'cobra-ai'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                <!-- show_meta -->
                <tr>
                    <th scope="row">
                        <label for="show_meta"><?php echo esc_html__('Show Meta', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="show_meta" 
                                   name="settings[display][show_meta]" 
                                   value="1" 
                                   <?php checked($settings['display']['show_meta'] ?? false); ?>>
                            <?php echo esc_html__('Display FAQ meta information', 'cobra-ai'); ?>
                        </label>
                    </td>
                </tr>
                <!-- show_views -->
                <tr>
                    <th scope="row">
                        <label for="show_views"><?php echo esc_html__('Show Views', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="show_views" 
                                   name="settings[display][show_views]" 
                                   value="1" 
                                   <?php checked($settings['display']['show_views'] ?? false); ?>>
                            <?php echo esc_html__('Display FAQ views count', 'cobra-ai'); ?>
                        </label>
                    </td>
                </tr>
                <!-- show_last_updated -->
                <tr>
                    <th scope="row">
                        <label for="show_last_updated"><?php echo esc_html__('Show Last Updated', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="show_last_updated" 
                                   name="settings[display][show_last_updated]" 
                                   value="1" 
                                   <?php checked($settings['display']['show_last_updated'] ?? false); ?>>
                            <?php echo esc_html__('Display last updated date', 'cobra-ai'); ?>
                        </label>
                    </td>
                </tr>
                <!-- show_helpful -->
                <tr>
                    <th scope="row">
                        <label for="show_helpful"><?php echo esc_html__('Show Helpful Buttons', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="show_helpful" 
                                   name="settings[display][show_helpful]" 
                                   value="1" 
                                   <?php checked($settings['display']['show_helpful'] ?? false); ?>>
                            <?php echo esc_html__('Display helpful buttons', 'cobra-ai'); ?>
                        </label>
                    </td>
                </tr>
                <!-- show_share -->
                <tr>
                    <th scope="row">
                        <label for="show_share"><?php echo esc_html__('Show Share Button', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="show_share" 
                                   name="settings[display][show_share]" 
                                   value="1" 
                                   <?php checked($settings['display']['show_share'] ?? false); ?>>
                            <?php echo esc_html__('Display share button', 'cobra-ai'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Search Settings -->
        <div class="cobra-settings-section">
            <h2><?php echo esc_html__('Search Settings', 'cobra-ai'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="enable_search"><?php echo esc_html__('Enable Search', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="enable_search" 
                                   name="settings[search][enable_search]" 
                                   value="1" 
                                   <?php checked($settings['search']['enable_search']); ?>>
                            <?php echo esc_html__('Show search functionality', 'cobra-ai'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="enable_autocomplete"><?php echo esc_html__('Enable Autocomplete', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="enable_autocomplete" 
                                   name="settings[search][enable_autocomplete]" 
                                   value="1" 
                                   <?php checked($settings['search']['enable_autocomplete']); ?>>
                            <?php echo esc_html__('Enable search autocomplete', 'cobra-ai'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="min_chars"><?php echo esc_html__('Minimum Characters', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="min_chars" 
                               name="settings[search][min_chars]" 
                               value="<?php echo esc_attr($settings['search']['min_chars']); ?>" 
                               min="1" 
                               max="10">
                        <p class="description">
                            <?php echo esc_html__('Minimum characters required to trigger search', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="show_category_filter"><?php echo esc_html__('Category Filter', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="show_category_filter" 
                                   name="settings[search][show_category_filter]" 
                                   value="1" 
                                   <?php checked($settings['search']['show_category_filter']); ?>>
                            <?php echo esc_html__('Show category filter in search', 'cobra-ai'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Styling Settings -->
        <div class="cobra-settings-section">
            <h2><?php echo esc_html__('Styling Settings', 'cobra-ai'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="theme"><?php echo esc_html__('Theme', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <select id="theme" name="settings[styling][theme]">
                            <option value="default" <?php selected($settings['styling']['theme'], 'default'); ?>>
                                <?php echo esc_html__('Default', 'cobra-ai'); ?>
                            </option>
                            <option value="minimal" <?php selected($settings['styling']['theme'], 'minimal'); ?>>
                                <?php echo esc_html__('Minimal', 'cobra-ai'); ?>
                            </option>
                            <option value="boxed" <?php selected($settings['styling']['theme'], 'boxed'); ?>>
                                <?php echo esc_html__('Boxed', 'cobra-ai'); ?>
                            </option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="animation"><?php echo esc_html__('Enable Animations', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="animation" 
                                   name="settings[styling][animation]" 
                                   value="1" 
                                   <?php checked($settings['styling']['animation']); ?>>
                            <?php echo esc_html__('Enable animation effects', 'cobra-ai'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="highlight_search"><?php echo esc_html__('Highlight Search Results', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="highlight_search" 
                                   name="settings[styling][highlight_search]" 
                                   value="1" 
                                   <?php checked($settings['styling']['highlight_search']); ?>>
                            <?php echo esc_html__('Highlight search terms in results', 'cobra-ai'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(); ?>
    </form>

    <!-- Shortcode Info -->
    <div class="cobra-settings-section">
        <h2><?php echo esc_html__('Shortcode Usage', 'cobra-ai'); ?></h2>
        <p><?php echo esc_html__('Use the following shortcode to display your FAQs:', 'cobra-ai'); ?></p>
        <code>[cobra_faq]</code>

        <h3><?php echo esc_html__('Optional Parameters', 'cobra-ai'); ?></h3>
        <ul>
            <li><code>category="category-slug"</code> - <?php echo esc_html__('Display FAQs from a specific category', 'cobra-ai'); ?></li>
            <li><code>limit="10"</code> - <?php echo esc_html__('Number of FAQs to display', 'cobra-ai'); ?></li>
            <li><code>orderby="date"</code> - <?php echo esc_html__('Order by: date, title, or views', 'cobra-ai'); ?></li>
            <li><code>order="DESC"</code> - <?php echo esc_html__('Sort order: DESC or ASC', 'cobra-ai'); ?></li>
        </ul>

        <h4><?php echo esc_html__('Example', 'cobra-ai'); ?></h4>
        <code>[cobra_faq category="general" limit="5" orderby="views" order="DESC"]</code>
    </div>
</div>

<style>
.cobra-settings-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.cobra-settings-section h2 {
    margin-top: 0;
    padding-bottom: 12px;
    border-bottom: 1px solid #eee;
}

.form-table th {
    width: 200px;
}

.cobra-settings-section code {
    background: #f0f0f1;
    padding: 3px 5px;
    border-radius: 3px;
}

/* assets/css/faq.css */

/* Container Styles */
.cobra-faq-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}

/* FAQ Title */
.cobra-faq-title {
    font-size: 2rem;
    font-weight: 600;
    margin-bottom: 2rem;
    text-align: center;
    color: #333;
}

/* Search Section */
.cobra-faq-search {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
}

.cobra-faq-search-input {
    flex: 1;
    padding: 0.75rem 1rem;
    border: 2px solid #e0e0e0;
    border-radius: 4px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.cobra-faq-search-input:focus {
    border-color: #007cba;
    outline: none;
    box-shadow: 0 0 0 1px #007cba;
}

.cobra-faq-category-filter {
    padding: 0.75rem 1rem;
    border: 2px solid #e0e0e0;
    border-radius: 4px;
    background-color: #fff;
    min-width: 150px;
}

/* Search Results */
.cobra-faq-search-results {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    margin-top: -1rem;
    margin-bottom: 2rem;
    max-height: 300px;
    overflow-y: auto;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.cobra-faq-result {
    padding: 1rem;
    border-bottom: 1px solid #e0e0e0;
}

.cobra-faq-result:last-child {
    border-bottom: none;
}

.cobra-faq-result h4 {
    margin: 0 0 0.5rem 0;
}

.cobra-faq-result a {
    color: #007cba;
    text-decoration: none;
}

.cobra-faq-result a:hover {
    text-decoration: underline;
}

/* FAQ Items */
.cobra-faq-item {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    margin-bottom: 1rem;
    overflow: hidden;
}

.cobra-faq-question {
    padding: 1.5rem;
    cursor: pointer;
    position: relative;
    user-select: none;
}

.cobra-faq-question h3 {
    margin: 0;
    padding-right: 2rem;
    font-size: 1.1rem;
    color: #333;
}

.cobra-faq-toggle {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    padding: 0.5rem;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.cobra-faq-toggle[aria-expanded="true"] {
    transform: translateY(-50%) rotate(45deg);
}

.cobra-faq-icon {
    display: block;
    width: 20px;
    height: 20px;
    stroke: currentColor;
    stroke-width: 2;
    transition: transform 0.3s ease;
}

/* Categories */
.cobra-faq-categories {
    margin-top: 0.5rem;
    font-size: 0.9rem;
}

.cobra-faq-category {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    background: #f0f0f0;
    border-radius: 999px;
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
    color: #666;
}

/* Answer Section */
.cobra-faq-answer {
    padding: 0 1.5rem 1.5rem;
    color: #555;
    line-height: 1.6;
}

.cobra-faq-answer[hidden] {
    display: none;
}

/* Meta Information */
.cobra-faq-meta {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
    font-size: 0.9rem;
    color: #666;
}

.cobra-faq-views,
.cobra-faq-updated {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Helpful Buttons */
.cobra-faq-helpful {
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid #e0e0e0;
}

.cobra-faq-helpful p {
    margin: 0 0 0.5rem 0;
    font-size: 0.9rem;
    color: #666;
}

.cobra-faq-helpful-buttons {
    display: flex;
    gap: 1rem;
}

.cobra-faq-helpful-yes,
.cobra-faq-helpful-no {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    background: #fff;
    cursor: pointer;
    transition: all 0.3s ease;
}

.cobra-faq-helpful-yes:hover {
    background: #e8f5e9;
    border-color: #4caf50;
    color: #2e7d32;
}

.cobra-faq-helpful-no:hover {
    background: #fbe9e7;
    border-color: #ff5722;
    color: #d84315;
}

/* Share Button */
.cobra-faq-share {
    margin-top: 1rem;
}

.cobra-faq-share-button {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    background: #fff;
    cursor: pointer;
    transition: all 0.3s ease;
}

.cobra-faq-share-button:hover {
    background: #e3f2fd;
    border-color: #2196f3;
    color: #1976d2;
}

/* Pagination */
.cobra-faq-pagination {
    margin-top: 2rem;
    display: flex;
    justify-content: center;
    gap: 0.5rem;
}

.cobra-faq-pagination .page-numbers {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    height: 2rem;
    padding: 0 0.5rem;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    text-decoration: none;
    color: #333;
    transition: all 0.3s ease;
}

.cobra-faq-pagination .page-numbers.current {
    background: #007cba;
    border-color: #007cba;
    color: #fff;
}

.cobra-faq-pagination .page-numbers:hover:not(.current) {
    background: #f0f0f0;
}

/* Load More Button */
.cobra-faq-load-more {
    display: block;
    width: 200px;
    margin: 2rem auto 0;
    padding: 0.75rem 1.5rem;
    background: #007cba;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-align: center;
    transition: background-color 0.3s ease;
}

.cobra-faq-load-more:hover {
    background: #006ba1;
}

.cobra-faq-load-more.loading {
    position: relative;
    color: transparent;
}

.cobra-faq-load-more.loading::after {
    content: '';
    position: absolute;
    left: 50%;
    top: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

/* Footer */
.cobra-faq-footer {
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid #e0e0e0;
    text-align: center;
}

.cobra-faq-footer p {
    margin: 0;
    color: #666;
}

.cobra-faq-footer a {
    color: #007cba;
    text-decoration: none;
}

.cobra-faq-footer a:hover {
    text-decoration: underline;
}

/* Theme Variations */
.cobra-faq-container[data-theme="minimal"] .cobra-faq-item {
    border: none;
    border-bottom: 1px solid #e0e0e0;
    border-radius: 0;
}

.cobra-faq-container[data-theme="boxed"] .cobra-faq-item {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Animations */
@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
    .cobra-faq-search {
        flex-direction: column;
    }

    .cobra-faq-category-filter {
        width: 100%;
    }

    .cobra-faq-meta {
        flex-direction: column;
        gap: 0.5rem;
    }

    .cobra-faq-helpful-buttons {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .cobra-faq-question h3 {
        font-size: 1rem;
    }

    .cobra-faq-toggle {
        padding: 0.25rem;
    }

    .cobra-faq-icon {
        width: 16px;
        height: 16px;
    }
}
</style>