<?php
/*
Plugin Name: Edent's Archive Calendar Widget
Description: A nice table layout for long archives
*/
/* Start Adding Functions Below this Line */
// Creating the widget 
class edent_calendar_widget extends WP_Widget 
{
	function __construct() 
	{
		parent::__construct(
			// Base ID of your widget
			'edent_calendar_widget', 

			// Widget name will appear in UI
			__('Edent\'s Archive Calendar Widget', 'edent_calendar_widget_domain'), 

			// Widget description
			array( 'description' => __( 'Table based calendar archive', 'edent_calendar_widget_domain' ), ) 
		);
	}

	// Creating widget front-end
	// This is where the action happens
	function widget($args = '') 
	{
		//	Database setup
		global $wpdb, $wp_locale;

		$output = "";

		//	Generates a list of YYYY MM POSTCOUNT
		$query = "SELECT YEAR(post_date) AS `year`, MONTH(post_date) AS `month`, 
				count(ID) as posts FROM $wpdb->posts 
				WHERE post_type = 'post' AND post_status = 'publish'  
				GROUP BY YEAR(post_date), MONTH(post_date) 
				ORDER BY post_date 
				DESC 
				LIMIT 256";

		//	Set up cacheing so we don't call this every time				
		$key = md5( $query );
		$key = "wp_get_archives:$key:$last_changed";
		if ( ! $results = wp_cache_get( $key, 'posts' ) ) {
			$results = $wpdb->get_results( $query );
			wp_cache_set( $key, $results, 'posts' );
		}

		//	Did we get something back? Sweet!
		if ( $results ) 
		{
			$current_year = "";
			$month_count = 0;
			$total_month_count = 0;
			foreach ( (array) $results as $result ) 
			{
				$url = get_month_link( $result->year, $result->month );
				/* translators: 1: month name, 2: 4-digit year */
				$text = sprintf(__('%1$s'), $wp_locale->get_month($result->month));
				$after = $result->posts.' ' . _n( "post", "posts",$result->posts );

				if ($current_year != $result->year)
				{
					if ($total_month_count >= 3)
					{
						$output = str_replace ($current_year . "col" , "3", $output);
					}	else 
					if (2 == $total_month_count)
					{
						$output = str_replace ($current_year . "col" , "2", $output);
					} else
					{
						$output = str_replace ($current_year . "col" , "1", $output);
					}

					$month_count = 0;
					$total_month_count = 0;
					if ("" != $current_year)
					{
						$output .= 		"
									</tbody>
								</table>
								<br/>";
					}
					$current_year = $result->year;

					$output .=	"<table class='archive-calendar' id='archive-calendar-" . $current_year . "'>
									<thead>
										<tr>
											<th colspan='".$current_year."col'>$current_year</th>
										</tr>
									</thead>
									<tbody>";
				}
				if (0 == $month_count)
				{
					$output.="<tr>";
				}

				$month_count++;
				$total_month_count++;
				
				$output .= "<td><a href='$url'>$text<br/>$after</a></td>";
				
				if (3 == $month_count)
				{
					$output.="</tr>";
					$month_count = 0;
				}	
			}
		if ($total_month_count >= 3)
					{
						$output = str_replace ($current_year . "col" , "3", $output);
					}	else 
					if (2 == $total_month_count)
					{
						$output = str_replace ($current_year . "col" , "2", $output);
					} else
					{
						$output = str_replace ($current_year . "col" , "1", $output);
					}
			
			
			$output .= 	"</tbody>
					</table>
					<br/>";
			
			extract($args);
			$title = apply_filters(	'widget_title', 
									empty($instance['title']) ? __('Archives') : $instance['title'], 
									$instance, $this->id_base);

			echo $args['before_widget'];
			if ( ! empty( $title ) )
				echo $args['before_title'] . $title . $args['after_title'];
			echo $output;
			echo $args['after_widget'];
			//var_dump($results);
		}
	}
			
	// Widget Backend 
	public function form( $instance ) 
	{
		if ( isset( $instance[ 'title' ] ) ) 
		{
			$title = $instance[ 'title' ];
		} else {
			$title = __( 'New title', 'edent_calendar_widget_domain' );
	}
	// Widget admin form
	?>
	<p>
		<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
	</p>
	<?php 
	}
		
	// Updating widget replacing old instances with new
	public function update( $new_instance, $old_instance ) 
	{
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		return $instance;
	}
} // Class edent_calendar_widget ends here

/**
 * Proper way to enqueue scripts and styles
 */
function tse_style() 
{
	wp_enqueue_style( 'style-name', 'edent-calendar-widget.css' );
}
 
function register_tse_style() {
	wp_register_style( 'tse_style', plugins_url('/edent-calendar-widget.css', __FILE__), false, '1.0.0', 'all' );
}
 

//wp_enqueue_style( 'tse_style' );

// Register and load the widget
function tse_load_widget() 
{
	register_tse_style();
	wp_enqueue_style( 'tse_style' );
	register_widget( 'edent_calendar_widget' );
}
add_action( 'widgets_init', 'tse_load_widget' );
