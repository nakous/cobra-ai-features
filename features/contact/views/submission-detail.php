<?php
/**
 * Contact Form Submission Detail View
 * 
 * @package CobraAI
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Format the date
$created_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission->created_at));

// Format status label
$status_labels = [
    'unread' => '<span class="status-unread">' . __('Unread', 'cobra-ai') . '</span>',
    'read'   => '<span class="status-read">' . __('Read', 'cobra-ai') . '</span>',
    'replied' => '<span class="status-replied">' . __('Replied', 'cobra-ai') . '</span>'
];
$status_label = isset($status_labels[$submission->status]) ? $status_labels[$submission->status] : $submission->status;

// Check if response exists
$has_response = !empty($submission->response);
$response_date = $has_response ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission->response_date)) : '';
?>

<div class="wrap cobra-contact-submission-detail">
    <h1 class="wp-heading-inline"><?php echo esc_html__('Contact Form Submission', 'cobra-ai'); ?></h1>
    
    <a href="<?php echo esc_url(admin_url('admin.php?page=cobra-contact-submissions')); ?>" class="page-title-action">
        <?php echo esc_html__('← Back to Submissions', 'cobra-ai'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-2">
            <div id="post-body-content">
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php echo esc_html__('Message Content', 'cobra-ai'); ?></h2>
                    </div>
                    <div class="inside">
                        <div class="submission-info">
                            <div class="submission-subject">
                                <h3><?php echo esc_html($submission->subject); ?></h3>
                            </div>
                            
                            <div class="submission-meta">
                                <p>
                                    <strong><?php echo esc_html__('From:', 'cobra-ai'); ?></strong> 
                                    <?php echo esc_html($submission->name); ?> 
                                    &lt;<?php echo esc_html($submission->email); ?>&gt;
                                </p>
                                <p>
                                    <strong><?php echo esc_html__('Date:', 'cobra-ai'); ?></strong> 
                                    <?php echo esc_html($created_date); ?>
                                </p>
                                <p>
                                    <strong><?php echo esc_html__('Status:', 'cobra-ai'); ?></strong> 
                                    <?php echo $status_label; ?>
                                </p>
                                <?php if ($submission->user_id): ?>
                                    <p>
                                        <strong><?php echo esc_html__('User:', 'cobra-ai'); ?></strong> 
                                        <?php
                                        $user = get_userdata($submission->user_id);
                                        if ($user) {
                                            echo '<a href="' . esc_url(admin_url('user-edit.php?user_id=' . $submission->user_id)) . '">' . 
                                                esc_html($user->display_name) . '</a>';
                                        } else {
                                            echo esc_html__('Unknown User', 'cobra-ai');
                                        }
                                        ?>
                                    </p>
                                <?php endif; ?>
                                <p>
                                    <strong><?php echo esc_html__('IP Address:', 'cobra-ai'); ?></strong> 
                                    <?php echo esc_html($submission->user_ip); ?>
                                </p>
                            </div>
                            
                            <div class="submission-content">
                                <h4><?php echo esc_html__('Message:', 'cobra-ai'); ?></h4>
                                <div class="submission-message">
                                    <?php echo nl2br(esc_html($submission->message)); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($has_response): ?>
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php echo esc_html__('Your Response', 'cobra-ai'); ?></h2>
                    </div>
                    <div class="inside">
                        <div class="response-info">
                            <p>
                                <strong><?php echo esc_html__('Replied on:', 'cobra-ai'); ?></strong> 
                                <?php echo esc_html($response_date); ?>
                            </p>
                            <div class="response-content">
                                <?php echo wpautop(esc_html($submission->response)); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="postbox" id="reply-box">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <?php echo $has_response 
                                ? esc_html__('Send Another Response', 'cobra-ai') 
                                : esc_html__('Send Response', 'cobra-ai'); ?>
                        </h2>
                    </div>
                    <div class="inside">
                        <div id="response-notice" class="notice" style="display: none;"></div>
                        
                        <div class="response-form">
                            <textarea id="response-content" rows="10" class="widefat"></textarea>
                            <p class="description">
                                <?php echo esc_html__('Enter your response here. This will be sent to the user via email.', 'cobra-ai'); ?>
                            </p>
                            
                            <div class="submit-area">
                                <button type="button" id="send-response" class="button button-primary" data-id="<?php echo esc_attr($submission->id); ?>">
                                    <?php echo esc_html__('Send Response', 'cobra-ai'); ?>
                                </button>
                                <span class="spinner" style="float: none; margin-top: 0;"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="postbox-container-1" class="postbox-container">
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php echo esc_html__('Actions', 'cobra-ai'); ?></h2>
                    </div>
                    <div class="inside">
                        <div class="action-buttons">
                            <?php if ($submission->status === 'unread'): ?>
                                <button type="button" id="mark-as-read" class="button" data-id="<?php echo esc_attr($submission->id); ?>">
                                    <?php echo esc_html__('Mark as Read', 'cobra-ai'); ?>
                                </button>
                            <?php endif; ?>
                            
                            <a href="mailto:<?php echo esc_attr($submission->email); ?>?subject=<?php echo esc_attr('Re: ' . $submission->subject); ?>" class="button">
                                <?php echo esc_html__('Reply via Email', 'cobra-ai'); ?>
                            </a>
                            
                            <button type="button" id="delete-submission" class="button button-link-delete" data-id="<?php echo esc_attr($submission->id); ?>">
                                <?php echo esc_html__('Delete Submission', 'cobra-ai'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php echo esc_html__('Email Template Variables', 'cobra-ai'); ?></h2>
                    </div>
                    <div class="inside">
                        <p class="description">
                            <?php echo esc_html__('You can use these variables in your response:', 'cobra-ai'); ?>
                        </p>
                        <ul class="template-variables">
                            <li><code>{{name}}</code> - <?php echo esc_html__('Sender name', 'cobra-ai'); ?></li>
                            <li><code>{{email}}</code> - <?php echo esc_html__('Sender email', 'cobra-ai'); ?></li>
                            <li><code>{{subject}}</code> - <?php echo esc_html__('Message subject', 'cobra-ai'); ?></li>
                            <li><code>{{message}}</code> - <?php echo esc_html__('Original message', 'cobra-ai'); ?></li>
                            <li><code>{{response}}</code> - <?php echo esc_html__('Your response', 'cobra-ai'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.submission-info,
.response-info {
    margin-bottom: 20px;
}
.submission-meta {
    margin: 15px 0;
}
.submission-content,
.response-content {
    background: #f9f9f9;
    padding: 15px;
    border: 1px solid #e5e5e5;
    border-radius: 3px;
    margin-top: 10px;
}
.submission-message {
    white-space: pre-line;
}
.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.status-unread {
    color: #d63638;
    font-weight: bold;
}
.status-read {
    color: #3582c4;
}
.status-replied {
    color: #00a32a;
}
.response-form {
    margin-top: 10px;
}
.submit-area {
    margin-top: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.template-variables {
    margin-top: 5px;
}
.template-variables code {
    background: #f0f0f1;
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 12px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle sending response
    $('#send-response').on('click', function() {
        var $button = $(this);
        var $spinner = $button.next('.spinner');
        var $notice = $('#response-notice');
        var submissionId = $button.data('id');
        var response = $('#response-content').val();
        
        if (!response.trim()) {
            $notice.removeClass('notice-success notice-error')
                   .addClass('notice-error')
                   .html('<p><?php echo esc_js(__('Please enter a response.', 'cobra-ai')); ?></p>')
                   .show();
            return;
        }
        
        // Confirm before sending
        if (!confirm("<?php echo esc_js(__('Are you sure you want to send this response?', 'cobra-ai')); ?>")) {
            return;
        }
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $notice.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cobra_contact_send_reply',
                submission_id: submissionId,
                response: response,
                nonce: '<?php echo wp_create_nonce('cobra_contact_admin'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $notice.removeClass('notice-error')
                           .addClass('notice-success')
                           .html('<p>' + response.data.message + '</p>')
                           .show();
                    
                    // Reload page after delay to show updated status
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $notice.removeClass('notice-success')
                           .addClass('notice-error')
                           .html('<p>' + response.data.message + '</p>')
                           .show();
                }
            },
            error: function() {
                $notice.removeClass('notice-success')
                       .addClass('notice-error')
                       .html('<p><?php echo esc_js(__('An error occurred. Please try again.', 'cobra-ai')); ?></p>')
                       .show();
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
    
    // Handle mark as read
    $('#mark-as-read').on('click', function() {
        var $button = $(this);
        var submissionId = $button.data('id');
        
        $button.prop('disabled', true)
               .text('<?php echo esc_js(__('Marking...', 'cobra-ai')); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cobra_contact_mark_read',
                submission_id: submissionId,
                nonce: '<?php echo wp_create_nonce('cobra_contact_admin'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $button.fadeOut(400, function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data.message);
                    $button.prop('disabled', false)
                           .text('<?php echo esc_js(__('Mark as Read', 'cobra-ai')); ?>');
                }
            },
            error: function() {
                alert("<?php echo esc_js(__('An error occurred. Please try again.', 'cobra-ai')); ?>");
                $button.prop('disabled', false)
                       .text('<?php echo esc_js(__('Mark as Read', 'cobra-ai')); ?>');
            }
        });
    });
    
    // Handle delete submission
    $('#delete-submission').on('click', function() {
        var $button = $(this);
        var submissionId = $button.data('id');
        
        if (!confirm("<?php echo esc_js(__('Are you sure you want to delete this submission? This action cannot be undone.', 'cobra-ai')); ?>")) {
            return;
        }
        
        $button.prop('disabled', true)
               .text('<?php echo esc_js(__('Deleting...', 'cobra-ai')); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cobra_contact_delete_message',
                submission_id: submissionId,
                nonce: '<?php echo wp_create_nonce('cobra_contact_admin'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Redirect back to list
                    window.location.href = '<?php echo esc_js(admin_url('admin.php?page=cobra-contact-submissions')); ?>&deleted=1';
                } else {
                    alert(response.data.message);
                    $button.prop('disabled', false)
                           .text('<?php echo esc_js(__('Delete Submission', 'cobra-ai')); ?>');
                }
            },
            error: function() {
                alert("<?php echo esc_js(__('An error occurred. Please try again.', 'cobra-ai')); ?>");
                $button.prop('disabled', false)
                       .text('<?php echo esc_js(__('Delete Submission', 'cobra-ai')); ?>');
            }
        });
    });
});
</script>