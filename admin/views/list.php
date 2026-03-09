<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$templates = get_posts( array(
    'post_type'      => 'dg_template',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
) );
?>

<div class="wrap dg-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Document Templates', 'document-generator' ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=document-generator-new' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Add New', 'document-generator' ); ?>
    </a>
    <hr class="wp-header-end">

    <?php if ( empty( $templates ) ) : ?>
        <div class="dg-empty-state">
            <div class="dg-empty-icon dashicons dashicons-media-document"></div>
            <h2><?php esc_html_e( 'No document templates yet', 'document-generator' ); ?></h2>
            <p><?php esc_html_e( 'Upload a DOCX file with placeholders (e.g. #first_name#, #company#) and map them to WordPress fields.', 'document-generator' ); ?></p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=document-generator-new' ) ); ?>" class="button button-primary button-hero">
                <?php esc_html_e( 'Create Your First Template', 'document-generator' ); ?>
            </a>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="column-title"><?php esc_html_e( 'Template Name', 'document-generator' ); ?></th>
                    <th class="column-filename"><?php esc_html_e( 'File', 'document-generator' ); ?></th>
                    <th class="column-placeholders"><?php esc_html_e( 'Mapped Fields', 'document-generator' ); ?></th>
                    <th class="column-roles"><?php esc_html_e( 'Allowed Roles', 'document-generator' ); ?></th>
                    <th class="column-shortcode"><?php esc_html_e( 'Shortcode', 'document-generator' ); ?></th>
                    <th class="column-actions"><?php esc_html_e( 'Actions', 'document-generator' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $templates as $tpl ) :
                    $filename      = get_post_meta( $tpl->ID, '_dg_filename', true );
                    $mapping       = get_post_meta( $tpl->ID, '_dg_mapping', true );
                    $allowed_roles = get_post_meta( $tpl->ID, '_dg_allowed_roles', true );
                    $mapped_count  = is_array( $mapping ) ? count( $mapping ) : 0;
                    $roles_text    = is_array( $allowed_roles ) && ! empty( $allowed_roles )
                        ? implode( ', ', array_map( 'ucfirst', $allowed_roles ) )
                        : __( 'All logged-in users', 'document-generator' );
                    $shortcode = '[document_generator id="' . $tpl->ID . '"]';
                ?>
                <tr>
                    <td class="column-title">
                        <strong>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=document-generator-edit&template_id=' . $tpl->ID ) ); ?>">
                                <?php echo esc_html( $tpl->post_title ); ?>
                            </a>
                        </strong>
                    </td>
                    <td class="column-filename">
                        <span class="dashicons dashicons-media-document"></span>
                        <?php echo esc_html( $filename ); ?>
                    </td>
                    <td class="column-placeholders">
                        <span class="dg-badge"><?php echo esc_html( $mapped_count ); ?></span>
                    </td>
                    <td class="column-roles">
                        <?php echo esc_html( $roles_text ); ?>
                    </td>
                    <td class="column-shortcode">
                        <code class="dg-shortcode-display" title="<?php esc_attr_e( 'Click to copy', 'document-generator' ); ?>"><?php echo esc_html( $shortcode ); ?></code>
                    </td>
                    <td class="column-actions">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=document-generator-edit&template_id=' . $tpl->ID ) ); ?>" class="button button-small">
                            <?php esc_html_e( 'Edit', 'document-generator' ); ?>
                        </a>
                        <button type="button" class="button button-small button-link-delete dg-delete-template" data-template-id="<?php echo esc_attr( $tpl->ID ); ?>">
                            <?php esc_html_e( 'Delete', 'document-generator' ); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="dg-info-box">
        <h3><?php esc_html_e( 'How to use', 'document-generator' ); ?></h3>
        <ol>
            <li><?php esc_html_e( 'Create a DOCX document with placeholders using #placeholder_name# format.', 'document-generator' ); ?></li>
            <li><?php esc_html_e( 'Upload the document and map each placeholder to a WordPress field.', 'document-generator' ); ?></li>
            <li><?php esc_html_e( 'For repeating table data, use #repeat:block_name# in a table row.', 'document-generator' ); ?></li>
            <li><?php esc_html_e( 'Copy the shortcode and paste it into any page or post.', 'document-generator' ); ?></li>
            <li><?php esc_html_e( 'Users with the right role will see a download button.', 'document-generator' ); ?></li>
        </ol>
    </div>
</div>
