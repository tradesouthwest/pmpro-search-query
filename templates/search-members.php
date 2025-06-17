<?php
/**
 * Template search members
 *
 * This template handles displaying a list of PMPro members,
 * including filtering based on search parameters from the search form.
 */

// Load WordPress header, including necessary scripts and styles.
get_header();
    if ( astra_page_layout() === 'left-sidebar' ) { ?>

        <?php get_sidebar(); ?>

    <?php } ?>
    <?php 
    // Make sure PMPro functions are available. If PMPro is not active,
    // this template might still load but the PMPro-specific functions will not exist.
    if ( ! function_exists( 'pmpro_get_members' ) ) {
        echo 
            '<div class="pmpro-member-search-results">
                <p class="no-results" style="color: red;">Error: Paid Memberships Pro is not active or not fully loaded. Cannot display member directory.</p>
            </div>';
        get_footer();
        return; // Stop execution if PMPro is not available.
    }
    ?>
<div id="primary" <?php astra_primary_class(); ?>>
    <?php
    astra_primary_content_top(); ?>
    <?php    
        if ( isset( $_GET['_wpnonce'] ) 
        && !wp_verify_nonce(  sanitize_text_field( wp_unslash( $_GET['_wpnonce'], 'search-members' ) ) ) ) {
        die( 'Security Check!' );
    }
    // Retrieve and sanitize search parameters from the URL.
    // Ensure these names match the 'name' attributes in your search form.
    $occupation_filter       = isset( $_GET['ocupaci_n'] ) 
                               ? sanitize_text_field( $_GET['ocupaci_n'] ) : '';
    $service_category_filter = isset( $_GET['categor_a_de_servicio'] ) 
                               ? sanitize_text_field( $_GET['categor_a_de_servicio'] ) : '';
    $keyword_filter          = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
    
    global $wpdb; // Access the WordPress database object for specific queries if needed (like esc_like).

    // Prepare arguments for pmpro_get_members(). This function is best for querying PMPro users.
    $pmpro_args = array(
        'limit'        => 40, // Number of results to display per page. Adjust as needed.
        'start'        => 0,  // Offset for pagination (can be extended for full pagination).
        'order_by'     => 'display_name', // Order results by user's display name.
        'order'        => 'ASC', // Ascending order.
        'meta_query'   => array( 'relation' => 'AND' ), // Default to AND for combining different meta filters.
        'search'       => '', // pmpro_get_members uses this for basic user data (display_name, user_login, etc.).
        'fields'       => 'all_with_meta', // Ensure all user meta is retrieved.
    );

    // Add keyword search if present. This will apply to basic user fields.
    // Also search 'mi_biograf_a' with keywords.
    if ( ! empty( $keyword_filter ) ) {
        $pmpro_args['search'] = $keyword_filter;
        $pmpmro_args['meta_query'][] = array(
            'key'     => 'mi_biograf_a',
            'value'   => '%' . $wpdb->esc_like( $keyword_filter ) . '%',
            'compare' => 'LIKE',
        );
    }

    // Add occupation filter if selected.
    if ( ! empty( $occupation_filter ) ) {
        $pmpro_args['meta_query'][] = array(
            'key'     => 'ocupaci_n',
            'value'   => $occupation_filter,
            'compare' => '=', // Exact match for occupation.
        );
    }

    // Add service category filter if selected.
    // For serialized data (like `categor_a_de_servicio`), we need to search using LIKE.
    // The `%` wildcards are essential for finding the value anywhere within the serialized string.
    if ( ! empty( $service_category_filter ) ) {
        $pmpro_args['meta_query'][] = array(
            'key'     => 'categor_a_de_servicio',
            'value'   => '%' . $wpdb->esc_like( $service_category_filter ) . '%',
            'compare' => 'LIKE',
        );
    }

    // Fetch members based on the constructed arguments.
    $members = pmpro_get_members( $pmpro_args );

    ?>

    <div id="primary-search" class="content-area">
        <main id="main" class="site-main" role="main">

            <?php
            // You can optionally display the search form on the results page too.
            // This shortcode is defined in your functions.php or custom plugin.
            //echo do_shortcode( '[pmpro_member_search_form]' );
            ?>

            <div class="pmpro-member-search-results">
                <h3>Resultados de la Búsqueda</h3>

                <?php if ( ! empty( $members ) ) : ?>
                    <ul class="pmpro-member-list">
                        <?php
                        foreach ( $members as $member ) :
                            $user_id = $member->ID;
                            // Get the full user data object for display name.
                            $user_data = get_userdata( $user_id ); 

                            // Retrieve custom field values for display.
                            // get_user_meta will return 'false' if the meta key doesn't exist,
                            // or an empty string if it's empty but exists.
                            $occupation_val = get_user_meta( $user_id, 'ocupaci_n', true );
                            $service_category_val = get_user_meta( $user_id, 'categor_a_de_servicio', true );
                            $biography_val = get_user_meta( $user_id, 'mi_biograf_a', true );

                            // Unserialize service category for display if it's stored as serialized.
                            if ( is_serialized( $service_category_val ) ) {
                                $unserialized_service_category = maybe_unserialize( $service_category_val );
                                if ( is_array( $unserialized_service_category ) ) {
                                    // If it's an array, implode it into a comma-separated string for display.
                                    $service_category_val = implode( 
                                        ', ', array_map( 'esc_html', $unserialized_service_category ) 
                                    );
                                } else {
                                    // If unserialized but not an array (e.g., a single value that was just serialized), escape it.
                                    $service_category_val = esc_html( $unserialized_service_category );
                                }
                            } else {
                                // If not serialized, just escape the value.
                                $service_category_val = esc_html( $service_category_val );
                            }

                            // Get PMPro member profile URL
                            $member_profile_url = pmpro_get_member_profile_url( $user_id );
                            ?>
                            <li class="pmpro-member-item">
                                <div class="member-details">
                                    <h4><a href="<?php echo esc_url( $member_profile_url ); ?>"><?php echo esc_html( $user_data->display_name ); ?></a></h4>

                                    <?php
                                    // Display membership level, ensuring it exists.
                                    $level = pmpro_getMembershipLevelForUser( $user_id );
                                    if ( $level && ! empty( $level->name ) ) : ?>
                                        <p><strong>Nivel de Membresía:</strong> <?php echo esc_html( $level->name ); ?></p>
                                    <?php endif; ?>

                                    <?php if ( ! empty( $occupation_val ) ) : ?>
                                        <p><strong>Ocupación:</strong> <?php echo esc_html( $occupation_val ); ?></p>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $service_category_val ) ) : ?>
                                        <p><strong>Categoría de servicio:</strong> <?php echo $service_category_val; ?></p>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $biography_val ) ) : ?>
                                        <p><strong>Mi biografía:</strong> <?php echo wp_kses_post( $biography_val ); ?></p>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="no-results">No se encontraron miembros que coincidan con sus criterios.</p>
                <?php endif; ?>
            </div><!-- .pmpro-member-search-results -->

        </main><!-- #main -->
    </div><!-- #primary -->
    <?php
    astra_primary_content_bottom();
    ?>
</div><!-- #primary -->
    <?php
    if ( astra_page_layout() === 'right-sidebar' ) {

        get_sidebar();

    } ?>
<?php get_footer(); 
