<?php
/**
 * Gli atti scaduti restano riservati anche in amministrazione (ALBO-34).
 *
 * Un atto la cui pubblicazione e' finita si consulta con il permesso apposito.
 * Chi non ha ne' quello ne' il permesso di pubblicare, cioe' chi redige
 * soltanto, non apre, non legge e non cancella un atto scaduto, non ne legge i
 * documenti e non lo trova nell'elenco degli atti. Chi pubblica conserva i
 * passaggi che partono da un atto scaduto, come l'annullamento.
 *
 * La scadenza e' quella del meccanismo comune, letta nell'istante: vale anche
 * se il compito pianificato non e' mai partito, e una data di fine non
 * leggibile vale come scaduta. Conta soltanto per gli stati che hanno una
 * pubblicazione: una bozza una data di fine non l'ha.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Lo scaduto in amministrazione, per chi redige soltanto.
 */
final class ScadutiRiservati {

	/**
	 * Gli stati di un atto che ha avuto una pubblicazione.
	 *
	 * @var array<int, string>
	 */
	const STATI = array( ChiusuraPubblicazione::PUBBLICATO, ChiusuraPubblicazione::ANNULLATO, ChiusuraPubblicazione::DEFISSO );

	/**
	 * L'utente puo' vedere gli scaduti: consulta o pubblica.
	 *
	 * Senza la corrispondenza dei permessi la risposta e' no: si sbaglia nella
	 * direzione che chiude.
	 *
	 * @param int $utente Utente.
	 * @return bool
	 */
	public static function li_vede( int $utente ): bool {
		$mappa = Permessi::mappa();

		if ( is_wp_error( $mappa ) || empty( $mappa[ Permessi::CHIAVE_CONSULTAZIONE ] ) || empty( $mappa['publish_posts'] ) ) {
			return false;
		}

		return user_can( $utente, $mappa[ Permessi::CHIAVE_CONSULTAZIONE ] ) || user_can( $utente, $mappa['publish_posts'] );
	}

	/**
	 * L'atto e' scaduto: ha avuto una pubblicazione e la sua fine e' passata.
	 *
	 * @param int $atto_id Atto.
	 * @return bool
	 */
	public static function scaduto( int $atto_id ): bool {
		$atto = get_post( $atto_id );

		if ( ! $atto instanceof \WP_Post || TIPO !== $atto->post_type || ! TipoAtto::tipo_nostro() ) {
			return false;
		}

		return in_array( $atto->post_status, self::STATI, true ) && true === conformita_core_scaduto( $atto_id );
	}

	/**
	 * Aggancio a `map_meta_cap`: lo scaduto non si apre a chi non lo vede.
	 *
	 * I documenti dell'atto chiedono gli stessi permessi all'atto
	 * (`DocumentiAtto::da_map_meta_cap()`), quindi lo seguono.
	 *
	 * @param array<int, string> $permessi  Permessi primitivi calcolati.
	 * @param string             $permesso  Permesso chiesto.
	 * @param int                $utente_id Utente.
	 * @param array<int, mixed>  $argomenti Argomenti del permesso.
	 * @return array<int, string>
	 */
	public static function da_map_meta_cap( $permessi, $permesso, $utente_id, $argomenti ) {
		if ( ! in_array( $permesso, array( 'edit_post', 'delete_post', 'read_post' ), true ) || ! isset( $argomenti[0] ) || ! is_numeric( $argomenti[0] ) ) {
			return $permessi;
		}

		if ( ! self::scaduto( (int) $argomenti[0] ) || self::li_vede( (int) $utente_id ) ) {
			return $permessi;
		}

		return array( 'do_not_allow' );
	}

	/**
	 * Aggancio a `posts_where`: l'elenco degli atti non mostra gli scaduti a chi non li vede.
	 *
	 * Vale per l'interrogazione principale della schermata degli atti. Resta
	 * un atto che non ha avuto una pubblicazione, oppure che ha una sola data
	 * di fine, scritta bene, di un giorno che esiste e non passato (lo spostamento
	 * di zero giorni rende vuoto un giorno che non esiste): una data
	 * scritta male, impossibile o doppia vale come scaduta, come per il
	 * meccanismo comune.
	 *
	 * @param string    $condizione     Condizione SQL.
	 * @param \WP_Query $interrogazione Interrogazione.
	 * @return string
	 */
	public static function da_posts_where( $condizione, $interrogazione ) {
		if ( ! $interrogazione instanceof \WP_Query || ! is_admin() || ! $interrogazione->is_main_query() || TIPO !== $interrogazione->get( 'post_type' ) || ! TipoAtto::tipo_nostro() ) {
			return $condizione;
		}

		$schermata = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( null === $schermata || 'edit' !== $schermata->base || self::li_vede( get_current_user_id() ) ) {
			return $condizione;
		}

		global $wpdb;

		$stati = implode( ', ', array_fill( 0, count( self::STATI ), '%s' ) );
		$oggi  = Passaggi::ora()->setTimezone( wp_timezone() )->format( 'Y-m-d' );

		$chiave = conformita_core_chiave_fine_pubblicazione();

		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- i segnaposto degli stati sono contati sopra.
		return $condizione . $wpdb->prepare(
			" AND ( {$wpdb->posts}.post_status NOT IN ( {$stati} ) OR ("
			. " ( SELECT COUNT(*) FROM {$wpdb->postmeta} AS righe WHERE righe.post_id = {$wpdb->posts}.ID AND righe.meta_key = %s ) = 1"
			. " AND EXISTS ( SELECT 1 FROM {$wpdb->postmeta} AS fine WHERE fine.post_id = {$wpdb->posts}.ID AND fine.meta_key = %s"
			. " AND DATE_FORMAT( DATE_ADD( STR_TO_DATE( fine.meta_value, '%%Y-%%m-%%d' ), INTERVAL 0 DAY ), '%%Y-%%m-%%d' ) = fine.meta_value AND fine.meta_value >= %s ) ) )",
			array_merge( self::STATI, array( $chiave, $chiave, $oggi ) )
		);
		// phpcs:enable
	}
}
