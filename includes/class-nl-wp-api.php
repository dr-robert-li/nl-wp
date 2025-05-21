<?php
/**
 * Handle NLWeb API endpoints.
 *
 * @since      1.0.0
 */
class NL_WP_Api {

    /**
     * REST API namespace.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $namespace    The REST API namespace.
     */
    private $namespace = 'nlwp/v1';

    /**
     * Register REST API routes.
     *
     * @since    1.0.0
     */
    public function register_routes() {
        // Register the /ask endpoint
        register_rest_route($this->namespace, '/ask', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_ask_get'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        // Register the /ask endpoint for POST requests
        register_rest_route($this->namespace, '/ask', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_ask_post'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
    }

    /**
     * Check API permissions.
     *
     * @since    1.0.0
     * @return   bool      Whether the user is allowed to access the API.
     */
    public function check_permissions() {
        // Check if API is restricted to logged-in users
        if (get_option('nlwp_api_restrict', 'no') === 'yes') {
            return is_user_logged_in();
        }
        return true;
    }

    /**
     * Handle GET requests to the /ask endpoint.
     *
     * @since    1.0.0
     * @param    WP_REST_Request $request    Full details about the request.
     * @return   WP_REST_Response            The response.
     */
    public function handle_ask_get($request) {
        // Get query parameters
        $query_params = $request->get_query_params();
        
        // Check if query parameter is provided
        if (!isset($query_params['query']) || empty($query_params['query'])) {
            return new WP_REST_Response(
                array('error' => 'Missing required parameter: query'),
                400
            );
        }

        // Process the query using NLWeb handler
        $result = $this->process_query($query_params);
        
        // Set up streaming if requested
        $streaming = isset($query_params['streaming']) ? 
            filter_var($query_params['streaming'], FILTER_VALIDATE_BOOLEAN) : true;
        
        if ($streaming) {
            $this->streaming_response($result);
        } else {
            return new WP_REST_Response($result, 200);
        }
    }

    /**
     * Handle POST requests to the /ask endpoint.
     *
     * @since    1.0.0
     * @param    WP_REST_Request $request    Full details about the request.
     * @return   WP_REST_Response            The response.
     */
    public function handle_ask_post($request) {
        // Get POST parameters
        $params = $request->get_params();
        
        // Check if query parameter is provided
        if (!isset($params['query']) || empty($params['query'])) {
            return new WP_REST_Response(
                array('error' => 'Missing required parameter: query'),
                400
            );
        }

        // Process the query using NLWeb handler
        $result = $this->process_query($params);
        
        // Set up streaming if requested
        $streaming = isset($params['streaming']) ? 
            filter_var($params['streaming'], FILTER_VALIDATE_BOOLEAN) : true;
        
        if ($streaming) {
            $this->streaming_response($result);
        } else {
            return new WP_REST_Response($result, 200);
        }
    }

    /**
     * Process a query using NLWeb handler.
     *
     * @since    1.0.0
     * @param    array    $params    The query parameters.
     * @return   array               The query results.
     */
    private function process_query($params) {
        // Get Milvus connection settings
        $milvus_host = get_option('nlwp_milvus_host', 'localhost');
        $milvus_port = get_option('nlwp_milvus_port', '19530');
        
        // Get embedding provider settings
        $embedding_provider = get_option('nlwp_embedding_provider', 'openai');
        
        // Get the API key based on the selected provider
        $embedding_api_key = '';
        switch ($embedding_provider) {
            case 'openai':
                $embedding_api_key = get_option('nlwp_openai_api_key', '');
                break;
            case 'anthropic':
                $embedding_api_key = get_option('nlwp_anthropic_api_key', '');
                break;
            case 'gemini':
                $embedding_api_key = get_option('nlwp_gemini_api_key', '');
                break;
            // Ollama doesn't use an API key
            default:
                $embedding_api_key = get_option('nlwp_openai_api_key', '');
        }
        
        try {
            // Create a new Milvus client
            $milvus = new NL_WP_Milvus($milvus_host, $milvus_port);
            
            // Format the query and get parameters needed for NLWeb
            $formatted_params = $this->format_query_params($params);
            
            // Create a unique query ID if not provided
            if (!isset($formatted_params['query_id'])) {
                $formatted_params['query_id'] = uniqid('nlwp_');
            }
            
            // Get site context if available
            $site_context = get_option('nlwp_site_context', '');
            
            // Perform vector search
            $results = $milvus->search($formatted_params['query'], $formatted_params);
            
            // Format results according to NLWeb format
            $response = array(
                'query_id' => $formatted_params['query_id'],
                'query' => $formatted_params['query'],
                'results' => $results,
                'chatbot_instructions' => $this->get_chatbot_instructions('search_results')
            );
            
            return $response;
        } catch (Exception $e) {
            return array(
                'error' => $e->getMessage(),
                'query' => $params['query']
            );
        }
    }

    /**
     * Format query parameters for NLWeb.
     *
     * @since    1.0.0
     * @param    array    $params    The original query parameters.
     * @return   array               The formatted parameters.
     */
    private function format_query_params($params) {
        $formatted = array();
        
        // Required parameter: query
        $formatted['query'] = $params['query'];
        
        // Optional parameters
        if (isset($params['site'])) {
            $formatted['site'] = $params['site'];
        } else {
            $formatted['site'] = get_bloginfo('name');
        }
        
        // Previous queries for context
        if (isset($params['prev'])) {
            $formatted['prev'] = $params['prev'];
        }
        
        // Decontextualized query
        if (isset($params['decontextualized_query'])) {
            $formatted['decontextualized_query'] = $params['decontextualized_query'];
        }
        
        // Query ID
        if (isset($params['query_id'])) {
            $formatted['query_id'] = $params['query_id'];
        }
        
        // Mode (list, summarize, or generate)
        if (isset($params['mode']) && in_array($params['mode'], array('list', 'summarize', 'generate'))) {
            $formatted['mode'] = $params['mode'];
        } else {
            $formatted['mode'] = 'list';
        }
        
        return $formatted;
    }

    /**
     * Get chatbot instructions based on the type.
     *
     * @since    1.0.0
     * @param    string    $type    The type of instructions to get.
     * @return   string             The instructions.
     */
    private function get_chatbot_instructions($type) {
        $site_name = get_bloginfo('name');
        $instructions = array(
            'search_results' => "You are an assistant for the website $site_name. Based on the search results provided, answer the user's query. Only use information from the results and do not make up information. If the results do not contain relevant information to answer the query, say so politely. IMPORTANT: If you see any text patterns like [nlwp_chat] or other text in square brackets, IGNORE them completely and don't mention them in your response. These are WordPress shortcodes that should not be visible to the user."
        );
        
        return isset($instructions[$type]) ? $instructions[$type] : '';
    }

    /**
     * Send a streaming response.
     *
     * @since    1.0.0
     * @param    array    $result    The query results.
     */
    private function streaming_response($result) {
        // Set headers for streaming
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        
        // Send a keep-alive comment to start
        echo ": keep-alive\n\n";
        flush();
        
        // Send the results as an event
        echo "data: " . json_encode($result) . "\n\n";
        flush();
        
        // Send a completion event
        echo "event: completion\n";
        echo "data: {\"status\": \"complete\"}\n\n";
        flush();
        
        // Exit to prevent WordPress from sending additional output
        exit();
    }
}