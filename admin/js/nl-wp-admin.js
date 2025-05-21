/**
 * NLWeb for WordPress - Admin JavaScript
 */
(function($) {
    'use strict';

    /**
     * Initialize admin functionality
     */
    function initAdmin() {
        // Initialize content ingestion
        initContentIngest();
        
        // Initialize database clearing
        initClearDatabase();
        
        // Initialize diagnostics
        initDiagnostics();
        
        // Initialize dynamic model selection
        initDynamicModelSelection();
    }

    /**
     * Initialize content ingestion functionality
     */
    function initContentIngest() {
        var $form = $('#nlwp-ingest-form');
        var $button = $('#nlwp-ingest-button');
        var $spinner = $form.find('.spinner');
        var $results = $('#nlwp-ingest-results');
        
        if (!$form.length) {
            return;
        }
        
        $form.on('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            var formData = {
                'action': 'nlwp_ingest_content',
                'nonce': nlwpAdmin.nonce,
                'post_type': $('#nlwp_post_type').val(),
                'limit': $('#nlwp_limit').val(),
                'offset': $('#nlwp_offset').val()
            };
            
            // Disable button and show spinner
            $button.prop('disabled', true);
            $spinner.css('visibility', 'visible');
            $results.html('<p>Ingesting content, please wait...</p>').show();
            
            // Make AJAX request
            $.ajax({
                url: nlwpAdmin.ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var message = '<p class="nlwp-success">✓ ' + data.message + '</p>';
                        message += '<p>Processed ' + data.processed + ' items out of ' + data.total + ' total.</p>';
                        
                        if (data.output) {
                            message += '<pre>' + data.output + '</pre>';
                        }
                        
                        $results.html(message);
                    } else {
                        var error = response.data ? response.data.message : 'Unknown error';
                        $results.html('<p class="nlwp-error">✗ Error: ' + error + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    $results.html('<p class="nlwp-error">✗ AJAX Error: ' + error + '</p>');
                },
                complete: function() {
                    // Enable button and hide spinner
                    $button.prop('disabled', false);
                    $spinner.css('visibility', 'hidden');
                }
            });
        });
    }

    /**
     * Initialize database clearing functionality
     */
    function initClearDatabase() {
        var $form = $('#nlwp-clear-form');
        var $button = $('#nlwp-clear-button');
        var $spinner = $form.find('.spinner');
        var $results = $('#nlwp-clear-results');
        
        if (!$form.length) {
            return;
        }
        
        $form.on('submit', function(e) {
            e.preventDefault();
            
            // Confirm action
            if (!confirm('Are you sure you want to clear the database? This will remove all indexed content and cannot be undone.')) {
                return;
            }
            
            // Get form data
            var formData = {
                'action': 'nlwp_clear_database',
                'nonce': nlwpAdmin.nonce
            };
            
            // Disable button and show spinner
            $button.prop('disabled', true);
            $spinner.css('visibility', 'visible');
            $results.html('<p>Clearing database, please wait...</p>').show();
            
            // Make AJAX request
            $.ajax({
                url: nlwpAdmin.ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var message = '<p class="nlwp-success">✓ ' + data.message + '</p>';
                        
                        if (data.output) {
                            message += '<pre>' + data.output + '</pre>';
                        }
                        
                        $results.html(message);
                    } else {
                        var error = response.data ? response.data.message : 'Unknown error';
                        $results.html('<p class="nlwp-error">✗ Error: ' + error + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    $results.html('<p class="nlwp-error">✗ AJAX Error: ' + error + '</p>');
                },
                complete: function() {
                    // Enable button and hide spinner
                    $button.prop('disabled', false);
                    $spinner.css('visibility', 'hidden');
                }
            });
        });
    }

    /**
     * Initialize diagnostic tools functionality
     */
    function initDiagnostics() {
        var $form = $('#nlwp-diagnostic-form');
        var $button = $('#nlwp-diagnostic-button');
        var $spinner = $form.find('.spinner');
        var $results = $('#nlwp-diagnostic-results');
        
        if (!$form.length) {
            return;
        }
        
        $form.on('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            var formData = {
                'action': 'nlwp_run_diagnostics',
                'nonce': nlwpAdmin.nonce,
                'test_text': $('#nlwp_test_text').val(),
                'test_type': $('#nlwp_test_type').val()
            };
            
            // Disable button and show spinner
            $button.prop('disabled', true);
            $spinner.css('visibility', 'visible');
            $results.html('<p>Running diagnostics, please wait...</p>').show();
            
            // Make AJAX request
            $.ajax({
                url: nlwpAdmin.ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var message = '<p class="nlwp-success">✓ ' + data.message + '</p>';
                        
                        // Add detailed results
                        if (data.details) {
                            message += '<div class="nlwp-details">';
                            
                            // Embedding details
                            if (data.details.embedding) {
                                var embedding = data.details.embedding;
                                message += '<h4>Embedding Test</h4>';
                                message += '<p>Status: <span class="nlwp-' + embedding.status + '">' + embedding.status.toUpperCase() + '</span></p>';
                                message += '<p>Provider: ' + embedding.provider + '</p>';
                                message += '<p>Model: ' + embedding.model + '</p>';
                                
                                // Special notice for Ollama when model is being pulled
                                if (embedding.provider === 'ollama' && embedding.model_pulled) {
                                    message += '<p class="nlwp-notice">⚠️ The model "' + embedding.model + '" was not found locally and is being pulled. This may take some time for larger models. Please try again in a few minutes.</p>';
                                }
                                
                                if (embedding.status === 'success') {
                                    message += '<p>Dimension: ' + embedding.dimension + '</p>';
                                    message += '<p>Preview: [' + embedding.preview.map(function(val) {
                                        return val.toFixed(4);
                                    }).join(', ') + ', ...]</p>';
                                } else if (embedding.error) {
                                    message += '<p>Error: ' + embedding.error + '</p>';
                                    
                                    // Add specific message for Ollama model not found errors
                                    if (embedding.provider === 'ollama' && embedding.error.includes('model not found')) {
                                        message += '<p class="nlwp-notice">ℹ️ The model is being pulled from the Ollama library. This may take several minutes. Please try again later.</p>';
                                    }
                                }
                                
                                message += '<p>Time: ' + embedding.time + '</p>';
                            }
                            
                            // Database details
                            if (data.details.database) {
                                var database = data.details.database;
                                message += '<h4>Database Connectivity Test</h4>';
                                message += '<p>Status: <span class="nlwp-' + database.status + '">' + database.status.toUpperCase() + '</span></p>';
                                message += '<p>Provider: ' + database.provider + '</p>';
                                
                                if (database.status === 'success') {
                                    message += '<p>Initialization: ' + database.initialization + '</p>';
                                } else if (database.error) {
                                    message += '<p>Error: ' + database.error + '</p>';
                                }
                                
                                message += '<p>Time: ' + database.time + '</p>';
                            }
                            
                            // Integration details
                            if (data.details.integration) {
                                var integration = data.details.integration;
                                message += '<h4>Full Integration Test</h4>';
                                message += '<p>Status: <span class="nlwp-' + integration.status + '">' + integration.status.toUpperCase() + '</span></p>';
                                
                                if (integration.status !== 'success' && integration.error) {
                                    message += '<p>Error: ' + integration.error + '</p>';
                                }
                                
                                message += '<p>Time: ' + integration.time + '</p>';
                            }
                            
                            message += '</div>';
                        }
                        
                        $results.html(message);
                    } else {
                        var error = response.data ? response.data.message : 'Unknown error';
                        $results.html('<p class="nlwp-error">✗ Error: ' + error + '</p>');
                        
                        // Add any available details even for errors
                        if (response.data && response.data.details) {
                            var detailsHtml = '<div class="nlwp-details">';
                            var details = response.data.details;
                            
                            Object.keys(details).forEach(function(key) {
                                var detail = details[key];
                                detailsHtml += '<h4>' + key.charAt(0).toUpperCase() + key.slice(1) + ' Test</h4>';
                                detailsHtml += '<p>Status: <span class="nlwp-' + detail.status + '">' + detail.status.toUpperCase() + '</span></p>';
                                
                                if (detail.error) {
                                    detailsHtml += '<p>Error: ' + detail.error + '</p>';
                                }
                                
                                if (detail.time) {
                                    detailsHtml += '<p>Time: ' + detail.time + '</p>';
                                }
                            });
                            
                            detailsHtml += '</div>';
                            $results.append(detailsHtml);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    $results.html('<p class="nlwp-error">✗ AJAX Error: ' + error + '</p>');
                },
                complete: function() {
                    // Enable button and hide spinner
                    $button.prop('disabled', false);
                    $spinner.css('visibility', 'hidden');
                }
            });
        });
    }

    /**
     * Initialize dynamic model selection functionality.
     * This updates the model options in real-time when the provider changes.
     */
    function initDynamicModelSelection() {
        var $providerSelect = $('#nlwp_embedding_provider');
        var $modelSelect = $('#nlwp_embedding_model');
        
        if (!$providerSelect.length || !$modelSelect.length) {
            return;
        }
        
        // Define models for each provider
        var providerModels = {
            'openai': {
                'text-embedding-3-small': 'text-embedding-3-small (1536)',
                'text-embedding-3-large': 'text-embedding-3-large (3072)',
                'text-embedding-ada-002': 'text-embedding-ada-002 (1536, legacy)'
            },
            'anthropic': {
                'voyage-3-large': 'Voyage 3 Large (1024)',
                'voyage-3-large:256': 'Voyage 3 Large (256)',
                'voyage-3-large:512': 'Voyage 3 Large (512)',
                'voyage-3-large:2048': 'Voyage 3 Large (2048)',
                'voyage-3': 'Voyage 3 (1024)',
                'voyage-3-lite': 'Voyage 3 Lite (512)',
                'voyage-code-3': 'Voyage Code 3 (1024)',
                'voyage-code-3:256': 'Voyage Code 3 (256)',
                'voyage-code-3:512': 'Voyage Code 3 (512)',
                'voyage-code-3:2048': 'Voyage Code 3 (2048)',
                'voyage-finance-2': 'Voyage Finance 2 (1024)',
                'voyage-law-2': 'Voyage Law 2 (1024)'
            },
            'gemini': {
                'embedding-001': 'embedding-001 (768)',
                'text-embedding-004': 'text-embedding-004 (768)',
                'gemini-embedding-exp': 'gemini-embedding-exp (1408)'
            },
            'ollama': {
                'nomic-embed-text': 'nomic-embed-text (768)',
                'snowflake-arctic-embed2': 'snowflake-arctic-embed2 (1024)',
                'granite-embedding': 'granite-embedding (1536)'
            }
        };
        
        // Default models for each provider
        var defaultModels = {
            'openai': 'text-embedding-3-small',
            'anthropic': 'voyage-3',
            'gemini': 'embedding-001',
            'ollama': 'nomic-embed-text'
        };
        
        // Function to update models based on selected provider
        function updateModels(provider, currentModel) {
            // Clear existing options
            $modelSelect.empty();
            
            // Check if provider exists in our definition
            if (!providerModels[provider]) {
                return;
            }
            
            // Get models for selected provider
            var models = providerModels[provider];
            
            // If no current model is provided, use default
            if (!currentModel || !models[currentModel]) {
                currentModel = defaultModels[provider];
            }
            
            // Add options for each model
            $.each(models, function(key, name) {
                var $option = $('<option></option>').val(key).text(name);
                
                if (key === currentModel) {
                    $option.attr('selected', 'selected');
                }
                
                $modelSelect.append($option);
            });
        }
        
        // Update models immediately when page loads
        var currentProvider = $providerSelect.val();
        var currentModel = $modelSelect.val();
        updateModels(currentProvider, currentModel);
        
        // Update models when provider changes
        $providerSelect.on('change', function() {
            var provider = $(this).val();
            updateModels(provider);
        });
    }

    // Initialize on document ready
    $(document).ready(function() {
        initAdmin();
    });

})(jQuery);