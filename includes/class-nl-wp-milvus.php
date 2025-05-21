<?php
/**
 * Handle Milvus Vector Database integration.
 *
 * @since      1.0.0
 */
class NL_WP_Milvus {

    /**
     * Milvus host.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $host    The Milvus host.
     */
    private $host;

    /**
     * Milvus port.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $port    The Milvus port.
     */
    private $port;

    /**
     * Collection name for WordPress content.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $collection    The Milvus collection name.
     */
    private $collection;

    /**
     * LLM provider for embeddings.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $llm_provider    The LLM provider.
     */
    private $llm_provider;

    /**
     * LLM API key.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $llm_api_key    The LLM API key.
     */
    private $llm_api_key;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $host           The Milvus host.
     * @param    string    $port           The Milvus port.
     * @param    string    $collection     The Milvus collection name.
     */
    public function __construct($host = null, $port = null, $collection = null) {
        $this->host = $host ?: get_option('nlwp_milvus_host', 'localhost');
        $this->port = $port ?: get_option('nlwp_milvus_port', '19530');
        $this->collection = $collection ?: get_option('nlwp_milvus_collection', 'wordpress_content');
        $this->llm_provider = get_option('nlwp_embedding_provider', 'openai');
        
        // Get the API key based on the selected provider
        switch ($this->llm_provider) {
            case 'openai':
                $this->llm_api_key = get_option('nlwp_openai_api_key', '');
                break;
            case 'anthropic':
                $this->llm_api_key = get_option('nlwp_anthropic_api_key', '');
                break;
            case 'gemini':
                $this->llm_api_key = get_option('nlwp_gemini_api_key', '');
                break;
            case 'ollama':
                $this->llm_api_key = ''; // Ollama doesn't use an API key
                break;
            default:
                $this->llm_api_key = get_option('nlwp_openai_api_key', '');
        }
    }

    /**
     * Create necessary Milvus collections if they don't exist.
     *
     * @since    1.0.0
     * @return   bool      Whether the collections were created successfully.
     */
    public function initialize_collections() {
        // This would normally use the Milvus PHP client
        // For now we'll use the Python cli via shell commands
        
        try {
            // Get the Python path from settings or use default
            $python_path = get_option('nlwp_python_path', 'python3');
            
            // Create a temporary Python script to initialize collections
            $temp_file = sys_get_temp_dir() . '/nlwp_milvus_init.py';
            
            $script_content = <<<PYTHON
import sys
from pymilvus import Collection, connections, utility, FieldSchema, CollectionSchema, DataType

# Connect to Milvus
connections.connect("default", host="{$this->host}", port="{$this->port}")

# Check if collection exists
if not utility.has_collection("{$this->collection}"):
    # Define fields for the collection
    fields = [
        FieldSchema(name="id", dtype=DataType.INT64, is_primary=True, auto_id=False),
        FieldSchema(name="wp_id", dtype=DataType.INT64),
        FieldSchema(name="wp_type", dtype=DataType.VARCHAR, max_length=50),
        FieldSchema(name="title", dtype=DataType.VARCHAR, max_length=500),
        FieldSchema(name="content", dtype=DataType.VARCHAR, max_length=65535),
        FieldSchema(name="url", dtype=DataType.VARCHAR, max_length=500),
        FieldSchema(name="embedding", dtype=DataType.FLOAT_VECTOR, dim=1536)
    ]
    
    # Create collection schema
    schema = CollectionSchema(fields, "WordPress content collection")
    
    # Create collection
    collection = Collection(name="{$this->collection}", schema=schema)
    
    # Create index on the embedding field
    index_params = {
        "metric_type": "L2",
        "index_type": "HNSW",
        "params": {"M": 8, "efConstruction": 64}
    }
    collection.create_index("embedding", index_params)
    
    print("Collection created successfully")
else:
    print("Collection already exists")

connections.disconnect("default")
sys.exit(0)
PYTHON;

            file_put_contents($temp_file, $script_content);
            
            // Execute the Python script
            $command = escapeshellcmd("$python_path $temp_file");
            $output = shell_exec($command);
            
            // Clean up
            unlink($temp_file);
            
            return true;
        } catch (Exception $e) {
            error_log('NLWP Milvus Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get content embedding using the configured LLM provider.
     *
     * @since    1.0.0
     * @param    string    $text     The text to embed.
     * @return   array               The embedding vector.
     */
    private function get_embedding($text) {
        // Truncate text if it's too long
        $text = substr($text, 0, 8000);
        
        switch ($this->llm_provider) {
            case 'openai':
                return $this->get_openai_embedding($text);
            case 'anthropic':
                return $this->get_anthropic_embedding($text);
            default:
                return $this->get_openai_embedding($text); // Default to OpenAI
        }
    }

    /**
     * Get OpenAI embedding for text.
     *
     * @since    1.0.0
     * @param    string    $text     The text to embed.
     * @return   array               The embedding vector.
     */
    private function get_openai_embedding($text) {
        $api_key = $this->llm_api_key;
        
        // Double-check that we have an OpenAI API key
        if (empty($api_key)) {
            // Try to get the API key directly from the option
            $api_key = get_option('nlwp_openai_api_key', '');
            
            // If it's still empty, throw an exception
            if (empty($api_key)) {
                throw new Exception('OpenAI API key is required');
            }
            
            // Update the instance property
            $this->llm_api_key = $api_key;
        }
        
        $url = 'https://api.openai.com/v1/embeddings';
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        );
        
        $data = array(
            'model' => 'text-embedding-3-small',
            'input' => $text
        );
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('OpenAI API error: ' . $response);
        }
        
        $response_data = json_decode($response, true);
        
        if (isset($response_data['data'][0]['embedding'])) {
            return $response_data['data'][0]['embedding'];
        } else {
            throw new Exception('Invalid response from OpenAI API');
        }
    }

    /**
     * Get Anthropic embedding for text.
     *
     * @since    1.0.0
     * @param    string    $text     The text to embed.
     * @return   array               The embedding vector.
     */
    private function get_anthropic_embedding($text) {
        $api_key = $this->llm_api_key;
        
        // Double-check that we have an Anthropic API key
        if (empty($api_key)) {
            // Try to get the API key directly from the option
            $api_key = get_option('nlwp_anthropic_api_key', '');
            
            // If it's still empty, throw an exception
            if (empty($api_key)) {
                throw new Exception('Anthropic API key is required');
            }
            
            // Update the instance property
            $this->llm_api_key = $api_key;
        }
        
        $url = 'https://api.anthropic.com/v1/embeddings';
        $headers = array(
            'Content-Type: application/json',
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01'
        );
        
        $data = array(
            'model' => 'claude-3-haiku-20240307',
            'input' => $text
        );
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('Anthropic API error: ' . $response);
        }
        
        $response_data = json_decode($response, true);
        
        if (isset($response_data['embedding'])) {
            return $response_data['embedding'];
        } else {
            throw new Exception('Invalid response from Anthropic API');
        }
    }

    /**
     * Ingest WordPress content into Milvus.
     *
     * @since    1.0.0
     * @param    string    $post_type    The post type to ingest.
     * @param    int       $limit        Maximum number of posts to ingest.
     * @param    int       $offset       Offset for pagination.
     * @return   array                   Status information about the ingestion.
     */
    public function ingest_content($post_type = 'post', $limit = 100, $offset = 0) {
        // Initialize collections if needed
        $this->initialize_collections();
        
        // Create a Python script to handle the ingest
        $temp_script = sys_get_temp_dir() . '/nlwp_milvus_ingest.py';
        $temp_data = sys_get_temp_dir() . '/nlwp_content_data.json';
        
        // Get the posts to ingest
        $args = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        $query = new WP_Query($args);
        $posts = $query->posts;
        $total_posts = $query->found_posts;
        
        if (empty($posts)) {
            return array(
                'status' => 'error',
                'message' => 'No posts found',
                'total' => 0,
                'processed' => 0
            );
        }
        
        // Prepare data in Schema.org format
        $content_data = array();
        $counter = 0;
        
        foreach ($posts as $post) {
            // Get post data
            $post_id = $post->ID;
            $title = $post->post_title;
            
            // Process content: first apply shortcodes then strip tags
            $processed_content = do_shortcode($post->post_content);
            $content = wp_strip_all_tags($processed_content);
            
            // Clean up any remaining shortcode tags that weren't processed
            $content = preg_replace('/\[\/?[a-zA-Z0-9_\-]+( [^\]]+)?\]/', '', $content);
            
            $url = get_permalink($post_id);
            $post_type = $post->post_type;
            
            // Get metadata for structured data
            // Process excerpt properly
            $excerpt = get_the_excerpt($post_id);
            if (empty(trim($excerpt))) {
                // If no excerpt, create one from processed content
                $excerpt = wp_trim_words($content, 55, '...');
                // Clean up any remaining shortcode tags
                $excerpt = preg_replace('/\[\/?[a-zA-Z0-9_\-]+( [^\]]+)?\]/', '', $excerpt);
            } else {
                // Make sure excerpt is also processed for shortcodes
                $excerpt = wp_strip_all_tags(do_shortcode($excerpt));
                // Clean up any remaining shortcode tags
                $excerpt = preg_replace('/\[\/?[a-zA-Z0-9_\-]+( [^\]]+)?\]/', '', $excerpt);
            }
            
            $schema_data = array(
                '@context' => 'https://schema.org',
                '@type' => $this->get_schema_type($post_type),
                'mainEntityOfPage' => array(
                    '@type' => 'WebPage',
                    '@id' => $url
                ),
                'headline' => $title,
                'url' => $url,
                'datePublished' => $post->post_date,
                'dateModified' => $post->post_modified,
                'author' => array(
                    '@type' => 'Person',
                    'name' => get_the_author_meta('display_name', $post->post_author)
                ),
                'description' => $excerpt
            );
            
            // Add featured image if available
            if (has_post_thumbnail($post_id)) {
                $image_url = get_the_post_thumbnail_url($post_id, 'full');
                $schema_data['image'] = $image_url;
            }
            
            // Get the embedding for title and content
            try {
                // Make sure there are no remaining shortcode patterns before embedding
                $clean_content = preg_replace('/\[\/?[a-zA-Z0-9_\-]+( [^\]]+)?\]/', '', $content);
                $embedding_text = $title . "\n\n" . $clean_content;
                $embedding = $this->get_embedding($embedding_text);
                
                // Add to data array
                $content_data[] = array(
                    'id' => $counter + 1,
                    'wp_id' => $post_id,
                    'wp_type' => $post_type,
                    'title' => $title,
                    'content' => $content,
                    'url' => $url,
                    'embedding' => $embedding,
                    'schema_data' => $schema_data
                );
                
                $counter++;
            } catch (Exception $e) {
                error_log('NLWP Embedding Error: ' . $e->getMessage());
                // Continue with next post
            }
        }
        
        // Save data to temporary file
        file_put_contents($temp_data, json_encode($content_data));
        
        // Create Python script to insert data into Milvus
        $script_content = <<<PYTHON
import sys
import json
from pymilvus import Collection, connections

# Load data from file
with open("{$temp_data}", "r") as f:
    data = json.load(f)

if not data:
    print("No data to insert")
    sys.exit(1)

# Connect to Milvus
connections.connect("default", host="{$this->host}", port="{$this->port}")

# Get the collection
collection = Collection("{$this->collection}")
collection.load()

# Prepare data for insertion
ids = [item["id"] for item in data]
wp_ids = [item["wp_id"] for item in data]
wp_types = [item["wp_type"] for item in data]
titles = [item["title"] for item in data]
contents = [item["content"] for item in data]
urls = [item["url"] for item in data]
embeddings = [item["embedding"] for item in data]

# Insert data
insert_result = collection.insert([
    ids,
    wp_ids,
    wp_types,
    titles,
    contents,
    urls,
    embeddings
])

# Get the count of inserted entities
print(f"Inserted {len(ids)} items into Milvus")

# Disconnect
connections.disconnect("default")
sys.exit(0)
PYTHON;

        file_put_contents($temp_script, $script_content);
        
        // Execute the Python script
        $python_path = get_option('nlwp_python_path', 'python3');
        $command = escapeshellcmd("$python_path $temp_script");
        $output = shell_exec($command);
        
        // Clean up
        unlink($temp_script);
        unlink($temp_data);
        
        // Return status
        return array(
            'status' => 'success',
            'message' => 'Content ingested successfully',
            'total' => $total_posts,
            'processed' => $counter,
            'output' => $output
        );
    }

    /**
     * Map WordPress post types to Schema.org types.
     *
     * @since    1.0.0
     * @param    string    $post_type    The WordPress post type.
     * @return   string                  The Schema.org type.
     */
    private function get_schema_type($post_type) {
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

    /**
     * Search Milvus for content matching a query.
     *
     * @since    1.0.0
     * @param    string    $query        The search query.
     * @param    array     $params       Additional search parameters.
     * @return   array                   The search results.
     */
    public function search($query, $params = array()) {
        // Get embedding for the query
        try {
            $embedding = $this->get_embedding($query);
        } catch (Exception $e) {
            error_log('NLWP Embedding Error: ' . $e->getMessage());
            return array();
        }
        
        // Create Python script to search Milvus
        $temp_script = sys_get_temp_dir() . '/nlwp_milvus_search.py';
        $temp_query = sys_get_temp_dir() . '/nlwp_query_embedding.json';
        $temp_results = sys_get_temp_dir() . '/nlwp_search_results.json';
        
        // Set search parameters
        $limit = isset($params['limit']) ? intval($params['limit']) : 10;
        $site = isset($params['site']) ? $params['site'] : '';
        $post_type = isset($params['post_type']) ? $params['post_type'] : '';
        
        // Save query embedding to file
        file_put_contents($temp_query, json_encode($embedding));
        
        // Create Python script to search Milvus
        $script_content = <<<PYTHON
import sys
import json
from pymilvus import Collection, connections

# Load query embedding from file
with open("{$temp_query}", "r") as f:
    query_embedding = json.load(f)

# Connect to Milvus
connections.connect("default", host="{$this->host}", port="{$this->port}")

# Get the collection
collection = Collection("{$this->collection}")
collection.load()

# Search parameters
search_params = {
    "metric_type": "L2",
    "params": {"ef": 32}
}

# Search vectors
results = collection.search(
    data=[query_embedding],
    anns_field="embedding",
    param=search_params,
    limit={$limit},
    output_fields=["id", "wp_id", "wp_type", "title", "content", "url"]
)

# Format results
formatted_results = []
for hits in results:
    for hit in hits:
        formatted_results.append({
            "id": hit.id,
            "score": hit.score,
            "wp_id": hit.entity.get("wp_id"),
            "wp_type": hit.entity.get("wp_type"),
            "title": hit.entity.get("title"),
            "content": hit.entity.get("content"),
            "url": hit.entity.get("url")
        })

# Save results to file
with open("{$temp_results}", "w") as f:
    json.dump(formatted_results, f)

# Disconnect
connections.disconnect("default")
sys.exit(0)
PYTHON;

        file_put_contents($temp_script, $script_content);
        
        // Execute the Python script
        $python_path = get_option('nlwp_python_path', 'python3');
        $command = escapeshellcmd("$python_path $temp_script");
        $output = shell_exec($command);
        
        // Read results
        $results = array();
        if (file_exists($temp_results)) {
            $results_json = file_get_contents($temp_results);
            $results_data = json_decode($results_json, true);
            
            if (!empty($results_data)) {
                foreach ($results_data as $result) {
                    // Get the post to generate schema.org data
                    $post = get_post($result['wp_id']);
                    
                    if ($post) {
                        // Create schema.org object
                        $schema_object = array(
                            '@context' => 'https://schema.org',
                            '@type' => $this->get_schema_type($result['wp_type']),
                            'mainEntityOfPage' => array(
                                '@type' => 'WebPage',
                                '@id' => $result['url']
                            ),
                            'headline' => $result['title'],
                            'url' => $result['url'],
                            'datePublished' => $post->post_date,
                            'dateModified' => $post->post_modified,
                            'author' => array(
                                '@type' => 'Person',
                                'name' => get_the_author_meta('display_name', $post->post_author)
                            ),
                            'description' => get_the_excerpt($post->ID)
                        );
                        
                        // Add featured image if available
                        if (has_post_thumbnail($post->ID)) {
                            $image_url = get_the_post_thumbnail_url($post->ID, 'full');
                            $schema_object['image'] = $image_url;
                        }
                        
                        // Generate a snippet or description for this result
                        $snippet = $this->generate_snippet($result['content'], $query);
                        
                        // Format the result according to NLWeb format
                        // Final cleanup to ensure no shortcode tags remain in the snippet
                        $clean_snippet = preg_replace('/\[\/?[a-zA-Z0-9_\-]+( [^\]]+)?\]/', '', $snippet);
                        
                        $results[] = array(
                            'url' => $result['url'],
                            'name' => $result['title'],
                            'site' => get_bloginfo('name'),
                            'score' => 1 - ($result['score'] / 20), // Convert distance to similarity score
                            'description' => $clean_snippet,
                            'schema_object' => $schema_object
                        );
                    }
                }
            }
            
            // Clean up results file
            unlink($temp_results);
        }
        
        // Clean up other temp files
        unlink($temp_script);
        unlink($temp_query);
        
        return $results;
    }

    /**
     * Generate a snippet or description for a search result.
     *
     * @since    1.0.0
     * @param    string    $content    The content to snippet.
     * @param    string    $query      The search query.
     * @return   string                The generated snippet.
     */
    private function generate_snippet($content, $query) {
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
            // Make sure we don't have shortcode tags in the output
            $clean_content = preg_replace('/\[\/?[a-zA-Z0-9_\-]+( [^\]]+)?\]/', '', $content);
            return substr($clean_content, 0, 160) . '...';
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
        
        // Clean up any remaining shortcode tags
        $snippet = preg_replace('/\[\/?[a-zA-Z0-9_\-]+( [^\]]+)?\]/', '', $snippet);
        
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
     * Clear all content from the Milvus collection.
     *
     * @since    1.0.0
     * @return   array     Status information about the clearing operation.
     */
    public function clear_database() {
        // Create a Python script to handle clearing the collection
        $temp_script = sys_get_temp_dir() . '/nlwp_milvus_clear.py';
        
        $script_content = <<<PYTHON
import sys
from pymilvus import Collection, connections, utility

# Connect to Milvus
connections.connect("default", host="{$this->host}", port="{$this->port}")

# Check if collection exists
if utility.has_collection("{$this->collection}"):
    # Get the collection
    collection = Collection("{$this->collection}")
    
    # Get the number of entities before deletion
    before_count = collection.num_entities
    
    # Drop the collection
    collection.drop()
    
    print(f"Collection dropped successfully. Removed {before_count} entities.")
else:
    print("Collection does not exist")

# Disconnect
connections.disconnect("default")
sys.exit(0)
PYTHON;

        file_put_contents($temp_script, $script_content);
        
        // Execute the Python script
        $python_path = get_option('nlwp_python_path', 'python3');
        $command = escapeshellcmd("$python_path $temp_script");
        $output = shell_exec($command);
        
        // Clean up
        unlink($temp_script);
        
        // Reinitialize collections
        $this->initialize_collections();
        
        return array(
            'status' => 'success',
            'message' => 'Database cleared successfully',
            'output' => $output
        );
    }
}