<?php
/**
 * NLWeb for WordPress - Chat Shortcode HTML
 *
 * @since      1.0.0
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

// Get shortcode attributes
$chat_title = $atts['title'];
$chat_placeholder = $atts['placeholder'];
$chat_width = $atts['width'];
$chat_height = $atts['height'];
$chat_color = get_option('nlwp_chat_color', '#0073aa');
?>

<!-- NLWeb Chat Shortcode -->
<div class="nlwp-shortcode-chat" style="--nlwp-primary-color: <?php echo esc_attr($chat_color); ?>; width: <?php echo esc_attr($chat_width); ?>; height: <?php echo esc_attr($chat_height); ?>;">
    
    <!-- Chat Header -->
    <div class="nlwp-chat-header">
        <div class="nlwp-chat-header-title"><?php echo esc_html($chat_title); ?></div>
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

<script>
    // Initialize the shortcode chat widget
    jQuery(document).ready(function($) {
        var $input = $('.nlwp-shortcode-chat .nlwp-chat-input');
        var $submit = $('.nlwp-shortcode-chat .nlwp-chat-submit');
        var $messages = $('.nlwp-shortcode-chat .nlwp-chat-messages');
        
        // Add welcome message if not already shown
        if (!sessionStorage.getItem('nlwp_welcomed_shortcode')) {
            $messages.append('<div class="nlwp-message nlwp-message-bot">Hello! How can I help you today?</div><div class="nlwp-clearfix"></div>');
            sessionStorage.setItem('nlwp_welcomed_shortcode', 'true');
        }
        
        // Enable/disable submit button based on input
        $input.on('input', function() {
            $submit.prop('disabled', $(this).val().trim() === '');
        });
        
        // Handle form submission
        function sendMessage() {
            var message = $input.val().trim();
            
            if (message === '') {
                return;
            }
            
            // Add user message
            $messages.append('<div class="nlwp-message nlwp-message-user">' + message + '</div><div class="nlwp-clearfix"></div>');
            
            // Force scrolling to bottom with a slight delay
            setTimeout(function() {
                $messages.scrollTop($messages[0].scrollHeight + 1000);
            }, 50);
            
            // Clear input
            $input.val('').focus();
            $submit.prop('disabled', true);
            
            // Show thinking indicator
            $messages.append('<div class="nlwp-thinking"><div class="nlwp-dot"></div><div class="nlwp-dot"></div><div class="nlwp-dot"></div></div><div class="nlwp-clearfix"></div>');
            
            // Force scrolling to bottom with a slight delay
            setTimeout(function() {
                $messages.scrollTop($messages[0].scrollHeight + 1000);
            }, 50);
            
            // Call the API
            $.ajax({
                url: nlwpData.apiUrl + 'ask',
                method: 'POST',
                data: {
                    query: message,
                    site: nlwpData.siteName,
                    streaming: false
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', nlwpData.nonce);
                },
                success: function(response) {
                    // Remove thinking indicator
                    $('.nlwp-thinking, .nlwp-thinking + .nlwp-clearfix').remove();
                    
                    // Process response
                    var botMessage = '';
                    
                    if (response.error) {
                        botMessage = 'Sorry, I encountered an error: ' + response.error;
                    } else if (response.results && response.results.length > 0) {
                        // Get top result
                        var result = response.results[0];
                        
                        // Clean up any shortcode tags that might remain in the description
                        var cleanDescription = '';
                        if (result.description) {
                            cleanDescription = result.description.replace(/\[\/?[a-zA-Z0-9_\-]+( [^\]]+)?\]/g, '');
                        }
                        
                        botMessage = cleanDescription || ""; // Add fallback for empty description
                        
                        // Add source link
                        botMessage += '\n\nSource: ' + result.url;
                    } else {
                        // Log the response for debugging
                        console.log("API Response with no results:", response);
                        botMessage = "I'm sorry, I couldn't find any specific information about \"" + response.query + "\" in this website's content. Feel free to ask another question or try rephrasing your query.";
                        
                        // Add default information about WordPress if the query is about WordPress
                        if (response.query && response.query.toLowerCase().includes("wordpress")) {
                            botMessage += "\n\nWordPress is a popular open-source content management system used to create websites, blogs, and applications. It's known for its flexibility, ease of use, and large community of developers and users.";
                        }
                    }
                    
                    // Add bot message - clean up any remaining shortcode tags
                    var cleanBotMessage = botMessage.replace(/\[\/?[a-zA-Z0-9_\-]+( [^\]]+)?\]/g, '');
                    $messages.append('<div class="nlwp-message nlwp-message-bot">' + cleanBotMessage.replace(/\n/g, '<br>') + '</div><div class="nlwp-clearfix"></div>');
                    
                    // Force scrolling to bottom with a slight delay
                    setTimeout(function() {
                        $messages.scrollTop($messages[0].scrollHeight + 1000);
                    }, 100);
                },
                error: function(xhr, status, error) {
                    // Remove thinking indicator
                    $('.nlwp-thinking, .nlwp-thinking + .nlwp-clearfix').remove();
                    
                    // Show error message
                    $messages.append('<div class="nlwp-message nlwp-message-bot">Sorry, I encountered an error: ' + error + '</div><div class="nlwp-clearfix"></div>');
                    
                    // Force scrolling to bottom with a slight delay
                    setTimeout(function() {
                        $messages.scrollTop($messages[0].scrollHeight + 1000);
                    }, 100);
                }
            });
        }
        
        // Submit on button click
        $submit.on('click', function(e) {
            e.preventDefault();
            sendMessage();
        });
        
        // Submit on Enter key
        $input.on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                sendMessage();
            }
        });
    });
</script>