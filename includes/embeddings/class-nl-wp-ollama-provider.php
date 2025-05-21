<?php
/**
 * Ollama Embedding Provider Class.
 *
 * @since      1.0.0
 */
class NL_WP_Ollama_Provider extends NL_WP_Embedding_Provider {

    /**
     * Ollama server URL.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $server_url    The Ollama server URL.
     */
    protected $server_url;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $api_key     The API key (not used for Ollama).
     * @param    string    $model       The model to use for embeddings.
     * @param    string    $server_url  The Ollama server URL.
     */
    public function __construct($api_key = null, $model = null, $server_url = null) {
        parent::__construct($api_key, $model);
        $this->server_url = $server_url ?: 'http://localhost:11434';
    }

    /**
     * Get embedding dimension for the specified model.
     *
     * @since    1.0.0
     * @param    string    $model    The model name.
     * @return   int                 The embedding dimension.
     */
    protected function get_dimension_for_model($model) {
        $dimensions = array(
            'nomic-embed-text' => 768,
            'snowflake-arctic-embed2' => 1024,
            'granite-embedding' => 1536
        );
        
        return isset($dimensions[$model]) ? $dimensions[$model] : 2048;
    }

    /**
     * Generate embeddings for a text using Ollama API.
     *
     * @since    1.0.0
     * @param    string    $text    The text to embed.
     * @return   array              The embedding vector.
     */
    protected function generate_embedding($text) {
        // Use retry logic for API calls
        return $this->execute_with_retry(array($this, 'make_ollama_request'), array($text));
    }
    
    /**
     * Make the actual Ollama API request.
     *
     * @since    1.0.0
     * @param    string    $text    The text to embed.
     * @return   array              The embedding vector.
     * @throws   Exception          If the request fails.
     */
    protected function make_ollama_request($text) {
        // Truncate text if it's too long
        $text = substr($text, 0, 8000);
        
        if (empty($this->model)) {
            throw new Exception('Ollama model name is required');
        }
        
        // Ensure the model is available before proceeding
        $this->ensure_model_available();
        
        $url = rtrim($this->server_url, '/') . '/api/embeddings';
        $headers = array(
            'Content-Type: application/json'
        );
        
        $data = array(
            'model' => $this->model,
            'prompt' => $text
        );
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 second timeout (Ollama can be slower)
        
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Handle curl errors
        if (!empty($curl_error)) {
            throw new Exception('cURL Error: ' . $curl_error);
        }
        
        // Handle HTTP errors
        if ($http_code !== 200) {
            $error_data = json_decode($response, true);
            $error_message = isset($error_data['error'])
                ? $error_data['error']
                : "HTTP Error $http_code: $response";
            
            throw new Exception('Ollama API error: ' . $error_message);
        }
        
        // Parse response
        $response_data = json_decode($response, true);
        
        if (isset($response_data['embedding'])) {
            return $response_data['embedding'];
        } else {
            throw new Exception('Invalid response from Ollama API');
        }
    }
    
    /**
     * Ensure the selected model is available in Ollama.
     * If not, attempt to pull it automatically.
     *
     * @since    1.0.0
     * @return   bool     Whether the model is available.
     */
    protected function ensure_model_available() {
        try {
            // First, check if the model is already available
            $models_url = rtrim($this->server_url, '/') . '/api/tags';
            
            $ch = curl_init($models_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code !== 200) {
                throw new Exception('Ollama API error when checking available models');
            }
            
            $models_data = json_decode($response, true);
            $available_models = array();
            
            if (isset($models_data['models'])) {
                foreach ($models_data['models'] as $model) {
                    if (isset($model['name'])) {
                        $available_models[] = $model['name'];
                    }
                }
            }
            
            // If model is already available, return early
            if (in_array($this->model, $available_models)) {
                return true;
            }
            
            // Model not available, attempt to pull it
            error_log('NLWP Ollama: Model "' . $this->model . '" not found, attempting to pull it...');
            
            // Make the pull request
            $pull_url = rtrim($this->server_url, '/') . '/api/pull';
            $headers = array(
                'Content-Type: application/json'
            );
            
            $data = array(
                'name' => $this->model,
                'stream' => false,  // Don't stream the pull
            );
            
            $ch = curl_init($pull_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 180); // 3 minute timeout for pulling
            
            $pull_response = curl_exec($ch);
            $pull_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($pull_http_code !== 200) {
                error_log('NLWP Ollama: Failed to pull model "' . $this->model . '": ' . $pull_response);
                return false;
            }
            
            error_log('NLWP Ollama: Successfully pulled model "' . $this->model . '"');
            return true;
            
        } catch (Exception $e) {
            error_log('NLWP Ollama: Error ensuring model availability: ' . $e->getMessage());
            return false;
        }
    }
}