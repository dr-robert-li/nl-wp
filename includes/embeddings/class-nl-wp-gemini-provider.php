<?php
/**
 * Google Gemini Embedding Provider Class.
 *
 * @since      1.0.0
 */
class NL_WP_Gemini_Provider extends NL_WP_Embedding_Provider {

    /**
     * Get embedding dimension for the specified model.
     *
     * @since    1.0.0
     * @param    string    $model    The model name.
     * @return   int                 The embedding dimension.
     */
    protected function get_dimension_for_model($model) {
        $dimensions = array(
            'embedding-001' => 768,
            'text-embedding-004' => 768,
        );
        
        return isset($dimensions[$model]) ? $dimensions[$model] : 768;
    }

    /**
     * Generate embeddings for a text using Google Gemini API.
     *
     * @since    1.0.0
     * @param    string    $text    The text to embed.
     * @return   array              The embedding vector.
     */
    protected function generate_embedding($text) {
        // Truncate text if it's too long
        $text = substr($text, 0, 8000);
        
        if (empty($this->api_key)) {
            throw new Exception('Google API key is required');
        }
        
        // Default to embedding-001 if no model specified
        $model = !empty($this->model) ? $this->model : 'embedding-001';
        
        // Use Gemini API
        $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:embedContent?key={$this->api_key}";
        $headers = array(
            'Content-Type: application/json'
        );
        
        $data = array(
            'content' => array(
                'parts' => array(
                    array('text' => $text)
                )
            )
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
            throw new Exception('Google Gemini API error: ' . $response);
        }
        
        $response_data = json_decode($response, true);
        
        if (isset($response_data['embedding']['values'])) {
            return $response_data['embedding']['values'];
        } else {
            throw new Exception('Invalid response from Google Gemini API');
        }
    }
}