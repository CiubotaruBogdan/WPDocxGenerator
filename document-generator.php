<?php
/**
 * Plugin Name: WPDocxGen
 * Plugin URI: https://ciubotarubogdan.work
 * Description: Generate documents from DOCX templates with dynamic field mapping. Supports WordPress user fields, site fields, and Toolset Types custom fields.
 * Version: 1.0
 * Author: Ciubotaru Bogdan
 * Author URI: https://ciubotarubogdan.work
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: document-generator
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( defined( 'DG_VERSION' ) ) {
    return; // Another copy of the plugin is already loaded.
}

define( 'DG_VERSION', '1.0' );
define( 'DG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'DG_PLACEHOLDER_PATTERN', '/#([a-zA-Z0-9_]+)#/' );

/**
 * Get the templates directory path (inside wp-content/uploads, guaranteed writable).
 */
function dg_get_templates_dir() {
    $upload_dir = wp_upload_dir();
    $dir = $upload_dir['basedir'] . '/dg-templates/';
    if ( ! file_exists( $dir ) ) {
        wp_mkdir_p( $dir );
        // Protect directory.
        $htaccess = $dir . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            file_put_contents( $htaccess, "Order Deny,Allow\nDeny from all\n" );
        }
        // Also add index.php for extra protection.
        $index = $dir . 'index.php';
        if ( ! file_exists( $index ) ) {
            file_put_contents( $index, "<?php\n// Silence is golden.\n" );
        }
    }
    return $dir;
}

/**
 * Main plugin class.
 */
final class Document_Generator {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        require_once DG_PLUGIN_DIR . 'includes/class-dg-admin.php';
        require_once DG_PLUGIN_DIR . 'includes/class-dg-template.php';
        require_once DG_PLUGIN_DIR . 'includes/class-dg-fields.php';
        require_once DG_PLUGIN_DIR . 'includes/class-dg-generator.php';
        require_once DG_PLUGIN_DIR . 'includes/class-dg-shortcode.php';
        require_once DG_PLUGIN_DIR . 'includes/class-dg-demo.php';
    }

    private function init_hooks() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'init', array( $this, 'load_textdomain' ) );

        if ( is_admin() ) {
            new DG_Admin();
        }

        new DG_Shortcode();
    }

    public function activate() {
        $this->register_post_type();
        flush_rewrite_rules();

        // Ensure templates directory exists.
        dg_get_templates_dir();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function register_post_type() {
        register_post_type( 'dg_template', array(
            'labels'              => array(
                'name'               => __( 'Document Templates', 'document-generator' ),
                'singular_name'      => __( 'Document Template', 'document-generator' ),
                'add_new'            => __( 'Add New Template', 'document-generator' ),
                'add_new_item'       => __( 'Add New Document Template', 'document-generator' ),
                'edit_item'          => __( 'Edit Document Template', 'document-generator' ),
                'view_item'          => __( 'View Document Template', 'document-generator' ),
                'all_items'          => __( 'All Templates', 'document-generator' ),
                'search_items'       => __( 'Search Templates', 'document-generator' ),
                'not_found'          => __( 'No templates found.', 'document-generator' ),
                'not_found_in_trash' => __( 'No templates found in Trash.', 'document-generator' ),
            ),
            'public'              => false,
            'show_ui'             => false, // We handle UI ourselves.
            'supports'            => array( 'title' ),
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        ) );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'document-generator', false, dirname( DG_PLUGIN_BASENAME ) . '/languages' );
    }
}

/**
 * Initialize the plugin.
 */
function document_generator() {
    return Document_Generator::instance();
}
add_action( 'plugins_loaded', 'document_generator' );
