<?php
/*
Plugin Name: Edent's Archive Calendar Widget
Description: A nice table layout for long archives
Version: trunk
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
	public function widget($args = '', $instance)
	{
		//	Database setup
		global $wpdb;

		//	This widget will display HTML, contained in $output

		//	Start with a details / summary widget
		$output  = '<details class="edent-calendar-summary"><summary class="edent-calendar-summary"><h2>🗓️ <u>Explore The Archives</u></h2></summary>';
		$output .= '<ul class="edent-calendars">';

		//	Generates a list of YYYY MM POSTCOUNT
		//	Only selects published posts
		$query = "SELECT YEAR(post_date)  AS `year`,
						 MONTH(post_date) AS `month`,
						 count(ID)        AS `posts`
				FROM $wpdb->posts
				WHERE post_type = 'post' AND post_status = 'publish'
				GROUP BY YEAR(post_date), MONTH(post_date)
				ORDER BY post_date ASC
				LIMIT 256";

		//	Set up cacheing so we don't call this every time
		$key = md5( $query );
		$last_changed = wp_cache_get_last_changed( 'posts' );
		$key = "wp_get_archives:$key:$last_changed";
		if ( ! $results = wp_cache_get( $key, 'posts' ) )
		{
			$results = $wpdb->get_results( $query );
			wp_cache_set( $key, $results, 'posts' );
		}

		/*
			The returned results will be something like
			array(
				array('year' => '2010','month' =>  '3','posts' =>  '1'),
				array('year' => '2014','month' => '12','posts' =>  '3'),
				array('year' => '2015','month' =>  '7','posts' =>  '7'),
				array('year' => '2015','month' =>  '8','posts' =>  '9'),
				array('year' => '2015','month' =>  '9','posts' => '10'),
				array('year' => '2015','month' => '10','posts' =>  '2'),
				...
		*/
		//	Did we get something back? Sweet!
		if ( $results )
		{
			/*	The table should look like this:
			 _______________________________
			|             2015              |
			|_______________________________|
			| January | February |   March  |
			| 3 posts | 2 posts  |  1 post  |
			|_________|_____________________|
			|  April  |   May    |   June   |
			| 3 posts | 2 posts  |  1 post  |
			|_________|__________|__________|
			|   July  |  August  | September|
			| 3 posts |          |  1 post  |
			|_________|__________|__________|
			| October | November | December |
			| 3 posts |          |          |
			|_________|__________|__________|

			 _______________________________
			|             2014              |
			...

				Everything nicely centred.
				Even if there is only one post in January and the date is August, the whole calendar should be shown.
				Newest year on top.

			*/

			//  Set up an empty string to hold the tables.
			$table_output = "";

			//  We start without a year
			$current_year = "";

			//  Iterate through the results, month by month
			foreach ($results as $result)
			{
				//  For the very first result
				if ("" == $current_year)
				{
					//  Set the current year
					$current_year = $result->year;

					//  Create an array of Jan - Dec, each month has 0 posts
					$month_array = array_fill(1, 12, 0);

					//  Add the number of posts to the correct month
					$month_array[$result->month] = $result->posts;
				}
				elseif ($result->year == $current_year) //  Still on the same year
				{
					//  Add the number of posts to the correct month
					$month_array[$result->month] = $result->posts;
				}
				elseif ($result->year != $current_year) //  We've encountered a new year
				{
					//  Generate the table for the previous year
					$table_output =  generate_archive_calendar_table($month_array, $current_year) . $table_output;

					//  Set the current year
					$current_year = $result->year;

					//  Create an array of Jan - Dec, each month has 0 posts
					$month_array = array_fill(1, 12, 0);

					//  Add the number of posts to the correct month
					$month_array[$result->month] = $result->posts;
				}
			}

			//  Generate the table for the previous year
			$table_output =  generate_archive_calendar_table($month_array,$current_year) . $table_output;

			$output .= $table_output;
			$output .= "</ul>";
			$output .= "</details>";

			extract($args);
			$title = apply_filters(	'widget_title',
									empty($instance['title']) ? __('Archives') : $instance['title'],
									$instance, $this->id_base);

			echo $args['before_widget'];
			// if ( ! empty( $title ) )
			// 	echo $args['before_title'] . $title . $args['after_title'];
			echo $output;
			echo $args['after_widget'];
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
		<label for=  "<?php echo $this->get_field_id(  'title' ); ?>"><?php _e( 'Title:' ); ?></label>
		<input class="widefat"
			   id=   "<?php echo $this->get_field_id(   'title' ); ?>"
			   name= "<?php echo $this->get_field_name( 'title' ); ?>"
			   type= "text"
			   value="<?php echo esc_attr( $title ); ?>"
		 />
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

function generate_archive_calendar_table($calendar, $year)
{
	//  This takes an array of months, each with the number of posts published that month, and the year.

	//	For correctly formatting the date
	global $wp_locale;

	//  Set up the output
	$table = "<li>";
	$table .=   "<time class='edent-calendar-year' id='edent-calendar-{$year}'>{$year}</time>";
	$table .=   "<ul class='edent-calendar-months'>";
	$table .=      "<li class='edent-calendar'>";

	//  Iterate through the calendar
	//  Keep track of which month we're in.  1 == Jan, 2 == Feb, etc
	$month_count = 0;

	foreach ($calendar as $month)
	{
		$month_count++;
		//  The number of posts, e.g. "7 posts"
		$number_of_posts = $calendar[$month_count];
		//  Set up the link to the archive
		$url = "";

		//  The text to display, e.g. "October"
		$month_text = sprintf(__('%1$s'), $wp_locale->get_month($month_count));
		$posts_text = $number_of_posts.' ' . _n( "post", "posts",$number_of_posts );

		if ($number_of_posts > 0) //  Only adding a link to the archive if there are posts to see
		{
			//  The link will be to "/YYYY/MM/"
			$url = get_month_link( $year, $month_count );
			$table .= "<a class='edent-calendar-month' href='$url'>{$month_text}\n{$posts_text}</a>";
		} else {
			$table .= "<p class='edent-calendar-month'>{$month_text}\n&nbsp;</p>";
		}		
	}
	$table .= "</ul></li>";
	//  Send back the HTML of the table
	return $table;
}

/**
 * Proper way to enqueue scripts and styles
 */
function edent_calendar_widget_style()
{
	wp_enqueue_style( 'style-name', 'edent-calendar-widget.css?cache=2023-10-01T08:17' );
}

function register_edent_calendar_widget_style() {
	wp_register_style( 'edent_calendar_widget_style', plugins_url('/edent-calendar-widget.css', __FILE__), false, '1.0.4', 'all' );
}


// Register and load the widget
function edent_calendar_widget_load_widget()
{
	register_edent_calendar_widget_style();
	wp_enqueue_style( 'edent_calendar_widget_style' );
	register_widget(  'edent_calendar_widget' );
}
add_action( 'widgets_init', 'edent_calendar_widget_load_widget' );
