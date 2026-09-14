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
 * **L'avvio automatico qui e' spento**, ed e' la condizione che rende la prova
 * onesta. Se restasse acceso, la procedura girerebbe una volta durante l'avvio
 * della suite, il componente comune disattiverebbe l'albo e lascerebbe in bacheca
 * un avviso agganciato con una chiusura anonima, che nessuno puo' piu' togliere.
 * La prova successiva troverebbe quell'avviso e lo scambierebbe per l'effetto
 * della propria chiamata, restando verde anche se la chiamata non producesse
 * niente. Spento l'aggancio, l'unica esecuzione della procedura in questo
 * processo e' quella che la prova fa di persona.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

define( 'CONFORMITA_CORE_VERSIONE_API', '2.0.0' );

require_once __DIR__ . '/avvio-comune.php';

albo_pretorio_prepara_suite( true, false );
