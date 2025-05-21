<?php
/**
 * Weaviate Vector Database Handler.
 *
 * @since      1.0.0
 */
class NL_WP_Weaviate_DB extends NL_WP_Vector_DB {

    /**
     * Weaviate host.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $host    The Weaviate host.
     */
    private $host;

    /**
     * Weaviate API key (if any).
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $api_key    The Weaviate API key.
     */
    private $api_key;

    /**
     * Weaviate class name.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $class_name    The Weaviate class name.
     */
    private $class_name;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    array     $config    Configuration parameters.
     */
    public function __construct($config = array()) {
        parent::__construct($config);
        
        $this->host = isset($config['host']) ? $config['host'] : get_option('nlwp_weaviate_host', 'http://localhost:8080');
        $this->api_key = isset($config['api_key']) ? $config['api_key'] : get_option('nlwp_weaviate_api_key', '');
        
        // Convert collection name to a valid Weaviate class name (capitalized, no hyphens)
        $this->class_name = $this->collection ? 
            str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $this->collection))) : 
            'WordpressContent';
    }

    /**
     * Create necessary Weaviate classes if they don't exist.
     *
     * @since    1.0.0
     * @return   bool      Whether the classes were created successfully.
     */
    public function initialize_collections() {
        try {
            // Get the Python path from settings or use default
            $python_path = get_option('nlwp_python_path', 'python3');
            
            // Create a temporary Python script to initialize classes
            $temp_file = sys_get_temp_dir() . '/nlwp_weaviate_init.py';
            
            $script_content = <<<PYTHON
import sys
import weaviate
from weaviate.auth import AuthApiKey

# Set up authentication
auth_config = None
if "{$this->api_key}":
    auth_config = weaviate.auth.AuthApiKey(api_key="{$this->api_key}")

# Connect to Weaviate
client = weaviate.Client(
    url="{$this->host}",
    auth_client_secret=auth_config
)

# Define class schema
class_obj = {
    "class": "{$this->class_name}",
    "vectorizer": "none",  # We will provide our own vectors
    "properties": [
        {
            "name": "wpId",
            "dataType": ["int"],
            "description": "WordPress post ID"
        },
        {
            "name": "wpType",
            "dataType": ["string"],
            "description": "WordPress post type",
            "indexFilterable": True,
            "indexSearchable": True
        },
        {
            "name": "title",
            "dataType": ["string"],
            "description": "Post title",
            "indexFilterable": True,
            "indexSearchable": True
        },
        {
            "name": "content",
            "dataType": ["text"],
            "description": "Post content",
            "indexSearchable": True
        },
        {
            "name": "url",
            "dataType": ["string"],
            "description": "Post URL"
        },
        {
            "name": "schemaType",
            "dataType": ["string"],
            "description": "Schema.org type"
        }
    ]
}

# Check if class exists
if not client.schema.exists("{$this->class_name}"):
    # Create class
    client.schema.create_class(class_obj)
    print("Class created successfully")
else:
    print("Class already exists")

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
            error_log('NLWP Weaviate Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Ingest WordPress content into Weaviate.
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
        $temp_script = sys_get_temp_dir() . '/nlwp_weaviate_ingest.py';
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
                    'id' => "wp-" . $post_id,
                    'wp_id' => $post_id,
                    'wp_type' => $post_type,
                    'title' => $title,
                    'content' => $content,
                    'url' => $url,
                    'embedding' => $embedding,
                    'schema_type' => $schema_data['@type']
                );
                
                $counter++;
            } catch (Exception $e) {
                error_log('NLWP Embedding Error: ' . $e->getMessage());
                // Continue with next post
            }
        }
        
        // Save data to temporary file
        file_put_contents($temp_data, json_encode($content_data));
        
        // Create Python script to insert data into Weaviate
        $script_content = <<<PYTHON
import sys
import json
import weaviate
import uuid
from weaviate.auth import AuthApiKey

# Load data from file
with open("{$temp_data}", "r") as f:
    data = json.load(f)

if not data:
    print("No data to insert")
    sys.exit(1)

# Set up authentication
auth_config = None
if "{$this->api_key}":
    auth_config = weaviate.auth.AuthApiKey(api_key="{$this->api_key}")

# Connect to Weaviate
client = weaviate.Client(
    url="{$this->host}",
    auth_client_secret=auth_config
)

# Define batch configuration
client.batch.configure(batch_size=100)

# Start batch process
with client.batch as batch:
    for item in data:
        # Generate UUID based on wp_id for deterministic IDs
        item_uuid = uuid.uuid5(uuid.NAMESPACE_URL, f"wp-{item['wp_id']}")
        
        properties = {
            "wpId": item["wp_id"],
            "wpType": item["wp_type"],
            "title": item["title"],
            "content": item["content"],
            "url": item["url"],
            "schemaType": item["schema_type"]
        }
        
        # Add data object with vector
        batch.add_data_object(
            data_object=properties,
            class_name="{$this->class_name}",
            uuid=str(item_uuid),
            vector=item["embedding"]
        )

print(f"Inserted {len(data)} items into Weaviate")
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
     * Search Weaviate for content matching a query.
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
        
        // Create Python script to search Weaviate
        $temp_script = sys_get_temp_dir() . '/nlwp_weaviate_search.py';
        $temp_query = sys_get_temp_dir() . '/nlwp_query_embedding.json';
        $temp_results = sys_get_temp_dir() . '/nlwp_search_results.json';
        
        // Set search parameters
        $limit = isset($params['limit']) ? intval($params['limit']) : 10;
        $site = isset($params['site']) ? $params['site'] : '';
        $post_type = isset($params['post_type']) ? $params['post_type'] : '';
        
        // Save query embedding to file
        file_put_contents($temp_query, json_encode($embedding));
        
        // Create Python script to search Weaviate
        $script_content = <<<PYTHON
import sys
import json
import weaviate
from weaviate.auth import AuthApiKey

# Load query embedding from file
with open("{$temp_query}", "r") as f:
    query_embedding = json.load(f)

# Set up authentication
auth_config = None
if "{$this->api_key}":
    auth_config = weaviate.auth.AuthApiKey(api_key="{$this->api_key}")

# Connect to Weaviate
client = weaviate.Client(
    url="{$this->host}",
    auth_client_secret=auth_config
)

# Build filter if post type is specified
where_filter = None
if "{$post_type}":
    where_filter = {
        "path": ["wpType"],
        "operator": "Equal",
        "valueString": "{$post_type}"
    }

# Search vectors
query_builder = client.query.get(
    "{$this->class_name}", 
    ["wpId", "wpType", "title", "content", "url", "schemaType"]
).with_additional(["id", "certainty"])

# Add vector search
query_builder = query_builder.with_near_vector({"vector": query_embedding})

# Add filter if specified
if where_filter:
    query_builder = query_builder.with_where(where_filter)

# Execute query with limit
results = query_builder.with_limit({$limit}).do()

# Format results
formatted_results = []
if "data" in results and "Get" in results["data"] and "{$this->class_name}" in results["data"]["Get"]:
    for item in results["data"]["Get"]["{$this->class_name}"]:
        formatted_results.append({
            "id": item["_additional"]["id"],
            "score": item["_additional"]["certainty"],
            "wp_id": item["wpId"],
            "wp_type": item["wpType"],
            "title": item["title"],
            "content": item["content"],
            "url": item["url"]
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
                            'score' => $result['score'], // Weaviate already provides a similarity score
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
     * Clear all content from the Weaviate class.
     *
     * @since    1.0.0
     * @return   array     Status information about the clearing operation.
     */
    public function clear_database() {
        // Create a Python script to handle clearing the class
        $temp_script = sys_get_temp_dir() . '/nlwp_weaviate_clear.py';
        
        $script_content = <<<PYTHON
import sys
import weaviate
from weaviate.auth import AuthApiKey

# Set up authentication
auth_config = None
if "{$this->api_key}":
    auth_config = weaviate.auth.AuthApiKey(api_key="{$this->api_key}")

# Connect to Weaviate
client = weaviate.Client(
    url="{$this->host}",
    auth_client_secret=auth_config
)

# Check if class exists
if client.schema.exists("{$this->class_name}"):
    # Get object count
    query_result = client.query.aggregate("{$this->class_name}").with_meta_count().do()
    object_count = 0
    if "data" in query_result and "Aggregate" in query_result["data"] and "{$this->class_name}" in query_result["data"]["Aggregate"]:
        object_count = query_result["data"]["Aggregate"]["{$this->class_name}"][0]["meta"]["count"]
    
    # Delete the class
    client.schema.delete_class("{$this->class_name}")
    
    print(f"Class deleted successfully. Removed {object_count} entities.")
else:
    print("Class does not exist")

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