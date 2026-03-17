<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles DOCX template parsing, placeholder detection, and content manipulation.
 * Works directly with DOCX XML to preserve all formatting.
 */
class DG_Template {

    /**
     * Extract all placeholders from a DOCX file.
     *
     * @param string $filepath Full path to the DOCX file.
     * @return array|WP_Error Array of placeholder names or error.
     */
    public function extract_placeholders( $filepath ) {
        $parts = $this->get_all_xml_parts( $filepath );

        if ( is_wp_error( $parts ) ) {
            return $parts;
        }

        $placeholders  = array();
        $repeat_blocks = array();

        foreach ( $parts as $part_name => $content ) {
            // Merge split placeholder runs first.
            $content = $this->merge_split_runs( $content );

            // Match standard placeholders: #name#
            if ( preg_match_all( '/#([a-zA-Z0-9_]+)#/', $content, $matches ) ) {
                $placeholders = array_merge( $placeholders, $matches[1] );
            }

            // Match repeat blocks: #repeat:source_name# and their inner placeholders.
            if ( preg_match_all( '/#repeat:([a-zA-Z0-9_]+)#/', $content, $rep_matches ) ) {
                foreach ( $rep_matches[1] as $block_name ) {
                    $repeat_blocks[] = $block_name;
                }
            }
        }

        $placeholders = array_unique( $placeholders );

        // Remove repeat/endrepeat markers from placeholder list.
        $placeholders = array_filter( $placeholders, function( $p ) {
            return ! preg_match( '/^(repeat|endrepeat)/', $p );
        } );

        return array(
            'placeholders'  => array_values( $placeholders ),
            'repeat_blocks' => array_unique( $repeat_blocks ),
        );
    }

    /**
     * Read the document.xml content from a DOCX file.
     *
     * @param string $filepath Full path to the DOCX file.
     * @return string|WP_Error XML content or error.
     */
    public function get_docx_xml( $filepath ) {
        if ( ! file_exists( $filepath ) ) {
            return new WP_Error( 'file_not_found', __( 'Template file not found.', 'document-generator' ) );
        }

        $zip = new ZipArchive();
        $result = $zip->open( $filepath );

        if ( true !== $result ) {
            return new WP_Error( 'zip_error', __( 'Could not open DOCX file.', 'document-generator' ) );
        }

        $content = $zip->getFromName( 'word/document.xml' );
        $zip->close();

        if ( false === $content ) {
            return new WP_Error( 'xml_error', __( 'Could not read document content.', 'document-generator' ) );
        }

        return $content;
    }

    /**
     * Get all XML parts from a DOCX (document, headers, footers).
     *
     * @param string $filepath Full path to the DOCX file.
     * @return array|WP_Error Associative array of part_name => xml_content.
     */
    public function get_all_xml_parts( $filepath ) {
        if ( ! file_exists( $filepath ) ) {
            return new WP_Error( 'file_not_found', __( 'Template file not found.', 'document-generator' ) );
        }

        $zip = new ZipArchive();
        if ( true !== $zip->open( $filepath ) ) {
            return new WP_Error( 'zip_error', __( 'Could not open DOCX file.', 'document-generator' ) );
        }

        $parts = array();

        // Main document.
        $content = $zip->getFromName( 'word/document.xml' );
        if ( false !== $content ) {
            $parts['word/document.xml'] = $content;
        }

        // Headers and footers.
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $name = $zip->getNameIndex( $i );
            if ( preg_match( '/^word\/(header|footer)\d+\.xml$/', $name ) ) {
                $parts[ $name ] = $zip->getFromName( $name );
            }
        }

        $zip->close();

        return $parts;
    }

    /**
     * Merge split XML runs that break placeholders.
     * Word often splits text like #placeholder# into multiple <w:r> elements.
     *
     * @param string $xml The XML content.
     * @return string Cleaned XML with merged placeholders.
     */
    public function merge_split_runs( $xml ) {
        // Pattern: find sequences where # starts in one run and ends in another.
        // We need to consolidate text within <w:p> (paragraph) elements.
        $dom = new DOMDocument();
        $dom->loadXML( $xml );

        $xpath    = new DOMXPath( $dom );
        $xpath->registerNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );

        // Process each paragraph.
        $paragraphs = $xpath->query( '//w:p' );

        foreach ( $paragraphs as $paragraph ) {
            $this->merge_paragraph_runs( $paragraph, $xpath );
        }

        return $dom->saveXML();
    }

    /**
     * Within a paragraph, merge runs that contain split placeholders.
     */
    private function merge_paragraph_runs( $paragraph, $xpath ) {
        // Use ./w:r (direct children) instead of .//w:r to avoid processing
        // runs inside nested textbox paragraphs as part of the outer paragraph.
        $runs = $xpath->query( './w:r', $paragraph );
        if ( $runs->length < 2 ) {
            return;
        }

        // Collect all text content to check for placeholders.
        $full_text = '';
        $run_texts = array();

        foreach ( $runs as $run ) {
            $text_nodes = $xpath->query( './w:t', $run );
            $text = '';
            foreach ( $text_nodes as $t ) {
                $text .= $t->textContent;
            }
            $run_texts[] = $text;
            $full_text .= $text;
        }

        // If no placeholders in this paragraph, skip.
        if ( ! preg_match( '/#[a-zA-Z0-9_]+#/', $full_text ) && ! preg_match( '/#repeat:[a-zA-Z0-9_]+#/', $full_text ) ) {
            return;
        }

        // Check if any placeholder is split across runs.
        $has_split = false;
        foreach ( $run_texts as $rt ) {
            // A run with unmatched # indicates a split.
            $hash_count = substr_count( $rt, '#' );
            if ( $hash_count % 2 !== 0 ) {
                $has_split = true;
                break;
            }
        }

        if ( ! $has_split ) {
            return;
        }

        // Merge strategy: find sequences of runs that form a placeholder and consolidate.
        $in_placeholder = false;
        $merge_start    = -1;
        $buffer         = '';

        $run_list = array();
        foreach ( $runs as $run ) {
            $run_list[] = $run;
        }

        for ( $i = 0; $i < count( $run_list ); $i++ ) {
            $text_nodes = $xpath->query( './w:t', $run_list[ $i ] );
            $text = '';
            foreach ( $text_nodes as $t ) {
                $text .= $t->textContent;
            }

            if ( ! $in_placeholder ) {
                // Check if this run starts a placeholder but doesn't finish it.
                if ( preg_match( '/#[^#]*$/', $text ) ) {
                    $in_placeholder = true;
                    $merge_start    = $i;
                    $buffer         = $text;
                }
            } else {
                $buffer .= $text;

                // Check if placeholder closes in this run.
                if ( strpos( $text, '#' ) !== false ) {
                    $in_placeholder = false;

                    // Merge: put all text in the first run, clear the rest.
                    $first_text_nodes = $xpath->query( './w:t', $run_list[ $merge_start ] );
                    if ( $first_text_nodes->length > 0 ) {
                        $first_text_nodes->item( 0 )->textContent = $buffer;
                        $first_text_nodes->item( 0 )->setAttribute( 'xml:space', 'preserve' );
                    }

                    // Remove text from merged runs.
                    for ( $j = $merge_start + 1; $j <= $i; $j++ ) {
                        $other_text_nodes = $xpath->query( './w:t', $run_list[ $j ] );
                        foreach ( $other_text_nodes as $t ) {
                            $t->textContent = '';
                        }
                    }

                    $buffer = '';
                }
            }
        }
    }

    /**
     * Replace placeholders in a DOCX file and return path to the generated file.
     *
     * @param string $template_path Path to the template DOCX.
     * @param array  $replacements  Associative array of placeholder => value.
     * @param array  $repeat_data   Associative array of block_name => array of rows.
     * @return string|WP_Error Path to generated file or error.
     */
    public function generate_document( $template_path, $replacements, $repeat_data = array() ) {
        if ( ! file_exists( $template_path ) ) {
            return new WP_Error( 'file_not_found', __( 'Template file not found.', 'document-generator' ) );
        }

        // Create a working copy.
        $upload_dir = wp_upload_dir();
        $temp_dir   = $upload_dir['basedir'] . '/dg-temp/';
        wp_mkdir_p( $temp_dir );

        $output_filename = 'dg-' . wp_generate_password( 12, false ) . '.docx';
        $output_path     = $temp_dir . $output_filename;

        if ( ! copy( $template_path, $output_path ) ) {
            return new WP_Error( 'copy_error', __( 'Failed to create working copy.', 'document-generator' ) );
        }

        $zip = new ZipArchive();
        if ( true !== $zip->open( $output_path ) ) {
            unlink( $output_path );
            return new WP_Error( 'zip_error', __( 'Could not open document for editing.', 'document-generator' ) );
        }

        // Process all XML parts (document, headers, footers).
        $xml_parts = array( 'word/document.xml' );

        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $name = $zip->getNameIndex( $i );
            if ( preg_match( '/^word\/(header|footer)\d+\.xml$/', $name ) ) {
                $xml_parts[] = $name;
            }
        }

        foreach ( $xml_parts as $part_name ) {
            $xml = $zip->getFromName( $part_name );
            if ( false === $xml ) {
                continue;
            }

            // Merge split runs.
            $xml = $this->merge_split_runs( $xml );

            // Handle repeat blocks in tables.
            if ( ! empty( $repeat_data ) ) {
                $xml = $this->process_repeat_blocks( $xml, $repeat_data );
            }

            // Replace simple placeholders.
            foreach ( $replacements as $placeholder => $value ) {
                $value = $this->escape_xml( $value );
                // Convert newlines to DOCX line breaks so multiline fields render correctly.
                $value = $this->convert_newlines_to_breaks( $value, $xml, $placeholder );
                $xml   = str_replace( '#' . $placeholder . '#', $value, $xml );
            }

            $zip->addFromString( $part_name, $xml );
        }

        $zip->close();

        return $output_path;
    }

    /**
     * Process repeat blocks in table rows.
     * A table row containing #repeat:name# will be duplicated for each data row.
     *
     * @param string $xml         The XML content.
     * @param array  $repeat_data Array of block_name => array of rows (each row is placeholder => value).
     * @return string Modified XML.
     */
    private function process_repeat_blocks( $xml, $repeat_data ) {
        $dom = new DOMDocument();
        $dom->loadXML( $xml );

        $xpath = new DOMXPath( $dom );
        $xpath->registerNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );

        foreach ( $repeat_data as $block_name => $rows ) {
            $marker = '#repeat:' . $block_name . '#';
            $end_marker = '#endrepeat#';

            // Find table rows containing the repeat marker.
            $table_rows = $xpath->query( '//w:tr' );

            foreach ( $table_rows as $row ) {
                $row_text = $row->textContent;

                if ( strpos( $row_text, $marker ) === false ) {
                    continue;
                }

                // This row is a repeat template row.
                $parent = $row->parentNode;

                // Check if the next row has endrepeat (multi-row template).
                // For simplicity, we handle single-row repeats.
                // Remove the repeat markers from the row XML.
                $row_xml = $dom->saveXML( $row );
                $row_xml = str_replace( $marker, '', $row_xml );
                $row_xml = str_replace( $end_marker, '', $row_xml );

                // Generate a new row for each data entry.
                foreach ( $rows as $row_data ) {
                    $new_row_xml = $row_xml;
                    foreach ( $row_data as $placeholder => $value ) {
                        $value = $this->escape_xml( $value );
                        $value = $this->convert_newlines_to_breaks( $value, $new_row_xml, $placeholder );
                        $new_row_xml = str_replace( '#' . $placeholder . '#', $value, $new_row_xml );
                    }

                    $fragment = $dom->createDocumentFragment();
                    $fragment->appendXML( $new_row_xml );
                    $parent->insertBefore( $fragment, $row );
                }

                // Remove the template row.
                $parent->removeChild( $row );
            }
        }

        return $dom->saveXML();
    }

    /**
     * Escape special XML characters in replacement values.
     */
    private function escape_xml( $value ) {
        return htmlspecialchars( (string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
    }

    /**
     * Convert newlines in a value to DOCX line breaks (<w:br/>).
     *
     * Inside DOCX XML, placeholders sit within <w:r><w:t> elements.
     * We close the current <w:t>, insert a <w:br/>, and reopen <w:t>.
     *
     * @param string $value       The escaped value (may contain newlines).
     * @param string $xml         The full XML content (unused, kept for signature).
     * @param string $placeholder The placeholder name (unused, kept for signature).
     * @return string The value with newlines converted to DOCX line breaks.
     */
    private function convert_newlines_to_breaks( $value, &$xml, $placeholder ) {
        $value = str_replace( array( "\r\n", "\r" ), "\n", $value );

        if ( strpos( $value, "\n" ) === false ) {
            return $value;
        }

        return implode( '</w:t><w:br/><w:t xml:space="preserve">', explode( "\n", $value ) );
    }
}
