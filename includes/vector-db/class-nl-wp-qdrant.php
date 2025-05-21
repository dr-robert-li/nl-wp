<?php
/**
 * Qdrant Vector Database Handler.
 *
 * @since      1.0.0
 */
class NL_WP_Qdrant_DB extends NL_WP_Vector_DB {

    /**
     * Qdrant host.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $host    The Qdrant host.
     */
    private $host;

    /**
     * Qdrant port.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $port    The Qdrant port.
     */
    private $port;

    /**
     * Qdrant API key (if any).
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $api_key    The Qdrant API key.
     */
    private $api_key;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    array     $config    Configuration parameters.
     */
    public function __construct($config = array()) {
        parent::__construct($config);
        
        $this->host = isset($config['host']) ? $config['host'] : get_option('nlwp_qdrant_host', 'localhost');
        $this->port = isset($config['port']) ? $config['port'] : get_option('nlwp_qdrant_port', '6333');
        $this->api_key = isset($config['api_key']) ? $config['api_key'] : get_option('nlwp_qdrant_api_key', '');
    }

    /**
     * Create necessary Qdrant collections if they don't exist.
     *
     * @since    1.0.0
     * @return   bool      Whether the collections were created successfully.
     */
    public function initialize_collections() {
        try {
            // Get the Python path from settings or use default
            $python_path = get_option('nlwp_python_path', 'python3');
            
            // Get the embedding dimension from the provider
            $dimension = $this->embedding_provider->get_dimension();
            
            // Create a temporary Python script to initialize collections
            $temp_file = sys_get_temp_dir() . '/nlwp_qdrant_init.py';
            
            $script_content = <<<PYTHON
import sys
from qdrant_client import QdrantClient
from qdrant_client.http import models

# Connect to Qdrant
client = QdrantClient(host="{$this->host}", port={$this->port}, api_key="{$this->api_key}" or None)

# Check if collection exists
collections = client.get_collections()
collection_exists = "{$this->collection}" in [c.name for c in collections.collections]

if collection_exists:
    # Check if we need to recreate the collection due to dimension change
    collection_info = client.get_collection("{$this->collection}")
    current_dim = collection_info.config.params.vectors.size
    
    # If dimension has changed, we need to recreate the collection
    if current_dim != {$dimension}:
        print(f"Embedding dimension changed from {current_dim} to {$dimension}. Recreating collection.")
        client.delete_collection(collection_name="{$this->collection}")
        collection_exists = False
    else:
        print("Collection already exists with correct dimensions")

if not collection_exists:
    # Create a new collection with the appropriate vector size and distance metric
    client.create_collection(
        collection_name="{$this->collection}",
        vectors_config=models.VectorParams(
            size={$dimension},  # Size from the embedding provider
            distance=models.Distance.COSINE
        ),
        optimizers_config=models.OptimizersConfigDiff(
            indexing_threshold=10000
        )
    )
    
    # Create field index for faster filtering
    client.create_payload_index(
        collection_name="{$this->collection}",
        field_name="wp_type",
        field_schema=models.PayloadSchemaType.KEYWORD
    )
    
    print("Collection created successfully with dimension {$dimension}")

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
            error_log('NLWP Qdrant Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Ingest WordPress content into Qdrant.
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
        $temp_script = sys_get_temp_dir() . '/nlwp_qdrant_ingest.py';
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
            $content = wp_strip_all_tags($post->post_content);
            $url = get_permalink($post_id);
            $post_type = $post->post_type;
            
            // Get metadata for structured data
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
                'description' => get_the_excerpt($post_id)
            );
            
            // Add featured image if available
            if (has_post_thumbnail($post_id)) {
                $image_url = get_the_post_thumbnail_url($post_id, 'full');
                $schema_data['image'] = $image_url;
            }
            
            // Get the embedding for title and content
            try {
                $embedding_text = $title . "\n\n" . $content;
                $embedding = $this->get_embedding($embedding_text);
                
                // Add to data array
                $content_data[] = array(
                    'id' => $post_id,
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
        
        // Create Python script to insert data into Qdrant
        $script_content = <<<PYTHON
import sys
import json
from qdrant_client import QdrantClient
from qdrant_client.http import models

# Load data from file
with open("{$temp_data}", "r") as f:
    data = json.load(f)

if not data:
    print("No data to insert")
    sys.exit(1)

# Connect to Qdrant
client = QdrantClient(host="{$this->host}", port={$this->port}, api_key="{$this->api_key}" or None)

# Prepare points for insertion
points = [
    models.PointStruct(
        id=item["id"],
        vector=item["embedding"],
        payload={
            "wp_id": item["wp_id"],
            "wp_type": item["wp_type"],
            "title": item["title"],
            "content": item["content"],
            "url": item["url"],
            "schema_type": item["schema_data"]["@type"]
        }
    )
    for item in data
]

# Insert points in batches
batch_size = 100
for i in range(0, len(points), batch_size):
    batch_points = points[i:i+batch_size]
    client.upsert(
        collection_name="{$this->collection}",
        points=batch_points
    )

print(f"Inserted {len(points)} items into Qdrant")
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
     * Search Qdrant for content matching a query.
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
        
        // Create Python script to search Qdrant
        $temp_script = sys_get_temp_dir() . '/nlwp_qdrant_search.py';
        $temp_query = sys_get_temp_dir() . '/nlwp_query_embedding.json';
        $temp_results = sys_get_temp_dir() . '/nlwp_search_results.json';
        
        // Set search parameters
        $limit = isset($params['limit']) ? intval($params['limit']) : 10;
        $site = isset($params['site']) ? $params['site'] : '';
        $post_type = isset($params['post_type']) ? $params['post_type'] : '';
        
        // Save query embedding to file
        file_put_contents($temp_query, json_encode($embedding));
        
        // Create Python script to search Qdrant
        $script_content = <<<PYTHON
import sys
import json
from qdrant_client import QdrantClient
from qdrant_client.http import models

# Load query embedding from file
with open("{$temp_query}", "r") as f:
    query_embedding = json.load(f)

# Connect to Qdrant
client = QdrantClient(host="{$this->host}", port={$this->port}, api_key="{$this->api_key}" or None)

# Set up filter if post type is specified
filter_condition = None
if "{$post_type}":
    filter_condition = models.Filter(
        must=[
            models.FieldCondition(
                key="wp_type",
                match=models.MatchValue(value="{$post_type}")
            )
        ]
    )

# Search vectors
search_results = client.search(
    collection_name="{$this->collection}",
    query_vector=query_embedding,
    limit={$limit},
    filter=filter_condition,
    with_payload=True
)

# Format results
formatted_results = []
for hit in search_results:
    formatted_results.append({
        "id": hit.id,
        "score": hit.score,
        "wp_id": hit.payload["wp_id"],
        "wp_type": hit.payload["wp_type"],
        "title": hit.payload["title"],
        "content": hit.payload["content"],
        "url": hit.payload["url"]
    })

# Save results to file
with open("{$temp_results}", "w") as f:
    json.dump(formatted_results, f)

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
                        $results[] = array(
                            'url' => $result['url'],
                            'name' => $result['title'],
                            'site' => get_bloginfo('name'),
                            'score' => $result['score'], // Qdrant already provides a similarity score
                            'description' => $snippet,
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
     * Clear all content from the Qdrant collection.
     *
     * @since    1.0.0
     * @return   array     Status information about the clearing operation.
     */
    public function clear_database() {
        // Create a Python script to handle clearing the collection
        $temp_script = sys_get_temp_dir() . '/nlwp_qdrant_clear.py';
        
        $script_content = <<<PYTHON
import sys
from qdrant_client import QdrantClient
from qdrant_client.http import models

# Connect to Qdrant
client = QdrantClient(host="{$this->host}", port={$this->port}, api_key="{$this->api_key}" or None)

# Check if collection exists
collections = client.get_collections()
collection_exists = "{$this->collection}" in [c.name for c in collections.collections]

if collection_exists:
    # Get collection info
    collection_info = client.get_collection("{$this->collection}")
    point_count = collection_info.points_count
    
    # Delete the collection
    client.delete_collection(collection_name="{$this->collection}")
    
    print(f"Collection dropped successfully. Removed {point_count} entities.")
else:
    print("Collection does not exist")

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