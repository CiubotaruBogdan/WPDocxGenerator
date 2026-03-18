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
     * @param int $entry_index When >= 0, resolve repeating fields for this specific entry only.
     * @return string|WP_Error Path to generated file or error.
     */
    public function generate( $template_id, $context_post_id = 0, $entry_index = -1 ) {
        $filename = get_post_meta( $template_id, '_dg_filename', true );
        $mapping  = get_post_meta( $template_id, '_dg_mapping', true );

        if ( ! $filename || ! file_exists( dg_get_templates_dir() . $filename ) ) {
            return new WP_Error( 'template_missing', __( 'Template file not found.', 'document-generator' ) );
        }

        if ( ! is_array( $mapping ) || empty( $mapping ) ) {
            return new WP_Error( 'no_mapping', __( 'No field mapping configured for this template.', 'document-generator' ) );
        }

        $template_path  = dg_get_templates_dir() . $filename;
        $user_id        = get_current_user_id();
        $repeat_source  = get_post_meta( $template_id, '_dg_repeat_source', true );

        // If entry_index is specified and we have a repeat source, pre-fetch the entry data.
        $entry_data = null;
        if ( $entry_index >= 0 && ! empty( $repeat_source ) ) {
            $all_entries = $this->fields_handler->resolve_repeat_data(
                array( 'source' => 'toolset_repeating', 'field' => $repeat_source ),
                $context_post_id
            );
            if ( isset( $all_entries[ $entry_index ] ) ) {
                $entry_data = $all_entries[ $entry_index ];
            } else {
                return new WP_Error( 'invalid_entry', __( 'Entry not found.', 'document-generator' ) );
            }
        }

        // Resolve all field values.
        $replacements = array();
        $repeat_data  = array();

        // Auto-inject denumire_document placeholder from template title.
        $replacements['denumire_document'] = get_the_title( $template_id );

        foreach ( $mapping as $placeholder => $config ) {
            $source = $config['source'] ?? '';
            $field  = $config['field'] ?? '';

            // If we have per-entry data, check if this field exists in the entry.
            if ( $entry_data !== null && $this->field_in_entry( $field, $entry_data ) ) {
                $clean = ( strpos( $field, 'wpcf-' ) === 0 ) ? substr( $field, 5 ) : $field;
                $replacements[ $placeholder ] = (string) ( $entry_data[ $clean ] ?? $entry_data[ $field ] ?? '' );
                continue;
            }

            // Check if this is a repeat block mapping (normal mode, no per-entry).
            if ( $entry_index < 0 && ( $source === 'toolset_repeating' || $source === 'wp_users' || ( isset( $config['is_repeat'] ) && $config['is_repeat'] ) ) ) {
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
     * Check if a field key exists in the repeat entry data.
     */
    private function field_in_entry( $field, $entry_data ) {
        if ( empty( $field ) || ! is_array( $entry_data ) ) {
            return false;
        }
        // Direct match.
        if ( array_key_exists( $field, $entry_data ) ) {
            return true;
        }
        // Try without 'wpcf-' prefix if present.
        if ( strpos( $field, 'wpcf-' ) === 0 ) {
            $clean = substr( $field, 5 );
            return array_key_exists( $clean, $entry_data );
        }
        return false;
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
