<?php
/**
 * ChromaDB Vector Database Handler.
 *
 * @since      1.0.0
 */
class NL_WP_Chroma_DB extends NL_WP_Vector_DB {

    /**
     * ChromaDB host.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $host    The ChromaDB host.
     */
    private $host;

    /**
     * ChromaDB port.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $port    The ChromaDB port.
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
        
        $this->host = isset($config['host']) ? $config['host'] : get_option('nlwp_chroma_host', 'localhost');
        $this->port = isset($config['port']) ? $config['port'] : get_option('nlwp_chroma_port', '8000');
    }

    /**
     * Create necessary ChromaDB collections if they don't exist.
     *
     * @since    1.0.0
     * @return   bool      Whether the collections were created successfully.
     */
    public function initialize_collections() {
        try {
            // Get the Python path from settings or use default
            $python_path = get_option('nlwp_python_path', 'python3');
            
            // Create a temporary Python script to initialize collections
            $temp_file = sys_get_temp_dir() . '/nlwp_chroma_init.py';
            
            $script_content = <<<PYTHON
import sys
import chromadb

# Connect to ChromaDB
client = chromadb.HttpClient(host="{$this->host}", port="{$this->port}")

# Check and create collection if it doesn't exist
try:
    # Try to get collection
    collection = client.get_collection(name="{$this->collection}")
    print("Collection already exists")
except Exception as e:
    # Create collection if it doesn't exist
    collection = client.create_collection(
        name="{$this->collection}",
        metadata={"description": "WordPress content collection"}
    )
    print("Collection created successfully")

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
            error_log('NLWP ChromaDB Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Ingest WordPress content into ChromaDB.
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
        $temp_script = sys_get_temp_dir() . '/nlwp_chroma_ingest.py';
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
                    'id' => (string)$post_id, // ChromaDB requires string IDs
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
        
        // Create Python script to insert data into ChromaDB
        $script_content = <<<PYTHON
import sys
import json
import chromadb

# Load data from file
with open("{$temp_data}", "r") as f:
    data = json.load(f)

if not data:
    print("No data to insert")
    sys.exit(1)

# Connect to ChromaDB
client = chromadb.HttpClient(host="{$this->host}", port="{$this->port}")

# Get the collection
collection = client.get_collection(name="{$this->collection}")

# Prepare data for insertion
ids = [item["id"] for item in data]
documents = [item["title"] + "\n\n" + item["content"] for item in data]
embeddings = [item["embedding"] for item in data]
metadatas = [
    {
        "wp_id": item["wp_id"],
        "wp_type": item["wp_type"],
        "title": item["title"],
        "url": item["url"],
        "schema_type": item["schema_data"]["@type"]
    } 
    for item in data
]

# Insert data in batches to avoid memory issues
batch_size = 100
for i in range(0, len(ids), batch_size):
    batch_ids = ids[i:i+batch_size]
    batch_documents = documents[i:i+batch_size]
    batch_embeddings = embeddings[i:i+batch_size]
    batch_metadatas = metadatas[i:i+batch_size]
    
    collection.add(
        ids=batch_ids,
        documents=batch_documents,
        embeddings=batch_embeddings,
        metadatas=batch_metadatas
    )

print(f"Inserted {len(ids)} items into ChromaDB")
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
     * Search ChromaDB for content matching a query.
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
        
        // Create Python script to search ChromaDB
        $temp_script = sys_get_temp_dir() . '/nlwp_chroma_search.py';
        $temp_query = sys_get_temp_dir() . '/nlwp_query_embedding.json';
        $temp_results = sys_get_temp_dir() . '/nlwp_search_results.json';
        
        // Set search parameters
        $limit = isset($params['limit']) ? intval($params['limit']) : 10;
        $site = isset($params['site']) ? $params['site'] : '';
        $post_type = isset($params['post_type']) ? $params['post_type'] : '';
        
        // Save query embedding to file
        file_put_contents($temp_query, json_encode($embedding));
        
        // Create Python script to search ChromaDB
        $script_content = <<<PYTHON
import sys
import json
import chromadb

# Load query embedding from file
with open("{$temp_query}", "r") as f:
    query_embedding = json.load(f)

# Connect to ChromaDB
client = chromadb.HttpClient(host="{$this->host}", port="{$this->port}")

# Get the collection
collection = client.get_collection(name="{$this->collection}")

# Search parameters
where_clause = {}
if "{$post_type}":
    where_clause["wp_type"] = "{$post_type}"

# Search vectors
results = collection.query(
    query_embeddings=[query_embedding],
    n_results={$limit},
    where=where_clause if where_clause else None,
    include=["metadatas", "documents", "distances"]
)

# Format results
formatted_results = []
for i in range(len(results["ids"][0])):
    doc_id = results["ids"][0][i]
    metadata = results["metadatas"][0][i]
    document = results["documents"][0][i]
    distance = results["distances"][0][i]
    
    # Split document into title and content
    parts = document.split("\n\n", 1)
    title = metadata["title"]
    content = parts[1] if len(parts) > 1 else document
    
    formatted_results.append({
        "id": doc_id,
        "score": distance,
        "wp_id": metadata["wp_id"],
        "wp_type": metadata["wp_type"],
        "title": title,
        "content": content,
        "url": metadata["url"]
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
                            'score' => 1 - ($result['score'] / 2), // Convert distance to similarity score
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
     * Clear all content from the ChromaDB collection.
     *
     * @since    1.0.0
     * @return   array     Status information about the clearing operation.
     */
    public function clear_database() {
        // Create a Python script to handle clearing the collection
        $temp_script = sys_get_temp_dir() . '/nlwp_chroma_clear.py';
        
        $script_content = <<<PYTHON
import sys
import chromadb

# Connect to ChromaDB
client = chromadb.HttpClient(host="{$this->host}", port="{$this->port}")

# Check if collection exists
try:
    # Try to get collection
    collection = client.get_collection(name="{$this->collection}")
    
    # Count documents before deletion
    count = collection.count()
    
    # Delete the collection
    client.delete_collection(name="{$this->collection}")
    
    print(f"Collection dropped successfully. Removed {count} entities.")
except Exception as e:
    print("Collection does not exist or could not be deleted:", str(e))

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