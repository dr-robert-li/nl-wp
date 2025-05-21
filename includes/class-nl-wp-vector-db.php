<?php
/**
 * Abstract Vector Database Handler.
 *
 * This class serves as a base for all vector database implementations.
 *
 * @since      1.0.0
 */
abstract class NL_WP_Vector_DB {

    /**
     * Embedding provider.
     *
     * @since    1.0.0
     * @access   protected
     * @var      NL_WP_Embedding_Provider    $embedding_provider    The embedding provider.
     */
    protected $embedding_provider;

    /**
     * Collection/Index name for WordPress content.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $collection    The collection/index name.
     */
    protected $collection;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    array     $config        Configuration parameters.
     */
    public function __construct($config = array()) {
        $this->collection = isset($config['collection']) ? $config['collection'] : 'wordpress_content';
        
        // Get embedding provider and model from config or settings
        $provider = isset($config['embedding_provider']) 
            ? $config['embedding_provider'] 
            : get_option('nlwp_embedding_provider', 'openai');
            
        $model = isset($config['embedding_model']) 
            ? $config['embedding_model'] 
            : get_option('nlwp_embedding_model', '');
        
        // Create embedding provider
        require_once NLWP_PLUGIN_DIR . 'includes/class-nl-wp-embedding-factory.php';
        $this->embedding_provider = NL_WP_Embedding_Factory::create_provider($provider, $model, $config);
    }

    /**
     * Initialize database collections/indices.
     *
     * @since    1.0.0
     * @return   bool      Whether the initialization was successful.
     */
    abstract public function initialize_collections();

    /**
     * Ingest WordPress content into the vector database.
     *
     * @since    1.0.0
     * @param    string    $post_type    The post type to ingest.
     * @param    int       $limit        Maximum number of posts to ingest.
     * @param    int       $offset       Offset for pagination.
     * @return   array                   Status information about the ingestion.
     */
    abstract public function ingest_content($post_type = 'post', $limit = 100, $offset = 0);

    /**
     * Search for content matching a query.
     *
     * @since    1.0.0
     * @param    string    $query        The search query.
     * @param    array     $params       Additional search parameters.
     * @return   array                   The search results.
     */
    abstract public function search($query, $params = array());

    /**
     * Clear all content from the database.
     *
     * @since    1.0.0
     * @return   array     Status information about the clearing operation.
     */
    abstract public function clear_database();

    /**
     * Get content embedding using the configured embedding provider.
     *
     * @since    1.0.0
     * @param    string    $text     The text to embed.
     * @return   array               The embedding vector.
     */
    protected function get_embedding($text) {
        return $this->embedding_provider->get_embedding($text);
    }

    /**
     * Generate a snippet or description for a search result.
     *
     * @since    1.0.0
     * @param    string    $content    The content to snippet.
     * @param    string    $query      The search query.
     * @return   string                The generated snippet.
     */
    protected function generate_snippet($content, $query) {
        // Simple snippet generation - find a relevant excerpt
        $words = explode(' ', strtolower($query));
        $content_lower = strtolower($content);
        
        // Find the first occurrence of any query word
        $best_pos = -1;
        $best_word = '';
        
        foreach ($words as $word) {
            if (strlen($word) < 3) continue; // Skip short words
            
            $pos = strpos($content_lower, $word);
            if ($pos !== false && ($best_pos === -1 || $pos < $best_pos)) {
                $best_pos = $pos;
                $best_word = $word;
            }
        }
        
        // If no match found, just return the first part of the content
        if ($best_pos === -1) {
            return substr($content, 0, 160) . '...';
        }
        
        // Calculate snippet start and end positions
        $start = max(0, $best_pos - 60);
        $end = min(strlen($content), $best_pos + 100);
        
        // Adjust to word boundaries
        while ($start > 0 && $content[$start] !== ' ') {
            $start--;
        }
        
        while ($end < strlen($content) && $content[$end] !== ' ') {
            $end++;
        }
        
        // Extract the snippet
        $snippet = substr($content, $start, $end - $start);
        
        // Add ellipsis if needed
        if ($start > 0) {
            $snippet = '...' . $snippet;
        }
        
        if ($end < strlen($content)) {
            $snippet .= '...';
        }
        
        return $snippet;
    }

    /**
     * Map WordPress post types to Schema.org types.
     *
     * @since    1.0.0
     * @param    string    $post_type    The WordPress post type.
     * @return   string                  The Schema.org type.
     */
    protected function get_schema_type($post_type) {
        $type_mapping = array(
            'post' => 'Article',
            'page' => 'WebPage',
            'product' => 'Product',
            'event' => 'Event',
            'recipe' => 'Recipe',
            'course' => 'Course',
            'book' => 'Book',
            'movie' => 'Movie',
            'restaurant' => 'Restaurant',
            'service' => 'Service'
        );
        
        return isset($type_mapping[$post_type]) ? $type_mapping[$post_type] : 'Article';
    }
}