<?php
/**
 * Avvio della suite con una versione di interfaccia incompatibile.
 *
 * Serve alla riga A-05. La versione la dichiara una costante che il componente
 * comune definisce al proprio caricamento, e una costante si definisce una
 * volta sola: definendola qui prima, il componente trova il posto occupato e
 * la guardia legge il valore che vogliamo. E' l'unico modo di far vedere alla
 * guardia vera una versione diversa da quella reale, senza aggiungere al
 * componente comune un parametro che esisterebbe solo per i test.
 *
 * Il numero maggiore diverso e' il caso piu' netto: incompatibile in entrambe
 * le direzioni, per la regola del componente comune.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

define( 'CONFORMITA_CORE_VERSIONE_API', '2.0.0' );

require_once __DIR__ . '/avvio-comune.php';

albo_pretorio_prepara_suite( true );
