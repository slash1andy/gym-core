<?php
/**
 * Custom post type registration for gym announcements.
 *
 * Registers the `gym_announcement` CPT with support for location/program
 * targeting, date-based auto-expiry, and pinned status. Used by the Coach
 * Briefing system to surface admin announcements to coaches before class.
 *
 * @package Gym_Core
 * @since   2.1.0
 */

declare( strict_types=1 );

namespace Gym_Core\Briefing;

/**
 * Registers and configures the gym_announcement custom post type.
 */
final class AnnouncementPostType {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'gym_announcement';

	/**
	 * Registers hooks for CPT registration and admin customizations.
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_columns' ), 10, 2 );
	}

	/**
	 * Registers the gym_announcement post type.
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'               => _x( 'Announcements', 'post type general name', 'gym-core' ),
			'singular_name'      => _x( 'Announcement', 'post type singular name', 'gym-core' ),
			'menu_name'          => _x( 'Announcements', 'admin menu', 'gym-core' ),
			'add_new'            => __( 'Add New Announcement', 'gym-core' ),
			'add_new_item'       => __( 'Add New Announcement', 'gym-core' ),
			'edit_item'          => __( 'Edit Announcement', 'gym-core' ),
			'new_item'           => __( 'New Announcement', 'gym-core' ),
			'view_item'          => __( 'View Announcement', 'gym-core' ),
			'search_items'       => __( 'Search Announcements', 'gym-core' ),
			'not_found'          => __( 'No announcements found', 'gym-core' ),
			'not_found_in_trash' => __( 'No announcements found in Trash', 'gym-core' ),
			'all_items'          => __( 'All Announcements', 'gym-core' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => 'gym-core',
			'show_in_rest'        => true,
			'rest_base'           => 'announcements',
			'capability_type'     => 'post',
			'has_archive'         => false,
			'rewrite'             => false,
			'supports'            => array( 'title', 'editor', 'author' ),
		);

		register_post_type( self::POST_TYPE, $args );

		$this->register_post_meta();
	}

	/**
	 * Registers post meta fields for the announcement CPT.
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	private function register_post_meta(): void {
		$meta_fields = array(
			'_gym_announcement_type'            => array(
				'type'        => 'string',
				'description' => 'Announcement type (global, location, program)',
				'default'     => 'global',
			),
			'_gym_announcement_target_location' => array(
				'type'        => 'string',
				'description' => 'Target location slug (empty for global)',
				'default'     => '',
			),
			'_gym_announcement_target_program'  => array(
				'type'        => 'string',
				'description' => 'Target program slug (empty for all programs)',
				'default'     => '',
			),
			'_gym_announcement_start_date'      => array(
				'type'        => 'string',
				'description' => 'Start date (Y-m-d format, empty for immediate)',
				'default'     => '',
			),
			'_gym_announcement_end_date'        => array(
				'type'        => 'string',
				'description' => 'End date (Y-m-d format, empty for no expiry)',
				'default'     => '',
			),
			'_gym_announcement_pinned'          => array(
				'type'        => 'string',
				'description' => 'Whether this announcement is pinned (yes/no)',
				'default'     => 'no',
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
	 * Adds meta boxes for announcement details in the post editor.
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'gym_announcement_details',
			__( 'Announcement Details', 'gym-core' ),
			array( $this, 'render_details_meta_box' ),
			self::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Renders the announcement details meta box.
	 *
	 * @since 2.1.0
	 *
	 * @param \WP_Post $post Current post object.
	 * @return void
	 */
	public function render_details_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'gym_announcement_meta', 'gym_announcement_meta_nonce' );

		$type            = get_post_meta( $post->ID, '_gym_announcement_type', true ) ?: 'global';
		$target_location = get_post_meta( $post->ID, '_gym_announcement_target_location', true );
		$target_program  = get_post_meta( $post->ID, '_gym_announcement_target_program', true );
		$start_date      = get_post_meta( $post->ID, '_gym_announcement_start_date', true );
		$end_date        = get_post_meta( $post->ID, '_gym_announcement_end_date', true );
		$pinned          = get_post_meta( $post->ID, '_gym_announcement_pinned', true ) ?: 'no';

		$types = array(
			'global'   => __( 'Global (all locations/programs)', 'gym-core' ),
			'location' => __( 'Specific location', 'gym-core' ),
			'program'  => __( 'Specific program', 'gym-core' ),
		);

		?>
		<p>
			<label for="gym_announcement_type"><?php esc_html_e( 'Type', 'gym-core' ); ?></label><br>
			<select id="gym_announcement_type" name="_gym_announcement_type" style="width:100%">
				<?php foreach ( $types as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="gym_announcement_target_location"><?php esc_html_e( 'Target Location', 'gym-core' ); ?></label><br>
			<input type="text" id="gym_announcement_target_location" name="_gym_announcement_target_location"
				value="<?php echo esc_attr( $target_location ); ?>" style="width:100%"
				placeholder="<?php esc_attr_e( 'Location slug (e.g., rockford)', 'gym-core' ); ?>">
		</p>
		<p>
			<label for="gym_announcement_target_program"><?php esc_html_e( 'Target Program', 'gym-core' ); ?></label><br>
			<input type="text" id="gym_announcement_target_program" name="_gym_announcement_target_program"
				value="<?php echo esc_attr( $target_program ); ?>" style="width:100%"
				placeholder="<?php esc_attr_e( 'Program slug (e.g., adult-bjj)', 'gym-core' ); ?>">
		</p>
		<p>
			<label for="gym_announcement_start_date"><?php esc_html_e( 'Start Date', 'gym-core' ); ?></label><br>
			<input type="date" id="gym_announcement_start_date" name="_gym_announcement_start_date"
				value="<?php echo esc_attr( $start_date ); ?>" style="width:100%">
		</p>
		<p>
			<label for="gym_announcement_end_date"><?php esc_html_e( 'End Date', 'gym-core' ); ?></label><br>
			<input type="date" id="gym_announcement_end_date" name="_gym_announcement_end_date"
				value="<?php echo esc_attr( $end_date ); ?>" style="width:100%">
		</p>
		<p>
			<label>
				<input type="checkbox" name="_gym_announcement_pinned" value="yes"
					<?php checked( $pinned, 'yes' ); ?>>
				<?php esc_html_e( 'Pinned (sticky until manually cleared)', 'gym-core' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Saves announcement meta on post save.
	 *
	 * @since 2.1.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['gym_announcement_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gym_announcement_meta_nonce'] ) ), 'gym_announcement_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$text_fields = array(
			'_gym_announcement_type',
			'_gym_announcement_target_location',
			'_gym_announcement_target_program',
			'_gym_announcement_start_date',
			'_gym_announcement_end_date',
		);

		foreach ( $text_fields as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
				update_post_meta( $post_id, $key, $value );
			}
		}

		// Pinned is a checkbox — absent means 'no'.
		$pinned = isset( $_POST['_gym_announcement_pinned'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_gym_announcement_pinned', $pinned );
	}

	/**
	 * Adds custom columns to the announcement list table.
	 *
	 * @since 2.1.0
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function add_admin_columns( array $columns ): array {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( 'title' === $key ) {
				$new_columns['gym_ann_type']    = __( 'Type', 'gym-core' );
				$new_columns['gym_ann_target']  = __( 'Target', 'gym-core' );
				$new_columns['gym_ann_dates']   = __( 'Active Dates', 'gym-core' );
				$new_columns['gym_ann_pinned']  = __( 'Pinned', 'gym-core' );
			}
		}

		return $new_columns;
	}

	/**
	 * Renders custom column content for the announcement list table.
	 *
	 * @since 2.1.0
	 *
	 * @param string $column  Column identifier.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_admin_columns( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'gym_ann_type':
				$type = get_post_meta( $post_id, '_gym_announcement_type', true ) ?: 'global';
				echo esc_html( ucfirst( $type ) );
				break;

			case 'gym_ann_target':
				$type     = get_post_meta( $post_id, '_gym_announcement_type', true ) ?: 'global';
				$location = get_post_meta( $post_id, '_gym_announcement_target_location', true );
				$program  = get_post_meta( $post_id, '_gym_announcement_target_program', true );

				if ( 'global' === $type ) {
					echo esc_html__( 'All', 'gym-core' );
				} elseif ( 'location' === $type && $location ) {
					echo esc_html( ucfirst( $location ) );
				} elseif ( 'program' === $type && $program ) {
					echo esc_html( $program );
				} else {
					echo '—';
				}
				break;

			case 'gym_ann_dates':
				$start = get_post_meta( $post_id, '_gym_announcement_start_date', true );
				$end   = get_post_meta( $post_id, '_gym_announcement_end_date', true );

				if ( $start && $end ) {
					echo esc_html( "{$start} — {$end}" );
				} elseif ( $start ) {
					/* translators: %s: start date */
					echo esc_html( sprintf( __( 'From %s', 'gym-core' ), $start ) );
				} elseif ( $end ) {
					/* translators: %s: end date */
					echo esc_html( sprintf( __( 'Until %s', 'gym-core' ), $end ) );
				} else {
					echo esc_html__( 'Always', 'gym-core' );
				}
				break;

			case 'gym_ann_pinned':
				$pinned = get_post_meta( $post_id, '_gym_announcement_pinned', true );
				echo 'yes' === $pinned ? esc_html__( 'Yes', 'gym-core' ) : '—';
				break;
		}
	}

	/**
	 * Queries active announcements matching the given criteria.
	 *
	 * Returns published announcements that:
	 * - Are within their start/end date window (or have no date constraints)
	 * - Match the given location (or are global)
	 * - Match the given program (or are not program-specific)
	 *
	 * @since 2.1.0
	 *
	 * @param string $location Location slug (empty for all).
	 * @param string $program  Program slug (empty for all).
	 * @return array<int, array{id: int, title: string, content: string, type: string, target_location: string, target_program: string, start_date: string, end_date: string, pinned: bool, author: string}>
	 */
	public static function get_active_announcements( string $location = '', string $program = '' ): array {
		$today = gmdate( 'Y-m-d' );

		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query = new \WP_Query( $args );

		$announcements = array();

		foreach ( $query->posts as $post ) {
			$ann_type     = get_post_meta( $post->ID, '_gym_announcement_type', true ) ?: 'global';
			$ann_location = get_post_meta( $post->ID, '_gym_announcement_target_location', true );
			$ann_program  = get_post_meta( $post->ID, '_gym_announcement_target_program', true );
			$start_date   = get_post_meta( $post->ID, '_gym_announcement_start_date', true );
			$end_date     = get_post_meta( $post->ID, '_gym_announcement_end_date', true );
			$pinned       = get_post_meta( $post->ID, '_gym_announcement_pinned', true );

			// Date filtering: skip if not yet started or already expired.
			if ( $start_date && $today < $start_date ) {
				continue;
			}
			if ( $end_date && $today > $end_date ) {
				continue;
			}

			// Location filtering: skip location-specific announcements that don't match.
			if ( 'location' === $ann_type && '' !== $location && $ann_location !== $location ) {
				continue;
			}

			// Program filtering: skip program-specific announcements that don't match.
			if ( 'program' === $ann_type && '' !== $program && $ann_program !== $program ) {
				continue;
			}

			$author = get_userdata( (int) $post->post_author );

			$announcements[] = array(
				'id'              => $post->ID,
				'title'           => $post->post_title,
				'content'         => $post->post_content,
				'type'            => $ann_type,
				'target_location' => $ann_location ?: '',
				'target_program'  => $ann_program ?: '',
				'start_date'      => $start_date ?: '',
				'end_date'        => $end_date ?: '',
				'pinned'          => 'yes' === $pinned,
				'author'          => $author ? $author->display_name : '',
			);
		}

		// Sort: pinned first, then by date.
		usort(
			$announcements,
			static function ( array $a, array $b ): int {
				if ( $a['pinned'] !== $b['pinned'] ) {
					return $a['pinned'] ? -1 : 1;
				}
				return 0; // Preserve date order from query.
			}
		);

		return $announcements;
	}
}
