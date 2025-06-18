<?php
/**
 * Plugin Name: PMPro Seach Query
 * Plugin URI: hhttps://github.com/tradesouthwest/pmpro-search-query/
 * Description: Extends PMPro Member Directory search to include custom user meta fields: ocupación, categoría de servicio, and biografía.
 * Version: 1.0.5
 * Author: Tradesouthwest
 * License: GPL v2 or later
 *
 * Requires PMPro Member Directory plugin to be active
 */

// Prevent direct access to the file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Activation hook to check for required plugins.
 */
register_activation_hook(__FILE__, 'pmpro_custom_member_search_activate');
function pmpro_custom_member_search_activate() {
    // Check if Paid Memberships Pro is active.
    if (!defined('PMPRO_VERSION')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('PMPro Custom Member Search requires Paid Memberships Pro to be installed and activated.', 'pmpro-custom-member-search')
        );
    }
    // Check if PMPro Member Directory Add On is active.
    if (!function_exists('pmpromd_search_form')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('PMPro Custom Member Search requires the PMPro Member Directory Add On to be installed and activated.', 'pmpro-custom-member-search')
        );
    }
}

/**
 * Define the locale for this plugin for internationalization.
 */
add_action('plugins_loaded', 'pmpro_custom_member_search_load_plugin_textdomain');
function pmpro_custom_member_search_load_plugin_textdomain() {
    load_plugin_textdomain('pmpro-custom-member-search', false, basename(dirname(__FILE__)) . '/languages');
}

/**
 * Main class for the custom member search functionality.
 */
class PMPro_Custom_Member_Search {

    public function __construct() {
        add_action('init', array($this, 'init_shortcode'));
        // Add the styling function to the WordPress head.
        add_action( 'wp_head', array($this, 'tsw_pmpro_search_form_styles' ));
    }

    /**
     * Initializes the custom search shortcode.
     */
    public function init_shortcode() {
        add_shortcode('pmpro_custom_member_search', array($this, 'display_custom_search_form'));
    }

    /**
     * Retrieves all unique service categories from user meta.
     * This is used to populate the dropdown for 'categor_a_de_servicio'.
     *
     * @return array An array of unique, sorted service categories.
     */
    private function get_unique_service_categories() {
        global $wpdb;

        $all_categories = array();
        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
                'categor_a_de_servicio'
            )
        );

        foreach ($results as $meta_value) {
            $unserialized_value = maybe_unserialize($meta_value);

            if (is_array($unserialized_value)) {
                foreach ($unserialized_value as $category) {
                    if (!empty($category) && is_string($category)) {
                        $all_categories[] = trim($category);
                    }
                }
            } else if (!empty($unserialized_value) && is_string($unserialized_value)) {
                $all_categories[] = trim($unserialized_value);
            }
        }

        $unique_categories = array_unique($all_categories);
        sort($unique_categories);

        return $unique_categories;
    }

    /**
     * Retrieves all unique 'ocupaci_n' values from user meta.
     * This is used to populate the dropdown for 'ocupaci_n'.
     *
     * @return array An array of unique, sorted ocupaci_n values.
     */
    private function get_unique_ocupacion() {
        global $wpdb;

        $ocupacion_values = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != ''",
                'ocupaci_n'
            )
        );

        // Filter out any potential serialized values if any were mistakenly stored.
        $clean_ocupacion_values = array();
        foreach ($ocupacion_values as $value) {
            $unserialized = maybe_unserialize($value);
            if (is_string($unserialized) && !empty($unserialized)) {
                $clean_ocupacion_values[] = trim($unserialized);
            }
        }

        $unique_ocupacion = array_unique($clean_ocupacion_values);
        sort($unique_ocupacion);

        return $unique_ocupacion;
    }

    /**
     * Displays the custom search form and handles search results.
     * This method is the callback for the [pmpro_custom_member_search] shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output for the search form and results.
     */
    public function display_custom_search_form($atts) {
        ob_start(); // Start output buffering

        // Sanitize and get current search values from $_GET.
        $ocupacion_val = isset($_GET['ocupaci_n']) ? sanitize_text_field($_GET['ocupaci_n']) : '';
        $biografia_val = isset($_GET['mi_biograf_a']) ? sanitize_text_field($_GET['mi_biograf_a']) : '';
        $categoria_servicio_val = isset($_GET['categor_a_de_servicio']) ? sanitize_text_field($_GET['categor_a_de_servicio']) : '';
        $current_paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

        // Determine the form action URL (current page for results on same page).
        $form_action = esc_url(add_query_arg(array()));

        // Get unique service categories for the dropdown.
        $service_categories = $this->get_unique_service_categories();
        // Get unique ocupacion values for the dropdown.
        $ocupacion_options = $this->get_unique_ocupacion();

        ?>
        <div class="pmpro-custom-member-search-wrap">
            <form role="search" method="get" class="pmpro-custom-search-form" action="<?php echo esc_url($form_action); ?>">
                <h2><?php esc_html_e('Buscar Miembros', 'pmpro-custom-member-search'); ?></h2>

                <div class="pmpro_search_box">
                    <label for="categor_a_de_servicio"><?php esc_html_e('Categoría de Servicio', 'pmpro-custom-member-search'); ?>:</label>
                    <select name="categor_a_de_servicio" id="categor_a_de_servicio">
                        <option value="">-- <?php esc_html_e('Seleccione una categoría', 'pmpro-custom-member-search'); ?> --</option>
                        <?php foreach ($service_categories as $category) : ?>
                            <option value="<?php echo esc_attr($category); ?>" <?php selected($categoria_servicio_val, $category); ?>>
                                <?php echo esc_html($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pmpro_search_box" style="display: none;">
                    <label for="ocupaci_n"><?php esc_html_e('Ocupación', 'pmpro-custom-member-search'); ?>:</label>
                    <select name="ocupaci_n" id="ocupaci_n">
                        <option value="">-- <?php esc_html_e('Seleccione una ocupación', 'pmpro-custom-member-search'); ?> --</option>
                        <?php foreach ($ocupacion_options as $option) : ?>
                            <option value="<?php echo esc_attr($option); ?>" <?php selected($ocupacion_val, $option); ?>>
                                <?php echo esc_html($option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            

                <div class="pmpro_search_box">
                    <label for="mi_biograf_a"><?php esc_html_e('Profesional', 'pmpro-custom-member-search'); ?>:</label>
                    <input type="text" name="mi_biograf_a" id="mi_biograf_a" value="<?php echo esc_attr($biografia_val); ?>" />
                </div>
            
                <div class="pmpro_search_box pmpro_search_buttons">
                    <?php wp_nonce_field('pmpro_custom_member_search_nonce', 'pmpro_custom_search_nonce_field'); ?>
                    <input type="submit" value="<?php esc_attr_e('Buscar', 'pmpro-custom-member-search'); ?>" />
                    <?php
                    // Add a reset button that clears search parameters
                    $reset_url = remove_query_arg(array('ocupaci_n', 'categor_a_de_servicio', 'mi_biograf_a', 's', 'paged', 'pmpro_custom_search_nonce_field'));
                    ?>
                    <a href="<?php echo esc_url($reset_url); ?>" class="pmpro_search_reset_button"><?php esc_html_e('Reiniciar', 'pmpro-custom-member-search'); ?></a>
                </div>
            </form>

            <div class="pmpro-custom-search-results">
                <?php $this->display_search_results($ocupacion_val, $categoria_servicio_val, $biografia_val, $current_paged); ?>
            </div>
        </div>
        <?php

        return ob_get_clean(); // Return the buffered content.
    }

    public function try_pmpro_get_member_profile_url($user_id) {
        error_log("pmpro_get_member_profile_url called for user ID: " . $user_id);
    
        if (empty($user_id) || !is_numeric($user_id)) {
            error_log("pmpro_get_member_profile_url: Invalid user ID.");
            return '';
        }
    
        // Check if member profile page is set
        $member_profile_page_id = pmpro_getOption('member_profile_page_id');
        if (empty($member_profile_page_id)) {
            error_log("pmpro_get_member_profile_url: Member profile page ID not set in PMPro options.");
            return '';
        }
        $member_profile_page_url = get_permalink($member_profile_page_id);
        if (empty($member_profile_page_url)) {
            error_log("pmpro_get_member_profile_url: Could not get permalink for page ID: " . $member_profile_page_id);
            return '';
        }
    
        // Add any other checks you find within the function, e.g., membership level checks
        // Example (hypothetical, PMPro might do this differently):
        // if (!pmpro_hasMembershipLevel(null, $user_id)) {
        //     error_log("pmpro_get_member_profile_url: User ID " . $user_id . " does not have an active membership level required for profile.");
        //     return '';
        // }
    
        $url = add_query_arg('pu', $user_id, $member_profile_page_url);
        error_log("pmpro_get_member_profile_url: Generated URL: " . $url);
        return $url;
    }

    /**
     * Displays search results based on submitted query parameters.
     *
     * @param string $ocupacion_val Filter value for 'ocupacion'.
     * @param string $categoria_servicio_val Filter value for 'categoria_de_servicio'.
     * @param string $biografia_val Filter value for 'mi_biograf_a'.
     * @param int    $current_paged Current page number for pagination.
     */
    private function display_search_results($ocupacion_val, $categoria_servicio_val, $biografia_val, $current_paged) {
        global $wpdb; // Use global wpdb for specialized queries.

        // --- DEBUG: Start of display_search_results ---
        error_log('[PMPRO_CUSTOM_SEARCH DEBUG] display_search_results called.');
        error_log('[PMPRO_CUSTOM_SEARCH DEBUG] Input values: Ocupacion="' . $ocupacion_val . '", Categoria="' . $categoria_servicio_val . '", Biografia="' . $biografia_val . '", Paged="' . $current_paged . '"');

        // Verify Nonce for security.
        // This will halt execution if the nonce is invalid.
        // It's important to do this only on form submission (when GET params exist related to our form).
        if (isset($_GET['pmpro_custom_search_nonce_field'])) {
            if (!wp_verify_nonce(sanitize_key($_GET['pmpro_custom_search_nonce_field']), 'pmpro_custom_member_search_nonce')) {
                error_log('[PMPRO_CUSTOM_SEARCH DEBUG] Nonce verification failed. Aborting search results display.');
                echo '<p class="pmpro-error">' . esc_html__('Error de seguridad: La solicitud no es válida.', 'pmpro-custom-member-search') . '</p>';
                return;
            }
            error_log('[PMPRO_CUSTOM_SEARCH DEBUG] Nonce verified successfully.');
        } else {
             error_log('[PMPRO_CUSTOM_SEARCH DEBUG] No nonce field found (likely initial page load, not form submission).');
        }

        // Determine if any search term has been provided.
        $has_search_term = !empty($ocupacion_val) || !empty($categoria_servicio_val) || !empty($biografia_val);

        // --- DEBUG: Checking initial display conditions ---
        error_log('[PMPRO_CUSTOM_SEARCH DEBUG] Has Search Term: ' . ($has_search_term ? 'true' : 'false') . ', Current Paged: ' . $current_paged);

        // Prevent display of results on initial page load if no search terms.
        if (!$has_search_term && $current_paged === 1) {
            echo '<p>' . esc_html__('Ingrese los criterios de búsqueda para encontrar miembros.', 'pmpro-custom-member-search') . '</p>';
            error_log('[PMPRO_CUSTOM_SEARCH DEBUG] No search terms and not paginated. Exiting display_search_results.');
            return;
        }

        // Start with an empty meta query array.
        $meta_query_array = array(
            'relation' => 'AND',
        );

        // Add search meta queries only if a search term is provided.
        if ($has_search_term) {
            if (!empty($ocupacion_val)) {
                $meta_query_array[] = array(
                    'key'     => 'ocupaci_n',
                    'value'   => $ocupacion_val,
                    'compare' => 'LIKE', // Use LIKE for partial matches from dropdown if needed, or '=' for exact.
                );
                error_log('[PMPRO_CUSTOM_SEARCH DEBUG] Adding ocupaci_n to meta_query: ' . $ocupacion_val);
            }

            if (!empty($biografia_val)) {
                $meta_query_array[] = array(
                    'key'     => 'mi_biograf_a',
                    'value'   => $biografia_val,
                    'compare' => 'LIKE',
                );
                error_log('[PMPRO_CUSTOM_SEARCH DEBUG] Adding mi_biograf_a to meta_query: ' . $biografia_val);
            }

            if (!empty($categoria_servicio_val)) {
                $escaped_val = $wpdb->esc_like($categoria_servicio_val);
                $meta_query_array[] = array(
                    'key'     => 'categor_a_de_servicio',
                    'value'   => '%"' . $escaped_val . '"%', // This pattern should capture string values in serialized arrays.
                    'compare' => 'LIKE',
                );
                error_log('[PMPRO_CUSTOM_SEARCH DEBUG] Adding categor_a_de_servicio to meta_query: searching for pattern "%"' . $escaped_val . '"%" with LIKE.');
            }
        } else {
             error_log('[PMPRO_CUSTOM_SEARCH DEBUG] No specific search terms provided, showing all users if not paginated (PMPro filter temporarily removed).');
        }


        // Arguments for WP_User_Query
        $args = array(
            'number'     => 10, // Number of results per page.
            'paged'      => $current_paged,
            'meta_query' => $meta_query_array,
            'orderby'    => 'display_name',
            'order'      => 'ASC',
        );

        // If no specific search terms are provided, and no other meta_query arguments
        // have been added, we need to ensure meta_query isn't empty, or remove it.
        if (empty($args['meta_query']) || (count($args['meta_query']) === 1 && $args['meta_query']['relation'] === 'AND')) {
            unset($args['meta_query']); // If only 'relation' is left, remove it.
        }

        // --- DEBUG: WP_User_Query Arguments ---
        error_log('[PMPRO_CUSTOM_SEARCH DEBUG] WP_User_Query Args: ' . print_r($args, true));


        $user_query = new WP_User_Query($args);

        // --- DEBUG: WP_User_Query Results ---
        error_log('[PMPRO_CUSTOM_SEARCH DEBUG] WP_User_Query Total Users found: ' . $user_query->total_users);
        if (!empty($user_query->results)) {
            error_log('[PMPRO_CUSTOM_SEARCH DEBUG] WP_User_Query Results (first few): ' . print_r(array_slice($user_query->results, 0, 3), true));
        } else {
            error_log('[PMPRO_CUSTOM_SEARCH DEBUG] WP_User_Query returned no results.');
        }


        if (!empty($user_query->results)) {
            echo '<h4 class="pmpro-search-results-title">' . esc_html__('Resultados de la Búsqueda:', 'pmpro-custom-member-search') . '</h4>';
            echo '<div class="pmpro_member_directory-items pmpro_cards_wrap">'; // Wrapper for cards.
            foreach ($user_query->results as $user) {
                // Fetch user display name and meta data.
                $user_id = $user->ID;
                $user_display_name = $user->display_name;

                $user_ocupacion = get_user_meta($user_id, 'ocupaci_n', true);
                $user_biografia = get_user_meta($user_id, 'mi_biograf_a', true);
                $user_categoria_servicio_raw = get_user_meta($user_id, 'categor_a_de_servicio', true);

                // Unserialize 'categor_a_de_servicio' for display.
                $user_categoria_servicio_display = '';
                $unserialized_cats = maybe_unserialize($user_categoria_servicio_raw);
                if (is_array($unserialized_cats)) {
                    $user_categoria_servicio_display = implode(', ', array_map('esc_html', $unserialized_cats));
                } else if (!empty($unserialized_cats)) {
                     $user_categoria_servicio_display = esc_html($unserialized_cats);
                }

                // Get member profile URL if PMPro Profile Pages are enabled.
                $member_profile_url = '';
                if (function_exists('pmpro_get_member_profile_url')) {
                    $member_profile_url = pmpro_get_member_profile_url($user_id);
                } else {
                    $member_profile_url = esc_url( 'https://conexionpro.us/perfil/' . $user->user_nicename);
                } 
                ?>
                <div class="pmpro_member_directory-item pmpro_card">
                    <div class="pmpro_card_body">
                        <?php if (!empty($member_profile_url)) : ?>
                            <h3><a style="color:green" href="<?php echo esc_url($member_profile_url); ?>"><?php echo esc_html($user_display_name); ?></a></h3>
                        <?php else : ?>
                            <h3><?php
                                echo esc_html($user_display_name); ?></h3>
                            
                        <?php endif; ?>

                        <ul class="pmpro_member_meta">
                            <?php if (!empty($user_ocupacion)) : ?>
                                <li><strong><?php esc_html_e('Ocupación:', 'pmpro-custom-member-search'); ?></strong> <?php echo esc_html($user_ocupacion); ?></li>
                            <?php endif; ?>
                            <?php if (!empty($user_categoria_servicio_display)) : ?>
                                <li><strong><?php esc_html_e('Categoría de Servicio:', 'pmpro-custom-member-search'); ?></strong> <?php echo $user_categoria_servicio_display; ?></li>
                            <?php endif; ?>
                            <?php if (!empty($user_biografia)) : ?>
                                <li class="biography-search"><strong><?php esc_html_e('Biografía:', 'pmpro-custom-member-search'); ?></strong> <?php echo esc_html($user_biografia); ?></li>
                            <?php endif; ?>
                            <?php
                            // Optionally display membership level.
                            // Note: This will now display level for *any* user found, not just active members.
                            $level = pmpro_getMembershipLevelForUser($user_id);
                            if ($level) {
                                echo '<li><strong>' . esc_html__('Nivel:', 'pmpro-custom-member-search') . '</strong> ' . esc_html($level->name) . '</li>';
                            }
                            ?>
                        </ul>
                    </div>
                </div>
                <?php
            }
            echo '</div>';

            // Add pagination.
            $total_users = $user_query->total_users;
            $users_per_page = $args['number'];
            $total_pages = ceil($total_users / $users_per_page);

            if ($total_pages > 1) {
                echo '<div class="pmpro-custom-member-pagination">';
                $paginate_base = add_query_arg('paged', '%#%');
                $paginate_base = remove_query_arg(array('ocupaci_n', 'categor_a_de_servicio', 'mi_biograf_a', 's', 'paged', 'pmpro_custom_search_nonce_field'));

                echo paginate_links(array(
                    'base'    => $paginate_base . '&ocupaci_n=' . urlencode($ocupacion_val) . '&categor_a_de_servicio=' . urlencode($categoria_servicio_val) . '&mi_biograf_a=' . urlencode($biografia_val) . '&paged=%#%',
                    'format'  => '',
                    'current' => max(1, $current_paged),
                    'total'   => $total_pages,
                    'prev_text' => __('&laquo; Anterior', 'pmpro-custom-member-search'),
                    'next_text' => __('Siguiente &raquo;', 'pmpro-custom-member-search'),
                    'type'    => 'list',
                ));
                echo '</div>';
            }

        } else {
            echo '<p>' . esc_html__('No se encontraron miembros que coincidan con los criterios de búsqueda.', 'pmpro-custom-member-search') . '</p>';
        }
        // --- DEBUG: End of display_search_results ---
        error_log('[PMPRO_CUSTOM_SEARCH DEBUG] End of display_search_results.');
    }
    /**
     * Adds basic CSS styling for the search form and results.
     * This function hooks into 'wp_head' to output styles directly into the <head> section.
     * For more complex styling, consider enqueuing a separate stylesheet.
     */
    public function tsw_pmpro_search_form_styles() {
        global $post;
        if ( has_shortcode( $post->post_content, 'pmpro_custom_member_search' ) ) {
        echo '<style id="pmpro-custom-search">
         /* Basic reset/base for consistency */
         .pmpro-custom-member-search-wrap *, .pmpro-custom-search-results * {
            box-sizing: border-box;
            font-family: "Inter", sans-serif;
        }
        /* Main form container styling */
        .pmpro-custom-member-search-wrap {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            padding: 25px;
            border-radius: 12px;
            max-width: 650px;
            margin: 30px auto;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            gap: 18px; /* Space between form fields */
        }

        /* Form title */
        .pmpro-custom-search-form h2 {
            text-align: center;
            color: #333333;
            margin-bottom: 25px;
            font-size: 1.8em;
            font-weight: 700;
        }

        /* Individual form field container */
        .pmpro-custom-search-form .form-field {
            display: flex;
            flex-direction: column;
        }

        /* Labels for fields */
        .pmpro-custom-search-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555555;
            font-size: 0.95em;
        }

        /* Select dropdowns and text inputs */
        .pmpro-custom-search-form select,
        .pmpro-custom-search-form input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #cccccc;
            border-radius: 8px;
            font-size: 1.05em;
            color: #333333;
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        .pmpro-custom-search-form select:focus,
        .pmpro-custom-search-form input[type="text"]:focus {
            border-color: #0073aa; /* WordPress blue on focus */
            box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.2);
            outline: none;
        }

        /* Placeholder text color */
        .pmpro-custom-search-form input[type="text"]::placeholder {
            color: #aaaaaa;
        }

        /* Submit button styling */
        .pmpro-custom-search-form input[type="submit"] {
            width: auto; /* Auto width based on content */
            padding: 12px 25px;
            background-color: #0073aa; /* WordPress default blue */
            color: #ffffff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.1s ease;
            display: block; /* Make it a block to center with margin: auto */
            margin: 20px auto 0; /* Top margin for spacing, auto for horizontal centering */
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .pmpro-custom-search-form input[type="submit"]:hover {
            background-color: #005f88; /* Darker blue on hover */
            transform: translateY(-1px); /* Slight lift effect */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .pmpro-custom-search-form input[type="submit"]:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .pmpro_search_reset_button {
            width: min-content; /* Auto width based on content */
            padding: 8px 15px;
            background-color:rgb(160, 53, 55); 
            color: #ffffff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.1s ease;
            display: block; /* Make it a block to center with margin: auto */
            margin: 20px auto 0; /* Top margin for spacing, auto for horizontal centering */
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            float: left;
        }
        /* Responsive adjustments for form */
        @media (max-width: 768px) {
            .pmpro-custom-search-form {
                margin: 15px;
                padding: 20px;
            }
            .pmpro-custom-search-form h2 {
                font-size: 1.5em;
                margin-bottom: 20px;
            }
            .pmpro-custom-search-form select,
            .pmpro-custom-search-form input[type="text"],
            .pmpro-custom-search-form input[type="submit"] {
                font-size: 0.95em;
                padding: 10px 12px;
            }
        }

        /* --- Search Results Styling --- */
        .pmpro-member-search-results {
            background-color: #f8f8f8;
            border: 1px solid #e0e0e0;
            padding: 25px;
            border-radius: 12px;
            max-width: 650px;
            margin: 30px auto;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .pmpro-member-search-results h3 {
            text-align: center;
            color: #333333;
            margin-bottom: 25px;
            font-size: 1.6em;
            font-weight: 700;
            border-bottom: 2px solid #eeeeee;
            padding-bottom: 15px;
        }

        .pmpro-member-search-results .no-results {
            text-align: center;
            color: #888888;
            font-style: italic;
            padding: 20px;
        }

        .pmpro-member-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid; /* Use grid for layout */
            gap: 20px; /* Space between list items */
        }

        .pmpro-member-item {
            background-color: #ffffff;
            border: 1px solid #dddddd;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .pmpro-member-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .pmpro-member-item h4 {
            color: #0073aa;
            font-size: 1.4em;
            margin-top: 0;
            margin-bottom: 10px;
            border-bottom: 1px dashed #f0f0f0;
            padding-bottom: 8px;
        }

        .pmpro-member-item h4 a {
            text-decoration: none;
            color: inherit;
        }

        .pmpro-member-item h4 a:hover {
            text-decoration: underline;
        }

        .pmpro-member-item p {
            margin-bottom: 6px;
            line-height: 1.5;
            color: #444444;
        }

        .pmpro-member-item p strong {
            color: #222222;
        }

        /* Responsive adjustments for results */
        @media (max-width: 768px) {
            .pmpro-member-search-results {
                margin: 15px;
                padding: 20px;
            }
            .pmpro-member-search-results h3 {
                font-size: 1.3em;
            }
            .pmpro-member-item {
                padding: 15px;
            }
            .pmpro-member-item h4 {
                font-size: 1.2em;
            }
            .pmpro-member-item p {
                font-size: 0.9em;
            }
        }
            </style>';
        } else {
            return;
        }
    }
}

// Initialize the plugin.
new PMPro_Custom_Member_Search();