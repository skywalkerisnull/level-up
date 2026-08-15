<?php
/**
 * Settings storage and the small amount of logic derived from it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<string,mixed>
 */
function levelup_defaults() {
	return array(
		'src_url'        => '',
		// site | standalone | bare — see levelup_variants().
		'variant'        => 'site',
		'page_id'        => 0,
		'frame_height'   => 2400,
		// wordpress | embedded | none — see levelup_form_modes().
		'form_mode'      => 'wordpress',
		'notify'         => 0,
		'notify_email'   => '',
	);
}

/**
 * How the page sits in the site. The first keeps Level Up as a page of the TYC
 * site; the other two drop the theme's chrome entirely.
 */
function levelup_variants() {
	return array(
		'site'       => __( 'Inside the site — show the WordPress header and navigation', 'level-up-embed' ),
		'standalone' => __( 'Standalone — no WordPress chrome, keep the page\'s own section links', 'level-up-embed' ),
		'bare'       => __( 'Standalone — no navigation at all', 'level-up-embed' ),
	);
}

function levelup_form_modes() {
	return array(
		'wordpress' => __( 'WordPress captures it — form rendered below the embed (recommended)', 'level-up-embed' ),
		'embedded'  => __( 'The embedded page handles its own signups — WordPress captures nothing', 'level-up-embed' ),
		'none'      => __( 'No signup form anywhere', 'level-up-embed' ),
	);
}

/**
 * @return array<string,mixed>
 */
function levelup_options() {
	$saved = get_option( LEVELUP_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( levelup_defaults(), $saved );
}

/**
 * @return mixed
 */
function levelup_option( $key ) {
	$options = levelup_options();
	return array_key_exists( $key, $options ) ? $options[ $key ] : null;
}

/**
 * Drops stored settings that no longer exist in this version.
 *
 * Upgrading in place leaves the previous version's saved array untouched, and
 * levelup_options() merges over it rather than replacing it — so a setting that
 * has been removed from the plugin would sit in the options table indefinitely,
 * only cleared if someone happened to press Save. That matters here because the
 * removed settings included Mailjet API credentials, and the options table ends
 * up in every database backup.
 */
add_action( 'admin_init', 'levelup_prune_stale_options' );
function levelup_prune_stale_options() {
	$saved = get_option( LEVELUP_OPTION );
	if ( ! is_array( $saved ) ) {
		return;
	}

	$known = levelup_defaults();
	if ( ! array_diff_key( $saved, $known ) ) {
		return; // Nothing stale.
	}

	update_option( LEVELUP_OPTION, array_intersect_key( array_merge( $known, $saved ), $known ) );
}

/**
 * Where notification emails go, falling back to the site admin address.
 */
function levelup_notify_address() {
	$to = trim( (string) levelup_option( 'notify_email' ) );
	if ( '' === $to ) {
		$to = (string) get_option( 'admin_email' );
	}
	return $to;
}

/**
 * The iframe URL, with the switches the static page understands appended.
 *
 * nav=0 hides the page's own sticky header. In "site" mode that matters: the
 * theme already supplies navigation and two stacked navs look broken.
 */
function levelup_iframe_src() {
	$options = levelup_options();
	$src     = trim( (string) $options['src_url'] );
	if ( '' === $src ) {
		return '';
	}

	return add_query_arg(
		array(
			'nav'      => ( 'standalone' === $options['variant'] ) ? '1' : '0',
			'waitlist' => ( 'embedded' === $options['form_mode'] ) ? '1' : '0',
			'footer'   => levelup_shows_embedded_footer() ? '1' : '0',
		),
		$src
	);
}

/**
 * Whether the embedded page should keep its own footer.
 *
 * Only when it is genuinely the end of the page. A footer is a full-width
 * bottom-of-page marker, so anything rendered after the frame makes it read as
 * a page that already ended and then kept going. It is therefore hidden when
 * the theme supplies a footer of its own ("site"), and when the plugin renders
 * the signup form below the frame — which would otherwise sit underneath a
 * footer. That leaves one case where it survives: a standalone page whose
 * signups are handled elsewhere, where it is the only footer there is.
 */
function levelup_shows_embedded_footer() {
	$options = levelup_options();
	return 'standalone' === $options['variant'] && 'wordpress' !== $options['form_mode'];
}

/**
 * Origin of the iframe, used to check postMessage senders. Returns '' when the
 * URL is unusable, and the front end then refuses every message.
 */
function levelup_iframe_origin() {
	$src = levelup_iframe_src();
	if ( '' === $src ) {
		return '';
	}
	$scheme = wp_parse_url( $src, PHP_URL_SCHEME );
	$host   = wp_parse_url( $src, PHP_URL_HOST );
	if ( ! $scheme || ! $host ) {
		return '';
	}
	$origin = $scheme . '://' . $host;
	$port   = wp_parse_url( $src, PHP_URL_PORT );
	if ( $port ) {
		$origin .= ':' . $port;
	}
	return $origin;
}

/**
 * Whether the plugin should render its own signup form.
 */
function levelup_shows_form() {
	return 'wordpress' === levelup_option( 'form_mode' );
}

/**
 * Whether the theme's header and footer should be suppressed for this page.
 */
function levelup_is_standalone() {
	return in_array( levelup_option( 'variant' ), array( 'standalone', 'bare' ), true );
}
