<?php

namespace CobraAI\Features\HelloWorld;

use CobraAI\FeatureBase;

class Feature extends FeatureBase
{
    protected $feature_id = 'hello-world';
    protected $name = 'Hello World';
    protected $description = 'A sample feature demonstrating core functionality';
    protected $version = '1.0.0';
    protected $author = 'Your Name';
    protected $has_settings = true;
    protected $has_admin = true;
    public function __construct()
    {

        parent::__construct();
    }
    /**
     * Setup feature
     */
    protected function setup(): void
    {
        global $wpdb;

        // Define feature tables
        $this->tables = [
            'hello' => [
                'name' => $wpdb->prefix . 'cobra_hello_table',
                'schema' => [
                    'id' => 'bigint(20) NOT NULL AUTO_INCREMENT',
                    'message' => 'varchar(255) NOT NULL',
                    'choice' => "enum('option1','option2','option3') NOT NULL DEFAULT 'option1'",
                    'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'PRIMARY KEY' => '(id)'
                ]
            ]
        ];

        // Register shortcode
        add_shortcode('helloworld', [$this, 'render_shortcode']);

        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'register_endpoints']);
    }

    /**
     * Render shortcode
     */
    public function render_shortcode($atts = [], $content = null): string
    {
        $settings = $this->get_settings();

        ob_start();
        $this->load_template('shortcode', [
            'message' => $settings['hello'] ?? 'Hello World!',
            'choice' => $settings['choice'] ?? 'option1'
        ]);
        return ob_get_clean();
    }

    /**
     * Register REST API endpoints
     */
    public function register_endpoints(): void
    {
        register_rest_route('cobra-ai/v1', '/hello-world', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_api_request'],
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * Handle API request
     */
    public function handle_api_request(\WP_REST_Request $request): \WP_REST_Response
    {
        $settings = $this->get_settings();

        return new \WP_REST_Response([
            'message' => $settings['hello'] ?? 'Hello World!',
            'choice' => $settings['choice'] ?? 'option1'
        ]);
    }

    /**
     * Default settings
     */
    protected function get_feature_default_options(): array
    {
        return [
            'hello' => 'Hello World!',
            'choice' => 'option1'
        ];
    }

    protected function validate_settings(array $settings): array
    {
        // Add validation logic here
        return $settings;
    }

    /**
     * Render settings page
     */
    public function render_settings(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Save settings
        if (isset($_POST['submit'])) {
            check_admin_referer('hello_world_settings');

            $settings = [
                'hello' => sanitize_text_field($_POST['hello'] ?? ''),
                'choice' => sanitize_text_field($_POST['choice'] ?? 'option1')
            ];

            update_option('cobra_ai_' . $this->feature_id . '_options', $settings);
            echo '<div class="notice notice-success"><p>' . __('Settings saved.', 'cobra-ai') . '</p></div>';
        }

        // Get current settings
        $settings = $this->get_settings();

        // Render settings form
?>
        <div class="wrap">
            <h1><?php echo esc_html($this->name . ' ' . __('Settings', 'cobra-ai')); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('hello_world_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="hello"><?php _e('Hello Message', 'cobra-ai'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                id="hello"
                                name="hello"
                                value="<?php echo esc_attr($settings['hello'] ?? ''); ?>"
                                class="regular-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Choose Option', 'cobra-ai'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio"
                                        name="choice"
                                        value="option1"
                                        <?php checked($settings['choice'] ?? 'option1', 'option1'); ?>>
                                    <?php _e('Option 1', 'cobra-ai'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="radio"
                                        name="choice"
                                        value="option2"
                                        <?php checked($settings['choice'] ?? 'option1', 'option2'); ?>>
                                    <?php _e('Option 2', 'cobra-ai'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="radio"
                                        name="choice"
                                        value="option3"
                                        <?php checked($settings['choice'] ?? 'option1', 'option3'); ?>>
                                    <?php _e('Option 3', 'cobra-ai'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
<?php
    }
}
