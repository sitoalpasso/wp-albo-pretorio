<?php
/**
 * I documenti di un atto fermo restano quelli pubblicati.
 *
 * Lo sbarramento (`ChiusuraPubblicazione`) tiene fermo l'atto in verifica,
 * pubblicato, annullato o tolto dall'albo. I suoi documenti pero' sono
 * contenuti a se', di tipo allegato, e lo sbarramento non li guarda: senza
 * questa classe un amministratore li staccherebbe dall'atto dalla libreria dei
 * media, e un altro componente li cancellerebbe, li metterebbe nel cestino o
 * li farebbe puntare a un altro file. L'atto perderebbe il documento
 * pubblicato, o ne mostrerebbe uno diverso, senza che nessuno lo veda.
 *
 * **Quali documenti.** Un allegato in vita, figlio di un atto fermo, che i
 * dati dell'atto indicano come principale o come allegato ulteriore, oppure
 * che porta il segno della sostituzione per oscuramento. I dati si leggono
 * dalla banca dati com'e', senza la memoria di WordPress, e una lettura che
 * non risponde vale come documento protetto. Un figlio che nessun dato indica
 * e senza segno, come il file caricato da un oscuramento non riuscito, non e'
 * un documento dell'atto e si toglie come prima.
 *
 * **Cosa resta com'e'.** Il documento non si cancella e non va nel cestino;
 * non cambia atto, stato, tipo ne' tipo di file; non cambiano il file a cui
 * punta, la marca, l'impronta, la dimensione e il deposito del meccanismo
 * comune; il segno della sostituzione, una volta scritto, resta. Titolo e
 * descrizione, che nessuna pagina pubblica mostra, restano modificabili.
 *
 * **Le vie chiuse.** La cancellazione e il cestino; il salvataggio del
 * contenuto, da qualunque parte arrivi (codice, interfaccia per programmi);
 * l'istruzione con cui WordPress stacca e aggancia gli allegati dalla
 * libreria dei media e quella con cui cambia il tipo di un contenuto; i
 * metadati, anche per numero di riga e anche tolti a tutti i contenuti.
 * Restano fuori il file sul disco, che l'impronta rivela, e un'istruzione
 * scritta a mano da un altro componente in una forma diversa da quelle di
 * WordPress.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * La protezione dei documenti di un atto fermo.
 */
final class DocumentiFermi {

	/**
	 * Il nome con cui un rifiuto si segnala a chi programma.
	 *
	 * @var string
	 */
	const SEGNALAZIONE = 'AlboPretorioPa\DocumentiFermi';

	/**
	 * Il documento non e' di un atto fermo.
	 *
	 * @var string
	 */
	const LIBERO = 'libero';

	/**
	 * Il documento e' di un atto fermo.
	 *
	 * @var string
	 */
	const PROTETTO = 'protetto';

	/**
	 * La lettura che lo decide non ha risposto: vale come protetto.
	 *
	 * @var string
	 */
	const SENZA_RISPOSTA = 'senza_risposta';

	/**
	 * I campi della riga del documento che non cambiano.
	 *
	 * @var array<int, string>
	 */
	const CAMPI = array( 'post_parent', 'post_status', 'post_type', 'post_mime_type' );

	/**
	 * Aggancia le difese.
	 *
	 * Come lo sbarramento dell'atto, all'ultima priorita' e al caricamento:
	 * deve valere anche quando il resto del componente e' inerte.
	 */
	public static function aggancia(): void {
		add_filter( 'pre_delete_attachment', array( self::class, 'da_pre_delete_attachment' ), PHP_INT_MAX, 2 );
		add_filter( 'pre_trash_post', array( self::class, 'da_pre_trash_post' ), PHP_INT_MAX, 2 );
		add_filter( 'wp_insert_post_empty_content', array( self::class, 'da_wp_insert_post_empty_content' ), PHP_INT_MAX, 2 );
		add_filter( 'wp_insert_attachment_data', array( self::class, 'da_dati_scritti' ), PHP_INT_MAX, 2 );
		add_filter( 'wp_insert_post_data', array( self::class, 'da_dati_scritti' ), PHP_INT_MAX, 2 );
		add_filter( 'query', array( self::class, 'da_query' ), PHP_INT_MAX );
		add_filter( 'add_post_metadata', array( self::class, 'da_add_post_metadata' ), PHP_INT_MAX, 3 );
		add_filter( 'update_post_metadata', array( self::class, 'da_update_post_metadata' ), PHP_INT_MAX, 3 );
		add_filter( 'delete_post_metadata', array( self::class, 'da_delete_post_metadata' ), PHP_INT_MAX, 5 );
		add_filter( 'update_post_metadata_by_mid', array( self::class, 'da_update_post_metadata_by_mid' ), PHP_INT_MAX, 4 );
		add_filter( 'delete_post_metadata_by_mid', array( self::class, 'da_delete_post_metadata_by_mid' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Le chiavi dei metadati del documento che non cambiano.
	 *
	 * Il segno della sostituzione non e' fra queste: si aggiunge quando manca,
	 * ed e' la sostituzione stessa a scriverlo, e una volta scritto resta.
	 *
	 * @return array<int, string>
	 */
	private static function chiavi_del_file(): array {
		$chiavi = array( '_wp_attached_file' );

		if ( class_exists( '\Conformita_Core_Allegati' ) ) {
			$chiavi[] = \Conformita_Core_Allegati::MARCA;
			$chiavi[] = \Conformita_Core_Allegati::IMPRONTA;
			$chiavi[] = \Conformita_Core_Allegati::DIMENSIONE;
			$chiavi[] = \Conformita_Core_Allegati::DEPOSITO;
		}

		return $chiavi;
	}

	/**
	 * Se il contenuto e' un documento di un atto fermo, letto dalla banca dati.
	 *
	 * @param int $documento_id Contenuto.
	 * @return string `libero`, `protetto` oppure `senza_risposta`.
	 */
	public static function stato( int $documento_id ): string {
		global $wpdb;

		if ( $documento_id <= 0 || ! TipoAtto::tipo_nostro() ) {
			return self::LIBERO;
		}

		$righe = Lettura::righe(
			$wpdb->prepare(
				'SELECT documento.post_type AS tipo_documento, documento.post_status AS stato_documento, documento.post_parent AS atto, atto.post_type AS tipo_atto, atto.post_status AS stato_atto FROM %i AS documento LEFT JOIN %i AS atto ON atto.ID = documento.post_parent WHERE documento.ID = %d',
				$wpdb->posts,
				$wpdb->posts,
				$documento_id
			),
			array( 'tipo_documento', 'stato_documento', 'atto', 'tipo_atto', 'stato_atto' )
		);

		if ( null === $righe ) {
			return self::SENZA_RISPOSTA;
		}

		if ( array() === $righe ) {
			return self::LIBERO;
		}

		$riga = $righe[0];

		if ( 'attachment' !== $riga['tipo_documento'] || 'inherit' !== $riga['stato_documento'] || TIPO !== $riga['tipo_atto']
			|| ! in_array( (string) $riga['stato_atto'], ChiusuraPubblicazione::STATI_FERMI, true ) ) {
			return self::LIBERO;
		}

		$atto_id = (int) $riga['atto'];
		$dati    = Lettura::righe(
			$wpdb->prepare(
				'SELECT post_id, meta_key, meta_value FROM %i WHERE ( post_id = %d AND meta_key IN ( %s, %s ) ) OR ( post_id = %d AND meta_key = %s )',
				$wpdb->postmeta,
				$atto_id,
				META_DOCUMENTO_PRINCIPALE,
				META_ALLEGATI_ULTERIORI,
				$documento_id,
				META_SOSTITUITO_DA
			),
			array( 'post_id', 'meta_key', 'meta_value' )
		);

		if ( null === $dati ) {
			return self::SENZA_RISPOSTA;
		}

		foreach ( $dati as $dato ) {
			if ( META_SOSTITUITO_DA === $dato['meta_key'] && $documento_id === (int) $dato['post_id'] ) {
				return self::PROTETTO;
			}

			if ( $atto_id !== (int) $dato['post_id'] ) {
				continue;
			}

			// Il dato com'e' scritto, senza la validazione della lettura: la validazione guarda proprio cio' che qui si protegge.
			$valori = META_DOCUMENTO_PRINCIPALE === $dato['meta_key'] ? array( $dato['meta_value'] ) : maybe_unserialize( (string) $dato['meta_value'] );

			foreach ( is_array( $valori ) ? $valori : array() as $valore ) {
				if ( is_scalar( $valore ) && 0 === strcmp( (string) $documento_id, trim( (string) $valore ) ) ) {
					return self::PROTETTO;
				}
			}
		}

		return self::LIBERO;
	}

	/**
	 * Se la scrittura del documento si rifiuta, e in quel caso la segnala.
	 *
	 * @param int $documento_id Contenuto.
	 * @return bool
	 */
	private static function rifiuta( int $documento_id ): bool {
		$stato = self::stato( $documento_id );

		if ( self::LIBERO === $stato ) {
			return false;
		}

		self::segnala( $documento_id, $stato );

		return true;
	}

	/**
	 * Segnala il rifiuto a chi programma.
	 *
	 * Gli agganci con cui WordPress lascia rifiutare queste scritture non
	 * portano un messaggio al chiamante: la segnalazione arriva dove chi
	 * programma guarda, come per lo sbarramento dell'atto.
	 *
	 * @param int    $documento_id Contenuto.
	 * @param string $stato        Perche' si rifiuta.
	 */
	private static function segnala( int $documento_id, string $stato ): void {
		$messaggio = self::SENZA_RISPOSTA === $stato
			? sprintf(
				/* translators: %d: identificativo del contenuto. */
				__( 'Scrittura rifiutata: la banca dati non ha risposto alla lettura che decide se il contenuto %d e\' un documento di un atto dell\'albo. Nessuna modifica e\' stata fatta.', 'albo-pretorio-pa' ),
				$documento_id
			)
			: sprintf(
				/* translators: %d: identificativo del contenuto. */
				__( 'Scrittura rifiutata: il contenuto %d e\' un documento di un atto dell\'albo in verifica, pubblicato, annullato o tolto dall\'albo, e resta com\'e\' stato pubblicato. Si possono cambiare soltanto titolo e descrizione.', 'albo-pretorio-pa' ),
				$documento_id
			);

		_doing_it_wrong( esc_html( self::SEGNALAZIONE ), esc_html( $messaggio ), esc_html( VERSIONE ) );
	}

	/**
	 * Aggancio a `pre_delete_attachment`: il documento non si cancella.
	 *
	 * Copre anche il cestino chiesto a `wp_delete_attachment()` e la
	 * cancellazione chiesta a `wp_delete_post()`, che per un allegato passano
	 * di qui.
	 *
	 * @param mixed $esito    Risposta proposta, nulla per proseguire.
	 * @param mixed $allegato Allegato.
	 * @return mixed
	 */
	public static function da_pre_delete_attachment( $esito, $allegato ) {
		return $allegato instanceof \WP_Post && self::rifiuta( (int) $allegato->ID ) ? false : $esito;
	}

	/**
	 * Aggancio a `pre_trash_post`: il documento non va nel cestino.
	 *
	 * @param mixed $esito    Risposta proposta, nulla per proseguire.
	 * @param mixed $allegato Contenuto.
	 * @return mixed
	 */
	public static function da_pre_trash_post( $esito, $allegato ) {
		if ( ! $allegato instanceof \WP_Post || 'attachment' !== $allegato->post_type ) {
			return $esito;
		}

		return self::rifiuta( (int) $allegato->ID ) ? false : $esito;
	}

	/**
	 * Aggancio a `wp_insert_post_empty_content`: il salvataggio non cambia atto, stato, tipo o tipo di file.
	 *
	 * Si confronta la richiesta con la riga com'e' nella banca dati. Titolo e
	 * descrizione passano.
	 *
	 * @param bool                 $vuoto   Risposta proposta: la scrittura va fermata.
	 * @param array<string, mixed> $postarr Dati della richiesta.
	 * @return bool
	 */
	public static function da_wp_insert_post_empty_content( $vuoto, $postarr ) {
		$postarr = is_array( $postarr ) ? $postarr : array();
		$id      = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;

		if ( $id <= 0 ) {
			return $vuoto;
		}

		$stato = self::stato( $id );

		if ( self::LIBERO === $stato ) {
			return $vuoto;
		}

		$riga = self::riga( $id );

		if ( null === $riga ) {
			self::segnala( $id, self::SENZA_RISPOSTA );

			return true;
		}

		$proposti = array(
			'post_parent'    => isset( $postarr['post_parent'] ) ? (string) (int) $postarr['post_parent'] : '0',
			'post_status'    => isset( $postarr['post_status'] ) ? (string) $postarr['post_status'] : '',
			'post_type'      => isset( $postarr['post_type'] ) ? (string) $postarr['post_type'] : '',
			'post_mime_type' => isset( $postarr['post_mime_type'] ) ? (string) $postarr['post_mime_type'] : '',
		);

		foreach ( self::CAMPI as $campo ) {
			if ( $proposti[ $campo ] !== $riga[ $campo ] ) {
				self::segnala( $id, $stato );

				return true;
			}
		}

		return $vuoto;
	}

	/**
	 * Aggancio a `wp_insert_attachment_data` e `wp_insert_post_data`: quello che si scrive ha i campi di prima.
	 *
	 * E' la difesa dietro il controllo della richiesta: un altro aggancio,
	 * come quello sull'atto di un allegato, puo' cambiare i dati dopo che la
	 * richiesta e' stata vagliata. Qui si rimettono i valori della banca
	 * dati.
	 *
	 * @param array<string, mixed> $data    Dati che stanno per essere scritti.
	 * @param array<string, mixed> $postarr Dati della richiesta.
	 * @return array<string, mixed>
	 */
	public static function da_dati_scritti( $data, $postarr ) {
		$id = is_array( $postarr ) && isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;

		if ( ! is_array( $data ) || $id <= 0 || self::LIBERO === self::stato( $id ) ) {
			return $data;
		}

		$riga = self::riga( $id );

		if ( null === $riga ) {
			wp_die(
				esc_html( __( 'Scrittura rifiutata: la banca dati non ha risposto alla lettura del documento di un atto dell\'albo. Nessuna modifica e\' stata fatta.', 'albo-pretorio-pa' ) ),
				esc_html__( 'Scrittura rifiutata', 'albo-pretorio-pa' ),
				array( 'response' => 403 )
			);
		}

		foreach ( self::CAMPI as $campo ) {
			$data[ $campo ] = 'post_parent' === $campo ? (int) $riga[ $campo ] : $riga[ $campo ];
		}

		return $data;
	}

	/**
	 * La riga del documento, letta dalla banca dati, o nulla se la lettura non risponde.
	 *
	 * @param int $id Contenuto.
	 * @return array<string, string>|null
	 */
	private static function riga( int $id ): ?array {
		global $wpdb;

		$righe = Lettura::righe(
			$wpdb->prepare( 'SELECT post_parent, post_status, post_type, post_mime_type FROM %i WHERE ID = %d', $wpdb->posts, $id ),
			self::CAMPI
		);

		if ( null === $righe || 1 !== count( $righe ) ) {
			return null;
		}

		return array_map( 'strval', $righe[0] );
	}

	/**
	 * Aggancio a `query`: le due istruzioni dirette di WordPress sulla riga di un allegato.
	 *
	 * La prima e' quella della libreria dei media che stacca o aggancia gli
	 * allegati scelti (`wp_media_attach_action()`): i documenti di un atto
	 * fermo escono dall'elenco, e se non resta nessuno l'istruzione non parte.
	 * La seconda e' quella di `set_post_type()`, che cambia il tipo senza
	 * passare dal salvataggio.
	 *
	 * @param mixed $istruzione Istruzione.
	 * @return mixed
	 */
	public static function da_query( $istruzione ) {
		global $wpdb;

		if ( ! is_string( $istruzione ) || 0 !== strncmp( $istruzione, 'UPDATE ', 7 ) ) {
			return $istruzione;
		}

		$tabella = preg_quote( $wpdb->posts, '/' );

		if ( 1 === preg_match( '/\AUPDATE ' . $tabella . ' SET post_parent = ([0-9]+) WHERE post_type = \'attachment\' AND ID IN \( ([0-9,]+) \)\z/', $istruzione, $pezzi ) ) {
			$restano = array();

			foreach ( explode( ',', $pezzi[2] ) as $id ) {
				if ( '' !== $id && ! self::rifiuta( (int) $id ) ) {
					$restano[] = (int) $id;
				}
			}

			if ( array() === $restano ) {
				return '';
			}

			return 'UPDATE ' . $wpdb->posts . ' SET post_parent = ' . $pezzi[1] . " WHERE post_type = 'attachment' AND ID IN ( " . implode( ',', $restano ) . ' )';
		}

		if ( 1 === preg_match( '/\AUPDATE `' . $tabella . '` SET `post_type` = \'(?:[^\'\\\\]|\\\\.)*\' WHERE `ID` = ([0-9]+)\z/', $istruzione, $pezzi ) && self::rifiuta( (int) $pezzi[1] ) ) {
			return '';
		}

		return $istruzione;
	}

	/**
	 * Aggancio a `add_post_metadata`.
	 *
	 * @param mixed $esito  Risposta proposta, nulla per proseguire.
	 * @param mixed $id     Contenuto.
	 * @param mixed $chiave Chiave.
	 * @return mixed
	 */
	public static function da_add_post_metadata( $esito, $id, $chiave ) {
		return self::metadato_ammesso( (int) $id, (string) $chiave ) ? $esito : false;
	}

	/**
	 * Aggancio a `update_post_metadata`.
	 *
	 * @param mixed $esito  Risposta proposta, nulla per proseguire.
	 * @param mixed $id     Contenuto.
	 * @param mixed $chiave Chiave.
	 * @return mixed
	 */
	public static function da_update_post_metadata( $esito, $id, $chiave ) {
		return self::metadato_ammesso( (int) $id, (string) $chiave ) ? $esito : false;
	}

	/**
	 * Aggancio a `delete_post_metadata`, anche quando la chiave si toglie a tutti i contenuti.
	 *
	 * @param mixed $esito  Risposta proposta, nulla per proseguire.
	 * @param mixed $id     Contenuto.
	 * @param mixed $chiave Chiave.
	 * @param mixed $valore Valore.
	 * @param mixed $tutti  Se la cancellazione vale per tutti i contenuti.
	 * @return mixed
	 */
	public static function da_delete_post_metadata( $esito, $id, $chiave, $valore, $tutti ) {
		if ( $tutti ) {
			return self::chiave_libera( (string) $chiave ) ? $esito : false;
		}

		return self::metadato_ammesso( (int) $id, (string) $chiave ) ? $esito : false;
	}

	/**
	 * Aggancio a `update_post_metadata_by_mid`, anche quando la riga cambia chiave.
	 *
	 * @param mixed $esito   Risposta proposta, nulla per proseguire.
	 * @param mixed $meta_id Numero della riga.
	 * @param mixed $valore  Valore.
	 * @param mixed $nuova   Chiave nuova, o falso se la chiave resta.
	 * @return mixed
	 */
	public static function da_update_post_metadata_by_mid( $esito, $meta_id, $valore, $nuova = false ) {
		$riga = self::riga_di_metadato( (int) $meta_id );

		if ( false === $riga ) {
			return false;
		}

		if ( null === $riga ) {
			return $esito;
		}

		$id = (int) $riga['post_id'];

		if ( ! self::metadato_ammesso( $id, (string) $riga['meta_key'] ) ) {
			return false;
		}

		return is_string( $nuova ) && '' !== $nuova && ! self::metadato_ammesso( $id, $nuova ) ? false : $esito;
	}

	/**
	 * Aggancio a `delete_post_metadata_by_mid`.
	 *
	 * @param mixed $esito   Risposta proposta, nulla per proseguire.
	 * @param mixed $meta_id Numero della riga.
	 * @return mixed
	 */
	public static function da_delete_post_metadata_by_mid( $esito, $meta_id ) {
		$riga = self::riga_di_metadato( (int) $meta_id );

		if ( false === $riga ) {
			return false;
		}

		return null === $riga || self::metadato_ammesso( (int) $riga['post_id'], (string) $riga['meta_key'] ) ? $esito : false;
	}

	/**
	 * La riga di metadato indicata per numero.
	 *
	 * @param int $meta_id Numero della riga.
	 * @return array<string, string>|null|false Nulla se la riga non c'e', falso se la lettura non ha risposto.
	 */
	private static function riga_di_metadato( int $meta_id ) {
		global $wpdb;

		if ( ! TipoAtto::tipo_nostro() ) {
			return null;
		}

		$righe = Lettura::righe(
			$wpdb->prepare( 'SELECT meta_key, post_id FROM %i WHERE meta_id = %d', $wpdb->postmeta, $meta_id ),
			array( 'post_id', 'meta_key' )
		);

		if ( null === $righe ) {
			self::segnala( 0, self::SENZA_RISPOSTA );

			return false;
		}

		return array() === $righe ? null : $righe[0];
	}

	/**
	 * Se la scrittura di questo metadato del contenuto e' ammessa.
	 *
	 * Le chiavi del file non si scrivono su un documento di un atto fermo. Il
	 * segno della sostituzione si scrive solo se manca.
	 *
	 * @param int    $id     Contenuto.
	 * @param string $chiave Chiave.
	 * @return bool
	 */
	private static function metadato_ammesso( int $id, string $chiave ): bool {
		if ( META_SOSTITUITO_DA === $chiave ) {
			return self::segno_assente( $id );
		}

		if ( ! in_array( $chiave, self::chiavi_del_file(), true ) ) {
			return true;
		}

		return ! self::rifiuta( $id );
	}

	/**
	 * Se il segno della sostituzione manca su un documento di un atto fermo, o il contenuto non e' uno di questi.
	 *
	 * @param int $id Contenuto.
	 * @return bool
	 */
	private static function segno_assente( int $id ): bool {
		global $wpdb;

		$stato = self::stato( $id );

		if ( self::LIBERO === $stato ) {
			return true;
		}

		if ( self::SENZA_RISPOSTA === $stato ) {
			self::segnala( $id, $stato );

			return false;
		}

		$righe = Lettura::righe(
			$wpdb->prepare( 'SELECT meta_id FROM %i WHERE post_id = %d AND meta_key = %s', $wpdb->postmeta, $id, META_SOSTITUITO_DA ),
			array( 'meta_id' )
		);

		if ( array() === $righe ) {
			return true;
		}

		self::segnala( $id, null === $righe ? self::SENZA_RISPOSTA : self::PROTETTO );

		return false;
	}

	/**
	 * Se una chiave del documento si puo' togliere a tutti i contenuti: nessun figlio di un atto fermo la porta.
	 *
	 * La verifica e' piu' larga della protezione, che guarda i figli indicati:
	 * per una cancellazione di massa basta un figlio qualunque.
	 *
	 * @param string $chiave Chiave.
	 * @return bool
	 */
	private static function chiave_libera( string $chiave ): bool {
		global $wpdb;

		if ( ! TipoAtto::tipo_nostro() || ( META_SOSTITUITO_DA !== $chiave && ! in_array( $chiave, self::chiavi_del_file(), true ) ) ) {
			return true;
		}

		$righe = Lettura::righe(
			$wpdb->prepare(
				'SELECT m.post_id FROM %i AS m JOIN %i AS documento ON documento.ID = m.post_id JOIN %i AS atto ON atto.ID = documento.post_parent WHERE m.meta_key = %s AND documento.post_type = %s AND atto.post_type = %s AND atto.post_status IN ( %s, %s, %s, %s ) LIMIT 1',
				$wpdb->postmeta,
				$wpdb->posts,
				$wpdb->posts,
				$chiave,
				'attachment',
				TIPO,
				ChiusuraPubblicazione::IN_VERIFICA,
				ChiusuraPubblicazione::PUBBLICATO,
				ChiusuraPubblicazione::ANNULLATO,
				ChiusuraPubblicazione::DEFISSO
			),
			array( 'post_id' )
		);

		if ( array() === $righe ) {
			return true;
		}

		self::segnala( null === $righe ? 0 : (int) $righe[0]['post_id'], null === $righe ? self::SENZA_RISPOSTA : self::PROTETTO );

		return false;
	}
}
