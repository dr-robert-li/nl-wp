<?php
/**
 * Factory class for creating Vector DB instances.
 *
 * @since      1.0.0
 */
class NL_WP_Factory {

    /**
     * Create a Vector DB instance based on the selected provider.
     *
     * @since    1.0.0
     * @param    string    $provider    The vector database provider.
     * @param    array     $config      Optional configuration parameters.
     * @return   NL_WP_Vector_DB       The vector database instance.
     */
    public static function create_vector_db($provider = null, $config = array()) {
        // If no provider specified, get from settings
        if (!$provider) {
            $provider = get_option('nlwp_vector_db_provider', 'milvus');
        }
        
        // Ensure required files are loaded
        self::load_vector_db_classes();
        
        // Create the appropriate vector database instance
        switch ($provider) {
            case 'milvus':
                return new NL_WP_Milvus_DB($config);
                
            case 'chroma':
                return new NL_WP_Chroma_DB($config);
                
            case 'qdrant':
                return new NL_WP_Qdrant_DB($config);
                
            case 'pinecone':
                return new NL_WP_Pinecone_DB($config);
                
            case 'weaviate':
                return new NL_WP_Weaviate_DB($config);
                
            default:
                // Default to Milvus
                return new NL_WP_Milvus_DB($config);
        }
    }

    /**
     * Load all vector database classes.
     *
     * @since    1.0.0
     */
    private static function load_vector_db_classes() {
        // Load the abstract base class
        require_once NLWP_PLUGIN_DIR . 'includes/class-nl-wp-vector-db.php';
        
        // Load the specific implementations
        require_once NLWP_PLUGIN_DIR . 'includes/vector-db/class-nl-wp-milvus.php';
        require_once NLWP_PLUGIN_DIR . 'includes/vector-db/class-nl-wp-chroma.php';
        require_once NLWP_PLUGIN_DIR . 'includes/vector-db/class-nl-wp-qdrant.php';
        require_once NLWP_PLUGIN_DIR . 'includes/vector-db/class-nl-wp-pinecone.php';
        require_once NLWP_PLUGIN_DIR . 'includes/vector-db/class-nl-wp-weaviate.php';
    }

    /**
     * Get a list of available vector database providers.
     *
     * @since    1.0.0
     * @return   array     List of available providers.
     */
    public static function get_available_providers() {
        return array(
            'milvus' => 'Milvus',
            'chroma' => 'ChromaDB',
            'qdrant' => 'Qdrant',
            'pinecone' => 'Pinecone',
            'weaviate' => 'Weaviate'
        );
    }
}