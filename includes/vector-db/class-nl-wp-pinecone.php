<?php
/**
 * Pinecone Vector Database Handler.
 *
 * @since      1.0.0
 */
class NL_WP_Pinecone_DB extends NL_WP_Vector_DB {

    /**
     * Pinecone API key.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $api_key    The Pinecone API key.
     */
    private $api_key;

    /**
     * Pinecone environment.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $environment    The Pinecone environment.
     */
    private $environment;

    /**
     * Pinecone index name.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $index    The Pinecone index name.
     */
    private $index;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    array     $config    Configuration parameters.
     */
    public function __construct($config = array()) {
        parent::__construct($config);
        
        $this->api_key = isset($config['api_key']) ? $config['api_key'] : get_option('nlwp_pinecone_api_key', '');
        $this->environment = isset($config['environment']) ? $config['environment'] : get_option('nlwp_pinecone_environment', 'us-west4-gcp');
        $this->index = isset($config['index']) ? $config['index'] : get_option('nlwp_pinecone_index', 'wordpress-content');
    }

    /**
     * Create necessary Pinecone indices if they don't exist.
     *
     * @since    1.0.0
     * @return   bool      Whether the indices were created successfully.
     */
    public function initialize_collections() {
        try {
            // Get the Python path from settings or use default
            $python_path = get_option('nlwp_python_path', 'python3');
            
            // Create a temporary Python script to initialize indices
            $temp_file = sys_get_temp_dir() . '/nlwp_pinecone_init.py';
            
            $script_content = <<<PYTHON
import sys
import pinecone
from pinecone import ServerlessSpec

# Initialize Pinecone
pinecone.init(api_key="{$this->api_key}", environment="{$this->environment}")

# Check if index exists
existing_indexes = pinecone.list_indexes()

if "{$this->index}" not in existing_indexes:
    # Create the index
    pinecone.create_index(
        name="{$this->index}",
        dimension=1536,  # OpenAI embedding dimension
        metric="cosine",
        spec=ServerlessSpec(
            cloud="aws", 
            region="us-west-2"
        )
    )
    print("Index created successfully")
else:
    print("Index already exists")

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
            error_log('NLWP Pinecone Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Ingest WordPress content into Pinecone.
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
        $temp_script = sys_get_temp_dir() . '/nlwp_pinecone_ingest.py';
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
                    'id' => "wp-" . $post_id, // Pinecone requires string IDs
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
        
        // Create Python script to insert data into Pinecone
        $script_content = <<<PYTHON
import sys
import json
import pinecone

# Load data from file
with open("{$temp_data}", "r") as f:
    data = json.load(f)

if not data:
    print("No data to insert")
    sys.exit(1)

# Initialize Pinecone
pinecone.init(api_key="{$this->api_key}", environment="{$this->environment}")

# Connect to index
index = pinecone.Index("{$this->index}")

# Prepare vectors for upsert
vectors = []
for item in data:
    vectors.append({
        "id": item["id"],
        "values": item["embedding"],
        "metadata": {
            "wp_id": item["wp_id"],
            "wp_type": item["wp_type"],
            "title": item["title"],
            "content": item["content"],
            "url": item["url"],
            "schema_type": item["schema_data"]["@type"]
        }
    })

# Upsert data in batches
batch_size = 100
for i in range(0, len(vectors), batch_size):
    batch = vectors[i:i+batch_size]
    index.upsert(vectors=batch)

print(f"Inserted {len(vectors)} items into Pinecone")
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
     * Search Pinecone for content matching a query.
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
        
        // Create Python script to search Pinecone
        $temp_script = sys_get_temp_dir() . '/nlwp_pinecone_search.py';
        $temp_query = sys_get_temp_dir() . '/nlwp_query_embedding.json';
        $temp_results = sys_get_temp_dir() . '/nlwp_search_results.json';
        
        // Set search parameters
        $limit = isset($params['limit']) ? intval($params['limit']) : 10;
        $site = isset($params['site']) ? $params['site'] : '';
        $post_type = isset($params['post_type']) ? $params['post_type'] : '';
        
        // Save query embedding to file
        file_put_contents($temp_query, json_encode($embedding));
        
        // Create Python script to search Pinecone
        $script_content = <<<PYTHON
import sys
import json
import pinecone

# Load query embedding from file
with open("{$temp_query}", "r") as f:
    query_embedding = json.load(f)

# Initialize Pinecone
pinecone.init(api_key="{$this->api_key}", environment="{$this->environment}")

# Connect to index
index = pinecone.Index("{$this->index}")

# Set up filter if post type is specified
filter_dict = {}
if "{$post_type}":
    filter_dict = {
        "wp_type": {"$eq": "{$post_type}"}
    }

# Search vectors
search_results = index.query(
    vector=query_embedding,
    top_k={$limit},
    include_metadata=True,
    filter=filter_dict if filter_dict else None
)

# Format results
formatted_results = []
for match in search_results["matches"]:
    formatted_results.append({
        "id": match["id"],
        "score": match["score"],
        "wp_id": match["metadata"]["wp_id"],
        "wp_type": match["metadata"]["wp_type"],
        "title": match["metadata"]["title"],
        "content": match["metadata"]["content"],
        "url": match["metadata"]["url"]
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
                            'score' => $result['score'], // Pinecone already provides a similarity score
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
     * Clear all content from the Pinecone index.
     *
     * @since    1.0.0
     * @return   array     Status information about the clearing operation.
     */
    public function clear_database() {
        // Create a Python script to handle clearing the index
        $temp_script = sys_get_temp_dir() . '/nlwp_pinecone_clear.py';
        
        $script_content = <<<PYTHON
import sys
import pinecone

# Initialize Pinecone
pinecone.init(api_key="{$this->api_key}", environment="{$this->environment}")

# Check if index exists
existing_indexes = pinecone.list_indexes()

if "{$this->index}" in existing_indexes:
    # Connect to index and get stats
    index = pinecone.Index("{$this->index}")
    stats = index.describe_index_stats()
    vector_count = stats["total_vector_count"]
    
    # Delete the index
    pinecone.delete_index("{$this->index}")
    
    print(f"Index deleted successfully. Removed {vector_count} entities.")
else:
    print("Index does not exist")

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