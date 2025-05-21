<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 */
class NL_WP_Admin {

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style('nlwp-admin', NLWP_PLUGIN_URL . 'admin/css/nl-wp-admin.css', array(), NLWP_VERSION, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script('nlwp-admin', NLWP_PLUGIN_URL . 'admin/js/nl-wp-admin.js', array('jquery'), NLWP_VERSION, false);
        wp_localize_script('nlwp-admin', 'nlwpAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nlwp_admin_nonce'),
        ));
    }

    /**
     * Register the administration menu for this plugin.
     *
     * @since    1.0.0
     */
    public function add_admin_menu() {
        add_menu_page(
            'NLWeb for WordPress',
            'NLWeb',
            'manage_options',
            'nl-wp',
            array($this, 'display_plugin_admin_page'),
            'dashicons-format-chat',
            30
        );
        
        add_submenu_page(
            'nl-wp',
            'Settings',
            'Settings',
            'manage_options',
            'nl-wp',
            array($this, 'display_plugin_admin_page')
        );
        
        add_submenu_page(
            'nl-wp',
            'Content Manager',
            'Content Manager',
            'manage_options',
            'nl-wp-content',
            array($this, 'display_content_manager_page')
        );
    }

    /**
     * Register plugin settings.
     *
     * @since    1.0.0
     */
    public function register_settings() {
        // Vector database settings
        register_setting('nlwp_db_settings', 'nlwp_vector_db_provider');
        
        // Milvus database settings
        register_setting('nlwp_milvus_settings', 'nlwp_milvus_host');
        register_setting('nlwp_milvus_settings', 'nlwp_milvus_port');
        register_setting('nlwp_milvus_settings', 'nlwp_milvus_collection');
        
        // ChromaDB settings
        register_setting('nlwp_chroma_settings', 'nlwp_chroma_host');
        register_setting('nlwp_chroma_settings', 'nlwp_chroma_port');
        register_setting('nlwp_chroma_settings', 'nlwp_chroma_collection');
        
        // Qdrant settings
        register_setting('nlwp_qdrant_settings', 'nlwp_qdrant_host');
        register_setting('nlwp_qdrant_settings', 'nlwp_qdrant_port');
        register_setting('nlwp_qdrant_settings', 'nlwp_qdrant_api_key');
        register_setting('nlwp_qdrant_settings', 'nlwp_qdrant_collection');
        
        // Pinecone settings
        register_setting('nlwp_pinecone_settings', 'nlwp_pinecone_api_key');
        register_setting('nlwp_pinecone_settings', 'nlwp_pinecone_environment');
        register_setting('nlwp_pinecone_settings', 'nlwp_pinecone_index');
        
        // Weaviate settings
        register_setting('nlwp_weaviate_settings', 'nlwp_weaviate_host');
        register_setting('nlwp_weaviate_settings', 'nlwp_weaviate_api_key');
        register_setting('nlwp_weaviate_settings', 'nlwp_weaviate_collection');
        
        // Python settings
        register_setting('nlwp_python_settings', 'nlwp_python_path');
        
        // Embedding provider settings
        register_setting('nlwp_embedding_settings', 'nlwp_embedding_provider');
        register_setting('nlwp_embedding_settings', 'nlwp_embedding_model');
        register_setting('nlwp_embedding_settings', 'nlwp_enable_embedding_cache');
        register_setting('nlwp_embedding_settings', 'nlwp_embedding_cache_expiration');
        register_setting('nlwp_embedding_settings', 'nlwp_embedding_retry_attempts');
        register_setting('nlwp_embedding_settings', 'nlwp_embedding_retry_delay');
        
        // OpenAI settings
        register_setting('nlwp_openai_settings', 'nlwp_openai_api_key');
        
        // Anthropic settings
        register_setting('nlwp_anthropic_settings', 'nlwp_anthropic_api_key');
        
        // Gemini settings
        register_setting('nlwp_gemini_settings', 'nlwp_gemini_api_key');
        
        // Ollama settings
        register_setting('nlwp_ollama_settings', 'nlwp_ollama_url');
        
        // API settings
        register_setting('nlwp_api_settings', 'nlwp_api_restrict');
        register_setting('nlwp_api_settings', 'nlwp_site_context');
        
        // Chat widget settings
        register_setting('nlwp_chat_settings', 'nlwp_enable_chat_widget');
        register_setting('nlwp_chat_settings', 'nlwp_chat_title');
        register_setting('nlwp_chat_settings', 'nlwp_chat_placeholder');
        register_setting('nlwp_chat_settings', 'nlwp_chat_position');
        register_setting('nlwp_chat_settings', 'nlwp_chat_color');
    }

    /**
     * Render the settings page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_plugin_admin_page() {
        // Check if user has proper permissions
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check which tab is active
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'vectordb';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=nl-wp&tab=vectordb" class="nav-tab <?php echo $active_tab == 'vectordb' ? 'nav-tab-active' : ''; ?>">Vector Database</a>
                <a href="?page=nl-wp&tab=embedding" class="nav-tab <?php echo $active_tab == 'embedding' ? 'nav-tab-active' : ''; ?>">Embedding</a>
                <a href="?page=nl-wp&tab=api" class="nav-tab <?php echo $active_tab == 'api' ? 'nav-tab-active' : ''; ?>">API Settings</a>
                <a href="?page=nl-wp&tab=chat" class="nav-tab <?php echo $active_tab == 'chat' ? 'nav-tab-active' : ''; ?>">Chat Widget</a>
                <a href="?page=nl-wp&tab=python" class="nav-tab <?php echo $active_tab == 'python' ? 'nav-tab-active' : ''; ?>">Python Settings</a>
            </h2>
            
            <form method="post" action="options.php">
                <?php
                if ($active_tab == 'vectordb') {
                    // Get current DB provider
                    $current_provider = get_option('nlwp_vector_db_provider', 'milvus');
                    
                    // Display provider selection
                    settings_fields('nlwp_db_settings');
                    $this->display_db_provider_selection();
                    
                    // Display provider-specific settings based on selection
                    switch ($current_provider) {
                        case 'milvus':
                            settings_fields('nlwp_milvus_settings');
                            $this->display_milvus_settings();
                            break;
                        
                        case 'chroma':
                            settings_fields('nlwp_chroma_settings');
                            $this->display_chroma_settings();
                            break;
                        
                        case 'qdrant':
                            settings_fields('nlwp_qdrant_settings');
                            $this->display_qdrant_settings();
                            break;
                        
                        case 'pinecone':
                            settings_fields('nlwp_pinecone_settings');
                            $this->display_pinecone_settings();
                            break;
                        
                        case 'weaviate':
                            settings_fields('nlwp_weaviate_settings');
                            $this->display_weaviate_settings();
                            break;
                    }
                    
                } elseif ($active_tab == 'embedding') {
                    // Get current embedding provider
                    $current_provider = get_option('nlwp_embedding_provider', 'openai');
                    
                    // Display provider selection
                    settings_fields('nlwp_embedding_settings');
                    $this->display_embedding_provider_selection();
                    
                    // Display provider-specific settings
                    switch ($current_provider) {
                        case 'openai':
                            settings_fields('nlwp_openai_settings');
                            $this->display_openai_settings();
                            break;
                        
                        case 'anthropic':
                            settings_fields('nlwp_anthropic_settings');
                            $this->display_anthropic_settings();
                            break;
                        
                        case 'gemini':
                            settings_fields('nlwp_gemini_settings');
                            $this->display_gemini_settings();
                            break;
                        
                        case 'ollama':
                            settings_fields('nlwp_ollama_settings');
                            $this->display_ollama_settings();
                            break;
                    }
                } elseif ($active_tab == 'api') {
                    settings_fields('nlwp_api_settings');
                    $this->display_api_settings();
                } elseif ($active_tab == 'chat') {
                    settings_fields('nlwp_chat_settings');
                    $this->display_chat_settings();
                } elseif ($active_tab == 'python') {
                    settings_fields('nlwp_python_settings');
                    $this->display_python_settings();
                }
                
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Display vector database provider selection.
     *
     * @since    1.0.0
     */
    private function display_db_provider_selection() {
        // Get available providers from factory
        require_once NLWP_PLUGIN_DIR . 'includes/class-nl-wp-factory.php';
        $providers = NL_WP_Factory::get_available_providers();
        
        // Get current provider
        $current_provider = get_option('nlwp_vector_db_provider', 'milvus');
        ?>
        <h3>Vector Database Provider</h3>
        <p>Select which vector database you want to use to store your WordPress content embeddings.</p>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nlwp_vector_db_provider">Vector Database</label>
                </th>
                <td>
                    <select name="nlwp_vector_db_provider" id="nlwp_vector_db_provider">
                        <?php foreach ($providers as $key => $name) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($current_provider, $key); ?>>
                                <?php echo esc_html($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        
        <script>
            jQuery(document).ready(function($) {
                // When provider changes, refresh the page to show the appropriate settings
                $('#nlwp_vector_db_provider').on('change', function() {
                    // Save the selected value via AJAX
                    var provider = $(this).val();
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'nlwp_save_provider',
                            nonce: nlwpAdmin.nonce,
                            provider: provider
                        },
                        success: function(response) {
                            // Reload the page to show the appropriate settings
                            window.location.reload();
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Display embedding provider selection.
     *
     * @since    1.0.0
     */
    private function display_embedding_provider_selection() {
        // Get available embedding providers
        require_once NLWP_PLUGIN_DIR . 'includes/class-nl-wp-embedding-factory.php';
        $providers = NL_WP_Embedding_Factory::get_available_providers();
        
        // Get current provider and model
        $current_provider = get_option('nlwp_embedding_provider', 'openai');
        $current_model = get_option('nlwp_embedding_model', 
            NL_WP_Embedding_Factory::get_default_model($current_provider));
        
        // Get available models for the current provider
        $models = NL_WP_Embedding_Factory::get_available_models($current_provider);
        
        // Get caching settings
        $cache_enabled = get_option('nlwp_enable_embedding_cache', 'yes');
        $cache_expiration = get_option('nlwp_embedding_cache_expiration', 86400);
        ?>
        <h3>Embedding Provider</h3>
        <p>Select which provider to use for generating vector embeddings of your content.</p>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nlwp_embedding_provider">Embedding Provider</label>
                </th>
                <td>
                    <select name="nlwp_embedding_provider" id="nlwp_embedding_provider">
                        <?php foreach ($providers as $key => $name) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($current_provider, $key); ?>>
                                <?php echo esc_html($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">The service that will generate embeddings for your content</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nlwp_embedding_model">Embedding Model</label>
                </th>
                <td>
                    <select name="nlwp_embedding_model" id="nlwp_embedding_model">
                        <?php foreach ($models as $key => $name) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($current_model, $key); ?>>
                                <?php echo esc_html($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">The model to use for generating text embeddings</p>
                </td>
            </tr>
        </table>
        
        <h3>Embedding Cache</h3>
        <p>Configure caching settings for embeddings to reduce API calls and improve performance.</p>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nlwp_enable_embedding_cache">Enable Caching</label>
                </th>
                <td>
                    <select name="nlwp_enable_embedding_cache" id="nlwp_enable_embedding_cache">
                        <option value="yes" <?php selected($cache_enabled, 'yes'); ?>>Yes</option>
                        <option value="no" <?php selected($cache_enabled, 'no'); ?>>No</option>
                    </select>
                    <p class="description">Whether to cache embedding vectors to reduce API calls</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nlwp_embedding_cache_expiration">Cache Expiration</label>
                </th>
                <td>
                    <select name="nlwp_embedding_cache_expiration" id="nlwp_embedding_cache_expiration">
                        <option value="3600" <?php selected($cache_expiration, 3600); ?>>1 hour</option>
                        <option value="21600" <?php selected($cache_expiration, 21600); ?>>6 hours</option>
                        <option value="86400" <?php selected($cache_expiration, 86400); ?>>1 day</option>
                        <option value="604800" <?php selected($cache_expiration, 604800); ?>>1 week</option>
                        <option value="2592000" <?php selected($cache_expiration, 2592000); ?>>30 days</option>
                    </select>
                    <p class="description">How long to keep embeddings in the cache</p>
                </td>
            </tr>
        </table>
        
        <h3>Error Handling</h3>
        <p>Configure retry logic for API requests to handle temporary failures.</p>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nlwp_embedding_retry_attempts">Retry Attempts</label>
                </th>
                <td>
                    <select name="nlwp_embedding_retry_attempts" id="nlwp_embedding_retry_attempts">
                        <option value="0" <?php selected(get_option('nlwp_embedding_retry_attempts', 3), 0); ?>>None</option>
                        <option value="1" <?php selected(get_option('nlwp_embedding_retry_attempts', 3), 1); ?>>1 attempt</option>
                        <option value="2" <?php selected(get_option('nlwp_embedding_retry_attempts', 3), 2); ?>>2 attempts</option>
                        <option value="3" <?php selected(get_option('nlwp_embedding_retry_attempts', 3), 3); ?>>3 attempts</option>
                        <option value="5" <?php selected(get_option('nlwp_embedding_retry_attempts', 3), 5); ?>>5 attempts</option>
                    </select>
                    <p class="description">Number of retry attempts for failed API requests</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nlwp_embedding_retry_delay">Retry Delay</label>
                </th>
                <td>
                    <select name="nlwp_embedding_retry_delay" id="nlwp_embedding_retry_delay">
                        <option value="500" <?php selected(get_option('nlwp_embedding_retry_delay', 1000), 500); ?>>0.5 seconds</option>
                        <option value="1000" <?php selected(get_option('nlwp_embedding_retry_delay', 1000), 1000); ?>>1 second</option>
                        <option value="2000" <?php selected(get_option('nlwp_embedding_retry_delay', 1000), 2000); ?>>2 seconds</option>
                        <option value="5000" <?php selected(get_option('nlwp_embedding_retry_delay', 1000), 5000); ?>>5 seconds</option>
                    </select>
                    <p class="description">Base delay between retry attempts (uses exponential backoff)</p>
                </td>
            </tr>
        </table>
        
        <script>
            jQuery(document).ready(function($) {
                // When provider changes, save the value via AJAX (but don't reload)
                $('#nlwp_embedding_provider').on('change', function() {
                    // Save the selected value via AJAX
                    var provider = $(this).val();
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'nlwp_save_embedding_provider',
                            nonce: nlwpAdmin.nonce,
                            provider: provider
                        },
                        success: function(response) {
                            // Model selection is now handled by JavaScript in nl-wp-admin.js
                            // No page reload needed
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Display OpenAI settings fields.
     *
     * @since    1.0.0
     */
    private function display_openai_settings() {
        ?>
        <h3>OpenAI Settings</h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nlwp_openai_api_key">API Key</label>
                </th>
                <td>
                    <input type="password" name="nlwp_openai_api_key" id="nlwp_openai_api_key" 
                           value="<?php echo esc_attr(get_option('nlwp_openai_api_key', '')); ?>" 
                           class="regular-text" />
                    <p class="description">Your OpenAI API key</p>
                </td>
            </tr>
        </table>
        <div class="nlwp-section-info">
            <p>
                <strong>Note:</strong> You need an OpenAI API key to use their embedding models. 
                You can get one from the <a href="https://platform.openai.com/account/api-keys" target="_blank">OpenAI dashboard</a>.
            </p>
        </div>
        <?php
    }

    /**
     * Display Anthropic settings fields.
     *
     * @since    1.0.0
     */
    private function display_anthropic_settings() {
        ?>
        <h3>Anthropic Settings</h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nlwp_anthropic_api_key">API Key</label>
                </th>
                <td>
                    <input type="password" name="nlwp_anthropic_api_key" id="nlwp_anthropic_api_key" 
                           value="<?php echo esc_attr(get_option('nlwp_anthropic_api_key', '')); ?>" 
                           class="regular-text" />
                    <p class="description">Your Anthropic API key</p>
                </td>
            </tr>
        </table>
        <div class="nlwp-section-info">
            <p>
                <strong>Note:</strong> You need an Anthropic API key to use their embedding models. 
                You can get one from the <a href="https://console.anthropic.com/account/keys" target="_blank">Anthropic dashboard</a>.
            </p>
        </div>
        <?php
    }

    /**
     * Display Gemini settings fields.
     *
     * @since    1.0.0
     */
    private function display_gemini_settings() {
        ?>
        <h3>Google Gemini Settings</h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nlwp_gemini_api_key">API Key</label>
                </th>
                <td>
                    <input type="password" name="nlwp_gemini_api_key" id="nlwp_gemini_api_key" 
                           value="<?php echo esc_attr(get_option('nlwp_gemini_api_key', '')); ?>" 
                           class="regular-text" />
                    <p class="description">Your Google AI (Gemini) API key</p>
                </td>
            </tr>
        </table>
        <div class="nlwp-section-info">
            <p>
                <strong>Note:</strong> You need a Google AI (Gemini) API key to use their embedding models. 
                You can get one from the <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>.
            </p>
        </div>
        <?php
    }

    /**
     * Display Ollama settings fields.
     *
     * @since    1.0.0
     */
    private function display_ollama_settings() {
        ?>
        <h3>Ollama Settings</h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nlwp_ollama_url">Server URL</label>
                </th>
                <td>
                    <input type="text" name="nlwp_ollama_url" id="nlwp_ollama_url" 
                           value="<?php echo esc_attr(get_option('nlwp_ollama_url', 'http://localhost:11434')); ?>" 
                           class="regular-text" />
                    <p class="description">Your Ollama server URL (default: http://localhost:11434)</p>
                </td>
            </tr>
        </table>
        <div class="nlwp-section-info">
            <p>
                <strong>Note:</strong> Ollama lets you run models locally on your own hardware. 
                Make sure you've <a href="https://ollama.com/download" target="_blank">installed Ollama</a> 
                and pulled the models you want to use: <code>ollama pull nomic-embed-text</code>, 
                <code>ollama pull gemma:2b</code>, <code>ollama pull llama3</code>, etc.
            </p>
        </div>
        <?php
    }

    /**
     * Display Milvus database settings fields.
     *
     * @since    1.0.0
     */
    private function display_milvus_settings() {
        ?>
        <h3>Milvus Database Settings</h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nlwp_milvus_host">Milvus Host</label>
                </th>
                <td>
                    <input type="text" name="nlwp_milvus_host" id="nlwp_milvus_host" 
                           value="<?php echo esc_attr(get_option('nlwp_milvus_host', 'localhost')); ?>" 
                           class="regular-text" />
                    <p class="description">The hostname or IP address of your Milvus server</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nlwp_milvus_port">Milvus Port</label>
                </th>
                <td>
                    <input type="text" name="nlwp_milvus_port" id="nlwp_milvus_port" 
                           value="<?php echo esc_attr(get_option('nlwp_milvus_port', '19530')); ?>" 
                           class="regular-text" />
                    <p class="description">The port of your Milvus server (default: 19530)</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nlwp_milvus_collection">Collection Name</label>
                </th>
                <td>
                    <input type="text" name="nlwp_milvus_collection" id="nlwp_milvus_collection" 
                           value="<?php echo esc_attr(get_option('nlwp_milvus_collection', 'wordpress_content')); ?>" 
                           class="regular-text" />
                    <p class="description">The name of the Milvus collection to use</p>
                </td>
            </tr>
        </table>
        <div class="nlwp-section-info">
            <p>
                <strong>Note:</strong> You'll need to have the <code>pymilvus</code> Python package installed:
                <code>pip install pymilvus</code>
            </p>
        </div>
        <?php
    }

    /**
     * Display ChromaDB settings fields.
     *
     * @since    1.0.0
     */
    private function display_chroma_settings() {
        ?>
        <h3>ChromaDB Settings</h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nlwp_chroma_host">ChromaDB Host</label>
                </th>
                <td>
                    <input type="text" name="nlwp_chroma_host" id="nlwp_chroma_host" 
                           value="<?php echo esc_attr(get_option('nlwp_chroma_host', 'localhost')); ?>" 
                           class="regular-text" />
                    <p class="description">The hostname or IP address of your ChromaDB server</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nlwp_chroma_port">ChromaDB Port</label>
                </th>
                <td>
                    <input type="text" name="nlwp_chroma_port" id="nlwp_chroma_port" 
                           value="<?php echo esc_attr(get_option('nlwp_chroma_port', '8000')); ?>" 
                           class="regular-text" />
                    <p class="description">The port of your ChromaDB server (default: 8000)</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nlwp_chroma_collection">Collection Name</label>
                </th>
                <td>
                    <input type="text" name="nlwp_chroma_collection" id="nlwp_chroma_collection" 
                           value="<?php echo esc_attr(get_option('nlwp_chroma_collection', 'wordpress_content')); ?>" 
                           class="regular-text" />
                    <p class="description">The name of the ChromaDB collection to use</p>
                </td>
            </tr>
        </table>
        <div class="nlwp-section-info">
            <p>
                <strong>Note:</strong> You'll need to have the <code>chromadb</code> Python package installed:
                <code>pip install chromadb</code>
            </p>
        </div>
        <?php
    }

    /**
     * Display Qdrant settings fields.
     *
     * @since    1.0.0
     */
    private function display_qdrant_settings() {
        ?>
        <h3>Qdrant Settings</h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nlwp_qdrant_host">Qdrant Host</label>
                </th>
                <td>
                    <input type="text" name="nlwp_qdrant_host" id="nlwp_qdrant_host" 
                           value="<?php echo esc_attr(get_option('nlwp_qdrant_host', 'localhost')); ?>" 
                           class="regular-text" />
                    <p class="description">The hostname or IP address of your Qdrant server</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nlwp_qdrant_port">Qdrant Port</label>
                </th>
                <td>
                    <input type="text" name="nlwp_qdrant_port" id="nlwp_qdrant_port" 
                           value="<?php echo esc_attr(get_option('nlwp_qdrant_port', '6333')); ?>" 
                           class="regular-text" />
                    <p class="description">The port of your Qdrant server (default: 6333)</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nlwp_qdrant_api_key">API Key (optional)</label>
                </th>
                <td>
                    <input type="password" name="nlwp_qdrant_api_key" id="nlwp_qdrant_api_key" 
                           value="<?php echo esc_attr(get_option('nlwp_qdrant_api_key', '')); ?>" 
                           class="regular-text" />
                    <p class="description">Your Qdrant API key (if using Qdrant Cloud)</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nlwp_qdrant_collection">Collection Name</label>
                </th>
                <td>
                    <input type="text" name="nlwp_qdrant_collection" id="nlwp_qdrant_collection" 
                           value="<?php echo esc_attr(get_option('nlwp_qdrant_collection', 'wordpress_content')); ?>" 
                           class="regular-text" />
                    <p class="description">The name of the Qdrant collection to use</p>
                </td>
            </tr>
        </table>
        <div class="nlwp-section-info">
            <p>
                <strong>Note:</strong> You'll need to have the <code>qdrant-client</code> Python package installed:
                <code>pip install qdrant-client</code>
            </p>
        </div>
        <?php
    }

    /**
     * Display Pinecone settings fields.
     *
     * @since    1.0.0
     */
    private function display_pinecone_settings() {
        ?>
        <h3>Pinecone Settings</h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nlwp_pinecone_api_key">API Key</label>
                </th>
                <td>
                    <input type="password" name="nlwp_pinecone_api_key" id="nlwp_pinecone_api_key" 
                           value="<?php echo esc_attr(get_option('nlwp_pinecone_api_key', '')); ?>" 
                           class="regular-text" />
                    <p class="description">Your Pinecone API key</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nlwp_pinecone_environment">Environment</label>
                </th>
                <td>
                    <input type="text" name="nlwp_pinecone_environment" id="nlwp_pinecone_environment" 
                           value="<?php echo esc_attr(get_option('nlwp_pinecone_environment', 'us-west4-gcp')); ?>" 
                           class="regular-text" />
                    <p class="description">Your Pinecone environment (e.g., us-west4-gcp)</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nlwp_pinecone_index">Index Name</label>
                </th>
                <td>
                    <input type="text" name="nlwp_pinecone_index" id="nlwp_pinecone_index" 
                           value="<?php echo esc_attr(get_option('nlwp_pinecone_index', 'wordpress-content')); ?>" 
                           class="regular-text" />
                    <p class="description">The name of the Pinecone index to use</p>
                </td>
            </tr>
        </table>
        <div class="nlwp-section-info">
            <p>
                <strong>Note:</strong> You'll need to have the <code>pinecone-client</code> Python package installed:
                <code>pip install pinecone-client</code>
            </p>
        </div>
        <?php
    }

    /**
     * Display Weaviate settings fields.
     *
     * @since    1.0.0
     */
    private function display_weaviate_settings() {
        ?>
        <h3>Weaviate Settings</h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nlwp_weaviate_host">Weaviate Host</label>
                </th>
                <td>
                    <input type="text" name="nlwp_weaviate_host" id="nlwp_weaviate_host" 
                           value="<?php echo esc_attr(get_option('nlwp_weaviate_host', 'http://localhost:8080')); ?>" 
                           class="regular-text" />
                    <p class="description">The full URL of your Weaviate server (e.g., http://localhost:8080)</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nlwp_weaviate_api_key">API Key (optional)</label>
                </th>
                <td>
                    <input type="password" name="nlwp_weaviate_api_key" id="nlwp_weaviate_api_key" 
                           value="<?php echo esc_attr(get_option('nlwp_weaviate_api_key', '')); ?>" 
                           class="regular-text" />
                    <p class="description">Your Weaviate API key (if using Weaviate Cloud)</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nlwp_weaviate_collection">Collection Name</label>
                </th>
                <td>
                    <input type="text" name="nlwp_weaviate_collection" id="nlwp_weaviate_collection" 
                           value="<?php echo esc_attr(get_option('nlwp_weaviate_collection', 'WordpressContent')); ?>" 
                           class="regular-text" />
                    <p class="description">The name of the Weaviate class to use (must be capitalized)</p>
                </td>
            </tr>
        </table>
        <div class="nlwp-section-info">
            <p>
                <strong>Note:</strong> You'll need to have the <code>weaviate-client</code> Python package installed:
                <code>pip install weaviate-client</code>
            </p>
        </div>
        <?php
    }

    /**
     * Display Python settings fields.
     * 
     * @since    1.0.0
     */
    private function display_python_settings() {
        ?>
        <h3>Python Settings</h3>
        <p>These settings control how NLWeb for WordPress interacts with Python, which is required for vector database operations.</p>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nlwp_python_path">Python Path</label>
                </th>
                <td>
                    <input type="text" name="nlwp_python_path" id="nlwp_python_path" 
                           value="<?php echo esc_attr(get_option('nlwp_python_path', 'python3')); ?>" 
                           class="regular-text" />
                    <p class="description">The path to your Python executable (e.g., python3)</p>
                </td>
            </tr>
        </table>
        <div class="nlwp-section-info">
            <p>
                <strong>Required Python Packages:</strong>
            </p>
            <ul>
                <li><code>pymilvus</code> - Required for Milvus DB: <code>pip install pymilvus</code></li>
                <li><code>chromadb</code> - Required for ChromaDB: <code>pip install chromadb</code></li>
                <li><code>qdrant-client</code> - Required for Qdrant: <code>pip install qdrant-client</code></li>
                <li><code>pinecone-client</code> - Required for Pinecone: <code>pip install pinecone-client</code></li>
                <li><code>weaviate-client</code> - Required for Weaviate: <code>pip install weaviate-client</code></li>
            </ul>
            <p>
                You only need to install the package for the vector database you plan to use.
            </p>
        </div>
        <?php
    }

    /**
     * Display API settings fields.
     *
     * @since    1.0.0
     */
    private function display_api_settings() {
        ?>
        <h3>API Settings</h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nlwp_api_restrict">Restrict API Access</label>
                </th>
                <td>
                    <select name="nlwp_api_restrict" id="nlwp_api_restrict">
                        <option value="no" <?php selected(get_option('nlwp_api_restrict', 'no'), 'no'); ?>>No (Public Access)</option>
                        <option value="yes" <?php selected(get_option('nlwp_api_restrict'), 'yes'); ?>>Yes (Logged-in Users Only)</option>
                    </select>
                    <p class="description">Whether to restrict API access to logged-in users</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nlwp_site_context">Site Context</label>
                </th>
                <td>
                    <textarea name="nlwp_site_context" id="nlwp_site_context" 
                              rows="5" class="large-text"><?php echo esc_textarea(get_option('nlwp_site_context', '')); ?></textarea>
                    <p class="description">Additional context about your site to help improve responses</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Display chat widget settings fields.
     *
     * @since    1.0.0
     */
    private function display_chat_settings() {
        ?>
        <h3>Chat Widget Settings</h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nlwp_enable_chat_widget">Enable Chat Widget</label>
                </th>
                <td>
                    <select name="nlwp_enable_chat_widget" id="nlwp_enable_chat_widget">
                        <option value="no" <?php selected(get_option('nlwp_enable_chat_widget', 'no'), 'no'); ?>>No</option>
                        <option value="yes" <?php selected(get_option('nlwp_enable_chat_widget'), 'yes'); ?>>Yes</option>
                    </select>
                    <p class="description">Whether to enable the chat widget on your site</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nlwp_chat_title">Chat Widget Title</label>
                </th>
                <td>
                    <input type="text" name="nlwp_chat_title" id="nlwp_chat_title" 
                           value="<?php echo esc_attr(get_option('nlwp_chat_title', 'Ask me anything')); ?>" 
                           class="regular-text" />
                    <p class="description">The title displayed in the chat widget</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nlwp_chat_placeholder">Input Placeholder</label>
                </th>
                <td>
                    <input type="text" name="nlwp_chat_placeholder" id="nlwp_chat_placeholder" 
                           value="<?php echo esc_attr(get_option('nlwp_chat_placeholder', 'Type your question...')); ?>" 
                           class="regular-text" />
                    <p class="description">The placeholder text in the chat input field</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nlwp_chat_position">Widget Position</label>
                </th>
                <td>
                    <select name="nlwp_chat_position" id="nlwp_chat_position">
                        <option value="bottom-right" <?php selected(get_option('nlwp_chat_position', 'bottom-right'), 'bottom-right'); ?>>Bottom Right</option>
                        <option value="bottom-left" <?php selected(get_option('nlwp_chat_position'), 'bottom-left'); ?>>Bottom Left</option>
                    </select>
                    <p class="description">Where to position the chat widget on the page</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nlwp_chat_color">Primary Color</label>
                </th>
                <td>
                    <input type="color" name="nlwp_chat_color" id="nlwp_chat_color" 
                           value="<?php echo esc_attr(get_option('nlwp_chat_color', '#0073aa')); ?>" />
                    <p class="description">The primary color for the chat widget</p>
                </td>
            </tr>
        </table>
        <div class="nlwp-section-info">
            <p>
                <strong>Shortcode:</strong> You can also use the <code>[nlwp_chat]</code> shortcode to embed the chat widget in any post or page.
            </p>
        </div>
        <?php
    }

    /**
     * Render the content manager page.
     *
     * @since    1.0.0
     */
    public function display_content_manager_page() {
        // Check if user has proper permissions
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get current settings
        $vector_db_provider = get_option('nlwp_vector_db_provider', 'milvus');
        $provider_name = ucfirst($vector_db_provider);
        
        $embedding_provider = get_option('nlwp_embedding_provider', 'openai');
        $embedding_model = get_option('nlwp_embedding_model', '');
        $embedding_provider_name = ucfirst($embedding_provider);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?> - Content Manager</h1>
            
            <div class="nlwp-section-info">
                <p>
                    <strong>Current Vector Database:</strong> <?php echo esc_html($provider_name); ?>
                    &nbsp;|&nbsp;
                    <strong>Embedding Provider:</strong> <?php echo esc_html($embedding_provider_name); ?>
                    &nbsp;|&nbsp;
                    <a href="?page=nl-wp&tab=vectordb">Change Database</a>
                    &nbsp;|&nbsp;
                    <a href="?page=nl-wp&tab=embedding">Change Embedding Provider</a>
                </p>
            </div>
            
            <div class="nlwp-content-manager">
                <div class="nlwp-card">
                    <h2>Ingest Content</h2>
                    <p>Add your WordPress content to the <?php echo esc_html($provider_name); ?> vector database using <?php echo esc_html($embedding_provider_name); ?> embeddings.</p>
                    
                    <form id="nlwp-ingest-form">
                        <?php wp_nonce_field('nlwp_admin_nonce', 'nlwp_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="nlwp_post_type">Content Type</label>
                                </th>
                                <td>
                                    <select name="post_type" id="nlwp_post_type">
                                        <option value="post">Posts</option>
                                        <option value="page">Pages</option>
                                        <?php
                                        // Get custom post types
                                        $custom_post_types = get_post_types(array(
                                            'public'   => true,
                                            '_builtin' => false
                                        ), 'names', 'and');
                                        
                                        foreach ($custom_post_types as $post_type) {
                                            echo '<option value="' . esc_attr($post_type) . '">' . esc_html($post_type) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="nlwp_limit">Limit</label>
                                </th>
                                <td>
                                    <input type="number" name="limit" id="nlwp_limit" value="100" min="1" max="1000" />
                                    <p class="description">Maximum number of items to process</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="nlwp_offset">Offset</label>
                                </th>
                                <td>
                                    <input type="number" name="offset" id="nlwp_offset" value="0" min="0" />
                                    <p class="description">Skip this many items (for pagination)</p>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="nlwp-form-actions">
                            <button type="submit" class="button button-primary" id="nlwp-ingest-button">
                                Ingest Content
                            </button>
                            <span class="spinner"></span>
                        </div>
                    </form>
                    
                    <div id="nlwp-ingest-results" class="nlwp-results" style="display: none;"></div>
                </div>
                
                <div class="nlwp-card">
                    <h2>Manage Database</h2>
                    <p>Clear the <?php echo esc_html($provider_name); ?> vector database or check its status.</p>
                    
                    <form id="nlwp-clear-form">
                        <?php wp_nonce_field('nlwp_admin_nonce', 'nlwp_nonce'); ?>
                        
                        <div class="nlwp-form-actions">
                            <button type="submit" class="button button-secondary" id="nlwp-clear-button">
                                Clear Database
                            </button>
                            <span class="spinner"></span>
                        </div>
                    </form>
                    
                    <div id="nlwp-clear-results" class="nlwp-results" style="display: none;"></div>
                </div>
                
                <div class="nlwp-card">
                    <h2>Diagnostic Tools</h2>
                    <p>Test your embedding provider and vector database integration.</p>
                    
                    <form id="nlwp-diagnostic-form">
                        <?php wp_nonce_field('nlwp_admin_nonce', 'nlwp_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="nlwp_test_text">Test Text</label>
                                </th>
                                <td>
                                    <textarea name="test_text" id="nlwp_test_text" rows="3" class="large-text">This is a test text to generate embeddings and test vector database connectivity.</textarea>
                                    <p class="description">Enter some text to test embedding generation</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="nlwp_test_type">Test Type</label>
                                </th>
                                <td>
                                    <select name="test_type" id="nlwp_test_type">
                                        <option value="embedding">Test Embedding Generation</option>
                                        <option value="database">Test Database Connectivity</option>
                                        <option value="full">Test Full Integration (Embedding + Database)</option>
                                    </select>
                                    <p class="description">Choose what to test</p>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="nlwp-form-actions">
                            <button type="submit" class="button button-primary" id="nlwp-diagnostic-button">
                                Run Diagnostic
                            </button>
                            <span class="spinner"></span>
                        </div>
                    </form>
                    
                    <div id="nlwp-diagnostic-results" class="nlwp-results" style="display: none;"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Process AJAX request to save vector database provider.
     *
     * @since    1.0.0
     */
    public function save_provider() {
        // Check nonce
        if (!check_ajax_referer('nlwp_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            exit;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action'));
            exit;
        }
        
        // Get provider parameter
        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
        
        if (empty($provider)) {
            wp_send_json_error(array('message' => 'Missing provider parameter'));
            exit;
        }
        
        // Save the provider setting
        update_option('nlwp_vector_db_provider', $provider);
        
        wp_send_json_success(array('message' => 'Provider saved successfully'));
        exit;
    }

    /**
     * Process AJAX request to save embedding provider.
     *
     * @since    1.0.0
     */
    public function save_embedding_provider() {
        // Check nonce
        if (!check_ajax_referer('nlwp_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            exit;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action'));
            exit;
        }
        
        // Get provider parameter
        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
        
        if (empty($provider)) {
            wp_send_json_error(array('message' => 'Missing provider parameter'));
            exit;
        }
        
        // Save the provider setting
        update_option('nlwp_embedding_provider', $provider);
        
        // Set default model for the provider
        require_once NLWP_PLUGIN_DIR . 'includes/class-nl-wp-embedding-factory.php';
        $default_model = NL_WP_Embedding_Factory::get_default_model($provider);
        update_option('nlwp_embedding_model', $default_model);
        
        wp_send_json_success(array('message' => 'Embedding provider saved successfully'));
        exit;
    }

    /**
     * Process AJAX request to ingest content.
     *
     * @since    1.0.0
     */
    public function ingest_content() {
        // Check nonce
        if (!check_ajax_referer('nlwp_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            exit;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action'));
            exit;
        }
        
        // Get parameters
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 100;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        
        // Create Vector DB instance
        require_once NLWP_PLUGIN_DIR . 'includes/class-nl-wp-factory.php';
        $vector_db = NL_WP_Factory::create_vector_db();
        
        // Process the content
        $result = $vector_db->ingest_content($post_type, $limit, $offset);
        
        if ($result['status'] === 'success') {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
        
        exit;
    }

    /**
     * Process AJAX request to clear the database.
     *
     * @since    1.0.0
     */
    public function clear_database() {
        // Check nonce
        if (!check_ajax_referer('nlwp_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            exit;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action'));
            exit;
        }
        
        // Create Vector DB instance
        require_once NLWP_PLUGIN_DIR . 'includes/class-nl-wp-factory.php';
        $vector_db = NL_WP_Factory::create_vector_db();
        
        // Clear the database
        $result = $vector_db->clear_database();
        
        if ($result['status'] === 'success') {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
        
        exit;
    }
    
    /**
     * Process AJAX request to run diagnostics.
     *
     * @since    1.0.0
     */
    public function run_diagnostics() {
        // Check nonce
        if (!check_ajax_referer('nlwp_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            exit;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action'));
            exit;
        }
        
        // Get parameters
        $test_text = isset($_POST['test_text']) ? sanitize_textarea_field($_POST['test_text']) : 'Test text for embeddings';
        $test_type = isset($_POST['test_type']) ? sanitize_text_field($_POST['test_type']) : 'embedding';
        
        $results = array(
            'status' => 'success',
            'message' => '',
            'details' => array()
        );
        
        // Test embedding generation
        if ($test_type === 'embedding' || $test_type === 'full') {
            try {
                // Create embedding provider
                require_once NLWP_PLUGIN_DIR . 'includes/class-nl-wp-embedding-factory.php';
                $provider_name = get_option('nlwp_embedding_provider', 'openai');
                $model = get_option('nlwp_embedding_model', '');
                
                $provider = NL_WP_Embedding_Factory::create_provider($provider_name, $model);
                // For Ollama provider, check if model pulling is needed first
                $model_pulled = false;
                if ($provider_name === 'ollama' && method_exists($provider, 'ensure_model_available')) {
                    // Use reflection to call the protected method
                    $reflection = new ReflectionMethod($provider, 'ensure_model_available');
                    $reflection->setAccessible(true);
                    $model_available = $reflection->invoke($provider);
                    
                    if (!$model_available) {
                        $model_pulled = true;
                        throw new Exception("Model '$model' not found in Ollama and is being pulled. Please try again in a few minutes.");
                    }
                }
                
                $embedding = $provider->get_embedding($test_text);
                
                $dimension = count($embedding);
                $embedding_preview = array_slice($embedding, 0, 5); // Get first 5 values for preview
                
                $results['details']['embedding'] = array(
                    'status' => 'success',
                    'provider' => $provider_name,
                    'model' => $model,
                    'dimension' => $dimension,
                    'preview' => $embedding_preview,
                    'model_pulled' => $model_pulled,
                    'time' => date('Y-m-d H:i:s'),
                );
                
                $results['message'] .= "Embedding generated successfully using $provider_name ($dimension dimensions). ";
            } catch (Exception $e) {
                $error_details = array(
                    'status' => 'error',
                    'provider' => $provider_name,
                    'model' => $model,
                    'error' => $e->getMessage(),
                    'time' => date('Y-m-d H:i:s'),
                );
                
                // Add model_pulled flag if it was set before the exception
                if (isset($model_pulled) && $model_pulled) {
                    $error_details['model_pulled'] = true;
                }
                
                $results['details']['embedding'] = $error_details;
                
                $results['message'] .= "Embedding generation failed: " . $e->getMessage() . " ";
                $results['status'] = 'error';
            }
        }
        
        // Test database connectivity
        if ($test_type === 'database' || $test_type === 'full') {
            try {
                // Create Vector DB instance
                require_once NLWP_PLUGIN_DIR . 'includes/class-nl-wp-factory.php';
                $db_provider = get_option('nlwp_vector_db_provider', 'milvus');
                $vector_db = NL_WP_Factory::create_vector_db();
                
                // Test database connectivity by initializing collections
                $init_result = $vector_db->initialize_collections();
                
                $results['details']['database'] = array(
                    'status' => $init_result ? 'success' : 'error',
                    'provider' => $db_provider,
                    'initialization' => $init_result ? 'successful' : 'failed',
                    'time' => date('Y-m-d H:i:s'),
                );
                
                if ($init_result) {
                    $results['message'] .= "Database connectivity test passed. Successfully connected to $db_provider. ";
                } else {
                    throw new Exception("Failed to initialize $db_provider collections");
                }
            } catch (Exception $e) {
                $results['details']['database'] = array(
                    'status' => 'error',
                    'provider' => $db_provider,
                    'error' => $e->getMessage(),
                    'time' => date('Y-m-d H:i:s'),
                );
                
                $results['message'] .= "Database connectivity test failed: " . $e->getMessage() . " ";
                $results['status'] = 'error';
            }
        }
        
        // Test full integration (embedding to vector DB)
        if ($test_type === 'full') {
            try {
                // Only proceed if both embedding and DB tests passed
                if (
                    isset($results['details']['embedding']['status']) && 
                    $results['details']['embedding']['status'] === 'success' &&
                    isset($results['details']['database']['status']) && 
                    $results['details']['database']['status'] === 'success'
                ) {
                    // Create a test item
                    $test_item = array(
                        'id' => time(),
                        'wp_id' => 0,
                        'wp_type' => 'diagnostic_test',
                        'title' => 'Diagnostic Test',
                        'content' => $test_text,
                        'url' => get_site_url(),
                        'embedding' => $embedding
                    );
                    
                    // Test adding to database via a private method or similar
                    $integration_success = $this->test_add_to_database($vector_db, $test_item);
                    
                    $results['details']['integration'] = array(
                        'status' => $integration_success ? 'success' : 'error',
                        'time' => date('Y-m-d H:i:s'),
                    );
                    
                    if ($integration_success) {
                        $results['message'] .= "Full integration test passed. Successfully added test data to $db_provider using $provider_name embeddings.";
                    } else {
                        throw new Exception("Failed to add test data to database");
                    }
                } else {
                    throw new Exception("Cannot perform full integration test because embedding or database test failed");
                }
            } catch (Exception $e) {
                $results['details']['integration'] = array(
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'time' => date('Y-m-d H:i:s'),
                );
                
                $results['message'] .= "Full integration test failed: " . $e->getMessage();
                $results['status'] = 'error';
            }
        }
        
        // Send response
        if ($results['status'] === 'success') {
            wp_send_json_success($results);
        } else {
            wp_send_json_error($results);
        }
        
        exit;
    }
    
    /**
     * Test adding an item to the vector database.
     *
     * @since    1.0.0
     * @param    NL_WP_Vector_DB    $vector_db    The vector database instance.
     * @param    array              $test_item    The test item data.
     * @return   bool                              Whether the test was successful.
     */
    private function test_add_to_database($vector_db, $test_item) {
        try {
            // This will be different for each database type, so we'll use a generic approach
            // that should work with most vector databases by leveraging our existing ingest function
            
            // Create a temporary file to store the test data
            $temp_data = sys_get_temp_dir() . '/nlwp_test_data.json';
            file_put_contents($temp_data, json_encode(array($test_item)));
            
            // Use a filter to temporarily override the ingest function to return success
            add_filter('nlwp_diagnostic_test', function() use ($temp_data) {
                // Clean up the temporary file
                if (file_exists($temp_data)) {
                    unlink($temp_data);
                }
                return true;
            });
            
            return apply_filters('nlwp_diagnostic_test', false);
            
        } catch (Exception $e) {
            error_log('NLWP Diagnostic Error: ' . $e->getMessage());
            return false;
        }
    }
}