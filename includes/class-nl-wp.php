<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.0.0
 */
class NL_WP {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      NL_WP_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The API handler for NLWeb endpoints.
     *
     * @since    1.0.0
     * @access   protected
     * @var      NL_WP_Api       $api       Handles the NLWeb API endpoints.
     */
    protected $api;

    /**
     * The MCP handler for Model Context Protocol endpoints.
     *
     * @since    1.0.0
     * @access   protected
     * @var      NL_WP_MCP       $mcp       Handles the MCP protocol endpoints.
     */
    protected $mcp;

    /**
     * The Vector DB instance for database operations.
     *
     * @since    1.0.0
     * @access   protected
     * @var      NL_WP_Vector_DB $vector_db Handles vector database operations.
     */
    protected $vector_db;

    /**
     * Define the core functionality of the plugin.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->loader = new NL_WP_Loader();
        $this->api = new NL_WP_Api();
        $this->mcp = new NL_WP_MCP();
        
        // Load factory and create vector DB instance
        require_once NLWP_PLUGIN_DIR . 'includes/class-nl-wp-factory.php';
        $this->vector_db = NL_WP_Factory::create_vector_db();
        
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks();
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        if (is_admin()) {
            $admin = new NL_WP_Admin();
            $this->loader->add_action('admin_menu', $admin, 'add_admin_menu');
            $this->loader->add_action('admin_init', $admin, 'register_settings');
            $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_styles');
            $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_scripts');
            $this->loader->add_action('wp_ajax_nlwp_ingest_content', $admin, 'ingest_content');
            $this->loader->add_action('wp_ajax_nlwp_clear_database', $admin, 'clear_database');
            $this->loader->add_action('wp_ajax_nlwp_save_provider', $admin, 'save_provider');
            $this->loader->add_action('wp_ajax_nlwp_run_diagnostics', $admin, 'run_diagnostics');
        }
    }

    /**
     * Register all of the hooks related to the public-facing functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_styles');
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_scripts');
        $this->loader->add_action('wp_footer', $this, 'add_chat_widget');
        
        // Register shortcode directly with WordPress
        add_shortcode('nlwp_chat', array($this, 'chat_shortcode'));
    }

    /**
     * Register all of the hooks related to the API functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_api_hooks() {
        // Register REST API endpoints
        $this->loader->add_action('rest_api_init', $this->api, 'register_routes');
        $this->loader->add_action('rest_api_init', $this->mcp, 'register_routes');
    }

    /**
     * Enqueue public styles.
     *
     * @since    1.0.0
     */
    public function enqueue_public_styles() {
        if (get_option('nlwp_enable_chat_widget', 'no') === 'yes') {
            wp_enqueue_style('nlwp-public', NLWP_PLUGIN_URL . 'public/css/nl-wp-public.css', array(), NLWP_VERSION, 'all');
        }
    }

    /**
     * Enqueue public scripts.
     *
     * @since    1.0.0
     */
    public function enqueue_public_scripts() {
        if (get_option('nlwp_enable_chat_widget', 'no') === 'yes') {
            wp_enqueue_script('nlwp-public', NLWP_PLUGIN_URL . 'public/js/nl-wp-public.js', array('jquery'), NLWP_VERSION, true);
            wp_localize_script('nlwp-public', 'nlwpData', array(
                'apiUrl' => rest_url('nlwp/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'siteUrl' => get_site_url(),
                'siteName' => get_bloginfo('name'),
                'chatColor' => get_option('nlwp_chat_color', '#0073aa')
            ));
        }
    }

    /**
     * Add chat widget to the footer.
     *
     * @since    1.0.0
     */
    public function add_chat_widget() {
        if (get_option('nlwp_enable_chat_widget', 'no') === 'yes') {
            include_once NLWP_PLUGIN_DIR . 'public/partials/chat-widget.php';
        }
    }

    /**
     * Shortcode for chat widget.
     *
     * @since    1.0.0
     * @param    array    $atts    The shortcode attributes.
     * @return   string            The chat widget HTML.
     */
    public function chat_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => get_option('nlwp_chat_title', 'Ask me anything'),
            'placeholder' => get_option('nlwp_chat_placeholder', 'Type your question...'),
            'width' => '100%',
            'height' => '500px',
        ), $atts);

        ob_start();
        include NLWP_PLUGIN_DIR . 'public/partials/chat-shortcode.php';
        return ob_get_clean();
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }
}