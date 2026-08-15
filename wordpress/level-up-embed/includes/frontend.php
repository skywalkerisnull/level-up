<?php
/**
 * Front end: the shortcode, the optional auto-render on a chosen page, asset
 * loading, and the standalone page template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LEVELUP_SHORTCODE = 'level_up_embed';

/**
 * True when the current request is the page the embed belongs on, either
 * because the shortcode is in the content or because it is the chosen page.
 */
function levelup_is_embed_context() {
	if ( is_admin() || ! is_singular() ) {
		return false;
	}
	$post = get_post();
	if ( ! $post ) {
		return false;
	}
	if ( (int) levelup_option( 'page_id' ) === (int) $post->ID ) {
		return true;
	}
	return has_shortcode( (string) $post->post_content, LEVELUP_SHORTCODE );
}

/**
 * The iframe renders in its own document and loads its own webfonts. The signup
 * form lives out here in WordPress and would otherwise fall back to the theme's
 * font and visibly stop matching the page above it.
 */
add_action( 'wp_enqueue_scripts', 'levelup_enqueue_assets' );
function levelup_enqueue_assets() {
	if ( ! levelup_is_embed_context() ) {
		return;
	}

	wp_enqueue_style(
		'level-up-fonts',
		'https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Archivo:wght@400;500;600;700;800;900&display=swap',
		array(),
		null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters -- Google Fonts is versioned by URL.
	);
	wp_enqueue_style( 'level-up-embed', LEVELUP_URL . 'assets/embed.css', array(), LEVELUP_VERSION );
	wp_enqueue_script( 'level-up-embed', LEVELUP_URL . 'assets/embed.js', array(), LEVELUP_VERSION, true );

	wp_localize_script(
		'level-up-embed',
		'LevelUpEmbed',
		array(
			'origin'   => levelup_iframe_origin(),
			'endpoint' => esc_url_raw( rest_url( 'levelup/v1/subscribe' ) ),
			'strings'  => array(
				'idle'    => __( 'NO SPAM. UNSUBSCRIBE ANY TIME.', 'level-up-embed' ),
				'sending' => __( 'SENDING...', 'level-up-embed' ),
				'bad'     => __( "THAT EMAIL DOESN'T LOOK RIGHT.", 'level-up-embed' ),
				'ok'      => __( "✓ YOU'RE IN. WATCH YOUR INBOX.", 'level-up-embed' ),
				'fail'    => __( 'COULDN’T ADD YOU. TRY AGAIN.', 'level-up-embed' ),
				'done'    => __( 'DONE', 'level-up-embed' ),
				'join'    => __( 'JOIN', 'level-up-embed' ),
			),
		)
	);
}

/**
 * Build the embed markup.
 */
function levelup_render( $atts = array() ) {
	$options = levelup_options();

	$defaults = array(
		'src'    => levelup_iframe_src(),
		'height' => (int) $options['frame_height'],
	);
	$a = shortcode_atts( $defaults, $atts, LEVELUP_SHORTCODE );

	$src = trim( (string) $a['src'] );
	if ( '' === $src ) {
		if ( current_user_can( 'manage_options' ) ) {
			return '<p><strong>' . esc_html__( 'Level Up Embed: no page URL is set under Settings → Level Up.', 'level-up-embed' ) . '</strong></p>';
		}
		return '';
	}

	$height    = max( 400, (int) $a['height'] );
	$show_form = levelup_shows_form();

	ob_start();
	?>
	<div class="lu-embed">
		<iframe
			class="lu-frame"
			src="<?php echo esc_url( $src ); ?>"
			title="<?php echo esc_attr__( 'Level Up — Toowoomba Young Chamber', 'level-up-embed' ); ?>"
			style="height:<?php echo esc_attr( $height ); ?>px"
			scrolling="no"
			loading="lazy"></iframe>

		<?php if ( $show_form ) : ?>
		<section class="lu-signup" id="lu-signup">
			<div class="lu-signup-inner">
				<div class="lu-kicker">CONTINUE? &nbsp;10&nbsp;9&nbsp;8...</div>
				<h2 class="lu-title"><?php esc_html_e( 'Get on the list', 'level-up-embed' ); ?></h2>
				<p class="lu-lede"><?php esc_html_e( 'Tickets, speakers, the panel line-up, your Meet the Boss roster and the venue all drop to the list first. One email a month, tops.', 'level-up-embed' ); ?></p>

				<form class="lu-form" novalidate>
					<label class="lu-sr" for="lu-email"><?php esc_html_e( 'Email address', 'level-up-embed' ); ?></label>
					<input id="lu-email" class="lu-input" type="email" name="email" autocomplete="email" placeholder="you@work.com.au" required>
					<!-- Honeypot: hidden from people, so anything in it is a bot. -->
					<input class="lu-trap" type="text" name="company" tabindex="-1" autocomplete="off" aria-hidden="true">
					<button class="lu-btn" type="submit">JOIN</button>
				</form>

				<p class="lu-status" aria-live="polite"><?php esc_html_e( 'NO SPAM. UNSUBSCRIBE ANY TIME.', 'level-up-embed' ); ?></p>
			</div>
		</section>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode( LEVELUP_SHORTCODE, 'levelup_render' );

/**
 * Render automatically on the chosen page, so a plain empty page is enough and
 * nobody has to remember the shortcode. Skipped if the shortcode is already in
 * the content, which would otherwise embed the page twice.
 */
add_filter( 'the_content', 'levelup_maybe_autorender', 20 );
function levelup_maybe_autorender( $content ) {
	$page_id = (int) levelup_option( 'page_id' );
	if ( ! $page_id || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( get_the_ID() !== $page_id ) {
		return $content;
	}
	if ( has_shortcode( $content, LEVELUP_SHORTCODE ) ) {
		return $content;
	}
	return $content . levelup_render();
}

/**
 * Serve the chosen page through the plugin's bare template when a standalone
 * variant is selected, so no theme file has to be copied or edited.
 */
add_filter( 'template_include', 'levelup_maybe_standalone_template', 99 );
function levelup_maybe_standalone_template( $template ) {
	$page_id = (int) levelup_option( 'page_id' );
	if ( ! $page_id || ! is_page( $page_id ) || ! levelup_is_standalone() ) {
		return $template;
	}
	$own = LEVELUP_DIR . 'templates/standalone.php';
	return file_exists( $own ) ? $own : $template;
}
