<?php
/**
 * Plugin Name: PMPro Custom User Search
 * Description: A custom search tool for Paid Memberships Pro users. Use shortcode (&91;pmpro_custom_user_search]) so you can easily place it on any page or post.
 * Version: 1.0
 * Author: Tradesouthwest
 */

// Ensure WordPress and Paid Memberships Pro are loaded
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}


/**
 * Generates the HTML for a member search form with specified custom field dropdowns.
 *
 * @return string The complete HTML string for the search form.
 */
function tsw_pmpro_member_details_search_form() {
    // Before proceeding, check if Paid Memberships Pro is active.
    // This prevents fatal errors if PMPro is not installed or activated.
    if ( ! function_exists( 'pmpro_getOption' ) ) {
        // Return an error message if PMPro is not found.
        return '<p style="color: red; text-align: center;">Error: Paid Memberships Pro is not active. This form requires PMPro.</p>';
    }

    global $wpdb; // Access the WordPress database object.
    $output = ''; // Initialize an empty string to build the form HTML.

    // Define the custom user fields that should be displayed as dropdowns.
    // The key is the meta_key (field name) and the value is the display label.
    // 'mi_biograf_a' is typically a long text field (textarea) and is not
    // suitable for a select dropdown. We'll focus on the other two.
    $searchable_fields = array(
        'ocupaci_n'             => 'Ocupación',
        'categor_a_de_servicio' => 'Categoría de servicio',
    );

    // Start the HTML form.
    // The form uses GET method, meaning parameters will be in the URL (e.g., ?ocupaci_n=Doctor).
    // The action points to the site's home URL, which is a common setup for basic search.
    // You might want to change this to a specific search results page if you have one.
    $output .= '<form method="get" action="' . esc_url( home_url( '/' ) ) . '">';
    $output .= '<div class="pmpro-member-search-form">'; // Main container div for styling.
    $output .= '<h2>Buscar miembros por detalles</h2>'; // Form title.

    // Loop through each defined searchable field to create a dropdown.
    foreach ( $searchable_fields as $meta_key => $label ) {
        $options = array(); // Array to hold dropdown options for the current field.

        // Query the WordPress user meta table to get all unique values
        // for the current meta_key. This is robust as it fetches actual
        // data entered by users, regardless of the field's original type
        // (e.g., text input vs. predefined select options).
        $query = $wpdb->prepare(
            "SELECT DISTINCT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != '' ORDER BY meta_value ASC",
            $meta_key
        );
        $raw_options = $wpdb->get_col( $query ); // Execute query and get results as an array of column values.

        // Add a default "select all" or "any" option at the beginning of the dropdown.
        // An empty value allows for clearing the filter for this field.
        $options[''] = '— Select ' . esc_html( $label ) . ' —';

        // Populate the options array from the database results.
        // The key and value are the same for simplicity in this case.
        foreach ( $raw_options as $option_value ) {
            $options[ $option_value ] = $option_value;
        }

        // Build the HTML for the current dropdown field.
        $output .= '<div class="form-field">';
        $output .= '<label for="' . esc_attr( $meta_key ) . '">' . esc_html( $label ) . ':</label>';
        $output .= '<select name="' . esc_attr( $meta_key ) . '" id="' . esc_attr( $meta_key ) . '" class="pmpro-field-select">';
        foreach ( $options as $value => $display ) {
            // Determine if the current option should be 'selected'.
            // This is useful if the form is reloaded after submission,
            // maintaining the user's previous selection.
            $selected = ( isset( $_GET[ $meta_key ] ) && $_GET[ $meta_key ] === $value ) ? 'selected' : '';
            $output .= '<option value="' . esc_attr( $value ) . '" ' . $selected . '>' . esc_html( $display ) . '</option>';
        }
        $output .= '</select>';
        $output .= '</div>'; // Close .form-field
    }

    // Optional: Add a general keyword search input.
    // This allows users to search by name or other text-based fields.
    $output .= '<div class="form-field">';
    $output .= '<label for="s">Keywords:</label>';
    $output .= '<input type="text" name="s" id="s" value="' . ( isset( $_GET['s'] ) ? esc_attr( $_GET['s'] ) : '' ) . '" placeholder="Search by name or general keyword" class="pmpro-field-text">';
    $output .= '</div>';

    // Add the submit button.
    $output .= '<div class="form-field form-submit">';
    $output .= '<input type="submit" value="Search Members" class="pmpro-submit-button">';
    $output .= '</div>';

    $output .= '</div>'; // Close .pmpro-member-search-form
    $output .= '</form>'; // Close form

    return $output; // Return the generated HTML.
}
// Register the shortcode. Users can now use [pmpro_custom_user_search] in pages/posts.
add_shortcode( 'pmpro_custom_user_search', 'tsw_pmpro_member_details_search_form' );

/**
 * Adds basic CSS styling for the search form.
 * This function hooks into 'wp_head' to output styles directly into the <head> section.
 * For more complex styling, consider enqueuing a separate stylesheet.
 */
function tsw_pmpro_search_form_styles() {
    echo '
    <style>
        /* Basic reset/base for consistency */
        .pmpro-member-search-form * {
            box-sizing: border-box;
            font-family: "Inter", sans-serif;
        }

        /* Main form container styling */
        .pmpro-member-search-form {
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
        .pmpro-member-search-form h2 {
            text-align: center;
            color: #333333;
            margin-bottom: 25px;
            font-size: 1.8em;
            font-weight: 700;
        }

        /* Individual form field container */
        .pmpro-member-search-form .form-field {
            display: flex;
            flex-direction: column;
        }

        /* Labels for fields */
        .pmpro-member-search-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555555;
            font-size: 0.95em;
        }

        /* Select dropdowns and text inputs */
        .pmpro-member-search-form select,
        .pmpro-member-search-form input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #cccccc;
            border-radius: 8px;
            font-size: 1.05em;
            color: #333333;
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        .pmpro-member-search-form select:focus,
        .pmpro-member-search-form input[type="text"]:focus {
            border-color: #0073aa; /* WordPress blue on focus */
            box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.2);
            outline: none;
        }

        /* Placeholder text color */
        .pmpro-member-search-form input[type="text"]::placeholder {
            color: #aaaaaa;
        }

        /* Submit button styling */
        .pmpro-member-search-form input[type="submit"] {
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

        .pmpro-member-search-form input[type="submit"]:hover {
            background-color: #005f88; /* Darker blue on hover */
            transform: translateY(-1px); /* Slight lift effect */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .pmpro-member-search-form input[type="submit"]:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .pmpro-member-search-form {
                margin: 15px;
                padding: 20px;
            }
            .pmpro-member-search-form h2 {
                font-size: 1.5em;
                margin-bottom: 20px;
            }
            .pmpro-member-search-form select,
            .pmpro-member-search-form input[type="text"],
            .pmpro-member-search-form input[type="submit"] {
                font-size: 0.95em;
                padding: 10px 12px;
            }
        }
    </style>
    ';
}
// Add the styling function to the WordPress head.
add_action( 'wp_head', 'tsw_pmpro_search_form_styles' );
?>
