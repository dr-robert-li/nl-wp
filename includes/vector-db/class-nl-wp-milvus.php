<?php
/**
 * Milvus Vector Database Handler.
 *
 * @since      1.0.0
 */
class NL_WP_Milvus_DB extends NL_WP_Vector_DB {

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
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    array     $config    Configuration parameters.
     */
    public function __construct($config = array()) {
        parent::__construct($config);
        
        $this->host = isset($config['host']) ? $config['host'] : get_option('nlwp_milvus_host', 'localhost');
        $this->port = isset($config['port']) ? $config['port'] : get_option('nlwp_milvus_port', '19530');
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
            
            // Get the embedding dimension from the provider
            $dimension = $this->embedding_provider->get_dimension();
            
            // Create a temporary Python script to initialize collections
            $temp_file = sys_get_temp_dir() . '/nlwp_milvus_init.py';
            
            $script_content = <<<PYTHON
import sys
from pymilvus import Collection, connections, utility, FieldSchema, CollectionSchema, DataType

# Connect to Milvus
connections.connect("default", host="{$this->host}", port="{$this->port}")

# Check if collection exists
if utility.has_collection("{$this->collection}"):
    # Check if we need to recreate the collection due to dimension change
    collection = Collection("{$this->collection}")
    schema = collection.schema
    current_dim = None
    
    # Find the embedding field to check its dimension
    for field in schema.fields:
        if field.name == "embedding" and field.dtype == DataType.FLOAT_VECTOR:
            current_dim = field.params['dim']
            break
    
    # If dimension has changed, we need to recreate the collection
    if current_dim != {$dimension}:
        print(f"Embedding dimension changed from {current_dim} to {$dimension}. Recreating collection.")
        collection.drop()
    else:
        print("Collection already exists with correct dimensions")
        connections.disconnect("default")
        sys.exit(0)

# Create collection if it doesn't exist or was dropped due to dimension change
if not utility.has_collection("{$this->collection}"):
    # Define fields for the collection
    fields = [
        FieldSchema(name="id", dtype=DataType.INT64, is_primary=True, auto_id=False),
        FieldSchema(name="wp_id", dtype=DataType.INT64),
        FieldSchema(name="wp_type", dtype=DataType.VARCHAR, max_length=50),
        FieldSchema(name="title", dtype=DataType.VARCHAR, max_length=500),
        FieldSchema(name="content", dtype=DataType.VARCHAR, max_length=65535),
        FieldSchema(name="url", dtype=DataType.VARCHAR, max_length=500),
        FieldSchema(name="embedding", dtype=DataType.FLOAT_VECTOR, dim={$dimension})
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
    
    print("Collection created successfully with dimension {$dimension}")

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
                        $results[] = array(
                            'url' => $result['url'],
                            'name' => $result['title'],
                            'site' => get_bloginfo('name'),
                            'score' => 1 - ($result['score'] / 20), // Convert distance to similarity score
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