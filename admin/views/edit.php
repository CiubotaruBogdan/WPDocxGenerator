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
$target_page   = 0;

if ( $is_edit ) {
    $template = get_post( $template_id );
    if ( $template && 'dg_template' === $template->post_type ) {
        $title         = $template->post_title;
        $filename      = get_post_meta( $template_id, '_dg_filename', true );
        $mapping       = get_post_meta( $template_id, '_dg_mapping', true );
        $allowed_roles = get_post_meta( $template_id, '_dg_allowed_roles', true );
        $button_text   = get_post_meta( $template_id, '_dg_button_text', true );
        $target_page   = get_post_meta( $template_id, '_dg_target_page', true );
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

// Get all pages for target page selector.
$all_pages = get_posts( array(
    'post_type'      => array( 'page', 'post' ),
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
) );

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
                    <th><label for="dg-button-text"><?php esc_html_e( 'Button Text', 'document-generator' ); ?></label></th>
                    <td>
                        <input type="text" id="dg-button-text" name="button_text" class="regular-text"
                               value="<?php echo esc_attr( $button_text ); ?>"
                               placeholder="<?php esc_attr_e( 'Download Document', 'document-generator' ); ?>">
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
                <tr>
                    <th><label for="dg-target-page"><?php esc_html_e( 'Target Page (Context)', 'document-generator' ); ?></label></th>
                    <td>
                        <select id="dg-target-page" name="target_page" class="regular-text">
                            <option value="0"><?php esc_html_e( '— Auto-detect from current page —', 'document-generator' ); ?></option>
                            <?php foreach ( $all_pages as $page ) : ?>
                                <option value="<?php echo esc_attr( $page->ID ); ?>"
                                        data-post-type="<?php echo esc_attr( $page->post_type ); ?>"
                                        <?php selected( $target_page, $page->ID ); ?>>
                                    <?php echo esc_html( $page->post_title ); ?> (<?php echo esc_html( $page->post_type ); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Select the page where this shortcode will be placed to identify available context fields.', 'document-generator' ); ?></p>
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
                            <th class="column-meta"><?php esc_html_e( 'Extra / Custom Value', 'document-generator' ); ?></th>
                            <th class="column-preview"><?php esc_html_e( 'Preview', 'document-generator' ); ?></th>
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
                                        <select class="dg-field-select" data-placeholder="<?php echo esc_attr( $placeholder ); ?>">
                                            <option value=""><?php esc_html_e( '— Select field —', 'document-generator' ); ?></option>
                                        </select>
                                    </td>
                                    <td class="column-meta">
                                        <input type="text" class="dg-meta-input regular-text"
                                               data-placeholder="<?php echo esc_attr( $placeholder ); ?>"
                                               value="<?php echo esc_attr( $config['meta'] ?? '' ); ?>"
                                               placeholder="<?php esc_attr_e( 'Custom value or format', 'document-generator' ); ?>">
                                    </td>
                                    <td class="column-preview">
                                        <span class="dg-preview-value">—</span>
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

        <!-- Step 3: Shortcode Output -->
        <div class="dg-section" id="dg-shortcode-section" <?php echo ! $is_edit ? 'style="display:none;"' : ''; ?>>
            <h2><?php esc_html_e( 'Shortcode', 'document-generator' ); ?></h2>
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
                <?php esc_html_e( 'Options: format="docx" or format="pdf" to force a specific format. class="my-class" for custom styling.', 'document-generator' ); ?>
            </p>
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
        <td class="column-meta">
            <input type="text" class="dg-meta-input regular-text"
                   data-placeholder="{{data.placeholder}}"
                   placeholder="<?php esc_attr_e( 'Custom value or format', 'document-generator' ); ?>">
        </td>
        <td class="column-preview">
            <span class="dg-preview-value">—</span>
        </td>
    </tr>
</script>

<!-- Existing mapping data for JS -->
<script>
    var dgExistingMapping = <?php echo wp_json_encode( $mapping ); ?>;
</script>
