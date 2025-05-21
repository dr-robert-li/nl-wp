<?php
/**
 * Anthropic Embedding Provider Class.
 *
 * @since      1.0.0
 */
class NL_WP_Anthropic_Provider extends NL_WP_Embedding_Provider {

    /**
     * Get embedding dimension for the specified model.
     *
     * @since    1.0.0
     * @param    string    $model    The model name.
     * @return   int                 The embedding dimension.
     */
    protected function get_dimension_for_model($model) {
        $dimensions = array(
            // Voyage model family
            'voyage-3-large' => 1024,
            'voyage-3' => 1024,
            'voyage-3-lite' => 512,
            'voyage-code-3' => 1024,
            'voyage-finance-2' => 1024,
            'voyage-law-2' => 1024
        );

        // Handle custom dimensions for models that support it
        if (strpos($model, ':') !== false) {
            list($base_model, $dimension) = explode(':', $model, 2);
            if (in_array($base_model, ['voyage-3-large', 'voyage-code-3']) && 
                in_array($dimension, ['256', '512', '1024', '2048'])) {
                return (int)$dimension;
            }
        }
        
        return isset($dimensions[$model]) ? $dimensions[$model] : 1536;
    }

    /**
     * Generate embeddings for a text using Anthropic API.
     *
     * @since    1.0.0
     * @param    string    $text    The text to embed.
     * @return   array              The embedding vector.
     */
    protected function generate_embedding($text) {
        // Truncate text if it's too long
        $text = substr($text, 0, 8000);
        
        if (empty($this->api_key)) {
            throw new Exception('Anthropic API key is required');
        }
        
        $url = 'https://api.anthropic.com/v1/embeddings';
        $headers = array(
            'Content-Type: application/json',
            'x-api-key: ' . $this->api_key,
            'anthropic-version: 2023-06-01'
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
}