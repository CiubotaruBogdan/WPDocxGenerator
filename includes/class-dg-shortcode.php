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
        add_action( 'wp_ajax_dg_get_repeat_entries', array( $this, 'ajax_get_repeat_entries' ) );

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

        $extra_class   = sanitize_html_class( $atts['class'] );
        $nonce         = wp_create_nonce( 'dg_download_' . $template_id );
        $repeat_source = get_post_meta( $template_id, '_dg_repeat_source', true );

        // Signal that the shortcode is being rendered (for asset enqueuing).
        do_action( 'dg_shortcode_rendered' );

        // Ensure assets are enqueued even when shortcode is in a template/page builder.
        if ( ! wp_script_is( 'dg-frontend', 'enqueued' ) ) {
            $this->enqueue_assets();
        }

        ob_start();

        if ( ! empty( $repeat_source ) ) {
            // Multi-document mode: render table with one download button per entry.
            $this->render_repeat_table( $template_id, $repeat_source, $nonce, $inline_style, $button_text, $extra_class );
        } else {
            // Single document mode: standard button.
            ?>
            <div class="dg-download-wrapper <?php echo esc_attr( $extra_class ); ?>" data-template-id="<?php echo esc_attr( $template_id ); ?>">
                <button type="button"
                        class="dg-download-btn dg-btn-docx"
                        style="<?php echo $inline_style; ?>"
                        data-template-id="<?php echo esc_attr( $template_id ); ?>"
                        data-format="docx"
                        data-nonce="<?php echo esc_attr( $nonce ); ?>">
                    <span class="dg-icon dg-icon-docx"></span>
                    <?php echo esc_html( $button_text ); ?>
                </button>
                <div class="dg-status" style="display:none;"></div>
            </div>
            <?php
        }

        return ob_get_clean();
    }

    /**
     * Render a table with one download button per repeating entry.
     */
    private function render_repeat_table( $template_id, $repeat_source, $nonce, $inline_style, $button_text, $extra_class ) {
        // Get current post ID for context.
        $context_post_id = get_the_ID();

        $fields_handler = new DG_Fields();
        $entries = $fields_handler->resolve_repeat_data(
            array( 'source' => 'toolset_repeating', 'field' => $repeat_source ),
            $context_post_id
        );

        if ( empty( $entries ) ) {
            echo '<p class="dg-no-entries">' . esc_html__( 'No entries found.', 'document-generator' ) . '</p>';
            return;
        }

        // Determine table columns from entry keys (exclude internal keys).
        $skip_keys = array( 'index', 'post_id', 'title' );
        $columns   = array();
        if ( ! empty( $entries[0] ) ) {
            foreach ( $entries[0] as $key => $val ) {
                if ( ! in_array( $key, $skip_keys, true ) ) {
                    $columns[ $key ] = $key;
                }
            }
        }

        // Try to get Toolset field labels for nicer column headers.
        $toolset_fields = get_option( 'wpcf-fields', array() );
        foreach ( $columns as $key => $label ) {
            if ( isset( $toolset_fields[ $key ]['name'] ) ) {
                $columns[ $key ] = $toolset_fields[ $key ]['name'];
            }
        }
        ?>
        <div class="dg-repeat-table-wrapper <?php echo esc_attr( $extra_class ); ?>" data-template-id="<?php echo esc_attr( $template_id ); ?>">
            <table class="dg-repeat-table">
                <thead>
                    <tr>
                        <th class="dg-col-index">#</th>
                        <?php foreach ( $columns as $key => $label ) : ?>
                            <th><?php echo esc_html( $label ); ?></th>
                        <?php endforeach; ?>
                        <th class="dg-col-action"><?php esc_html_e( 'Download', 'document-generator' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $entries as $idx => $entry ) : ?>
                        <tr>
                            <td class="dg-col-index"><?php echo esc_html( $entry['index'] ?? ( $idx + 1 ) ); ?></td>
                            <?php foreach ( $columns as $key => $label ) : ?>
                                <td><?php echo esc_html( $entry[ $key ] ?? '' ); ?></td>
                            <?php endforeach; ?>
                            <td class="dg-col-action">
                                <button type="button"
                                        class="dg-download-btn dg-btn-docx dg-btn-small"
                                        style="<?php echo $inline_style; ?>"
                                        data-template-id="<?php echo esc_attr( $template_id ); ?>"
                                        data-format="docx"
                                        data-nonce="<?php echo esc_attr( $nonce ); ?>"
                                        data-entry-index="<?php echo esc_attr( $idx ); ?>">
                                    <span class="dg-icon dg-icon-docx"></span>
                                    <?php echo esc_html( $button_text ); ?>
                                </button>
                                <div class="dg-status" style="display:none;"></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
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
        $post_id     = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $entry_index = isset( $_POST['entry_index'] ) ? intval( $_POST['entry_index'] ) : -1;

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

        $generator = new DG_Generator();
        $file_path = $generator->generate( $template_id, $post_id, $entry_index );

        if ( is_wp_error( $file_path ) ) {
            wp_send_json_error( $file_path->get_error_message() );
        }

        // Build download filename: AAAALLZZ_N_###_[template_name]-[02512].docx
        $template_title = sanitize_file_name( get_the_title( $template_id ) );
        $download_name  = wp_date( 'Ymd' ) . '_N_###_' . $template_title . '-02512.docx';

        // Create a temporary download token.
        $token = wp_generate_password( 32, false );
        set_transient( 'dg_download_' . $token, array(
            'path' => $file_path,
            'name' => $download_name,
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

    $filename = $data['name'];

    // Send file.
    header( 'Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document' );
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
