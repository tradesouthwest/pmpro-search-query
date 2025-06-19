<?php
/**
 * Plugin Name: Tsw PMPro Directory Widget
 * Description: Add widget to Member Directory page to filter results.
 * @see experimental, NOT USED in this plugin at this point.
 */
class Tsw_PMPro_Directory_Widget extends WP_Widget {

	/**
	 * Sets up the widget
	 */
	public function __construct() {
		parent::__construct(
			'tsw_pmpro_directory_widget',
			'Tsw PMPro Directory Widget',
			array( 'description' => 'Filter the PMPro Member Directory' )
		);
	}

	/**
	 * Code that runs on the frontend.
	 *
	 * Modify the content in the <li> tags to
	 * create filter inputs in the sidebar
	 */
	public function widget( $args, $instance ) {
		// If we're not on a page with a PMPro directory, return.
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'pmpro_member_directory' ) ) {
			return;
		}
		?>
		<aside id="tsw_pmpro_directory_widget" class="widget tsw_pmpro_directory_widget">
			<h3 class="widget-title">Filter Directory</h3>
			<form>
				<ul>
					<li>
						<strong>Membership Level:</strong><br/>
						<?php
						global $pmpro_levels;
						foreach ( $pmpro_levels as $key => $value ) {
							// Check if this value should default to be checked.
							$checked_modifier = '';
							if ( ! empty( $_REQUEST['membership_levels'] ) && in_array( $key, $_REQUEST['membership_levels'] ) ) {
								$checked_modifier = ' checked';
							}
							// Add checkbox.
							echo '<input type="checkbox" name="membership_levels[]" value="' . $key . '"' . $checked_modifier . '><label> ' . $value->name . '</label><br/>';
						}
						?>
					</li>
					<li>
						<strong>Dog Coat Color:</strong><br/>
						<?php
						// Set up values to filter for.
						$colors = array(
							'brown'  => 'Brown',
							'black'  => 'Black',
							'white'  => 'White',
							'gold'   => 'Gold',
							'yellow' => 'Yellow',
							'grey'   => 'Grey',
						);
						foreach ( $colors as $key => $value ) {
							// Check if this value should default to be checked.
							$checked_modifier = '';
							if ( ! empty( $_REQUEST['coat_color'] ) && in_array( $key, $_REQUEST['coat_color'] ) ) {
								$checked_modifier = ' checked';
							}
							// Add checkbox.
							echo '<input type="checkbox" name="coat_color[]" value="' . $key . '"' . $checked_modifier . '><label> ' . $value . '</label><br/>';
						}
						?>
					</li>
					<li>
						<strong>Dog Weight (lbs):</strong><br/>
						<?php
						// Check if there was a value passed in.
						$max_weight_modifier = '';
						$min_weight_modifier = '';
						if ( ! empty( $_REQUEST['max_weight'] ) && is_numeric( $_REQUEST['max_weight'] ) ) {
							$max_weight_modifier = ' value=' . $_REQUEST['max_weight'];
						}
						if ( ! empty( $_REQUEST['min_weight'] ) && is_numeric( $_REQUEST['min_weight'] ) ) {
							$min_weight_modifier = ' value=' . $_REQUEST['min_weight'];
						}
						// Add inputs.
						echo '<label>Max</label><br/><input type="text" name="max_weight"' . esc_html( $max_weight_modifier ) . '><br/>';
						echo '<label>Min</label><br/><input type="text" name="min_weight"' . esc_html( $min_weight_modifier ) . '><br/>';
						?>
					</li>
					<li><input type="submit" value="Filter"></li>
				</ul>
			</form>
		</aside>
		<?php
	}

}

/**
 * Check $_REQUEST for parameters from the widget. Add to SQL query.
 */
function tsw_pmpro_directory_widget_filter_sql_parts( $sql_parts, $levels, $s, $pn, $limit, $start, $end, $order_by, $order ) {
	global $wpdb;

	// Filter results based on membership level if a level was selected.
	if ( ! empty( $_REQUEST['membership_levels'] ) && is_array( $_REQUEST['membership_levels'] ) ) {
		// User's membership level is already joined, so we can skip that step.
		$sql_parts['WHERE'] .= " AND mu.membership_id in ('" . implode( "','", $_REQUEST['membership_levels'] ) . "') ";
	}

	// Filter results based on coat color if a color is selected.
	if ( ! empty( $_REQUEST['coat_color'] ) && is_array( $_REQUEST['coat_color'] ) ) {
		$sql_parts['JOIN'] .= " LEFT JOIN $wpdb->usermeta um_coat_color ON um_coat_color.meta_key = 'dog_coat_color' AND u.ID = um_coat_color.user_id ";
		$sql_parts['WHERE'] .= " AND um_coat_color.meta_value in ('" . implode( "','", $_REQUEST['coat_color'] ) . "') ";
	}

	// Filter results based on max weight if a max weight was inputted.
	if ( ! empty( $_REQUEST['max_weight'] ) && is_numeric( $_REQUEST['max_weight'] ) ) {
		$join_weight = true; // We will JOIN this later, but we don't want to JOIN it twice.
		$sql_parts['WHERE'] .= ' AND um_dog_weight.meta_value <= ' . $_REQUEST['max_weight'] . ' ';
	}
	// Filter results based on min weight if a min weight was inputted.
	if ( ! empty( $_REQUEST['min_weight'] ) && is_numeric( $_REQUEST['min_weight'] ) ) {
		$join_weight = true; // We will JOIN this later, but we don't want to JOIN it twice.
		$sql_parts['WHERE'] .= ' AND um_dog_weight.meta_value >= ' . $_REQUEST['min_weight'] . ' ';
	}
	// Make sure to get the dog weight in the SQL query if we use that in a WHERE clause.
	if ( ! empty( $join_weight ) ) {
		$sql_parts['JOIN'] .= " LEFT JOIN $wpdb->usermeta um_dog_weight ON um_dog_weight.meta_key = 'dog_weight' AND u.ID = um_dog_weight.user_id ";
	}

	return $sql_parts;
}
add_filter( 'pmpro_member_directory_sql_parts', 'tsw_pmpro_directory_widget_filter_sql_parts', 10, 9 );

/**
 * Registers widget.
 */
function tsw_pmpro_register_directory_widget() {
	register_widget( 'Tsw_PMPro_Directory_Widget' );
}
add_action( 'widgets_init', 'tsw_pmpro_register_directory_widget' );

/**
 * Remember filters being used while using "Next" and "Previous" buttons.
 *
 * @since 2020/06/25
 */
function tsw_pmpromd_pagination_url_filter_directory( $query_args ) {
	$directory_filters = array( 'membership_levels', 'coat_color', 'max_weight', 'min_weight' );
	foreach ( $directory_filters as $directory_filter ) {
		if ( ! empty( $_REQUEST[ $directory_filter ] ) ) {
        		$query_args[ $directory_filter ] =  $_REQUEST[ $directory_filter ];
    		}
	}
	return $query_args;
}
add_filter( 'pmpromd_pagination_url', 'tsw_pmpromd_pagination_url_filter_directory' );
