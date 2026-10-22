<?php
/**
 * Tema spoglio di prova: la pagina com'e', senza niente intorno.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<?php wp_head(); ?>
</head>
<body>
<main>
<?php
if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		echo '<h1>' . esc_html( get_the_title() ) . '</h1>';
		the_content();
	}
} else {
	echo '<h1>' . esc_html__( 'Pagina non trovata', 'albo-pretorio-pa' ) . '</h1>';
}
?>
</main>
<?php wp_footer(); ?>
</body>
</html>
