<?php
/**
 * Settings -> Level Up.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_options_page(
			__( 'Level Up Embed', 'level-up-embed' ),
			__( 'Level Up', 'level-up-embed' ),
			'manage_options',
			'level-up-embed',
			'levelup_render_settings_page'
		);
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'levelup_settings_group',
			LEVELUP_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'levelup_sanitize_settings',
				'default'           => levelup_defaults(),
			)
		);
	}
);

function levelup_settings_url() {
	return admin_url( 'options-general.php?page=level-up-embed' );
}

/**
 * @param mixed $input
 * @return array<string,mixed>
 */
function levelup_sanitize_settings( $input ) {
	$out = levelup_defaults();
	if ( ! is_array( $input ) ) {
		return $out;
	}

	$src = isset( $input['src_url'] ) ? trim( (string) $input['src_url'] ) : '';
	if ( '' !== $src ) {
		$src = esc_url_raw( $src, array( 'http', 'https' ) );
		if ( '' === $src ) {
			add_settings_error(
				LEVELUP_OPTION,
				'levelup_src_url',
				__( 'That page URL is not a valid http/https address, so it was not saved.', 'level-up-embed' )
			);
		}
	}
	$out['src_url'] = $src;

	$variant        = isset( $input['variant'] ) ? (string) $input['variant'] : 'site';
	$out['variant'] = array_key_exists( $variant, levelup_variants() ) ? $variant : 'site';

	$mode             = isset( $input['form_mode'] ) ? (string) $input['form_mode'] : 'wordpress';
	$out['form_mode'] = array_key_exists( $mode, levelup_form_modes() ) ? $mode : 'wordpress';

	$out['page_id']      = isset( $input['page_id'] ) ? absint( $input['page_id'] ) : 0;
	$out['frame_height'] = isset( $input['frame_height'] ) ? max( 400, absint( $input['frame_height'] ) ) : 2400;
	$out['notify']       = empty( $input['notify'] ) ? 0 : 1;

	$notify = isset( $input['notify_email'] ) ? sanitize_email( $input['notify_email'] ) : '';
	if ( '' !== $notify && ! is_email( $notify ) ) {
		add_settings_error( LEVELUP_OPTION, 'levelup_notify', __( 'That notification address is not a valid email, so it was not saved.', 'level-up-embed' ) );
		$notify = '';
	}
	$out['notify_email'] = $notify;

	if ( 'wordpress' !== $out['form_mode'] ) {
		add_settings_error(
			LEVELUP_OPTION,
			'levelup_form_mode',
			__( 'Saved. Note that WordPress is not collecting signups with the option you chose.', 'level-up-embed' ),
			'info'
		);
	}

	return $out;
}

/**
 * Creates a page for the embed and selects it, so there is no need to go and
 * make an empty page by hand first.
 */
add_action(
	'admin_post_levelup_create_page',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'level-up-embed' ), 403 );
		}
		check_admin_referer( 'levelup_create_page' );

		$title = isset( $_POST['levelup_page_title'] ) ? sanitize_text_field( wp_unslash( $_POST['levelup_page_title'] ) ) : '';
		if ( '' === $title ) {
			$title = __( 'Level Up', 'level-up-embed' );
		}

		$slug     = isset( $_POST['levelup_page_slug'] ) ? sanitize_title( wp_unslash( $_POST['levelup_page_slug'] ) ) : '';
		$existing = $slug ? get_page_by_path( $slug ) : null;
		if ( $existing ) {
			wp_safe_redirect( add_query_arg( 'levelup_notice', 'slug_taken', levelup_settings_url() ) );
			exit;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				// Left empty on purpose: the plugin renders the embed onto the
				// chosen page automatically, so content here would only duplicate it.
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			wp_safe_redirect( add_query_arg( 'levelup_notice', 'create_failed', levelup_settings_url() ) );
			exit;
		}

		$options            = levelup_options();
		$options['page_id'] = (int) $page_id;
		update_option( LEVELUP_OPTION, $options );

		wp_safe_redirect( add_query_arg( 'levelup_notice', 'created', levelup_settings_url() ) );
		exit;
	}
);

function levelup_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$o       = levelup_options();
	$src     = levelup_iframe_src();
	$page_id = (int) $o['page_id'];
	$notice  = isset( $_GET['levelup_notice'] ) ? sanitize_key( wp_unslash( $_GET['levelup_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Level Up Embed', 'level-up-embed' ); ?></h1>

		<?php if ( 'created' === $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Page created and selected below.', 'level-up-embed' ); ?></p></div>
		<?php elseif ( 'slug_taken' === $notice ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'A page with that URL already exists. Pick it from the dropdown, or choose a different URL.', 'level-up-embed' ); ?></p></div>
		<?php elseif ( 'create_failed' === $notice ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'The page could not be created.', 'level-up-embed' ); ?></p></div>
		<?php endif; ?>

		<?php settings_errors( LEVELUP_OPTION ); ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'levelup_settings_group' ); ?>

			<h2 class="title"><?php esc_html_e( 'The embedded page', 'level-up-embed' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lu-src"><?php esc_html_e( 'Page URL', 'level-up-embed' ); ?></label></th>
					<td>
						<input name="<?php echo esc_attr( LEVELUP_OPTION ); ?>[src_url]" id="lu-src" type="url"
							class="regular-text code" placeholder="https://example.github.io/level-up/"
							value="<?php echo esc_attr( $o['src_url'] ); ?>">
						<p class="description">
							<?php esc_html_e( 'Where the Level Up page is published. Must be https if this site is https, or browsers will block it.', 'level-up-embed' ); ?>
						</p>
						<?php if ( '' !== $src ) : ?>
							<p class="description"><?php esc_html_e( 'Loaded as:', 'level-up-embed' ); ?> <code><?php echo esc_html( $src ); ?></code></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Layout', 'level-up-embed' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Layout', 'level-up-embed' ); ?></legend>
							<?php foreach ( levelup_variants() as $value => $label ) : ?>
								<label style="display:block;margin-bottom:6px">
									<input type="radio" name="<?php echo esc_attr( LEVELUP_OPTION ); ?>[variant]"
										value="<?php echo esc_attr( $value ); ?>" <?php checked( $o['variant'], $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'The standalone options need a page selected below — that is how the plugin knows which page to strip the theme from.', 'level-up-embed' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lu-page"><?php esc_html_e( 'Show it on', 'level-up-embed' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => esc_attr( LEVELUP_OPTION ) . '[page_id]',
								'id'                => 'lu-page',
								'selected'          => $page_id,
								'show_option_none'  => __( '— Use the [level_up_embed] shortcode instead —', 'level-up-embed' ),
								'option_none_value' => 0,
							)
						);
						?>
						<?php if ( $page_id && get_post( $page_id ) ) : ?>
							<p class="description">
								<?php esc_html_e( 'Live at:', 'level-up-embed' ); ?>
								<a href="<?php echo esc_url( get_permalink( $page_id ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_permalink( $page_id ) ); ?></a>
								&nbsp;·&nbsp;
								<a href="<?php echo esc_url( get_edit_post_link( $page_id ) ); ?>"><?php esc_html_e( 'Edit page', 'level-up-embed' ); ?></a>
							</p>
						<?php endif; ?>
						<p class="description">
							<?php esc_html_e( 'The embed is added to the chosen page automatically — it can be completely empty. Or create one below.', 'level-up-embed' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lu-height"><?php esc_html_e( 'Starting height', 'level-up-embed' ); ?></label></th>
					<td>
						<input name="<?php echo esc_attr( LEVELUP_OPTION ); ?>[frame_height]" id="lu-height" type="number"
							min="400" step="100" class="small-text" value="<?php echo esc_attr( (int) $o['frame_height'] ); ?>"> px
						<p class="description">
							<?php esc_html_e( 'Only used for the moment before the page reports its real height. Too small and the page visibly jumps on load.', 'level-up-embed' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Signups', 'level-up-embed' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Who collects them', 'level-up-embed' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Who collects them', 'level-up-embed' ); ?></legend>
							<?php foreach ( levelup_form_modes() as $value => $label ) : ?>
								<label style="display:block;margin-bottom:6px">
									<input type="radio" name="<?php echo esc_attr( LEVELUP_OPTION ); ?>[form_mode]"
										value="<?php echo esc_attr( $value ); ?>" <?php checked( $o['form_mode'], $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'Addresses are stored in WordPress and listed under Settings → Level Up Signups. Export them as CSV when it is time to send something.', 'level-up-embed' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Notify me', 'level-up-embed' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( LEVELUP_OPTION ); ?>[notify]" value="1" <?php checked( (int) $o['notify'], 1 ); ?>>
							<?php esc_html_e( 'Email me whenever someone signs up', 'level-up-embed' ); ?>
						</label>
						<p style="margin-top:10px">
							<label for="lu-notify"><?php esc_html_e( 'Send those to', 'level-up-embed' ); ?></label><br>
							<input name="<?php echo esc_attr( LEVELUP_OPTION ); ?>[notify_email]" id="lu-notify" type="email"
								class="regular-text" value="<?php echo esc_attr( $o['notify_email'] ); ?>"
								placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
						</p>
						<p class="description"><?php esc_html_e( 'Defaults to the site admin address. Signups are stored either way.', 'level-up-embed' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>

		<hr>

		<h2 class="title"><?php esc_html_e( 'Create a page for it', 'level-up-embed' ); ?></h2>
		<p class="description" style="max-width:46em">
			<?php esc_html_e( 'Makes a published, empty page and selects it above. The embed is added to it automatically, so there is nothing to paste in.', 'level-up-embed' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="levelup_create_page">
			<?php wp_nonce_field( 'levelup_create_page' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lu-new-title"><?php esc_html_e( 'Page title', 'level-up-embed' ); ?></label></th>
					<td><input name="levelup_page_title" id="lu-new-title" type="text" class="regular-text" value="Level Up"></td>
				</tr>
				<tr>
					<th scope="row"><label for="lu-new-slug"><?php esc_html_e( 'Page URL', 'level-up-embed' ); ?></label></th>
					<td>
						<code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code>
						<input name="levelup_page_slug" id="lu-new-slug" type="text" class="regular-text" value="level-up" style="width:14em">
						<p class="description"><?php esc_html_e( 'Leave the title if unsure — the URL is what visitors see.', 'level-up-embed' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Create page', 'level-up-embed' ), 'secondary' ); ?>
		</form>

		<hr>

		<h2 class="title"><?php esc_html_e( 'Stored signups', 'level-up-embed' ); ?></h2>
		<p>
			<?php
			$count = levelup_signup_count();
			printf(
				/* translators: %s: number of signups */
				esc_html( _n( '%s address collected so far.', '%s addresses collected so far.', $count, 'level-up-embed' ) ),
				'<strong>' . esc_html( number_format_i18n( $count ) ) . '</strong>'
			);
			?>
		</p>
		<p>
			<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . LEVELUP_CPT ) ); ?>"><?php esc_html_e( 'View signups', 'level-up-embed' ); ?></a>
			<?php if ( $count ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=levelup_export' ), 'levelup_export' ) ); ?>"><?php esc_html_e( 'Export CSV', 'level-up-embed' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
	<?php
}
