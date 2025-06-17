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
 * Register the custom user meta fields if they don't already exist through PMPRO settings.
 * This is a fallback and good practice. If you've already set them up in PMPRO,
 * this section is not strictly necessary but harmless.
 */
function pmpro_csm_register_custom_user_fields() {
    // Before proceeding, check if Paid Memberships Pro is active.
    // This prevents fatal errors if PMPro is not installed or activated.
    if ( ! function_exists( 'pmpro_getOption' ) ) {
        return '<p style="color: red; text-align: center;">Error: Paid Memberships Pro is not active. This form requires PMPro.</p>';
    }

    global $wpdb; // Access the WordPress database object.
    $output = ''; // Initialize an empty string to build the form HTML.

    // Define the custom user fields that should be displayed as dropdowns.
    // 'ocupaci_n' and 'categor_a_de_servicio' are suitable for dropdowns.
    // 'mi_biograf_a' is a text area and is best covered by the general keyword search.
    $searchable_fields = array(
        'ocupaci_n'             => 'Ocupación',
        'categor_a_de_servicio' => 'Categoría de servicio',
    );

    // --- Determine the form action URL ---
    // Check if PMPro's members directory page is configured.
    $pmpro_members_page_id = pmpro_getOption( 'members_page_id' );
    if ( ! empty( $pmpro_members_page_id ) ) {
        // If the PMPro members page exists, use its permalink as the form action.
        $form_action_url = get_permalink( $pmpro_members_page_id );
    } else {
        // Fallback if PMPro directory page isn't explicitly set.
        // This assumes your theme might have a generic `/members/` or a custom archive template
        // that you will set up to handle member searches.
        $form_action_url = '';
    }

    // --- Search Form HTML Generation ---
    // Start the HTML form.
    // The form uses GET method, meaning parameters will be in the URL (e.g., ?ocupaci_n=Doctor).
    $output .= '<form method="get" action="' . esc_url( $form_action_url ) . '">';
    $output .= '<div class="pmpro-member-search-form">'; // Main container div for styling.
    $output .= '<h2>Buscar Miembros</h2>'; // Form title, updated for clarity

    // Loop through each defined searchable field to create a dropdown.
    foreach ( $searchable_fields as $meta_key => $label ) {
        $unique_values_for_dropdown = array(); // Array to hold unique, processed values for the dropdown.

        // Query the WordPress user meta table to get all distinct meta_values
        // for the current meta_key. This fetches the raw data as stored.
        $query = $wpdb->prepare(
            "SELECT DISTINCT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != '' ORDER BY meta_value ASC",
            $meta_key
        );
        $raw_options = $wpdb->get_col( $query ); // Execute query and get results as an array of column values.

        // Process raw options to handle potential serialization and extract clean alphanumeric values.
        foreach ( $raw_options as $option_value ) {
            // Use maybe_unserialize() which attempts to unserialize if the string is serialized,
            // otherwise, it returns the original value. This is robust for various storage types.
            $unserialized_value = maybe_unserialize( $option_value );

            // If the value is an array (e.g., from a multi-select field stored as serialized array),
            // iterate through its elements.
            if ( is_array( $unserialized_value ) ) {
                foreach ( $unserialized_value as $sub_value ) {
                    // Ensure the sub-value is a string and trim whitespace.
                    $cleaned_sub_value = trim( strval( $sub_value ) );
                    // Validate that the cleaned value is not empty and contains allowed alphanumeric/punctuation characters.
                    // The regex allows letters, numbers, spaces, hyphens, underscores, periods, commas, parentheses, and ampersands.
                    if ( ! empty( $cleaned_sub_value ) && preg_match( '/^[a-zA-Z0-9\s\-_.,()&]+$/', $cleaned_sub_value ) ) {
                        $unique_values_for_dropdown[] = $cleaned_sub_value;
                    }
                }
            } else {
                // If it's a single string value (or unserialized to a non-array type),
                // clean it and add it directly.
                $cleaned_value = trim( strval( $unserialized_value ) );
                if ( ! empty( $cleaned_value ) && preg_match( '/^[a-zA-Z0-9\s\-_.,()&]+$/', $cleaned_value ) ) {
                    $unique_values_for_dropdown[] = $cleaned_value;
                }
            }
        }

        // Ensure all values are truly unique and sort them alphabetically for the dropdown.
        $unique_values_for_dropdown = array_unique( $unique_values_for_dropdown );
        sort( $unique_values_for_dropdown );

        $options = array(); // Final array to hold dropdown options for the current field.
        // Add a default "select all" or "any" option at the beginning of the dropdown.
        // An empty value allows for clearing the filter for this field.
        $options[''] = '— Seleccionar ' . esc_html( $label ) . ' —';

        // Populate the dropdown options from the processed unique values.
        foreach ( $unique_values_for_dropdown as $value ) {
            $options[ $value ] = $value;
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

    // Optional: Add a general keyword search input, suitable for fields like 'mi_biograf_a'.
    $output .= '<div class="form-field">';
    $output .= '<label for="s">Palabras Clave:</label>';
    $output .= '<input type="text" name="s" id="s" value="' . ( isset( $_GET['s'] ) ? esc_attr( $_GET['s'] ) : '' ) . '" placeholder="Buscar por nombre, biografía o palabra clave general" class="pmpro-field-text">';
    $output .= '</div>';

    // Add the submit button.
    $output .= '<div class="form-field form-submit">';
    $output .= '<input type="submit" value="Buscar Miembros" class="pmpro-submit-button">';
    $output .= '</div>';

    $output .= '</div>'; // Close .pmpro-member-search-form
    $output .= '</form>'; // Close form

    return $output; // Return only the generated HTML for the form.
}
// register shortcode
add_shortcode( 'pmpro_custom_user_search', 'pmpro_csm_register_custom_user_fields' );


/**
 * Adds basic CSS styling for the search form and results.
 * This function hooks into 'wp_head' to output styles directly into the <head> section.
 * For more complex styling, consider enqueuing a separate stylesheet.
 */
function tsw_pmpro_search_form_styles() {
    echo '
    <style>
        /* Basic reset/base for consistency */
        .pmpro-member-search-form *, .pmpro-member-search-results * {
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

        /* Responsive adjustments for form */
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
    </style>
    ';
}
// Add the styling function to the WordPress head.
add_action( 'wp_head', 'tsw_pmpro_search_form_styles' );
?>
