<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles all admin functionality: menus, pages, AJAX endpoints.
 */
class DG_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // AJAX handlers.
        add_action( 'wp_ajax_dg_upload_template', array( $this, 'ajax_upload_template' ) );
        add_action( 'wp_ajax_dg_save_mapping', array( $this, 'ajax_save_mapping' ) );
        add_action( 'wp_ajax_dg_delete_template', array( $this, 'ajax_delete_template' ) );
        add_action( 'wp_ajax_dg_get_fields', array( $this, 'ajax_get_fields' ) );
        add_action( 'wp_ajax_dg_download_demo', array( $this, 'ajax_download_demo' ) );
    }

    public function add_menu_pages() {
        add_menu_page(
            __( 'WPDocxGen', 'document-generator' ),
            __( 'WPDocxGen', 'document-generator' ),
            'manage_options',
            'document-generator',
            array( $this, 'render_list_page' ),
            'dashicons-media-document',
            30
        );

        add_submenu_page(
            'document-generator',
            __( 'All Templates', 'document-generator' ),
            __( 'All Templates', 'document-generator' ),
            'manage_options',
            'document-generator',
            array( $this, 'render_list_page' )
        );

        add_submenu_page(
            'document-generator',
            __( 'Add New Template', 'document-generator' ),
            __( 'Add New', 'document-generator' ),
            'manage_options',
            'document-generator-new',
            array( $this, 'render_edit_page' )
        );

        add_submenu_page(
            'document-generator',
            __( 'Tutorial', 'document-generator' ),
            __( 'Tutorial', 'document-generator' ),
            'manage_options',
            'document-generator-tutorial',
            array( $this, 'render_tutorial_page' )
        );

        // Hidden page for editing existing templates.
        add_submenu_page(
            null,
            __( 'Edit Template', 'document-generator' ),
            __( 'Edit Template', 'document-generator' ),
            'manage_options',
            'document-generator-edit',
            array( $this, 'render_edit_page' )
        );
    }

    public function enqueue_assets( $hook ) {
        // Check if we're on a plugin page by query param (more reliable than hook names
        // which depend on the sanitized menu title).
        $page = isset( $_GET['page'] ) ? $_GET['page'] : '';
        if ( strpos( $page, 'document-generator' ) !== 0 ) {
            return;
        }

        wp_enqueue_style(
            'dg-admin',
            DG_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            DG_VERSION
        );

        wp_enqueue_script(
            'dg-admin',
            DG_PLUGIN_URL . 'admin/js/admin.js',
            array( 'jquery', 'jquery-ui-sortable' ),
            DG_VERSION,
            true
        );

        wp_localize_script( 'dg-admin', 'dgAdmin', array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'dg_admin_nonce' ),
            'strings'  => array(
                'confirmDelete'  => __( 'Are you sure you want to delete this template?', 'document-generator' ),
                'uploading'      => __( 'Uploading...', 'document-generator' ),
                'saving'         => __( 'Saving...', 'document-generator' ),
                'saved'          => __( 'Saved successfully!', 'document-generator' ),
                'error'          => __( 'An error occurred. Please try again.', 'document-generator' ),
                'noPlaceholders' => __( 'No placeholders found in this document.', 'document-generator' ),
                'selectField'    => __( '— Select a field —', 'document-generator' ),
                'enterCustomText' => __( 'Enter custom text...', 'document-generator' ),
            ),
        ) );
    }

    public function render_list_page() {
        include DG_PLUGIN_DIR . 'admin/views/list.php';
    }

    public function render_edit_page() {
        $template_id = isset( $_GET['template_id'] ) ? absint( $_GET['template_id'] ) : 0;
        include DG_PLUGIN_DIR . 'admin/views/edit.php';
    }

    public function render_tutorial_page() {
        include DG_PLUGIN_DIR . 'admin/views/tutorial.php';
    }

    /**
     * AJAX: Download a demo template file.
     */
    public function ajax_download_demo() {
        check_ajax_referer( 'dg_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permission denied.', 'document-generator' ) );
        }

        $type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';
        if ( ! in_array( $type, array( 'simple', 'table' ), true ) ) {
            wp_die( __( 'Invalid demo type.', 'document-generator' ) );
        }

        $path = DG_Demo::get_demo_path( $type );

        if ( ! file_exists( $path ) ) {
            wp_die( __( 'Demo file could not be created.', 'document-generator' ) );
        }

        $filename = 'demo-' . $type . '.docx';

        header( 'Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $path ) );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );

        readfile( $path );
        exit;
    }

    /**
     * AJAX: Upload a DOCX template and detect placeholders.
     */
    public function ajax_upload_template() {
        check_ajax_referer( 'dg_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'document-generator' ) );
        }

        if ( empty( $_FILES['template_file'] ) ) {
            wp_send_json_error( __( 'No file uploaded.', 'document-generator' ) );
        }

        $file = $_FILES['template_file'];

        // Validate file type.
        $allowed_types = array(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );
        $file_type = wp_check_filetype( $file['name'], array( 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ) );

        if ( ! $file_type['type'] ) {
            wp_send_json_error( __( 'Invalid file type. Only DOCX files are allowed.', 'document-generator' ) );
        }

        // Generate unique filename.
        $filename = sanitize_file_name( $file['name'] );
        $templates_dir = dg_get_templates_dir();
        $filename = wp_unique_filename( $templates_dir, $filename );
        $filepath = $templates_dir . $filename;

        if ( ! move_uploaded_file( $file['tmp_name'], $filepath ) ) {
            wp_send_json_error( __( 'Failed to save uploaded file.', 'document-generator' ) );
        }

        // Parse placeholders.
        $parser       = new DG_Template();
        $placeholders = $parser->extract_placeholders( $filepath );

        if ( is_wp_error( $placeholders ) ) {
            unlink( $filepath );
            wp_send_json_error( $placeholders->get_error_message() );
        }

        wp_send_json_success( array(
            'filename'     => $filename,
            'placeholders' => $placeholders,
        ) );
    }

    /**
     * AJAX: Save template mapping configuration.
     */
    public function ajax_save_mapping() {
        check_ajax_referer( 'dg_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'document-generator' ) );
        }

        $template_id   = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
        $title         = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $filename      = isset( $_POST['filename'] ) ? sanitize_file_name( wp_unslash( $_POST['filename'] ) ) : '';
        $repeat_source = isset( $_POST['repeat_source'] ) ? sanitize_text_field( wp_unslash( $_POST['repeat_source'] ) ) : '';
        $mapping_raw   = isset( $_POST['mapping'] ) ? wp_unslash( $_POST['mapping'] ) : '{}';
        $allowed_roles = isset( $_POST['allowed_roles'] ) ? array_map( 'sanitize_text_field', (array) $_POST['allowed_roles'] ) : array();
        $button_text   = isset( $_POST['button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['button_text'] ) ) : __( 'Download Document', 'document-generator' );
        $button_style  = array();
        if ( isset( $_POST['button_style'] ) && is_array( $_POST['button_style'] ) ) {
            $button_style = array(
                'bg_color'      => sanitize_hex_color( $_POST['button_style']['bg_color'] ?? '#2b579a' ),
                'text_color'    => sanitize_hex_color( $_POST['button_style']['text_color'] ?? '#ffffff' ),
                'border_color'  => sanitize_hex_color( $_POST['button_style']['border_color'] ?? '' ),
                'border_width'  => absint( $_POST['button_style']['border_width'] ?? 0 ),
                'font_size'     => absint( $_POST['button_style']['font_size'] ?? 15 ),
                'border_radius' => absint( $_POST['button_style']['border_radius'] ?? 6 ),
            );
        }
        if ( empty( $title ) ) {
            wp_send_json_error( __( 'Title is required.', 'document-generator' ) );
        }

        if ( empty( $filename ) || ! file_exists( dg_get_templates_dir() . $filename ) ) {
            wp_send_json_error( __( 'Template file not found. Please upload a DOCX file.', 'document-generator' ) );
        }

        $mapping = json_decode( $mapping_raw, true );
        if ( ! is_array( $mapping ) ) {
            wp_send_json_error( __( 'Invalid mapping data.', 'document-generator' ) );
        }

        // Sanitize mapping.
        $clean_mapping = array();
        foreach ( $mapping as $placeholder => $field_config ) {
            $placeholder = sanitize_text_field( $placeholder );
            $clean_mapping[ $placeholder ] = array(
                'source' => sanitize_text_field( $field_config['source'] ?? '' ),
                'field'  => sanitize_text_field( $field_config['field'] ?? '' ),
                'meta'   => sanitize_text_field( $field_config['meta'] ?? '' ),
            );
        }

        $post_data = array(
            'post_type'   => 'dg_template',
            'post_title'  => $title,
            'post_status' => 'publish',
        );

        if ( $template_id ) {
            $post_data['ID'] = $template_id;
            $result = wp_update_post( $post_data, true );
        } else {
            $result = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        $post_id = is_int( $result ) ? $result : $template_id;

        update_post_meta( $post_id, '_dg_filename', $filename );
        update_post_meta( $post_id, '_dg_repeat_source', $repeat_source );
        update_post_meta( $post_id, '_dg_mapping', $clean_mapping );
        update_post_meta( $post_id, '_dg_allowed_roles', $allowed_roles );
        update_post_meta( $post_id, '_dg_button_text', $button_text );
        if ( ! empty( $button_style ) ) {
            update_post_meta( $post_id, '_dg_button_style', $button_style );
        }

        wp_send_json_success( array(
            'template_id' => $post_id,
            'shortcode'   => '[document_generator id="' . $post_id . '"]',
        ) );
    }

    /**
     * AJAX: Delete a template.
     */
    public function ajax_delete_template() {
        check_ajax_referer( 'dg_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'document-generator' ) );
        }

        $template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;

        if ( ! $template_id ) {
            wp_send_json_error( __( 'Invalid template ID.', 'document-generator' ) );
        }

        // Delete the DOCX file.
        $filename = get_post_meta( $template_id, '_dg_filename', true );
        if ( $filename && file_exists( dg_get_templates_dir() . $filename ) ) {
            unlink( dg_get_templates_dir() . $filename );
        }

        wp_delete_post( $template_id, true );

        wp_send_json_success();
    }

    /**
     * AJAX: Get available fields by source type.
     */
    public function ajax_get_fields() {
        check_ajax_referer( 'dg_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'document-generator' ) );
        }

        $source = isset( $_POST['source'] ) ? sanitize_text_field( $_POST['source'] ) : '';

        $fields_handler = new DG_Fields();
        $fields = $fields_handler->get_fields_by_source( $source );

        wp_send_json_success( $fields );
    }

    /**
     * AJAX: Get fields available in the context of a specific page.
     */
}
