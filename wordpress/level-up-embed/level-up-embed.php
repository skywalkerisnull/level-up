<?php
/**
 * Plugin Name:       Level Up Embed
 * Description:       Embeds the Level Up landing page as an auto-resizing iframe and collects waitlist signups into WordPress.
 * Version:           1.2.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Toowoomba Young Chamber
 * License:           GPLv2 or later
 * Text Domain:       level-up-embed
 *
 * Everything is configured under Settings -> Level Up.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LEVELUP_VERSION', '1.2.1' );
define( 'LEVELUP_DIR', plugin_dir_path( __FILE__ ) );
define( 'LEVELUP_URL', plugin_dir_url( __FILE__ ) );
define( 'LEVELUP_OPTION', 'levelup_settings' );

require_once LEVELUP_DIR . 'includes/options.php';
require_once LEVELUP_DIR . 'includes/signups.php';
require_once LEVELUP_DIR . 'includes/settings.php';
require_once LEVELUP_DIR . 'includes/frontend.php';
require_once LEVELUP_DIR . 'includes/rest.php';

/**
 * Seed defaults on activation so the settings screen opens with sensible values
 * rather than a page of empty boxes.
 */
register_activation_hook(
	__FILE__,
	function () {
		if ( false === get_option( LEVELUP_OPTION ) ) {
			add_option( LEVELUP_OPTION, levelup_defaults() );
		}
	}
);

/**
 * Nudge towards the settings screen from the plugins list — without this the
 * plugin activates and appears to do nothing at all.
 */
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		$url = admin_url( 'options-general.php?page=level-up-embed' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'level-up-embed' ) . '</a>' );
		return $links;
	}
);

/**
 * If the plugin is active but has no source URL, it silently renders nothing.
 * Say so where someone will see it.
 */
add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && 'settings_page_level-up-embed' === $screen->id ) {
			return; // The settings page makes this obvious on its own.
		}
		if ( '' !== trim( (string) levelup_option( 'src_url' ) ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Level Up Embed has no page URL set yet, so it will not display anything.', 'level-up-embed' ),
			esc_url( admin_url( 'options-general.php?page=level-up-embed' ) ),
			esc_html__( 'Open settings', 'level-up-embed' )
		);
	}
);
