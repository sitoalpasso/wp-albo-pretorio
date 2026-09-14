<?php
/**
 * Avvio della suite senza il componente comune.
 *
 * Serve alla riga A-03: il caso da provare e' "le funzioni pubbliche del
 * componente comune non esistono", e in un processo dove quel componente e'
 * caricato quel caso non esiste. Non e' una simulazione: il componente non
 * viene proprio caricato.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

require_once __DIR__ . '/avvio-comune.php';

albo_pretorio_prepara_suite( false );
