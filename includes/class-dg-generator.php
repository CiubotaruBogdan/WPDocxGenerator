<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles document generation: resolves field mappings and generates DOCX.
 */
class DG_Generator {

    private $template_handler;
    private $fields_handler;

    public function __construct() {
        $this->template_handler = new DG_Template();
        $this->fields_handler   = new DG_Fields();
    }

    /**
     * Generate a document from a template with resolved field values.
     *
     * @param int $template_id The dg_template post ID.
     * @param int $context_post_id The current post/page ID for context.
     * @return string|WP_Error Path to generated file or error.
     */
    public function generate( $template_id, $context_post_id = 0 ) {
        $filename = get_post_meta( $template_id, '_dg_filename', true );
        $mapping  = get_post_meta( $template_id, '_dg_mapping', true );

        if ( ! $filename || ! file_exists( dg_get_templates_dir() . $filename ) ) {
            return new WP_Error( 'template_missing', __( 'Template file not found.', 'document-generator' ) );
        }

        if ( ! is_array( $mapping ) || empty( $mapping ) ) {
            return new WP_Error( 'no_mapping', __( 'No field mapping configured for this template.', 'document-generator' ) );
        }

        $template_path = dg_get_templates_dir() . $filename;
        $user_id       = get_current_user_id();

        // Resolve all field values.
        $replacements = array();
        $repeat_data  = array();

        // Auto-inject denumire_document placeholder.
        $document_name = get_post_meta( $template_id, '_dg_document_name', true );
        if ( $document_name ) {
            $replacements['denumire_document'] = $document_name;
        }

        foreach ( $mapping as $placeholder => $config ) {
            $source = $config['source'] ?? '';

            // Check if this is a repeat block mapping.
            if ( $source === 'toolset_repeating' || $source === 'wp_users' || ( isset( $config['is_repeat'] ) && $config['is_repeat'] ) ) {
                $block_name = $placeholder;
                $repeat_data[ $block_name ] = $this->fields_handler->resolve_repeat_data(
                    $config,
                    $context_post_id
                );
                continue;
            }

            $value = $this->fields_handler->resolve_field_value(
                $config,
                $context_post_id,
                $user_id
            );

            $replacements[ $placeholder ] = (string) $value;
        }

        // Generate the document.
        $output_path = $this->template_handler->generate_document(
            $template_path,
            $replacements,
            $repeat_data
        );

        return $output_path;
    }

    /**
     * Clean up old temporary files (called via cron or on generation).
     */
    public static function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir   = $upload_dir['basedir'] . '/dg-temp/';

        if ( ! is_dir( $temp_dir ) ) {
            return;
        }

        $files = glob( $temp_dir . 'dg-*' );
        $max_age = 3600; // 1 hour.

        foreach ( $files as $file ) {
            if ( ( time() - filemtime( $file ) ) > $max_age ) {
                unlink( $file );
            }
        }
    }
}
