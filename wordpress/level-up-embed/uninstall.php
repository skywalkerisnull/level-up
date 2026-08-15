<?php
/**
 * Removes the plugin's settings when it is deleted through WordPress.
 * Deactivating leaves everything intact; only deletion reaches this file.
 *
 * Collected signups are deliberately NOT deleted. They are the mailing list,
 * they exist nowhere else, and a plugin delete is far too easy to do by
 * accident to be worth wiring to an irreversible loss of it. Export the CSV and
 * remove the "Level Up Signups" entries by hand if you really want them gone.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'levelup_settings' );

// Per-IP rate limit counters. Harmless if missed, but there is no reason to
// leave them in the options table.
global $wpdb;
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_levelup\_rl\_%'
	    OR option_name LIKE '\_transient\_timeout\_levelup\_rl\_%'"
);
