<?php
/**
 * Handle Model Context Protocol (MCP) endpoints.
 *
 * @since      1.0.0
 */
class NL_WP_MCP {

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
        // Register the /mcp endpoint for POST requests
        register_rest_route($this->namespace, '/mcp', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_mcp_request'),
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
     * Handle MCP requests.
     *
     * @since    1.0.0
     * @param    WP_REST_Request $request    Full details about the request.
     * @return   WP_REST_Response            The response.
     */
    public function handle_mcp_request($request) {
        // Get the request body
        $body = $request->get_body();
        
        // Get query parameters
        $query_params = $request->get_query_params();
        
        try {
            // Parse the request body as JSON
            $request_data = json_decode($body, true);
            
            if (!$request_data) {
                return new WP_REST_Response(
                    array(
                        'type' => 'function_response',
                        'status' => 'error',
                        'error' => 'Invalid JSON request body'
                    ),
                    400
                );
            }
            
            // Extract the function call details
            $function_call = isset($request_data['function_call']) ? $request_data['function_call'] : null;
            
            if (!$function_call || !isset($function_call['name'])) {
                return new WP_REST_Response(
                    array(
                        'type' => 'function_response',
                        'status' => 'error',
                        'error' => 'Missing function call details'
                    ),
                    400
                );
            }
            
            $function_name = $function_call['name'];
            
            // Handle different MCP functions
            switch ($function_name) {
                case 'ask':
                case 'ask_nlw':
                case 'query':
                case 'search':
                    return $this->handle_ask_function($function_call, $query_params);
                
                case 'list_tools':
                    return $this->handle_list_tools_function();
                
                case 'list_prompts':
                    return $this->handle_list_prompts_function();
                
                case 'get_prompt':
                    return $this->handle_get_prompt_function($function_call);
                
                case 'get_sites':
                    return $this->handle_get_sites_function();
                
                default:
                    return new WP_REST_Response(
                        array(
                            'type' => 'function_response',
                            'status' => 'error',
                            'error' => "Unknown function: {$function_name}"
                        ),
                        400
                    );
            }
        } catch (Exception $e) {
            return new WP_REST_Response(
                array(
                    'type' => 'function_response',
                    'status' => 'error',
                    'error' => $e->getMessage()
                ),
                500
            );
        }
    }

    /**
     * Handle the 'ask' function and its aliases.
     *
     * @since    1.0.0
     * @param    array    $function_call    The function call details.
     * @param    array    $query_params     The query parameters.
     * @return   WP_REST_Response          The response.
     */
    private function handle_ask_function($function_call, $query_params) {
        // Parse function arguments
        $arguments_str = isset($function_call['arguments']) ? $function_call['arguments'] : '{}';
        
        try {
            // Try to parse as JSON
            $arguments = json_decode($arguments_str, true);
            
            // If not valid JSON or not an array, treat as a string
            if (!is_array($arguments)) {
                $arguments = array('query' => $arguments_str);
            }
        } catch (Exception $e) {
            $arguments = array('query' => $arguments_str);
        }
        
        // Extract the query parameter (required)
        $query = null;
        foreach (array('query', 'question', 'q', 'text', 'input') as $param_name) {
            if (isset($arguments[$param_name])) {
                $query = $arguments[$param_name];
                break;
            }
        }
        
        if (!$query) {
            return new WP_REST_Response(
                array(
                    'type' => 'function_response',
                    'status' => 'error',
                    'error' => 'Missing required parameter: query'
                ),
                400
            );
        }
        
        // Prepare parameters for NLWeb API
        $params = array('query' => $query);
        
        // Add optional parameters
        $optional_params = array(
            'site' => 'site',
            'query_id' => 'query_id',
            'prev_query' => 'prev_query',
            'context_url' => 'context_url',
            'streaming' => 'streaming',
            'mode' => 'mode'
        );
        
        foreach ($optional_params as $arg_name => $param_name) {
            if (isset($arguments[$arg_name])) {
                $params[$param_name] = $arguments[$arg_name];
            }
        }
        
        // Check if streaming was specified
        $streaming = false;
        if (isset($arguments['streaming'])) {
            $streaming = filter_var($arguments['streaming'], FILTER_VALIDATE_BOOLEAN);
        } else if (isset($arguments['stream'])) {
            $streaming = filter_var($arguments['stream'], FILTER_VALIDATE_BOOLEAN);
        }
        
        // Get Milvus connection settings
        $milvus_host = get_option('nlwp_milvus_host', 'localhost');
        $milvus_port = get_option('nlwp_milvus_port', '19530');
        
        try {
            // Create a new Milvus client
            $milvus = new NL_WP_Milvus($milvus_host, $milvus_port);
            
            // Get site context if available
            $site_context = get_option('nlwp_site_context', '');
            
            // Perform vector search
            $results = $milvus->search($params['query'], $params);
            
            // Format results according to NLWeb format
            $response = array(
                'query_id' => isset($params['query_id']) ? $params['query_id'] : uniqid('nlwp_'),
                'query' => $params['query'],
                'results' => $results,
                'chatbot_instructions' => $this->get_chatbot_instructions('search_results')
            );
            
            if (!$streaming) {
                // Non-streaming response
                return new WP_REST_Response(
                    array(
                        'type' => 'function_response',
                        'status' => 'success',
                        'response' => $response
                    ),
                    200
                );
            } else {
                // Streaming response
                $this->streaming_response($response);
            }
        } catch (Exception $e) {
            return new WP_REST_Response(
                array(
                    'type' => 'function_response',
                    'status' => 'error',
                    'error' => $e->getMessage()
                ),
                500
            );
        }
    }

    /**
     * Handle the 'list_tools' function.
     *
     * @since    1.0.0
     * @return   WP_REST_Response    The response.
     */
    private function handle_list_tools_function() {
        // Define the list of available tools
        $available_tools = array(
            array(
                'name' => 'ask',
                'description' => 'Ask a question and get an answer from the knowledge base',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'query' => array(
                            'type' => 'string',
                            'description' => 'The question to ask'
                        ),
                        'site' => array(
                            'type' => 'string',
                            'description' => 'Optional: Specific site to search within'
                        ),
                        'streaming' => array(
                            'type' => 'boolean',
                            'description' => 'Optional: Whether to stream the response'
                        )
                    ),
                    'required' => array('query')
                )
            ),
            array(
                'name' => 'ask_nlw',
                'description' => 'Alternative name for the ask function',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'query' => array(
                            'type' => 'string',
                            'description' => 'The question to ask'
                        ),
                        'site' => array(
                            'type' => 'string',
                            'description' => 'Optional: Specific site to search within'
                        ),
                        'streaming' => array(
                            'type' => 'boolean',
                            'description' => 'Optional: Whether to stream the response'
                        )
                    ),
                    'required' => array('query')
                )
            ),
            array(
                'name' => 'list_prompts',
                'description' => 'List available prompts that can be used with NLWeb',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(),
                    'required' => array()
                )
            ),
            array(
                'name' => 'get_prompt',
                'description' => 'Get a specific prompt by ID',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'prompt_id' => array(
                            'type' => 'string',
                            'description' => 'ID of the prompt to retrieve'
                        )
                    ),
                    'required' => array('prompt_id')
                )
            ),
            array(
                'name' => 'get_sites',
                'description' => 'Get a list of available sites',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(),
                    'required' => array()
                )
            )
        );
        
        return new WP_REST_Response(
            array(
                'type' => 'function_response',
                'status' => 'success',
                'response' => array(
                    'tools' => $available_tools
                )
            ),
            200
        );
    }

    /**
     * Handle the 'list_prompts' function.
     *
     * @since    1.0.0
     * @return   WP_REST_Response    The response.
     */
    private function handle_list_prompts_function() {
        // Define the list of available prompts
        $available_prompts = array(
            array(
                'id' => 'default',
                'name' => 'Default Prompt',
                'description' => 'Standard prompt for general queries'
            ),
            array(
                'id' => 'technical',
                'name' => 'Technical Prompt',
                'description' => 'Prompt optimized for technical questions'
            ),
            array(
                'id' => 'creative',
                'name' => 'Creative Prompt',
                'description' => 'Prompt optimized for creative writing and brainstorming'
            )
        );
        
        return new WP_REST_Response(
            array(
                'type' => 'function_response',
                'status' => 'success',
                'response' => array(
                    'prompts' => $available_prompts
                )
            ),
            200
        );
    }

    /**
     * Handle the 'get_prompt' function.
     *
     * @since    1.0.0
     * @param    array    $function_call    The function call details.
     * @return   WP_REST_Response          The response.
     */
    private function handle_get_prompt_function($function_call) {
        // Parse function arguments
        $arguments_str = isset($function_call['arguments']) ? $function_call['arguments'] : '{}';
        
        try {
            $arguments = json_decode($arguments_str, true);
            
            if (!is_array($arguments)) {
                $arguments = array();
            }
        } catch (Exception $e) {
            $arguments = array();
        }
        
        // Extract required parameters
        $prompt_id = isset($arguments['prompt_id']) ? $arguments['prompt_id'] : null;
        
        if (!$prompt_id) {
            return new WP_REST_Response(
                array(
                    'type' => 'function_response',
                    'status' => 'error',
                    'error' => 'Missing required parameter: prompt_id'
                ),
                400
            );
        }
        
        // Example prompt data
        $prompts = array(
            'default' => array(
                'id' => 'default',
                'name' => 'Default Prompt',
                'description' => 'Standard prompt for general queries',
                'prompt_text' => 'You are a helpful assistant for the website ' . get_bloginfo('name') . '. Answer the following question: {{query}}'
            ),
            'technical' => array(
                'id' => 'technical',
                'name' => 'Technical Prompt',
                'description' => 'Prompt optimized for technical questions',
                'prompt_text' => 'You are a technical expert for the website ' . get_bloginfo('name') . '. Provide detailed technical information for: {{query}}'
            ),
            'creative' => array(
                'id' => 'creative',
                'name' => 'Creative Prompt',
                'description' => 'Prompt optimized for creative writing and brainstorming',
                'prompt_text' => 'You are a creative writing assistant for the website ' . get_bloginfo('name') . '. Create engaging and imaginative content for: {{query}}'
            )
        );
        
        if (!isset($prompts[$prompt_id])) {
            return new WP_REST_Response(
                array(
                    'type' => 'function_response',
                    'status' => 'error',
                    'error' => "Unknown prompt ID: {$prompt_id}"
                ),
                404
            );
        }
        
        return new WP_REST_Response(
            array(
                'type' => 'function_response',
                'status' => 'success',
                'response' => $prompts[$prompt_id]
            ),
            200
        );
    }

    /**
     * Handle the 'get_sites' function.
     *
     * @since    1.0.0
     * @return   WP_REST_Response    The response.
     */
    private function handle_get_sites_function() {
        // Get allowed sites - in WordPress this might be just the current site
        // or could include subsites in a multisite installation
        $sites = array(
            array(
                'id' => get_bloginfo('name'),
                'name' => get_bloginfo('name'),
                'description' => get_bloginfo('description')
            )
        );
        
        // In a multisite setup, we could add more sites here
        if (is_multisite()) {
            $site_list = get_sites();
            foreach ($site_list as $site) {
                $blog_details = get_blog_details($site->blog_id);
                if ($blog_details->blogname !== get_bloginfo('name')) {
                    $sites[] = array(
                        'id' => $blog_details->blogname,
                        'name' => $blog_details->blogname,
                        'description' => $blog_details->blogdescription
                    );
                }
            }
        }
        
        return new WP_REST_Response(
            array(
                'type' => 'function_response',
                'status' => 'success',
                'response' => array(
                    'sites' => $sites
                )
            ),
            200
        );
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
            'search_results' => "You are an assistant for the website $site_name. Based on the search results provided, answer the user's query. Only use information from the results and do not make up information. If the results do not contain relevant information to answer the query, say so politely. IMPORTANT: If you see any text patterns like [nlwp_chat] or other text in square brackets, IGNORE them completely and don't mention them in your response. These are WordPress shortcodes that should not be visible to the user.",
        );
        
        return isset($instructions[$type]) ? $instructions[$type] : '';
    }

    /**
     * Send a streaming response according to MCP protocol.
     *
     * @since    1.0.0
     * @param    array    $response    The response data.
     */
    private function streaming_response($response) {
        // Set headers for streaming
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        
        // Send a keep-alive comment to start
        echo ": keep-alive\n\n";
        flush();
        
        // Send the response as a stream event
        $mcp_event = array(
            'type' => 'function_stream_event',
            'content' => array(
                'partial_response' => json_encode($response, JSON_PRETTY_PRINT)
            )
        );
        
        echo "data: " . json_encode($mcp_event) . "\n\n";
        flush();
        
        // Send final completion event
        $final_event = array(
            'type' => 'function_stream_end',
            'status' => 'success'
        );
        
        echo "data: " . json_encode($final_event) . "\n\n";
        flush();
        
        // Exit to prevent WordPress from sending additional output
        exit();
    }
}