<?php
// views/public/single-plan.php

if (!defined('ABSPATH')) exit;

get_header();

// Debug output
// if (defined('WP_DEBUG') && WP_DEBUG) {
//     global $wp_query, $post;
//     error_log('Current query: ' . print_r($wp_query->query, true));
//     error_log('Current post: ' . print_r($post, true));
// }

while (have_posts()) : 
    the_post();
    
    // Get plan data
    $plan_data = get_post_meta(get_the_ID(), '_stripe_plan_data', true) ?: [];
    ?>
    <div class="plan-single-wrapper">
        <div class="container">
            <article id="post-<?php the_ID(); ?>" <?php post_class('stripe-plan'); ?>>
                <header class="entry-header">
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                    
                    <?php if (!empty($plan_data['price'])): ?>
                        <div class="plan-price">
                            <?php 
                            echo esc_html(
                                sprintf(
                                    '%s %s/%s',
                                    $plan_data['currency'],
                                    number_format($plan_data['price'], 2),
                                    $plan_data['billing_interval'] ?? 'month'
                                )
                            ); 
                            ?>
                        </div>
                    <?php endif; ?>
                </header>

                <div class="entry-content">
                    <?php the_content(); ?>
                    
                    <?php if (!empty($plan_data['features'])): ?>
                        <div class="plan-features">
                            <h3><?php _e('Features', 'cobra-ai'); ?></h3>
                            <ul>
                                <?php foreach ($plan_data['features'] as $feature): ?>
                                    <li><?php echo esc_html($feature); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($plan_data['trial_days'])): ?>
                        <div class="plan-trial">
                            <?php 
                            printf(
                                __('%d day free trial', 'cobra-ai'),
                                $plan_data['trial_days']
                            ); 
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        </div>
    </div>
<?php 
endwhile;

get_footer();