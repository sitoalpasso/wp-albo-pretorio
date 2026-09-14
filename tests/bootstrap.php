<?php
/**
 * Avvio della suite di test sulla suite di WordPress.
 *
 * Carica il componente comune prima dell'albo. L'ordine conta: l'albo non lo
 * chiama al caricamento del proprio file, ma la prova che verifica l'avvio con
 * il componente comune presente ha bisogno che ci sia davvero.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

require_once __DIR__ . '/avvio-comune.php';

albo_pretorio_prepara_suite( true );
