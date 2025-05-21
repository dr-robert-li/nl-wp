<?php
/**
 * OpenAI Embedding Provider Class.
 *
 * @since      1.0.0
 */
class NL_WP_OpenAI_Provider extends NL_WP_Embedding_Provider {

    /**
     * Get embedding dimension for the specified model.
     *
     * @since    1.0.0
     * @param    string    $model    The model name.
     * @return   int                 The embedding dimension.
     */
    protected function get_dimension_for_model($model) {
        $dimensions = array(
            'text-embedding-3-small' => 1536,
            'text-embedding-3-large' => 3072,
            'text-embedding-ada-002' => 1536
        );
        
        return isset($dimensions[$model]) ? $dimensions[$model] : 1536;
    }

    /**
     * Generate embeddings for a text using OpenAI API.
     *
     * @since    1.0.0
     * @param    string    $text    The text to embed.
     * @return   array              The embedding vector.
     */
    protected function generate_embedding($text) {
        // Use retry logic for API calls
        return $this->execute_with_retry(array($this, 'make_openai_request'), array($text));
    }
    
    /**
     * Make the actual OpenAI API request.
     *
     * @since    1.0.0
     * @param    string    $text    The text to embed.
     * @return   array              The embedding vector.
     * @throws   Exception          If the request fails.
     */
    protected function make_openai_request($text) {
        // Truncate text if it's too long (OpenAI has token limits)
        $text = substr($text, 0, 8000);
        
        if (empty($this->api_key)) {
            throw new Exception('OpenAI API key is required');
        }
        
        $url = 'https://api.openai.com/v1/embeddings';
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key
        );
        
        $data = array(
            'model' => $this->model,
            'input' => $text
        );
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 second timeout
        
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
            $error_message = isset($error_data['error']['message'])
                ? $error_data['error']['message']
                : "HTTP Error $http_code: $response";
            
            throw new Exception('OpenAI API error: ' . $error_message);
        }
        
        // Parse response
        $response_data = json_decode($response, true);
        
        if (isset($response_data['data'][0]['embedding'])) {
            return $response_data['data'][0]['embedding'];
        } else {
            throw new Exception('Invalid response from OpenAI API');
        }
    }
}