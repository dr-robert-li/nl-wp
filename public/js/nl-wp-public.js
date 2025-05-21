/**
 * NLWeb for WordPress - Frontend JavaScript
 */
(function($) {
    'use strict';

    /**
     * Chat Widget Handler
     */
    var NLWPChat = {
        
        /**
         * Initialize the chat widget
         */
        init: function() {
            // Store elements
            this.chatButton = $('.nlwp-chat-button');
            this.chatWindow = $('.nlwp-chat-window');
            this.chatMessages = $('.nlwp-chat-messages');
            this.chatInput = $('.nlwp-chat-input');
            this.chatSubmit = $('.nlwp-chat-submit');
            this.chatClose = $('.nlwp-chat-close');
            
            // Set primary color from settings
            this.setPrimaryColor();
            
            // Bind events
            this.bindEvents();
            
            // Add welcome message if this is the first time
            if (!sessionStorage.getItem('nlwp_welcomed')) {
                this.addMessage('Hello! How can I help you today?', 'bot');
                sessionStorage.setItem('nlwp_welcomed', 'true');
            }
        },
        
        /**
         * Set the primary color from settings
         */
        setPrimaryColor: function() {
            var primaryColor = nlwpData.chatColor || '#0073aa';
            document.documentElement.style.setProperty('--nlwp-primary-color', primaryColor);
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Toggle chat window
            this.chatButton.on('click', function() {
                self.toggleChatWindow();
            });
            
            // Close chat window
            this.chatClose.on('click', function(e) {
                e.preventDefault();
                self.closeChatWindow();
            });
            
            // Submit message on button click
            this.chatSubmit.on('click', function(e) {
                e.preventDefault();
                self.sendMessage();
            });
            
            // Submit message on Enter key
            this.chatInput.on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    self.sendMessage();
                }
            });
            
            // Enable/disable submit button based on input
            this.chatInput.on('input', function() {
                self.chatSubmit.prop('disabled', $(this).val().trim() === '');
            });
        },
        
        /**
         * Toggle the chat window visibility
         */
        toggleChatWindow: function() {
            this.chatButton.toggleClass('active');
            this.chatWindow.toggleClass('active');
            
            if (this.chatWindow.hasClass('active')) {
                this.chatInput.focus();
            }
        },
        
        /**
         * Close the chat window
         */
        closeChatWindow: function() {
            this.chatButton.removeClass('active');
            this.chatWindow.removeClass('active');
        },
        
        /**
         * Send a message to the API
         */
        sendMessage: function() {
            var message = this.chatInput.val().trim();
            
            if (message === '') {
                return;
            }
            
            // Add user message to chat
            this.addMessage(message, 'user');
            
            // Clear input
            this.chatInput.val('').focus();
            this.chatSubmit.prop('disabled', true);
            
            // Show thinking indicator
            this.showThinking();
            
            // Get conversation history
            var history = this.getConversationHistory();
            
            // Send to API
            this.callAPI(message, history);
        },
        
        /**
         * Add a message to the chat
         */
        addMessage: function(text, type) {
            var messageClass = 'nlwp-message nlwp-message-' + type;
            var message = $('<div class="' + messageClass + '"></div>').text(text);
            this.chatMessages.append(message).append('<div class="nlwp-clearfix"></div>');
            
            // Force scrolling to the bottom
            setTimeout(() => {
                this.scrollToBottom();
            }, 100);
            
            // Store message in history
            if (type === 'user' || type === 'bot') {
                this.storeMessage(text, type);
            }
        },
        
        /**
         * Show thinking indicator
         */
        showThinking: function() {
            var thinking = $('<div class="nlwp-thinking"><div class="nlwp-dot"></div><div class="nlwp-dot"></div><div class="nlwp-dot"></div></div>');
            this.chatMessages.append(thinking).append('<div class="nlwp-clearfix"></div>');
            this.scrollToBottom();
        },
        
        /**
         * Hide thinking indicator
         */
        hideThinking: function() {
            $('.nlwp-thinking').remove();
            $('.nlwp-clearfix:last').remove();
        },
        
        /**
         * Store message in conversation history
         */
        storeMessage: function(text, type) {
            var history = JSON.parse(sessionStorage.getItem('nlwp_history') || '[]');
            history.push({
                text: text,
                type: type,
                timestamp: new Date().getTime()
            });
            
            // Limit history to 20 messages
            if (history.length > 20) {
                history = history.slice(history.length - 20);
            }
            
            sessionStorage.setItem('nlwp_history', JSON.stringify(history));
        },
        
        /**
         * Get conversation history
         */
        getConversationHistory: function() {
            var history = JSON.parse(sessionStorage.getItem('nlwp_history') || '[]');
            var prevQueries = [];
            
            // Get previous user queries
            for (var i = 0; i < history.length; i++) {
                if (history[i].type === 'user') {
                    prevQueries.push(history[i].text);
                }
            }
            
            return prevQueries.join(',');
        },
        
        /**
         * Scroll chat messages to bottom
         */
        scrollToBottom: function() {
            if (this.chatMessages && this.chatMessages[0]) {
                this.chatMessages.scrollTop(this.chatMessages[0].scrollHeight + 1000); // Add extra to ensure it scrolls to the bottom
            }
        },
        
        /**
         * Call the NLWeb API
         */
        callAPI: function(query, prevQueries) {
            var self = this;
            var apiUrl = nlwpData.apiUrl + 'ask';
            
            // Build request data
            var data = {
                query: query,
                site: nlwpData.siteName,
                streaming: false
            };
            
            // Add previous queries if available
            if (prevQueries && prevQueries.length > 0) {
                data.prev = prevQueries;
            }
            
            // Make API request
            $.ajax({
                url: apiUrl,
                method: 'POST',
                data: data,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', nlwpData.nonce);
                },
                success: function(response) {
                    self.hideThinking();
                    
                    // Process the response
                    self.processResponse(response);
                },
                error: function(xhr, status, error) {
                    self.hideThinking();
                    
                    // Show error message
                    self.addMessage('Sorry, I encountered an error: ' + error, 'bot');
                    console.error('NLWP API Error:', error);
                }
            });
        },
        
        /**
         * Process the API response
         */
        processResponse: function(response) {
            // Check for errors
            if (response.error) {
                this.addMessage('Sorry, I encountered an error: ' + response.error, 'bot');
                return;
            }
            
            var message = '';
            
            // If there are results, generate a response based on mode
            if (response.results && response.results.length > 0) {
                // Clean any shortcode content from results
                var cleanResults = response.results.map(function(result) {
                    if (result.description) {
                        result.description = result.description.replace(/\[\/?[a-zA-Z0-9_\-]+( [^\]]+)?\]/g, '');
                    }
                    return result;
                });
                
                // Check if there's a chatbot instruction
                if (response.chatbot_instructions) {
                    message = this.generateResponseFromResults(cleanResults);
                } else {
                    // Default response when no instructions
                    message = this.formatResultsAsMessage(cleanResults);
                }
            } else {
                // No results found
                message = "I'm sorry, I couldn't find any specific information about \"" + response.query + "\" in this website's content. Feel free to ask another question or try rephrasing your query.";
                
                // Add default information about WordPress if the query is about WordPress
                if (response.query && response.query.toLowerCase().includes("wordpress")) {
                    message += "\n\nWordPress is a popular open-source content management system used to create websites, blogs, and applications. It's known for its flexibility, ease of use, and large community of developers and users.";
                }
                
                // Log for debugging
                console.log("API Response with no results:", response);
            }
            
            // Remove any remaining shortcode tags
            message = message.replace(/\[\/?[a-zA-Z0-9_\-]+( [^\]]+)?\]/g, '');
            
            // Add the message to the chat
            this.addMessage(message, 'bot');
        },
        
        /**
         * Generate a response from results based on chatbot instructions
         */
        generateResponseFromResults: function(results) {
            // For simplicity, we'll just format the top results
            // In a real implementation, you might use NLP to generate a more natural response
            
            if (results.length === 1) {
                var result = results[0];
                return this.formatSingleResult(result);
            } else {
                return this.formatMultipleResults(results);
            }
        },
        
        /**
         * Format a single result as a natural language response
         */
        formatSingleResult: function(result) {
            var schemaType = result.schema_object['@type'] || 'Content';
            var response = '';
            
            // Different formatting based on schema type
            switch(schemaType) {
                case 'Article':
                    response = result.description;
                    response += '\n\nYou can read more at: ' + result.url;
                    break;
                    
                case 'Product':
                    response = 'I found this product: ' + result.name;
                    if (result.schema_object.description) {
                        response += '\n\n' + result.schema_object.description;
                    }
                    response += '\n\nYou can view it at: ' + result.url;
                    break;
                    
                default:
                    response = result.description;
                    response += '\n\nSource: ' + result.url;
            }
            
            return response;
        },
        
        /**
         * Format multiple results as a natural language response
         */
        formatMultipleResults: function(results) {
            var response = 'I found several relevant pieces of information:\n\n';
            
            // Limit to top 3 results
            var topResults = results.slice(0, 3);
            
            for (var i = 0; i < topResults.length; i++) {
                var result = topResults[i];
                response += (i + 1) + '. ' + result.name + '\n';
                response += result.description + '\n';
                response += 'Source: ' + result.url + '\n\n';
            }
            
            return response;
        },
        
        /**
         * Format results as a simple message
         */
        formatResultsAsMessage: function(results) {
            var response = 'Here are the top matches for your query:\n\n';
            
            // Limit to top 3 results
            var topResults = results.slice(0, 3);
            
            for (var i = 0; i < topResults.length; i++) {
                var result = topResults[i];
                response += (i + 1) + '. ' + result.name + '\n';
                response += 'Source: ' + result.url + '\n\n';
            }
            
            return response;
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        NLWPChat.init();
    });

})(jQuery);