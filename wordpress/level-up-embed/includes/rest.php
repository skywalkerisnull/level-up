<?php
/**
 * The signup endpoint. Addresses are stored in WordPress — see signups.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'levelup/v1',
			'/subscribe',
			array(
				'methods'  => 'POST',
				'callback' => 'levelup_handle_subscribe',
				// A public signup form has to accept submissions from logged-out
				// strangers, so there is no capability to check. The honeypot and
				// the per-IP rate limit below are what stand in for it.
				//
				// A nonce would add nothing and would actively cause harm: they
				// expire after 24h, so a page served from a cache for longer
				// would start rejecting real signups.
				'permission_callback' => '__return_true',
				'args'                => array(
					'email'   => array(
						'required' => true,
						'type'     => 'string',
					),
					'company' => array(
						'required' => false,
						'type'     => 'string',
					),
				),
			)
		);
	}
);

/**
 * @return WP_REST_Response|WP_Error
 */
function levelup_handle_subscribe( WP_REST_Request $request ) {
	if ( ! levelup_shows_form() ) {
		return new WP_Error( 'levelup_disabled', __( 'Signups are not being collected here.', 'level-up-embed' ), array( 'status' => 404 ) );
	}

	// Honeypot. People never see this field, so anything in it is a bot. Report
	// success rather than an error, so the bot does not learn it was filtered,
	// and store nothing.
	if ( '' !== trim( (string) $request->get_param( 'company' ) ) ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	$email = sanitize_email( (string) $request->get_param( 'email' ) );
	if ( ! is_email( $email ) || strlen( $email ) > 254 ) {
		return new WP_Error( 'levelup_bad_email', __( 'That email does not look right.', 'level-up-embed' ), array( 'status' => 400 ) );
	}

	// Rate limit per IP. Stops someone scripting the endpoint to fill the list
	// with junk. The IP is used as a transient key and is never stored.
	$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key  = 'levelup_rl_' . md5( $ip );
	$hits = (int) get_transient( $key );
	if ( $hits >= 5 ) {
		return new WP_Error( 'levelup_rate_limited', __( 'Too many attempts. Try again later.', 'level-up-embed' ), array( 'status' => 429 ) );
	}
	set_transient( $key, $hits + 1, HOUR_IN_SECONDS );

	$stored = levelup_store_signup( $email );
	if ( is_wp_error( $stored ) ) {
		return $stored;
	}

	levelup_maybe_notify( $email );

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/**
 * Optional "tell me when someone signs up" email. A failure here is not the
 * visitor's problem — their address is already stored — so it is logged and
 * swallowed rather than turned into an error.
 */
function levelup_maybe_notify( $email ) {
	if ( ! levelup_option( 'notify' ) ) {
		return;
	}

	$to = levelup_notify_address();
	if ( ! is_email( $to ) ) {
		return;
	}

	$sent = wp_mail(
		$to,
		/* translators: %s: the email address that signed up */
		sprintf( __( 'Level Up signup: %s', 'level-up-embed' ), $email ),
		sprintf(
			"%s\n\n%s\n",
			/* translators: %s: the email address that signed up */
			sprintf( __( 'New waitlist signup: %s', 'level-up-embed' ), $email ),
			__( 'All signups are listed under Settings -> Level Up Signups.', 'level-up-embed' )
		)
	);

	if ( ! $sent ) {
		error_log( 'Level Up: notification email failed for ' . $email ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}
}
