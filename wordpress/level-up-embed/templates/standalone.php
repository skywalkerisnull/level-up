<?php
/**
 * Bare page template used when the Layout setting is one of the standalone
 * options. No theme header, navigation, sidebar or footer — just the embed.
 *
 * Loaded from the plugin via the template_include filter, so nothing has to be
 * copied into the theme and a theme update cannot remove it.
 *
 * wp_head() and wp_footer() stay in deliberately: without them the fonts,
 * styles and scripts this page depends on are never printed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
	<style>
		html, body { margin: 0; padding: 0; background: #080b18; }
		body.level-up-standalone { overflow-x: hidden; }
		/* Themes routinely constrain content width; this page wants none of it. */
		.level-up-standalone .entry-content,
		.level-up-standalone .wp-block-post-content { max-width: none; margin: 0; padding: 0; }
		.level-up-standalone .lu-embed { margin: 0; }
	</style>
</head>
<body <?php body_class( 'level-up-standalone' ); ?>>
<?php wp_body_open(); ?>

<main>
	<?php
	while ( have_posts() ) {
		the_post();
		the_content();
	}
	?>
</main>

<?php wp_footer(); ?>
</body>
</html>
