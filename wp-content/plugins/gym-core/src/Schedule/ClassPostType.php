<?php
/**
 * Custom post type registration for gym classes.
 *
 * Registers the `gym_class` CPT with support for the gym_location taxonomy,
 * custom meta fields (instructor, program, capacity, recurrence), and
 * admin columns.
 *
 * @package Gym_Core
 * @since   1.2.0
 */

declare( strict_types=1 );

namespace Gym_Core\Schedule;

/**
 * Registers and configures the gym_class custom post type.
 */
final class ClassPostType {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'gym_class';

	/**
	 * Program taxonomy slug.
	 *
	 * @var string
	 */
	public const PROGRAM_TAXONOMY = 'gym_program';

	/**
	 * Registers hooks for CPT and taxonomy registration.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_program_taxonomy' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_columns' ), 10, 2 );
	}

	/**
	 * Registers the gym_class post type.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'               => _x( 'Classes', 'post type general name', 'gym-core' ),
			'singular_name'      => _x( 'Class', 'post type singular name', 'gym-core' ),
			'menu_name'          => _x( 'Classes', 'admin menu', 'gym-core' ),
			'add_new'            => __( 'Add New Class', 'gym-core' ),
			'add_new_item'       => __( 'Add New Class', 'gym-core' ),
			'edit_item'          => __( 'Edit Class', 'gym-core' ),
			'new_item'           => __( 'New Class', 'gym-core' ),
			'view_item'          => __( 'View Class', 'gym-core' ),
			'search_items'       => __( 'Search Classes', 'gym-core' ),
			'not_found'          => __( 'No classes found', 'gym-core' ),
			'not_found_in_trash' => __( 'No classes found in Trash', 'gym-core' ),
			'all_items'          => __( 'All Classes', 'gym-core' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => 'gym-core',
			'show_in_rest'       => true,
			'rest_base'          => 'classes',
			'capability_type'    => 'post',
			'has_archive'        => true,
			'rewrite'            => array( 'slug' => 'classes' ),
			'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'taxonomies'         => array( 'gym_location', self::PROGRAM_TAXONOMY ),
			'register_meta_args' => array(),
		);

		register_post_type( self::POST_TYPE, $args );

		$this->register_post_meta();
	}

	/**
	 * Registers the gym_program taxonomy for class categorization.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function register_program_taxonomy(): void {
		$labels = array(
			'name'          => _x( 'Programs', 'taxonomy general name', 'gym-core' ),
			'singular_name' => _x( 'Program', 'taxonomy singular name', 'gym-core' ),
			'menu_name'     => __( 'Programs', 'gym-core' ),
			'all_items'     => __( 'All Programs', 'gym-core' ),
			'edit_item'     => __( 'Edit Program', 'gym-core' ),
			'add_new_item'  => __( 'Add New Program', 'gym-core' ),
			'search_items'  => __( 'Search Programs', 'gym-core' ),
			'not_found'     => __( 'No programs found', 'gym-core' ),
		);

		register_taxonomy(
			self::PROGRAM_TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => $labels,
				'hierarchical'      => true,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'program' ),
			)
		);
	}

	/**
	 * Registers post meta fields for the class CPT.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	private function register_post_meta(): void {
		$meta_fields = array(
			'_gym_class_instructor'  => array(
				'type'        => 'integer',
				'description' => 'Instructor user ID',
				'default'     => 0,
			),
			'_gym_class_capacity'    => array(
				'type'        => 'integer',
				'description' => 'Maximum students',
				'default'     => 30,
			),
			'_gym_class_day_of_week' => array(
				'type'        => 'string',
				'description' => 'Day of week (monday, tuesday, etc.)',
				'default'     => '',
			),
			'_gym_class_start_time'  => array(
				'type'        => 'string',
				'description' => 'Start time in H:i format (24hr)',
				'default'     => '',
			),
			'_gym_class_end_time'    => array(
				'type'        => 'string',
				'description' => 'End time in H:i format (24hr)',
				'default'     => '',
			),
			'_gym_class_recurrence'  => array(
				'type'        => 'string',
				'description' => 'Recurrence rule (weekly, biweekly, or specific dates)',
				'default'     => 'weekly',
			),
			'_gym_class_status'      => array(
				'type'        => 'string',
				'description' => 'Class status (active, cancelled, suspended)',
				'default'     => 'active',
			),
		);

		foreach ( $meta_fields as $key => $config ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => $config['type'],
					'description'   => $config['description'],
					'default'       => $config['default'],
					'auth_callback' => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Adds meta boxes for class details in the post editor.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'gym_class_details',
			__( 'Class Details', 'gym-core' ),
			array( $this, 'render_details_meta_box' ),
			self::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Renders the class details meta box.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_Post $post Current post object.
	 * @return void
	 */
	public function render_details_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'gym_core_class_meta', 'gym_class_meta_nonce' );

		$instructor = (int) get_post_meta( $post->ID, '_gym_class_instructor', true );
		$capacity   = (int) get_post_meta( $post->ID, '_gym_class_capacity', true ) ?: 30;
		$day        = get_post_meta( $post->ID, '_gym_class_day_of_week', true );
		$start_time = get_post_meta( $post->ID, '_gym_class_start_time', true );
		$end_time   = get_post_meta( $post->ID, '_gym_class_end_time', true );
		$recurrence = get_post_meta( $post->ID, '_gym_class_recurrence', true ) ?: 'weekly';
		$status     = get_post_meta( $post->ID, '_gym_class_status', true ) ?: 'active';

		$days = array(
			'monday'    => __( 'Monday', 'gym-core' ),
			'tuesday'   => __( 'Tuesday', 'gym-core' ),
			'wednesday' => __( 'Wednesday', 'gym-core' ),
			'thursday'  => __( 'Thursday', 'gym-core' ),
			'friday'    => __( 'Friday', 'gym-core' ),
			'saturday'  => __( 'Saturday', 'gym-core' ),
			'sunday'    => __( 'Sunday', 'gym-core' ),
		);

		?>
		<p>
			<label for="gym_class_day"><?php esc_html_e( 'Day of Week', 'gym-core' ); ?></label><br>
			<select id="gym_class_day" name="_gym_class_day_of_week" style="width:100%">
				<option value=""><?php esc_html_e( '— Select —', 'gym-core' ); ?></option>
				<?php foreach ( $days as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $day, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="gym_class_start"><?php esc_html_e( 'Start Time', 'gym-core' ); ?></label><br>
			<input type="time" id="gym_class_start" name="_gym_class_start_time"
				value="<?php echo esc_attr( $start_time ); ?>" style="width:100%">
		</p>
		<p>
			<label for="gym_class_end"><?php esc_html_e( 'End Time', 'gym-core' ); ?></label><br>
			<input type="time" id="gym_class_end" name="_gym_class_end_time"
				value="<?php echo esc_attr( $end_time ); ?>" style="width:100%">
		</p>
		<p>
			<label for="gym_class_capacity"><?php esc_html_e( 'Capacity', 'gym-core' ); ?></label><br>
			<input type="number" id="gym_class_capacity" name="_gym_class_capacity"
				value="<?php echo esc_attr( (string) $capacity ); ?>" min="1" style="width:100%">
		</p>
		<p>
			<label for="gym_class_instructor"><?php esc_html_e( 'Instructor', 'gym-core' ); ?></label><br>
			<?php
			wp_dropdown_users(
				array(
					'name'             => '_gym_class_instructor',
					'id'               => 'gym_class_instructor',
					'selected'         => $instructor,
					'show_option_none' => __( '— Select Instructor —', 'gym-core' ),
					'role__in'         => array( 'administrator', 'editor', 'shop_manager' ),
				)
			);
			?>
		</p>
		<p>
			<label for="gym_class_recurrence"><?php esc_html_e( 'Recurrence', 'gym-core' ); ?></label><br>
			<select id="gym_class_recurrence" name="_gym_class_recurrence" style="width:100%">
				<option value="weekly" <?php selected( $recurrence, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'gym-core' ); ?></option>
				<option value="biweekly" <?php selected( $recurrence, 'biweekly' ); ?>><?php esc_html_e( 'Biweekly', 'gym-core' ); ?></option>
				<option value="monthly" <?php selected( $recurrence, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'gym-core' ); ?></option>
			</select>
		</p>
		<p>
			<label for="gym_class_status"><?php esc_html_e( 'Status', 'gym-core' ); ?></label><br>
			<select id="gym_class_status" name="_gym_class_status" style="width:100%">
				<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'gym-core' ); ?></option>
				<option value="cancelled" <?php selected( $status, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'gym-core' ); ?></option>
				<option value="suspended" <?php selected( $status, 'suspended' ); ?>><?php esc_html_e( 'Suspended', 'gym-core' ); ?></option>
			</select>
		</p>
		<?php
	}

	/**
	 * Saves class meta on post save.
	 *
	 * @since 1.2.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['gym_class_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gym_class_meta_nonce'] ) ), 'gym_core_class_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			'_gym_class_instructor'  => 'absint',
			'_gym_class_capacity'    => 'absint',
			'_gym_class_day_of_week' => 'sanitize_text_field',
			'_gym_class_start_time'  => 'sanitize_text_field',
			'_gym_class_end_time'    => 'sanitize_text_field',
			'_gym_class_recurrence'  => 'sanitize_text_field',
			'_gym_class_status'      => 'sanitize_text_field',
		);

		foreach ( $fields as $key => $sanitize ) {
			if ( isset( $_POST[ $key ] ) ) {
				$value = call_user_func( $sanitize, wp_unslash( $_POST[ $key ] ) );
				update_post_meta( $post_id, $key, $value );
			}
		}
	}

	/**
	 * Adds custom columns to the class list table.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function add_admin_columns( array $columns ): array {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( 'title' === $key ) {
				$new_columns['gym_day']        = __( 'Day', 'gym-core' );
				$new_columns['gym_time']       = __( 'Time', 'gym-core' );
				$new_columns['gym_instructor'] = __( 'Instructor', 'gym-core' );
				$new_columns['gym_capacity']   = __( 'Capacity', 'gym-core' );
				$new_columns['gym_status']     = __( 'Status', 'gym-core' );
			}
		}

		return $new_columns;
	}

	/**
	 * Renders custom column content for the class list table.
	 *
	 * @since 1.2.0
	 *
	 * @param string $column  Column identifier.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_admin_columns( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'gym_day':
				$day = get_post_meta( $post_id, '_gym_class_day_of_week', true );
				echo esc_html( ucfirst( $day ) );
				break;

			case 'gym_time':
				$start = get_post_meta( $post_id, '_gym_class_start_time', true );
				$end   = get_post_meta( $post_id, '_gym_class_end_time', true );
				if ( $start && $end ) {
					echo esc_html( "{$start} – {$end}" );
				}
				break;

			case 'gym_instructor':
				$user_id = (int) get_post_meta( $post_id, '_gym_class_instructor', true );
				if ( $user_id ) {
					$user = get_userdata( $user_id );
					echo $user ? esc_html( $user->display_name ) : '—';
				}
				break;

			case 'gym_capacity':
				echo esc_html( (string) get_post_meta( $post_id, '_gym_class_capacity', true ) );
				break;

			case 'gym_status':
				$status = get_post_meta( $post_id, '_gym_class_status', true ) ?: 'active';
				echo esc_html( ucfirst( $status ) );
				break;
		}
	}
}
