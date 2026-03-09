<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles shortcode registration, frontend rendering, and download AJAX endpoint.
 */
class DG_Shortcode {

    public function __construct() {
        add_shortcode( 'document_generator', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_dg_download', array( $this, 'handle_download' ) );
        add_action( 'wp_ajax_nopriv_dg_download', array( $this, 'handle_download_denied' ) );

        // Cleanup cron.
        add_action( 'dg_cleanup_temp', array( 'DG_Generator', 'cleanup_temp_files' ) );
        if ( ! wp_next_scheduled( 'dg_cleanup_temp' ) ) {
            wp_schedule_event( time(), 'hourly', 'dg_cleanup_temp' );
        }
    }

    /**
     * Render the [document_generator] shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'id'     => 0,
            'class'  => '',
            'format' => '', // Empty = let user choose, 'docx' or 'pdf' to force.
        ), $atts, 'document_generator' );

        $template_id = absint( $atts['id'] );

        if ( ! $template_id ) {
            return '<!-- Document Generator: No template ID specified -->';
        }

        $template = get_post( $template_id );
        if ( ! $template || 'dg_template' !== $template->post_type ) {
            return '<!-- Document Generator: Template not found -->';
        }

        // Check role-based access.
        $allowed_roles = get_post_meta( $template_id, '_dg_allowed_roles', true );
        if ( ! $this->check_role_access( $allowed_roles ) ) {
            return ''; // Silently hide for unauthorized users.
        }

        $button_text = get_post_meta( $template_id, '_dg_button_text', true );
        if ( empty( $button_text ) ) {
            $button_text = __( 'Download Document', 'document-generator' );
        }

        // Format: shortcode attribute overrides saved setting.
        $saved_format = get_post_meta( $template_id, '_dg_button_format', true );
        if ( empty( $saved_format ) ) {
            $saved_format = 'both';
        }
        $forced_format = sanitize_text_field( $atts['format'] );
        if ( empty( $forced_format ) ) {
            $forced_format = $saved_format;
        }

        // Button style.
        $button_style = get_post_meta( $template_id, '_dg_button_style', true );
        $style_defaults = array(
            'bg_color'      => '#2b579a',
            'text_color'    => '#ffffff',
            'border_color'  => '',
            'border_width'  => '0',
            'font_size'     => '15',
            'border_radius' => '6',
        );
        if ( ! is_array( $button_style ) ) {
            $button_style = $style_defaults;
        } else {
            $button_style = wp_parse_args( $button_style, $style_defaults );
        }

        $inline_style = sprintf(
            'background-color:%s;color:%s;border:%spx solid %s;font-size:%spx;border-radius:%spx;',
            esc_attr( $button_style['bg_color'] ),
            esc_attr( $button_style['text_color'] ),
            esc_attr( $button_style['border_width'] ),
            esc_attr( $button_style['border_color'] ?: $button_style['bg_color'] ),
            esc_attr( $button_style['font_size'] ),
            esc_attr( $button_style['border_radius'] )
        );

        // PDF button uses slightly different color by default (red-ish).
        $pdf_style = $inline_style;
        if ( $button_style['bg_color'] === '#2b579a' ) {
            // Only override if using default color.
            $pdf_style = str_replace( 'background-color:#2b579a', 'background-color:#d32f2f', $pdf_style );
            if ( empty( $button_style['border_color'] ) || $button_style['border_color'] === '#2b579a' ) {
                $pdf_style = str_replace( 'solid #2b579a', 'solid #d32f2f', $pdf_style );
            }
        }

        $extra_class = sanitize_html_class( $atts['class'] );
        $nonce       = wp_create_nonce( 'dg_download_' . $template_id );

        // Signal that the shortcode is being rendered (for asset enqueuing).
        do_action( 'dg_shortcode_rendered' );

        // Ensure assets are enqueued even when shortcode is in a template/page builder.
        if ( ! wp_script_is( 'dg-frontend', 'enqueued' ) ) {
            $this->enqueue_assets();
        }

        ob_start();
        ?>
        <div class="dg-download-wrapper <?php echo esc_attr( $extra_class ); ?>" data-template-id="<?php echo esc_attr( $template_id ); ?>">
            <?php if ( 'both' === $forced_format ) : ?>
                <div class="dg-format-selector">
                    <button type="button"
                            class="dg-download-btn dg-btn-docx"
                            style="<?php echo $inline_style; ?>"
                            data-template-id="<?php echo esc_attr( $template_id ); ?>"
                            data-format="docx"
                            data-nonce="<?php echo esc_attr( $nonce ); ?>">
                        <span class="dg-icon dg-icon-docx"></span>
                        <?php echo esc_html( $button_text ); ?> (DOCX)
                    </button>
                    <button type="button"
                            class="dg-download-btn dg-btn-pdf"
                            style="<?php echo $pdf_style; ?>"
                            data-template-id="<?php echo esc_attr( $template_id ); ?>"
                            data-format="pdf"
                            data-nonce="<?php echo esc_attr( $nonce ); ?>">
                        <span class="dg-icon dg-icon-pdf"></span>
                        <?php echo esc_html( $button_text ); ?> (PDF)
                    </button>
                </div>
            <?php else : ?>
                <button type="button"
                        class="dg-download-btn dg-btn-<?php echo esc_attr( $forced_format ); ?>"
                        style="<?php echo ( 'pdf' === $forced_format ? $pdf_style : $inline_style ); ?>"
                        data-template-id="<?php echo esc_attr( $template_id ); ?>"
                        data-format="<?php echo esc_attr( $forced_format ); ?>"
                        data-nonce="<?php echo esc_attr( $nonce ); ?>">
                    <span class="dg-icon dg-icon-<?php echo esc_attr( $forced_format ); ?>"></span>
                    <?php echo esc_html( $button_text ); ?>
                </button>
            <?php endif; ?>
            <div class="dg-status" style="display:none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue frontend assets (only when shortcode is present).
     */
    public function enqueue_assets() {
        // Check the current post content for the shortcode.
        global $post;
        $has_shortcode = $post && has_shortcode( $post->post_content, 'document_generator' );

        // Also check if the shortcode was already rendered during this request
        // (e.g., via page builder templates, Toolset Content Templates, theme templates).
        if ( ! $has_shortcode && ! did_action( 'dg_shortcode_rendered' ) ) {
            return;
        }

        wp_enqueue_style(
            'dg-frontend',
            DG_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            DG_VERSION
        );

        wp_enqueue_script(
            'dg-frontend',
            DG_PLUGIN_URL . 'assets/js/frontend.js',
            array( 'jquery' ),
            DG_VERSION,
            true
        );

        wp_localize_script( 'dg-frontend', 'dgFrontend', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'strings' => array(
                'generating' => __( 'Generating document...', 'document-generator' ),
                'error'      => __( 'An error occurred. Please try again.', 'document-generator' ),
                'noAccess'   => __( 'You do not have permission to download this document.', 'document-generator' ),
            ),
        ) );
    }

    /**
     * Handle document download AJAX request.
     */
    public function handle_download() {
        $template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
        $format      = isset( $_POST['format'] ) ? sanitize_text_field( $_POST['format'] ) : 'docx';
        $post_id     = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        if ( ! $template_id ) {
            wp_send_json_error( __( 'Invalid template.', 'document-generator' ) );
        }

        // Verify nonce.
        if ( ! check_ajax_referer( 'dg_download_' . $template_id, 'nonce', false ) ) {
            wp_send_json_error( __( 'Security check failed.', 'document-generator' ) );
        }

        // Check role access.
        $allowed_roles = get_post_meta( $template_id, '_dg_allowed_roles', true );
        if ( ! $this->check_role_access( $allowed_roles ) ) {
            wp_send_json_error( __( 'You do not have permission to download this document.', 'document-generator' ) );
        }

        // Validate format.
        if ( ! in_array( $format, array( 'docx', 'pdf' ), true ) ) {
            $format = 'docx';
        }

        // Use target page as fallback if no post_id detected from the frontend.
        // The frontend auto-detects the current post from body classes.
        // Target page provides context when the shortcode is on a page without a detectable post ID.
        if ( ! $post_id ) {
            $target_page = get_post_meta( $template_id, '_dg_target_page', true );
            if ( $target_page ) {
                $post_id = absint( $target_page );
            }
        }

        $generator = new DG_Generator();
        $file_path = $generator->generate( $template_id, $format, $post_id );

        if ( is_wp_error( $file_path ) ) {
            wp_send_json_error( $file_path->get_error_message() );
        }

        // Create a temporary download token.
        $token = wp_generate_password( 32, false );
        set_transient( 'dg_download_' . $token, array(
            'path'   => $file_path,
            'format' => $format,
            'name'   => sanitize_file_name( get_the_title( $template_id ) ) . '.' . $format,
        ), 300 ); // 5 minute expiry.

        wp_send_json_success( array(
            'download_url' => add_query_arg( array(
                'dg_download' => $token,
            ), home_url( '/' ) ),
        ) );
    }

    /**
     * Deny downloads for non-logged-in users.
     */
    public function handle_download_denied() {
        wp_send_json_error( __( 'You must be logged in to download documents.', 'document-generator' ) );
    }

    /**
     * Check if the current user has one of the allowed roles.
     */
    private function check_role_access( $allowed_roles ) {
        if ( ! is_array( $allowed_roles ) || empty( $allowed_roles ) ) {
            // No restriction = all logged-in users.
            return is_user_logged_in();
        }

        if ( in_array( 'all', $allowed_roles, true ) ) {
            return is_user_logged_in();
        }

        $user = wp_get_current_user();
        if ( ! $user->exists() ) {
            return false;
        }

        return ! empty( array_intersect( $allowed_roles, $user->roles ) );
    }
}

/**
 * Handle file download via URL token (runs early, before headers sent).
 */
add_action( 'template_redirect', function() {
    if ( ! isset( $_GET['dg_download'] ) ) {
        return;
    }

    $token = sanitize_text_field( $_GET['dg_download'] );
    $data  = get_transient( 'dg_download_' . $token );

    if ( ! $data || ! file_exists( $data['path'] ) ) {
        wp_die( __( 'Download link expired or invalid.', 'document-generator' ), __( 'Download Error', 'document-generator' ), 404 );
    }

    // Delete the transient (one-time download).
    delete_transient( 'dg_download_' . $token );

    $format   = $data['format'];
    $filename = $data['name'];

    if ( 'pdf' === $format ) {
        $content_type = 'application/pdf';
    } else {
        $content_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    // Send file.
    header( 'Content-Type: ' . $content_type );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Length: ' . filesize( $data['path'] ) );
    header( 'Cache-Control: no-cache, no-store, must-revalidate' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    readfile( $data['path'] );

    // Cleanup.
    unlink( $data['path'] );

    exit;
} );
