<?php
/**
 * Tema spoglio di prova: solo il titolo della finestra, nessuno stile.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function (): void {
		add_theme_support( 'title-tag' );
	}
);
