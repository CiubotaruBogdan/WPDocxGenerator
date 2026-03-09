<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Discovers available WordPress fields from multiple sources:
 * - User profile fields
 * - Site/blog info fields
 * - Post/page fields
 * - Custom Post Types
 * - Toolset Types custom fields
 */
class DG_Fields {

    /**
     * Get all available field sources.
     *
     * @return array
     */
    public function get_sources() {
        $sources = array(
            'user'    => __( 'User Fields', 'document-generator' ),
            'site'    => __( 'Site Fields', 'document-generator' ),
            'post'    => __( 'Post/Page Fields', 'document-generator' ),
            'custom'  => __( 'Custom Text', 'document-generator' ),
            'date'    => __( 'Date/Time', 'document-generator' ),
        );

        // Add registered CPTs.
        $post_types = get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' );
        foreach ( $post_types as $pt ) {
            $sources[ 'cpt_' . $pt->name ] = sprintf( __( 'CPT: %s', 'document-generator' ), $pt->label );
        }

        // Add Toolset Types if available.
        if ( $this->is_toolset_active() ) {
            $sources['toolset'] = __( 'Toolset Custom Fields', 'document-generator' );
            $sources['toolset_repeating'] = __( 'Toolset Repeating Fields (for tables)', 'document-generator' );
        }

        return $sources;
    }

    /**
     * Get fields for a specific source.
     *
     * @param string $source The source identifier.
     * @return array
     */
    public function get_fields_by_source( $source ) {
        switch ( $source ) {
            case 'user':
                return $this->get_user_fields();
            case 'site':
                return $this->get_site_fields();
            case 'post':
                return $this->get_post_fields();
            case 'custom':
                return $this->get_custom_fields();
            case 'date':
                return $this->get_date_fields();
            case 'toolset':
                return $this->get_toolset_fields();
            case 'toolset_repeating':
                return $this->get_toolset_repeating_fields();
            default:
                // Check if it's a CPT source.
                if ( strpos( $source, 'cpt_' ) === 0 ) {
                    $post_type = substr( $source, 4 );
                    return $this->get_cpt_fields( $post_type );
                }
                return array();
        }
    }

    /**
     * Get WordPress user profile fields.
     */
    public function get_user_fields() {
        $fields = array(
            array( 'value' => 'user_login',       'label' => __( 'Username', 'document-generator' ) ),
            array( 'value' => 'user_email',        'label' => __( 'Email', 'document-generator' ) ),
            array( 'value' => 'user_firstname',    'label' => __( 'First Name', 'document-generator' ) ),
            array( 'value' => 'user_lastname',     'label' => __( 'Last Name', 'document-generator' ) ),
            array( 'value' => 'display_name',      'label' => __( 'Display Name', 'document-generator' ) ),
            array( 'value' => 'user_nicename',     'label' => __( 'Nicename', 'document-generator' ) ),
            array( 'value' => 'user_url',          'label' => __( 'Website URL', 'document-generator' ) ),
            array( 'value' => 'user_registered',   'label' => __( 'Registration Date', 'document-generator' ) ),
            array( 'value' => 'user_description',  'label' => __( 'Biographical Info', 'document-generator' ) ),
            array( 'value' => 'user_role',         'label' => __( 'Role', 'document-generator' ) ),
        );

        // Add user meta keys from usermeta table.
        $custom_meta = $this->get_user_meta_keys();
        foreach ( $custom_meta as $key ) {
            $fields[] = array(
                'value' => 'usermeta_' . $key,
                'label' => sprintf( __( 'User Meta: %s', 'document-generator' ), $key ),
            );
        }

        return $fields;
    }

    /**
     * Get unique user meta keys (excluding internal WP ones).
     */
    private function get_user_meta_keys() {
        global $wpdb;

        $exclude = array(
            'nickname', 'first_name', 'last_name', 'description',
            'rich_editing', 'syntax_highlighting', 'comment_shortcuts',
            'admin_color', 'use_ssl', 'show_admin_bar_front',
            'locale', 'wp_capabilities', 'wp_user_level',
            'dismissed_wp_pointers', 'show_welcome_panel',
            'session_tokens', 'wp_user-settings', 'wp_user-settings-time',
            'wp_dashboard_quick_press_last_post_id', 'community-events-location',
            'closedpostboxes_dashboard', 'metaboxhidden_dashboard',
            'managenav-menuscolumnshidden',
        );

        $exclude_like = $wpdb->esc_like( 'wp_' ) . '%';

        $keys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT meta_key FROM {$wpdb->usermeta}
                 WHERE meta_key NOT IN (" . implode( ',', array_fill( 0, count( $exclude ), '%s' ) ) . ")
                 AND meta_key NOT LIKE %s
                 ORDER BY meta_key
                 LIMIT 100",
                array_merge( $exclude, array( $exclude_like ) )
            )
        );

        return $keys ?: array();
    }

    /**
     * Get site/blog info fields.
     */
    public function get_site_fields() {
        return array(
            array( 'value' => 'site_name',        'label' => __( 'Site Name', 'document-generator' ) ),
            array( 'value' => 'site_description',  'label' => __( 'Site Description (Tagline)', 'document-generator' ) ),
            array( 'value' => 'site_url',          'label' => __( 'Site URL', 'document-generator' ) ),
            array( 'value' => 'home_url',          'label' => __( 'Home URL', 'document-generator' ) ),
            array( 'value' => 'admin_email',       'label' => __( 'Admin Email', 'document-generator' ) ),
            array( 'value' => 'site_language',     'label' => __( 'Site Language', 'document-generator' ) ),
        );
    }

    /**
     * Get current post/page fields.
     */
    public function get_post_fields() {
        return array(
            array( 'value' => 'post_title',        'label' => __( 'Post Title', 'document-generator' ) ),
            array( 'value' => 'post_content',      'label' => __( 'Post Content', 'document-generator' ) ),
            array( 'value' => 'post_excerpt',      'label' => __( 'Post Excerpt', 'document-generator' ) ),
            array( 'value' => 'post_date',         'label' => __( 'Post Date', 'document-generator' ) ),
            array( 'value' => 'post_modified',     'label' => __( 'Last Modified Date', 'document-generator' ) ),
            array( 'value' => 'post_author_name',  'label' => __( 'Author Name', 'document-generator' ) ),
            array( 'value' => 'post_permalink',    'label' => __( 'Permalink', 'document-generator' ) ),
            array( 'value' => 'post_slug',         'label' => __( 'Slug', 'document-generator' ) ),
            array( 'value' => 'post_id',           'label' => __( 'Post ID', 'document-generator' ) ),
            array( 'value' => 'post_type',         'label' => __( 'Post Type', 'document-generator' ) ),
            array( 'value' => 'featured_image_url', 'label' => __( 'Featured Image URL', 'document-generator' ) ),
        );
    }

    /**
     * Get custom/static field options.
     */
    public function get_custom_fields() {
        return array(
            array( 'value' => 'custom_text', 'label' => __( 'Custom Text (enter value)', 'document-generator' ) ),
        );
    }

    /**
     * Get date/time field options.
     */
    public function get_date_fields() {
        return array(
            array( 'value' => 'current_date',      'label' => __( 'Current Date', 'document-generator' ) ),
            array( 'value' => 'current_time',      'label' => __( 'Current Time', 'document-generator' ) ),
            array( 'value' => 'current_datetime',  'label' => __( 'Current Date & Time', 'document-generator' ) ),
            array( 'value' => 'current_year',      'label' => __( 'Current Year', 'document-generator' ) ),
            array( 'value' => 'current_month',     'label' => __( 'Current Month', 'document-generator' ) ),
            array( 'value' => 'current_day',       'label' => __( 'Current Day', 'document-generator' ) ),
        );
    }

    /**
     * Get fields from a Custom Post Type (including its meta keys).
     */
    public function get_cpt_fields( $post_type ) {
        $fields = array(
            array( 'value' => 'cpt_title',     'label' => __( 'Title', 'document-generator' ) ),
            array( 'value' => 'cpt_content',   'label' => __( 'Content', 'document-generator' ) ),
            array( 'value' => 'cpt_excerpt',   'label' => __( 'Excerpt', 'document-generator' ) ),
            array( 'value' => 'cpt_date',      'label' => __( 'Date', 'document-generator' ) ),
            array( 'value' => 'cpt_author',    'label' => __( 'Author', 'document-generator' ) ),
            array( 'value' => 'cpt_permalink', 'label' => __( 'Permalink', 'document-generator' ) ),
        );

        // Get custom meta keys for this post type.
        global $wpdb;
        $meta_keys = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT pm.meta_key
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = %s
             AND pm.meta_key NOT LIKE %s
             ORDER BY pm.meta_key
             LIMIT 200",
            $post_type,
            $wpdb->esc_like( '_' ) . '%'
        ) );

        if ( $meta_keys ) {
            foreach ( $meta_keys as $key ) {
                $fields[] = array(
                    'value' => 'cpt_meta_' . $key,
                    'label' => sprintf( __( 'Meta: %s', 'document-generator' ), $key ),
                );
            }
        }

        // Taxonomies for this CPT.
        $taxonomies = get_object_taxonomies( $post_type, 'objects' );
        foreach ( $taxonomies as $tax ) {
            $fields[] = array(
                'value' => 'cpt_tax_' . $tax->name,
                'label' => sprintf( __( 'Taxonomy: %s', 'document-generator' ), $tax->label ),
            );
        }

        return $fields;
    }

    /**
     * Get Toolset Types custom fields, grouped by field group.
     */
    public function get_toolset_fields() {
        $fields = array();

        if ( ! $this->is_toolset_active() ) {
            return $fields;
        }

        $toolset_fields = get_option( 'wpcf-fields', array() );

        // Build a mapping: field slug => group name.
        $field_to_group = $this->get_toolset_field_group_map();

        // Group fields by their group.
        $grouped = array();
        if ( is_array( $toolset_fields ) ) {
            foreach ( $toolset_fields as $slug => $field_data ) {
                $group = isset( $field_to_group[ $slug ] ) ? $field_to_group[ $slug ] : __( 'Other Fields', 'document-generator' );
                $grouped[ $group ][] = array(
                    'slug'  => $slug,
                    'data'  => $field_data,
                );
            }
        }

        // Output grouped fields with group headers.
        foreach ( $grouped as $group_name => $group_fields ) {
            $fields[] = array(
                'value'    => '',
                'label'    => '— ' . $group_name . ' —',
                'disabled' => true,
                'group'    => $group_name,
            );
            foreach ( $group_fields as $gf ) {
                $label = isset( $gf['data']['name'] ) ? $gf['data']['name'] : $gf['slug'];
                $type  = isset( $gf['data']['type'] ) ? $gf['data']['type'] : 'text';
                $fields[] = array(
                    'value' => 'toolset_' . $gf['slug'],
                    'label' => sprintf( '%s (%s)', $label, $type ),
                    'type'  => $type,
                    'group' => $group_name,
                );
            }
        }

        // Toolset user fields.
        $toolset_user_fields = get_option( 'wpcf-usermeta', array() );
        if ( is_array( $toolset_user_fields ) && ! empty( $toolset_user_fields ) ) {
            $user_group_map = $this->get_toolset_user_field_group_map();
            $user_grouped = array();

            foreach ( $toolset_user_fields as $slug => $field_data ) {
                $group = isset( $user_group_map[ $slug ] ) ? $user_group_map[ $slug ] : __( 'User Fields', 'document-generator' );
                $user_grouped[ $group ][] = array(
                    'slug'  => $slug,
                    'data'  => $field_data,
                );
            }

            foreach ( $user_grouped as $group_name => $group_fields ) {
                $fields[] = array(
                    'value'    => '',
                    'label'    => '— ' . $group_name . ' (User) —',
                    'disabled' => true,
                    'group'    => $group_name,
                );
                foreach ( $group_fields as $gf ) {
                    $label = isset( $gf['data']['name'] ) ? $gf['data']['name'] : $gf['slug'];
                    $type  = isset( $gf['data']['type'] ) ? $gf['data']['type'] : 'text';
                    $fields[] = array(
                        'value' => 'toolset_user_' . $gf['slug'],
                        'label' => sprintf( __( 'User: %s (%s)', 'document-generator' ), $label, $type ),
                        'type'  => $type,
                        'group' => $group_name,
                    );
                }
            }
        }

        return $fields;
    }

    /**
     * Get mapping of Toolset field slug => group name for post fields.
     */
    private function get_toolset_field_group_map() {
        $map = array();

        $groups = get_posts( array(
            'post_type'      => 'wp-types-group',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ) );

        foreach ( $groups as $group ) {
            $group_fields = get_post_meta( $group->ID, '_wp_types_group_fields', true );
            if ( ! empty( $group_fields ) ) {
                // Stored as comma-separated list of field slugs.
                $slugs = array_filter( array_map( 'trim', explode( ',', $group_fields ) ) );
                foreach ( $slugs as $slug ) {
                    $map[ $slug ] = $group->post_title;
                }
            }
        }

        return $map;
    }

    /**
     * Get mapping of Toolset field slug => group name for user fields.
     */
    private function get_toolset_user_field_group_map() {
        $map = array();

        $groups = get_posts( array(
            'post_type'      => 'wp-types-user-group',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ) );

        foreach ( $groups as $group ) {
            $group_fields = get_post_meta( $group->ID, '_wp_types_group_fields', true );
            if ( ! empty( $group_fields ) ) {
                $slugs = array_filter( array_map( 'trim', explode( ',', $group_fields ) ) );
                foreach ( $slugs as $slug ) {
                    $map[ $slug ] = $group->post_title;
                }
            }
        }

        return $map;
    }

    /**
     * Get Toolset repeating field groups (for table repeat blocks).
     */
    public function get_toolset_repeating_fields() {
        $fields = array();

        if ( ! $this->is_toolset_active() ) {
            return $fields;
        }

        $toolset_fields = get_option( 'wpcf-fields', array() );

        if ( is_array( $toolset_fields ) ) {
            foreach ( $toolset_fields as $slug => $field_data ) {
                // Repetitive fields have 'data' => 'repetitive' => 1
                $is_repetitive = false;
                if ( isset( $field_data['data']['repetitive'] ) && $field_data['data']['repetitive'] ) {
                    $is_repetitive = true;
                }

                if ( $is_repetitive ) {
                    $label = isset( $field_data['name'] ) ? $field_data['name'] : $slug;
                    $fields[] = array(
                        'value' => 'toolset_repeat_' . $slug,
                        'label' => sprintf( __( 'Repeating: %s', 'document-generator' ), $label ),
                    );
                }
            }
        }

        // Also check for Toolset RFG (Repeating Field Groups) if available.
        if ( function_exists( 'toolset_get_related_posts' ) ) {
            $rfg_post_types = get_post_types( array( 'public' => false ), 'objects' );
            foreach ( $rfg_post_types as $pt ) {
                if ( strpos( $pt->name, 'rfg_' ) === 0 ) {
                    $fields[] = array(
                        'value' => 'toolset_rfg_' . $pt->name,
                        'label' => sprintf( __( 'RFG: %s', 'document-generator' ), $pt->label ),
                    );
                }
            }
        }

        return $fields;
    }

    /**
     * Get fields available in the context of a specific page.
     * This helps identify what data is available when the shortcode is placed on a specific page.
     */
    public function get_context_fields( $page_id ) {
        $context = array(
            'available_sources' => array(),
            'fields'           => array(),
        );

        if ( ! $page_id ) {
            // No specific page - return all sources.
            $context['available_sources'] = array_keys( $this->get_sources() );
            return $context;
        }

        $post = get_post( $page_id );
        if ( ! $post ) {
            return $context;
        }

        // Always available.
        $context['available_sources'][] = 'user';
        $context['available_sources'][] = 'site';
        $context['available_sources'][] = 'date';
        $context['available_sources'][] = 'custom';

        // Post fields are available for the current page.
        $context['available_sources'][] = 'post';

        // Check post type for CPT-specific fields.
        $post_type = $post->post_type;
        if ( ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
            $context['available_sources'][] = 'cpt_' . $post_type;
        }

        // If Toolset is active, check for Toolset fields assigned to this post type.
        if ( $this->is_toolset_active() ) {
            $context['available_sources'][] = 'toolset';

            // Check for repeating field groups.
            $toolset_fields = get_option( 'wpcf-fields', array() );
            $has_repeating = false;
            if ( is_array( $toolset_fields ) ) {
                foreach ( $toolset_fields as $field_data ) {
                    if ( isset( $field_data['data']['repetitive'] ) && $field_data['data']['repetitive'] ) {
                        $has_repeating = true;
                        break;
                    }
                }
            }
            if ( $has_repeating ) {
                $context['available_sources'][] = 'toolset_repeating';
            }
        }

        // Gather all fields for available sources.
        foreach ( $context['available_sources'] as $source ) {
            $context['fields'][ $source ] = $this->get_fields_by_source( $source );
        }

        return $context;
    }

    /**
     * Resolve a field mapping to its actual value at runtime.
     *
     * @param array    $field_config The field mapping config.
     * @param int|null $post_id      Current post ID (context).
     * @param int|null $user_id      Current user ID.
     * @return string The resolved value.
     */
    public function resolve_field_value( $field_config, $post_id = null, $user_id = null ) {
        $source = $field_config['source'] ?? '';
        $field  = $field_config['field'] ?? '';
        $meta   = $field_config['meta'] ?? '';

        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        switch ( $source ) {
            case 'user':
                return $this->resolve_user_field( $field, $user_id );

            case 'site':
                return $this->resolve_site_field( $field );

            case 'post':
                return $this->resolve_post_field( $field, $post_id );

            case 'date':
                return $this->resolve_date_field( $field );

            case 'custom':
                return $meta; // Static text value.

            case 'toolset':
                return $this->resolve_toolset_field( $field, $post_id );

            default:
                if ( strpos( $source, 'cpt_' ) === 0 ) {
                    return $this->resolve_cpt_field( $field, $post_id, $source );
                }
                return '';
        }
    }

    /**
     * Resolve repeating field data for table blocks.
     *
     * @param array    $repeat_config Configuration for the repeat block.
     * @param int|null $post_id       Current post ID.
     * @return array Array of rows, each row is placeholder => value.
     */
    public function resolve_repeat_data( $repeat_config, $post_id = null ) {
        $source = $repeat_config['source'] ?? '';
        $field  = $repeat_config['field'] ?? '';

        if ( $source === 'toolset_repeating' && $post_id ) {
            return $this->resolve_toolset_repeating( $field, $post_id );
        }

        // For CPT child posts.
        if ( strpos( $source, 'cpt_' ) === 0 && $post_id ) {
            return $this->resolve_cpt_children( $source, $post_id, $repeat_config );
        }

        return array();
    }

    // --- Private resolvers ---

    private function resolve_user_field( $field, $user_id ) {
        if ( ! $user_id ) {
            return '';
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return '';
        }

        if ( strpos( $field, 'usermeta_' ) === 0 ) {
            $meta_key = substr( $field, 9 );
            return get_user_meta( $user_id, $meta_key, true );
        }

        switch ( $field ) {
            case 'user_login':      return $user->user_login;
            case 'user_email':      return $user->user_email;
            case 'user_firstname':  return $user->first_name;
            case 'user_lastname':   return $user->last_name;
            case 'display_name':    return $user->display_name;
            case 'user_nicename':   return $user->user_nicename;
            case 'user_url':        return $user->user_url;
            case 'user_registered': return $user->user_registered;
            case 'user_description': return $user->description;
            case 'user_role':
                $roles = $user->roles;
                return ! empty( $roles ) ? implode( ', ', $roles ) : '';
            default:
                return '';
        }
    }

    private function resolve_site_field( $field ) {
        switch ( $field ) {
            case 'site_name':        return get_bloginfo( 'name' );
            case 'site_description': return get_bloginfo( 'description' );
            case 'site_url':         return site_url();
            case 'home_url':         return home_url();
            case 'admin_email':      return get_option( 'admin_email' );
            case 'site_language':    return get_locale();
            default:                 return '';
        }
    }

    private function resolve_post_field( $field, $post_id ) {
        if ( ! $post_id ) {
            return '';
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return '';
        }

        switch ( $field ) {
            case 'post_title':       return $post->post_title;
            case 'post_content':     return wp_strip_all_tags( $post->post_content );
            case 'post_excerpt':     return $post->post_excerpt;
            case 'post_date':        return $post->post_date;
            case 'post_modified':    return $post->post_modified;
            case 'post_author_name':
                $author = get_userdata( $post->post_author );
                return $author ? $author->display_name : '';
            case 'post_permalink':   return get_permalink( $post_id );
            case 'post_slug':        return $post->post_name;
            case 'post_id':          return (string) $post_id;
            case 'post_type':        return $post->post_type;
            case 'featured_image_url':
                return get_the_post_thumbnail_url( $post_id, 'full' ) ?: '';
            default:
                return '';
        }
    }

    private function resolve_date_field( $field ) {
        $format = get_option( 'date_format' );
        $time_format = get_option( 'time_format' );

        switch ( $field ) {
            case 'current_date':     return wp_date( $format );
            case 'current_time':     return wp_date( $time_format );
            case 'current_datetime': return wp_date( $format . ' ' . $time_format );
            case 'current_year':     return wp_date( 'Y' );
            case 'current_month':    return wp_date( 'm' );
            case 'current_day':      return wp_date( 'd' );
            default:                 return '';
        }
    }

    private function resolve_toolset_field( $field, $post_id ) {
        if ( strpos( $field, 'toolset_user_' ) === 0 ) {
            $slug = substr( $field, 13 );
            $user_id = get_current_user_id();
            if ( function_exists( 'types_render_usermeta_field' ) ) {
                return types_render_usermeta_field( $slug, array( 'user_id' => $user_id, 'output' => 'raw' ) );
            }
            return get_user_meta( $user_id, 'wpcf-' . $slug, true );
        }

        if ( strpos( $field, 'toolset_' ) === 0 ) {
            $slug = substr( $field, 8 );
            if ( $post_id && function_exists( 'types_render_field' ) ) {
                return types_render_field( $slug, array( 'post_id' => $post_id, 'output' => 'raw' ) );
            }
            if ( $post_id ) {
                return get_post_meta( $post_id, 'wpcf-' . $slug, true );
            }
        }

        return '';
    }

    private function resolve_toolset_repeating( $field, $post_id ) {
        $rows = array();

        if ( strpos( $field, 'toolset_repeat_' ) === 0 ) {
            $slug = substr( $field, 15 );
            $values = get_post_meta( $post_id, 'wpcf-' . $slug, false );

            if ( is_array( $values ) ) {
                foreach ( $values as $index => $value ) {
                    $rows[] = array(
                        $slug          => $value,
                        'index'        => $index + 1,
                    );
                }
            }
        }

        // RFG (Repeating Field Groups).
        if ( strpos( $field, 'toolset_rfg_' ) === 0 && function_exists( 'toolset_get_related_posts' ) ) {
            $rfg_type = substr( $field, 12 );

            $related = toolset_get_related_posts(
                $post_id,
                $rfg_type,
                array( 'query_by_role' => 'parent', 'return' => 'post_id' )
            );

            if ( is_array( $related ) ) {
                foreach ( $related as $index => $child_id ) {
                    $child_post = get_post( $child_id );
                    $row = array(
                        'index'   => $index + 1,
                        'title'   => $child_post ? $child_post->post_title : '',
                        'post_id' => $child_id,
                    );

                    // Get all meta for this child.
                    $child_meta = get_post_meta( $child_id );
                    foreach ( $child_meta as $key => $values ) {
                        if ( strpos( $key, 'wpcf-' ) === 0 ) {
                            $clean_key = substr( $key, 5 );
                            $row[ $clean_key ] = is_array( $values ) ? $values[0] : $values;
                        }
                    }

                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    private function resolve_cpt_field( $field, $post_id, $source ) {
        if ( ! $post_id ) {
            return '';
        }

        if ( strpos( $field, 'cpt_meta_' ) === 0 ) {
            $meta_key = substr( $field, 9 );
            return get_post_meta( $post_id, $meta_key, true );
        }

        if ( strpos( $field, 'cpt_tax_' ) === 0 ) {
            $taxonomy = substr( $field, 8 );
            $terms = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
            return is_array( $terms ) ? implode( ', ', $terms ) : '';
        }

        // Standard CPT fields reuse post resolver.
        $mapped = str_replace( 'cpt_', 'post_', $field );
        return $this->resolve_post_field( $mapped, $post_id );
    }

    private function resolve_cpt_children( $source, $post_id, $config ) {
        $post_type = substr( $source, 4 );
        $rows = array();

        $children = get_posts( array(
            'post_type'      => $post_type,
            'post_parent'    => $post_id,
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ) );

        foreach ( $children as $index => $child ) {
            $row = array(
                'index'     => $index + 1,
                'title'     => $child->post_title,
                'content'   => wp_strip_all_tags( $child->post_content ),
                'excerpt'   => $child->post_excerpt,
                'date'      => $child->post_date,
                'permalink' => get_permalink( $child->ID ),
            );

            $child_meta = get_post_meta( $child->ID );
            foreach ( $child_meta as $key => $values ) {
                if ( strpos( $key, '_' ) !== 0 ) {
                    $row[ $key ] = is_array( $values ) ? $values[0] : $values;
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Check if Toolset Types is active.
     */
    private function is_toolset_active() {
        return defined( 'WPCF_VERSION' ) || class_exists( 'WPCF_Loader' );
    }
}
