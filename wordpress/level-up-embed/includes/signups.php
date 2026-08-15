<?php
/**
 * Stored signups.
 *
 * Addresses are kept in WordPress rather than pushed to an email provider, so
 * the site holds the list and nothing needs API credentials. A custom post type
 * is used because it comes with an admin list, search and deletion for free.
 *
 * Only the address and the date it arrived are stored. No IP address, no user
 * agent — the less personal data kept, the less there is to look after.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LEVELUP_CPT = 'levelup_signup';

add_action(
	'init',
	function () {
		register_post_type(
			LEVELUP_CPT,
			array(
				'labels'          => array(
					'name'               => __( 'Level Up Signups', 'level-up-embed' ),
					'singular_name'      => __( 'Signup', 'level-up-embed' ),
					'menu_name'          => __( 'Level Up Signups', 'level-up-embed' ),
					'search_items'       => __( 'Search signups', 'level-up-embed' ),
					'not_found'          => __( 'No signups yet.', 'level-up-embed' ),
					'not_found_in_trash' => __( 'No signups in the bin.', 'level-up-embed' ),
				),
				// Never public: these are email addresses, so there must be no
				// front-end URL, no archive and no inclusion in search results.
				'public'          => false,
				'publicly_queryable' => false,
				'exclude_from_search' => true,
				'show_ui'         => true,
				'show_in_menu'    => 'options-general.php',
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'map_meta_cap'    => true,
				'capabilities'    => array(
					// Signups arrive through the form; adding them by hand in the
					// admin would only create malformed entries.
					'create_posts' => 'do_not_allow',
				),
			)
		);
	}
);

/**
 * Record an address. Returns true when stored, and also true when it was
 * already there — from the visitor's point of view both are success.
 *
 * @return true|WP_Error
 */
function levelup_store_signup( $email ) {
	if ( levelup_signup_exists( $email ) ) {
		return true;
	}

	$id = wp_insert_post(
		array(
			'post_type'   => LEVELUP_CPT,
			'post_title'  => $email,
			'post_status' => 'publish',
		),
		true
	);

	if ( is_wp_error( $id ) ) {
		error_log( 'Level Up: could not store signup — ' . $id->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		return new WP_Error( 'levelup_store_failed', __( 'Could not record your signup.', 'level-up-embed' ), array( 'status' => 500 ) );
	}

	return true;
}

function levelup_signup_exists( $email ) {
	$found = get_posts(
		array(
			'post_type'              => LEVELUP_CPT,
			'title'                  => $email,
			'post_status'            => 'any',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	return ! empty( $found );
}

function levelup_signup_count() {
	$counts = wp_count_posts( LEVELUP_CPT );
	return isset( $counts->publish ) ? (int) $counts->publish : 0;
}

/**
 * Every stored address, oldest first.
 *
 * @return WP_Post[]
 */
function levelup_all_signups() {
	return get_posts(
		array(
			'post_type'              => LEVELUP_CPT,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => 'date',
			'order'                  => 'ASC',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
}

function levelup_export_url() {
	return wp_nonce_url( admin_url( 'admin-post.php?action=levelup_export' ), 'levelup_export' );
}

/**
 * Spreadsheets treat a leading =, +, - or @ as the start of a formula, so a
 * value like "+tag@example.com" would execute rather than display. Prefixing
 * with an apostrophe keeps it a string. Rare for an address, but this file is
 * opened in Excel by definition.
 */
function levelup_csv_safe( $value ) {
	$value = (string) $value;
	return ( '' !== $value && strpos( '=+-@', $value[0] ) !== false ) ? "'" . $value : $value;
}

/**
 * CSV export — how addresses get out of here and into whatever sends the email.
 */
add_action(
	'admin_post_levelup_export',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'level-up-embed' ), 403 );
		}
		check_admin_referer( 'levelup_export' );

		$rows = levelup_all_signups();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=level-up-signups-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		// Excel assumes the system codepage without this and mangles anything
		// non-ASCII in an address.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $out, array( 'email', 'signed_up_utc' ) );
		foreach ( $rows as $row ) {
			fputcsv(
				$out,
				array(
					levelup_csv_safe( $row->post_title ),
					get_post_time( 'Y-m-d H:i:s', true, $row ),
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		exit;
	}
);

/**
 * Export controls on the signups list itself.
 *
 * The settings screen has the same button, but this is the screen someone is
 * looking at when they want the addresses, so it belongs here too.
 */
add_action(
	'manage_posts_extra_tablenav',
	function ( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || LEVELUP_CPT !== $screen->post_type || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$rows  = levelup_all_signups();
		$count = count( $rows );
		?>
		<div class="alignleft actions" style="display:flex;gap:6px;align-items:center">
			<a class="button button-primary" href="<?php echo esc_url( levelup_export_url() ); ?>">
				<?php
				printf(
					/* translators: %s: number of signups */
					esc_html( _n( 'Export %s address (CSV)', 'Export all %s addresses (CSV)', $count, 'level-up-embed' ) ),
					esc_html( number_format_i18n( $count ) )
				);
				?>
			</a>
			<?php if ( $count ) : ?>
				<button type="button" class="button" id="lu-copy-all">
					<?php esc_html_e( 'Copy addresses', 'level-up-embed' ); ?>
				</button>
				<span id="lu-copy-done" style="color:#0a7c2f;display:none"><?php esc_html_e( 'Copied.', 'level-up-embed' ); ?></span>
				<textarea id="lu-all-addresses" readonly tabindex="-1" aria-hidden="true"
					style="position:absolute;left:-9999px;width:1px;height:1px"><?php
					echo esc_textarea( implode( ', ', wp_list_pluck( $rows, 'post_title' ) ) );
				?></textarea>
				<script>
				(function () {
					var btn = document.getElementById('lu-copy-all');
					var box = document.getElementById('lu-all-addresses');
					var ok  = document.getElementById('lu-copy-done');
					if (!btn || !box) return;
					btn.addEventListener('click', function () {
						// execCommand is the fallback: the async clipboard API
						// needs a secure context, and plenty of WP admins are
						// still served over plain http on a local network.
						var done = false;
						if (navigator.clipboard && window.isSecureContext) {
							navigator.clipboard.writeText(box.value).then(function () {
								ok.style.display = 'inline';
							});
							done = true;
						}
						if (!done) {
							box.style.position = 'static';
							box.select();
							try { document.execCommand('copy'); ok.style.display = 'inline'; } catch (e) {}
							box.style.position = 'absolute';
						}
					});
				})();
				</script>
			<?php endif; ?>
		</div>
		<?php
	}
);

/**
 * The admin list shows a "Title" column by default, which is meaningless here.
 */
add_filter(
	'manage_' . LEVELUP_CPT . '_posts_columns',
	function ( $columns ) {
		return array(
			'cb'         => isset( $columns['cb'] ) ? $columns['cb'] : '',
			'title'      => __( 'Email address', 'level-up-embed' ),
			'levelup_on' => __( 'Signed up', 'level-up-embed' ),
		);
	}
);

add_action(
	'manage_' . LEVELUP_CPT . '_posts_custom_column',
	function ( $column, $post_id ) {
		if ( 'levelup_on' === $column ) {
			echo esc_html( get_the_date( '', $post_id ) . ' ' . get_the_time( '', $post_id ) );
		}
	},
	10,
	2
);
