<?php
/**
 * NLWeb for WordPress - Chat Widget HTML
 *
 * @since      1.0.0
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

// Get chat widget settings
$chat_title = get_option('nlwp_chat_title', 'Ask me anything');
$chat_placeholder = get_option('nlwp_chat_placeholder', 'Type your question...');
$chat_position = get_option('nlwp_chat_position', 'bottom-right');
$chat_color = get_option('nlwp_chat_color', '#0073aa');
?>

<!-- NLWeb Chat Widget -->
<div class="nlwp-chat-widget nlwp-position-<?php echo esc_attr($chat_position); ?>" style="--nlwp-primary-color: <?php echo esc_attr($chat_color); ?>;">
    
    <!-- Chat Button -->
    <div class="nlwp-chat-button">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path d="M12 1c-6.627 0-12 4.364-12 9.749 0 3.131 1.817 5.917 4.64 7.7.868 2.167-1.083 4.008-3.142 4.503 2.271.195 6.311-.121 9.374-2.498 7.095.538 13.128-3.997 13.128-9.705 0-5.385-5.373-9.749-12-9.749z"/>
        </svg>
    </div>
    
    <!-- Chat Window -->
    <div class="nlwp-chat-window">
        <!-- Chat Header -->
        <div class="nlwp-chat-header">
            <div class="nlwp-chat-header-title"><?php echo esc_html($chat_title); ?></div>
            <div class="nlwp-chat-close">×</div>
        </div>
        
        <!-- Chat Messages -->
        <div class="nlwp-chat-messages"></div>
        
        <!-- Chat Input -->
        <div class="nlwp-chat-input-container">
            <input type="text" class="nlwp-chat-input" placeholder="<?php echo esc_attr($chat_placeholder); ?>">
            <button class="nlwp-chat-submit" disabled>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                </svg>
            </button>
        </div>
    </div>
</div>