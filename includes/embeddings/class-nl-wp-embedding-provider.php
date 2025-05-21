<?php
/**
 * Abstract Embedding Provider class.
 *
 * This class serves as a base for all embedding provider implementations.
 *
 * @since      1.0.0
 */
abstract class NL_WP_Embedding_Provider {

    /**
     * API key for the provider.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $api_key    The API key.
     */
    protected $api_key;

    /**
     * Model name to use for embeddings.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $model    The model name.
     */
    protected $model;

    /**
     * Embedding dimension.
     *
     * @since    1.0.0
     * @access   protected
     * @var      int    $dimension    The embedding dimension.
     */
    protected $dimension;
    
    /**
     * Cache expiration time in seconds.
     *
     * @since    1.0.0
     * @access   protected
     * @var      int    $cache_expiration    The cache expiration time in seconds.
     */
    protected $cache_expiration = 86400; // 24 hours
    
    /**
     * Whether caching is enabled.
     *
     * @since    1.0.0
     * @access   protected
     * @var      bool    $cache_enabled    Whether caching is enabled.
     */
    protected $cache_enabled = true;
    
    /**
     * Number of retry attempts for API requests.
     *
     * @since    1.0.0
     * @access   protected
     * @var      int    $retry_attempts    The number of retry attempts.
     */
    protected $retry_attempts = 3;
    
    /**
     * Delay between retry attempts in milliseconds.
     *
     * @since    1.0.0
     * @access   protected
     * @var      int    $retry_delay    The delay between retry attempts in milliseconds.
     */
    protected $retry_delay = 1000;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $api_key     The API key for the provider.
     * @param    string    $model       The model to use for embeddings.
     */
    public function __construct($api_key = null, $model = null) {
        $this->api_key = $api_key;
        $this->model = $model;
        $this->dimension = $this->get_dimension_for_model($model);
        
        // Get cache settings from options
        $this->cache_enabled = get_option('nlwp_enable_embedding_cache', 'yes') === 'yes';
        $this->cache_expiration = intval(get_option('nlwp_embedding_cache_expiration', 86400));
        
        // Get retry settings from options
        $this->retry_attempts = intval(get_option('nlwp_embedding_retry_attempts', 3));
        $this->retry_delay = intval(get_option('nlwp_embedding_retry_delay', 1000));
    }

    /**
     * Get embedding dimension for the specified model.
     *
     * @since    1.0.0
     * @param    string    $model    The model name.
     * @return   int                 The embedding dimension.
     */
    protected function get_dimension_for_model($model) {
        // Default dimension
        return 1536;
    }

    /**
     * Get embeddings for a text with caching.
     *
     * @since    1.0.0
     * @param    string    $text    The text to embed.
     * @return   array              The embedding vector.
     */
    public function get_embedding($text) {
        // Use caching if enabled
        if ($this->cache_enabled) {
            // Generate a unique cache key based on text, model, and provider class
            $cache_key = $this->generate_cache_key($text);
            
            // Check if we have a cached embedding
            $cached_embedding = $this->get_cached_embedding($cache_key);
            if ($cached_embedding !== false) {
                return $cached_embedding;
            }
            
            // No cache hit, generate new embedding
            $embedding = $this->generate_embedding($text);
            
            // Cache the embedding
            $this->cache_embedding($cache_key, $embedding);
            
            return $embedding;
        }
        
        // If caching is disabled, just generate the embedding
        return $this->generate_embedding($text);
    }
    
    /**
     * Generate a unique cache key for the given text.
     *
     * @since    1.0.0
     * @param    string    $text    The text to embed.
     * @return   string             The cache key.
     */
    protected function generate_cache_key($text) {
        // Using a truncated hash of the text, model name, and provider class
        // to create a unique but reasonably sized key
        $provider_class = get_class($this);
        $key_data = $text . '|' . $this->model . '|' . $provider_class;
        return 'nlwp_embedding_' . md5($key_data);
    }
    
    /**
     * Get a cached embedding.
     *
     * @since    1.0.0
     * @param    string    $cache_key    The cache key.
     * @return   array|bool              The embedding vector or false if not cached.
     */
    protected function get_cached_embedding($cache_key) {
        // Try to get from WordPress transient cache
        $cached_data = get_transient($cache_key);
        
        if ($cached_data === false) {
            return false;
        }
        
        return $cached_data;
    }
    
    /**
     * Cache an embedding.
     *
     * @since    1.0.0
     * @param    string    $cache_key     The cache key.
     * @param    array     $embedding     The embedding vector.
     * @return   bool                     Whether the value was set.
     */
    protected function cache_embedding($cache_key, $embedding) {
        // Store in WordPress transient cache
        return set_transient($cache_key, $embedding, $this->cache_expiration);
    }
    
    /**
     * Generate embeddings for a text via API.
     * To be implemented by child classes.
     *
     * @since    1.0.0
     * @param    string    $text    The text to embed.
     * @return   array              The embedding vector.
     */
    abstract protected function generate_embedding($text);
    
    /**
     * Execute a function with retry logic.
     *
     * @since    1.0.0
     * @param    callable    $func     The function to execute.
     * @param    array       $args     The arguments to pass to the function.
     * @return   mixed                 The result of the function.
     * @throws   Exception             If all retries fail.
     */
    protected function execute_with_retry($func, $args = array()) {
        $attempt = 0;
        $last_exception = null;
        
        while ($attempt < $this->retry_attempts) {
            try {
                return call_user_func_array($func, $args);
            } catch (Exception $e) {
                $last_exception = $e;
                
                // Log the error
                error_log('NLWP Embedding Error (Attempt ' . ($attempt + 1) . '): ' . $e->getMessage());
                
                // Check if error is retryable
                if (!$this->is_retryable_error($e->getMessage())) {
                    throw $e; // Non-retryable error, rethrow immediately
                }
                
                // Delay before retry (exponential backoff)
                $delay = $this->retry_delay * pow(2, $attempt);
                usleep($delay * 1000); // Convert to microseconds
                
                $attempt++;
            }
        }
        
        // If we reach here, all retries failed
        throw new Exception('Failed after ' . $this->retry_attempts . ' attempts. Last error: ' . $last_exception->getMessage());
    }
    
    /**
     * Check if an error is retryable.
     *
     * @since    1.0.0
     * @param    string    $error_message    The error message.
     * @return   bool                        Whether the error is retryable.
     */
    protected function is_retryable_error($error_message) {
        // Common retryable errors (rate limits, timeouts, temporary server issues)
        $retryable_patterns = array(
            '/rate limit/i',           // Rate limit errors
            '/too many requests/i',    // Rate limit errors
            '/timeout/i',              // Request timeouts
            '/connection/i',           // Connection issues
            '/503/i',                  // Service unavailable
            '/504/i',                  // Gateway timeout
            '/5[0-9]{2}/',             // Other 5xx errors
            '/socket/i',               // Socket errors
            '/temporary/i',            // Temporary issues
            '/overloaded/i',           // Server overload
            '/capacity/i',             // Capacity issues
        );
        
        foreach ($retryable_patterns as $pattern) {
            if (preg_match($pattern, $error_message)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get the embedding dimension.
     *
     * @since    1.0.0
     * @return   int    The embedding dimension.
     */
    public function get_dimension() {
        return $this->dimension;
    }
}