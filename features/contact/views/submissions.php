<?php
/**
 * Contact Form Submissions Admin View
 * 
 * @package CobraAI
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get unread count for highlight
global $wpdb;
$table_name = $this->tables['submissions']['name'];
$unread_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'unread'");
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html__('Contact Form Submissions', 'cobra-ai'); ?></h1>
    
    <?php if ($unread_count > 0): ?>
        <span class="awaiting-mod count-<?php echo esc_attr($unread_count); ?>">
            <span class="pending-count"><?php echo esc_html($unread_count); ?></span>
        </span>
    <?php endif; ?>
    
    <hr class="wp-header-end">
    
    <form id="submissions-filter" method="post">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
        <?php
        // Display search box
        $submissions_table->search_box(__('Search', 'cobra-ai'), 'search-submissions');
        
        // Display the table
        $submissions_table->display();
        ?>
    </form>
</div>

<style>
.column-status .status-unread {
    color: #d63638;
    font-weight: bold;
}
.column-status .status-read {
    color: #3582c4;
}
.column-status .status-replied {
    color: #00a32a;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle delete action
    $('.delete-submission').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm("<?php echo esc_js(__('Are you sure you want to delete this submission?', 'cobra-ai')); ?>")) {
            return;
        }
        
        var $button = $(this);
        var submissionId = $button.data('id');
        var nonce = $button.data('nonce');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cobra_contact_delete_message',
                submission_id: submissionId,
                nonce: nonce
            },
            beforeSend: function() {
                $button.html("<?php echo esc_js(__('Deleting...', 'cobra-ai')); ?>");
            },
            success: function(response) {
                if (response.success) {
                    // Remove row from table
                    $button.closest('tr').fadeOut(400, function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data.message);
                    $button.html("<?php echo esc_js(__('Delete', 'cobra-ai')); ?>");
                }
            },
            error: function() {
                alert("<?php echo esc_js(__('An error occurred. Please try again.', 'cobra-ai')); ?>");
                $button.html("<?php echo esc_js(__('Delete', 'cobra-ai')); ?>");
            }
        });
    });
});
</script>