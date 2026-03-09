<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles document generation: resolves field mappings, generates DOCX, converts to PDF.
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
     * @param int    $template_id The dg_template post ID.
     * @param string $format      Output format: 'docx' or 'pdf'.
     * @param int    $context_post_id The current post/page ID for context.
     * @return string|WP_Error Path to generated file or error.
     */
    public function generate( $template_id, $format = 'docx', $context_post_id = 0 ) {
        $filename = get_post_meta( $template_id, '_dg_filename', true );
        $mapping  = get_post_meta( $template_id, '_dg_mapping', true );

        if ( ! $filename || ! file_exists( DG_TEMPLATES_DIR . $filename ) ) {
            return new WP_Error( 'template_missing', __( 'Template file not found.', 'document-generator' ) );
        }

        if ( ! is_array( $mapping ) || empty( $mapping ) ) {
            return new WP_Error( 'no_mapping', __( 'No field mapping configured for this template.', 'document-generator' ) );
        }

        $template_path = DG_TEMPLATES_DIR . $filename;
        $user_id       = get_current_user_id();

        // Resolve all field values.
        $replacements = array();
        $repeat_data  = array();

        foreach ( $mapping as $placeholder => $config ) {
            $source = $config['source'] ?? '';

            // Check if this is a repeat block mapping.
            if ( $source === 'toolset_repeating' || ( isset( $config['is_repeat'] ) && $config['is_repeat'] ) ) {
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

        if ( is_wp_error( $output_path ) ) {
            return $output_path;
        }

        // Convert to PDF if requested.
        if ( 'pdf' === $format ) {
            $pdf_path = $this->convert_to_pdf( $output_path );

            // Clean up the DOCX temp file.
            if ( ! is_wp_error( $pdf_path ) ) {
                unlink( $output_path );
                return $pdf_path;
            }

            return $pdf_path;
        }

        return $output_path;
    }

    /**
     * Convert a DOCX file to PDF.
     * Tries LibreOffice first (most reliable for DOCX), falls back to DomPDF.
     *
     * @param string $docx_path Path to the DOCX file.
     * @return string|WP_Error Path to PDF file or error.
     */
    private function convert_to_pdf( $docx_path ) {
        // Method 1: LibreOffice (best quality, preserves formatting).
        $pdf_path = $this->convert_with_libreoffice( $docx_path );
        if ( ! is_wp_error( $pdf_path ) ) {
            return $pdf_path;
        }

        // Method 2: unoconv
        $pdf_path = $this->convert_with_unoconv( $docx_path );
        if ( ! is_wp_error( $pdf_path ) ) {
            return $pdf_path;
        }

        return new WP_Error(
            'pdf_conversion_failed',
            __( 'PDF conversion failed. Please install LibreOffice on the server for PDF support (apt-get install libreoffice), or download as DOCX.', 'document-generator' )
        );
    }

    /**
     * Convert using LibreOffice command line.
     */
    private function convert_with_libreoffice( $docx_path ) {
        $libreoffice = $this->find_executable( array( 'libreoffice', 'soffice', '/usr/bin/libreoffice', '/usr/bin/soffice' ) );

        if ( ! $libreoffice ) {
            return new WP_Error( 'no_libreoffice', __( 'LibreOffice not found.', 'document-generator' ) );
        }

        $output_dir = dirname( $docx_path );
        $cmd = sprintf(
            '%s --headless --convert-to pdf --outdir %s %s 2>&1',
            escapeshellarg( $libreoffice ),
            escapeshellarg( $output_dir ),
            escapeshellarg( $docx_path )
        );

        $output = array();
        $return_code = 0;
        exec( $cmd, $output, $return_code );

        if ( 0 !== $return_code ) {
            return new WP_Error( 'libreoffice_error', implode( "\n", $output ) );
        }

        $pdf_path = preg_replace( '/\.docx$/i', '.pdf', $docx_path );

        if ( ! file_exists( $pdf_path ) ) {
            return new WP_Error( 'pdf_not_created', __( 'PDF file was not created.', 'document-generator' ) );
        }

        return $pdf_path;
    }

    /**
     * Convert using unoconv.
     */
    private function convert_with_unoconv( $docx_path ) {
        $unoconv = $this->find_executable( array( 'unoconv', '/usr/bin/unoconv' ) );

        if ( ! $unoconv ) {
            return new WP_Error( 'no_unoconv', __( 'unoconv not found.', 'document-generator' ) );
        }

        $pdf_path = preg_replace( '/\.docx$/i', '.pdf', $docx_path );

        $cmd = sprintf(
            '%s -f pdf -o %s %s 2>&1',
            escapeshellarg( $unoconv ),
            escapeshellarg( $pdf_path ),
            escapeshellarg( $docx_path )
        );

        $output = array();
        $return_code = 0;
        exec( $cmd, $output, $return_code );

        if ( 0 !== $return_code || ! file_exists( $pdf_path ) ) {
            return new WP_Error( 'unoconv_error', implode( "\n", $output ) );
        }

        return $pdf_path;
    }

    /**
     * Find an executable in the system.
     */
    private function find_executable( $names ) {
        foreach ( $names as $name ) {
            if ( file_exists( $name ) && is_executable( $name ) ) {
                return $name;
            }

            $which = trim( shell_exec( 'which ' . escapeshellarg( $name ) . ' 2>/dev/null' ) );
            if ( $which && is_executable( $which ) ) {
                return $which;
            }
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
