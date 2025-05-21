<?php
/**
 * Factory class for creating Embedding Provider instances.
 *
 * @since      1.0.0
 */
class NL_WP_Embedding_Factory {

    /**
     * Create an Embedding Provider instance based on the selected provider.
     *
     * @since    1.0.0
     * @param    string    $provider    The embedding provider.
     * @param    string    $model       The embedding model.
     * @param    array     $config      Additional configuration parameters.
     * @return   NL_WP_Embedding_Provider  The embedding provider instance.
     */
    public static function create_provider($provider = null, $model = null, $config = array()) {
        // If no provider specified, get from settings
        if (!$provider) {
            $provider = get_option('nlwp_embedding_provider', 'openai');
        }
        
        // If no model specified, get from settings
        if (!$model) {
            $model = get_option('nlwp_embedding_model', self::get_default_model($provider));
        }
        
        // Ensure required files are loaded
        self::load_provider_classes();
        
        // Get API key from settings
        $api_key = isset($config['api_key']) ? $config['api_key'] : get_option("nlwp_{$provider}_api_key", '');
        
        // Create the appropriate embedding provider instance
        switch ($provider) {
            case 'openai':
                return new NL_WP_OpenAI_Provider($api_key, $model);
                
            case 'anthropic':
                return new NL_WP_Anthropic_Provider($api_key, $model);
                
            case 'gemini':
                return new NL_WP_Gemini_Provider($api_key, $model);
                
            case 'ollama':
                $server_url = isset($config['server_url']) ? $config['server_url'] : get_option('nlwp_ollama_url', 'http://localhost:11434');
                return new NL_WP_Ollama_Provider($api_key, $model, $server_url);
                
            default:
                // Default to OpenAI
                return new NL_WP_OpenAI_Provider($api_key, $model);
        }
    }

    /**
     * Load all embedding provider classes.
     *
     * @since    1.0.0
     */
    private static function load_provider_classes() {
        // Load the abstract base class
        require_once NLWP_PLUGIN_DIR . 'includes/embeddings/class-nl-wp-embedding-provider.php';
        
        // Load the specific implementations
        require_once NLWP_PLUGIN_DIR . 'includes/embeddings/class-nl-wp-openai-provider.php';
        require_once NLWP_PLUGIN_DIR . 'includes/embeddings/class-nl-wp-anthropic-provider.php';
        require_once NLWP_PLUGIN_DIR . 'includes/embeddings/class-nl-wp-gemini-provider.php';
        require_once NLWP_PLUGIN_DIR . 'includes/embeddings/class-nl-wp-ollama-provider.php';
    }

    /**
     * Get a list of available embedding providers.
     *
     * @since    1.0.0
     * @return   array     List of available providers.
     */
    public static function get_available_providers() {
        return array(
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'gemini' => 'Google Gemini',
            'ollama' => 'Ollama (Local)'
        );
    }

    /**
     * Get available models for a provider.
     *
     * @since    1.0.0
     * @param    string    $provider    The embedding provider.
     * @return   array                  List of available models for the provider.
     */
    public static function get_available_models($provider) {
        switch ($provider) {
            case 'openai':
                return array(
                    'text-embedding-3-small' => 'text-embedding-3-small (1536)',
                    'text-embedding-3-large' => 'text-embedding-3-large (3072)',
                    'text-embedding-ada-002' => 'text-embedding-ada-002 (1536, legacy)'
                );
                
            case 'anthropic':
                return array(
                    'voyage-3-large' => 'Voyage 3 Large (1024)',
                    'voyage-3-large:256' => 'Voyage 3 Large (256)',
                    'voyage-3-large:512' => 'Voyage 3 Large (512)',
                    'voyage-3-large:2048' => 'Voyage 3 Large (2048)',
                    'voyage-3' => 'Voyage 3 (1024)',
                    'voyage-3-lite' => 'Voyage 3 Lite (512)',
                    'voyage-code-3' => 'Voyage Code 3 (1024)',
                    'voyage-code-3:256' => 'Voyage Code 3 (256)',
                    'voyage-code-3:512' => 'Voyage Code 3 (512)',
                    'voyage-code-3:2048' => 'Voyage Code 3 (2048)',
                    'voyage-finance-2' => 'Voyage Finance 2 (1024)',
                    'voyage-law-2' => 'Voyage Law 2 (1024)'
                );
                
            case 'gemini':
                return array(
                    'embedding-001' => 'embedding-001 (768)',
                    'text-embedding-004' => 'text-embedding-004 (768)',
                    'gemini-embedding-exp' => 'gemini-embedding-exp (1408)'
                );
                
            case 'ollama':
                return array(
                    'nomic-embed-text' => 'nomic-embed-text (768)',
                    'snowflake-arctic-embed2' => 'snowflake-arctic-embed2 (1024)',
                    'granite-embedding' => 'granite-embedding (1536)'
                );
                
            default:
                return array();
        }
    }

    /**
     * Get the default model for a provider.
     *
     * @since    1.0.0
     * @param    string    $provider    The embedding provider.
     * @return   string                 The default model for the provider.
     */
    public static function get_default_model($provider) {
        switch ($provider) {
            case 'openai':
                return 'text-embedding-3-small';
                
            case 'anthropic':
                return 'voyage-3';
                
            case 'gemini':
                return 'embedding-001';
                
            case 'ollama':
                return 'nomic-embed-text';
                
            default:
                return 'text-embedding-3-small';
        }
    }
}