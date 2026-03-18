<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$template_id   = isset( $template_id ) ? $template_id : 0;
$is_edit       = (bool) $template_id;
$title         = '';
$filename      = '';
$mapping       = array();
$allowed_roles = array();
$button_text   = __( 'Download Document', 'document-generator' );
$button_style  = array(
    'bg_color'     => '#2b579a',
    'text_color'   => '#ffffff',
    'border_color' => '',
    'border_width' => '0',
    'font_size'    => '15',
    'border_radius' => '6',
);

if ( $is_edit ) {
    $template = get_post( $template_id );
    if ( $template && 'dg_template' === $template->post_type ) {
        $title         = $template->post_title;
        $filename      = get_post_meta( $template_id, '_dg_filename', true );
        $mapping       = get_post_meta( $template_id, '_dg_mapping', true );
        $allowed_roles = get_post_meta( $template_id, '_dg_allowed_roles', true );
        $button_text   = get_post_meta( $template_id, '_dg_button_text', true );
        $saved_style   = get_post_meta( $template_id, '_dg_button_style', true );
        if ( is_array( $saved_style ) ) {
            $button_style = wp_parse_args( $saved_style, $button_style );
        }
    }
    if ( ! is_array( $mapping ) ) {
        $mapping = array();
    }
    if ( ! is_array( $allowed_roles ) ) {
        $allowed_roles = array();
    }
    if ( empty( $button_text ) ) {
        $button_text = __( 'Download Document', 'document-generator' );
    }
}

// Get all WP roles.
$wp_roles = wp_roles()->get_names();

// Get field sources.
$fields_handler = new DG_Fields();
$sources = $fields_handler->get_sources();
?>

<div class="wrap dg-wrap">
    <h1>
        <?php echo $is_edit
            ? esc_html__( 'Edit Document Template', 'document-generator' )
            : esc_html__( 'Add New Document Template', 'document-generator' ); ?>
    </h1>

    <form id="dg-template-form" class="dg-form">
        <input type="hidden" name="template_id" value="<?php echo esc_attr( $template_id ); ?>">

        <!-- Step 1: Basic Settings -->
        <div class="dg-section">
            <h2><?php esc_html_e( 'Template Settings', 'document-generator' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th><label for="dg-title"><?php esc_html_e( 'Template Name', 'document-generator' ); ?></label></th>
                    <td>
                        <input type="text" id="dg-title" name="title" class="regular-text"
                               value="<?php echo esc_attr( $title ); ?>"
                               placeholder="<?php esc_attr_e( 'e.g., Employment Contract', 'document-generator' ); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'DOCX Template File', 'document-generator' ); ?></label></th>
                    <td>
                        <div id="dg-upload-area" class="dg-upload-area <?php echo $filename ? 'has-file' : ''; ?>">
                            <?php if ( $filename ) : ?>
                                <div class="dg-current-file">
                                    <span class="dashicons dashicons-media-document"></span>
                                    <span class="dg-filename"><?php echo esc_html( $filename ); ?></span>
                                    <button type="button" class="button button-small" id="dg-change-file">
                                        <?php esc_html_e( 'Change File', 'document-generator' ); ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                            <div class="dg-upload-prompt" <?php echo $filename ? 'style="display:none;"' : ''; ?>>
                                <span class="dashicons dashicons-upload"></span>
                                <p><?php esc_html_e( 'Drag & drop a DOCX file here, or click to browse', 'document-generator' ); ?></p>
                                <p class="description"><?php esc_html_e( 'Use #placeholder_name# format for dynamic fields', 'document-generator' ); ?></p>
                                <input type="file" id="dg-file-input" accept=".docx" style="display:none;">
                                <button type="button" class="button" id="dg-browse-btn">
                                    <?php esc_html_e( 'Browse Files', 'document-generator' ); ?>
                                </button>
                            </div>
                            <div class="dg-upload-progress" style="display:none;">
                                <div class="dg-progress-bar"><div class="dg-progress-fill"></div></div>
                                <p class="dg-progress-text"><?php esc_html_e( 'Uploading...', 'document-generator' ); ?></p>
                            </div>
                        </div>
                        <input type="hidden" id="dg-filename" name="filename" value="<?php echo esc_attr( $filename ); ?>">
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Allowed Roles', 'document-generator' ); ?></label></th>
                    <td>
                        <fieldset>
                            <?php foreach ( $wp_roles as $role_slug => $role_name ) : ?>
                                <label style="display:block; margin-bottom:4px;">
                                    <input type="checkbox" name="allowed_roles[]"
                                           value="<?php echo esc_attr( $role_slug ); ?>"
                                           <?php checked( in_array( $role_slug, $allowed_roles, true ) ); ?>>
                                    <?php echo esc_html( translate_user_role( $role_name ) ); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description"><?php esc_html_e( 'Leave unchecked to allow all logged-in users.', 'document-generator' ); ?></p>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Step 2: Field Mapping -->
        <div class="dg-section" id="dg-mapping-section" <?php echo empty( $mapping ) && ! $filename ? 'style="display:none;"' : ''; ?>>
            <h2><?php esc_html_e( 'Field Mapping', 'document-generator' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Map each placeholder from your document to a WordPress field.', 'document-generator' ); ?></p>

            <div id="dg-mapping-table">
                <table class="wp-list-table widefat dg-mapping-list">
                    <thead>
                        <tr>
                            <th class="column-placeholder"><?php esc_html_e( 'Placeholder', 'document-generator' ); ?></th>
                            <th class="column-source"><?php esc_html_e( 'Field Source', 'document-generator' ); ?></th>
                            <th class="column-field"><?php esc_html_e( 'Field', 'document-generator' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="dg-mapping-body">
                        <?php if ( ! empty( $mapping ) ) : ?>
                            <?php foreach ( $mapping as $placeholder => $config ) : ?>
                                <tr class="dg-mapping-row" data-placeholder="<?php echo esc_attr( $placeholder ); ?>">
                                    <td class="column-placeholder">
                                        <code>#<?php echo esc_html( $placeholder ); ?>#</code>
                                    </td>
                                    <td class="column-source">
                                        <select class="dg-source-select" data-placeholder="<?php echo esc_attr( $placeholder ); ?>">
                                            <option value=""><?php esc_html_e( '— Select source —', 'document-generator' ); ?></option>
                                            <?php foreach ( $sources as $src_key => $src_label ) : ?>
                                                <option value="<?php echo esc_attr( $src_key ); ?>"
                                                        <?php selected( $config['source'] ?? '', $src_key ); ?>>
                                                    <?php echo esc_html( $src_label ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="column-field">
                                        <?php if ( ( $config['source'] ?? '' ) === 'custom' ) : ?>
                                            <input type="text" class="dg-custom-text-input regular-text" data-placeholder="<?php echo esc_attr( $placeholder ); ?>" placeholder="<?php esc_attr_e( 'Enter custom text...', 'document-generator' ); ?>" value="<?php echo esc_attr( $config['meta'] ?? '' ); ?>">
                                        <?php else : ?>
                                            <select class="dg-field-select" data-placeholder="<?php echo esc_attr( $placeholder ); ?>">
                                                <option value=""><?php esc_html_e( '— Select field —', 'document-generator' ); ?></option>
                                            </select>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Repeat blocks section -->
            <div id="dg-repeat-blocks" style="display:none;">
                <h3><?php esc_html_e( 'Repeating Table Blocks', 'document-generator' ); ?></h3>
                <p class="description"><?php esc_html_e( 'These blocks will generate multiple rows in your document tables.', 'document-generator' ); ?></p>
                <div id="dg-repeat-blocks-list"></div>
            </div>
        </div>

        <!-- Step 3: Shortcode & Button -->
        <div class="dg-section" id="dg-shortcode-section" <?php echo ! $is_edit ? 'style="display:none;"' : ''; ?>>
            <h2><?php esc_html_e( 'Shortcode & Button', 'document-generator' ); ?></h2>

            <div class="dg-shortcode-output">
                <code id="dg-shortcode-code"><?php
                    echo $is_edit
                        ? esc_html( '[document_generator id="' . $template_id . '"]' )
                        : '';
                ?></code>
                <button type="button" class="button" id="dg-copy-shortcode">
                    <?php esc_html_e( 'Copy Shortcode', 'document-generator' ); ?>
                </button>
            </div>
            <p class="description"><?php esc_html_e( 'Paste this shortcode into any page or post to display the download button.', 'document-generator' ); ?></p>
            <p class="description">
                <?php esc_html_e( 'Options: class="my-class" for custom CSS class.', 'document-generator' ); ?>
            </p>

            <hr style="margin: 20px 0;">

            <table class="form-table" style="margin-top:0;">
                <tr>
                    <th><label for="dg-button-text"><?php esc_html_e( 'Button Text', 'document-generator' ); ?></label></th>
                    <td>
                        <input type="text" id="dg-button-text" name="button_text" class="regular-text"
                               value="<?php echo esc_attr( $button_text ); ?>"
                               placeholder="<?php esc_attr_e( 'Download Document', 'document-generator' ); ?>">
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Button Style', 'document-generator' ); ?></th>
                    <td>
                        <div class="dg-button-style-grid">
                            <label>
                                <?php esc_html_e( 'Background Color', 'document-generator' ); ?>
                                <input type="color" name="button_style[bg_color]" value="<?php echo esc_attr( $button_style['bg_color'] ); ?>">
                            </label>
                            <label>
                                <?php esc_html_e( 'Text Color', 'document-generator' ); ?>
                                <input type="color" name="button_style[text_color]" value="<?php echo esc_attr( $button_style['text_color'] ); ?>">
                            </label>
                            <label>
                                <?php esc_html_e( 'Border Color', 'document-generator' ); ?>
                                <input type="color" name="button_style[border_color]" value="<?php echo esc_attr( $button_style['border_color'] ?: '#2b579a' ); ?>">
                            </label>
                            <label>
                                <?php esc_html_e( 'Border Width (px)', 'document-generator' ); ?>
                                <input type="number" name="button_style[border_width]" value="<?php echo esc_attr( $button_style['border_width'] ); ?>" min="0" max="10" step="1" style="width:70px;">
                            </label>
                            <label>
                                <?php esc_html_e( 'Font Size (px)', 'document-generator' ); ?>
                                <input type="number" name="button_style[font_size]" value="<?php echo esc_attr( $button_style['font_size'] ); ?>" min="10" max="30" step="1" style="width:70px;">
                            </label>
                            <label>
                                <?php esc_html_e( 'Border Radius (px)', 'document-generator' ); ?>
                                <input type="number" name="button_style[border_radius]" value="<?php echo esc_attr( $button_style['border_radius'] ); ?>" min="0" max="50" step="1" style="width:70px;">
                            </label>
                        </div>
                        <div class="dg-button-preview" style="margin-top:15px;">
                            <p><strong><?php esc_html_e( 'Preview:', 'document-generator' ); ?></strong></p>
                            <button type="button" id="dg-btn-preview" class="dg-download-btn" style="
                                background-color: <?php echo esc_attr( $button_style['bg_color'] ); ?>;
                                color: <?php echo esc_attr( $button_style['text_color'] ); ?>;
                                border: <?php echo esc_attr( $button_style['border_width'] ); ?>px solid <?php echo esc_attr( $button_style['border_color'] ?: $button_style['bg_color'] ); ?>;
                                font-size: <?php echo esc_attr( $button_style['font_size'] ); ?>px;
                                border-radius: <?php echo esc_attr( $button_style['border_radius'] ); ?>px;
                                padding: 12px 24px; font-weight: 600; cursor: default;
                            "><?php echo esc_html( $button_text ); ?></button>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Submit -->
        <div class="dg-section dg-submit-section">
            <button type="submit" class="button button-primary button-large" id="dg-save-btn">
                <?php echo $is_edit
                    ? esc_html__( 'Update Template', 'document-generator' )
                    : esc_html__( 'Save Template', 'document-generator' ); ?>
            </button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=document-generator' ) ); ?>" class="button button-large">
                <?php esc_html_e( 'Cancel', 'document-generator' ); ?>
            </a>
            <span class="dg-save-status" id="dg-save-status"></span>
        </div>
    </form>
</div>

<script type="text/html" id="tmpl-dg-mapping-row">
    <tr class="dg-mapping-row" data-placeholder="{{data.placeholder}}">
        <td class="column-placeholder">
            <code>#{{data.placeholder}}#</code>
        </td>
        <td class="column-source">
            <select class="dg-source-select" data-placeholder="{{data.placeholder}}">
                <option value=""><?php esc_html_e( '— Select source —', 'document-generator' ); ?></option>
                <?php foreach ( $sources as $src_key => $src_label ) : ?>
                    <option value="<?php echo esc_attr( $src_key ); ?>"><?php echo esc_html( $src_label ); ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="column-field">
            <select class="dg-field-select" data-placeholder="{{data.placeholder}}">
                <option value=""><?php esc_html_e( '— Select field —', 'document-generator' ); ?></option>
            </select>
        </td>
    </tr>
</script>

<!-- Existing mapping data for JS -->
<script>
    var dgExistingMapping = <?php echo wp_json_encode( $mapping ); ?>;
</script>
