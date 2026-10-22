# Annullamento di un atto pubblicato

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: costruita, in attesa di revisione.** Requisito ALBO-22 (la transizione da pubblicato ad annullato e la
sotto-decisione 1, chiusa il 2026-10-22), con ALBO-10 per il motivo nel registro. Righe di
collaudo A-224..A-230. Nessuna versione nuova del meccanismo comune. La defissione anticipata
per le cause dell'elenco, la dichiarazione dell'effetto e l'annullamento da defisso sono
l'unità successiva; l'importazione degli annullati ancora in pubblicazione è un passo a sé,
subito dopo questo.

## In tre paragrafi

**Cosa costruiamo.** Chi ha il permesso di annullare (oggi chi pubblica) apre un atto
pubblicato, scrive il motivo e lo annulla. L'atto resta nel registro con il suo numero, la
sua data di inizio e la sua data di fine, e non si modifica né si cancella più: annullato è
uno stato terminale. Il cittadino, **fino alla scadenza**, trova l'atto in elenco segnato
"Annullato"; la scheda mostra numero, oggetto, periodo di pubblicazione, data e motivo
dell'annullamento, e nessun documento. Il documento principale e gli allegati non si
scaricano più, con la stessa risposta "non trovato" dei documenti scaduti. Alla scadenza
l'atto sparisce come tutti gli altri. È la scelta "Solo l'avviso" del 2026-10-22: chi ha già
letto l'atto sa che è stato annullato e perché, e se l'errore era un dato personale di
troppo, il documento smette di girare. **Nessuna impostazione nuova.**

**Come la costruiamo.** Annullato diventa uno stato vero dell'atto, `albo_annullato`,
registrato come stato non pubblico: WordPress non mostra un contenuto in quello stato a chi
non lo gestisce, e il punto pubblico di consegna del meccanismo comune consegna soltanto i
documenti di un atto in stato pubblicato, quindi i documenti dell'annullato si fermano senza
che l'albo debba fare niente, anche se un altro componente ha aperto il tipo. Il passaggio
lo compie `Passaggi`, come gli altri: permesso, motivo, tabelle che annullano, transazione,
scrittura dello stato con il gettone, voce automatica del meccanismo comune (`rimozione`),
voce dell'albo `annullamento` con il motivo, e controllo finale che niente altro sia
cambiato. Il guardiano tratta l'annullato come il pubblicato: fermo per ogni scrittura, per
ogni ingresso e per ogni ruolo. La pagina pubblica mostra l'avviso, perché per lei "in
pubblicazione" vuol dire pubblicato o annullato, con il numero e non scaduto; la scadenza la
decide la data letta, come sempre, e l'elenco la filtra già con la sua condizione sulla data
di fine.

**Come si prova che funziona, e come si prova che il test è vero.** Prove vere sulla
pagina dell'albo e sul punto di consegna, con atti portati a pubblicato e poi annullati dai
passaggi veri, e l'orologio fissato. Ogni assenza ha accanto la sua presenza di controllo:
un atto in corso non annullato, nello stesso istante, si vede con i suoi documenti. Il
rifiuto del documento dell'annullato si confronta per intero con quello del documento di uno
scaduto. Il tutto o niente si prova facendo fallire un pezzo per volta. Guasti introdotti nel
codice per vedere quale prova li vede: elencati sotto, in "Com'è andata davvero".

## I nove punti

### 1. File che si pensa di creare o modificare

| File | Cosa conterrà |
|---|---|
| `includes/class-tipo-atto.php` | la registrazione dello stato `albo_annullato`, non pubblico, con il suo nome e il conteggio nell'elenco degli atti |
| `includes/class-chiusura-pubblicazione.php` | l'annullato fra gli stati fermi e fra quelli che nessuna richiesta di WordPress ottiene; la concessione del passaggio da pubblicato ad annullato, solo al gettone dell'annullamento |
| `includes/class-passaggi.php` | il passaggio `annulla_atto()`, con la stessa transazione e le stesse difese degli altri |
| `includes/class-permessi.php`, `albo-pretorio-pa.php` | il permesso di annullare, distinto da quello di pubblicare, nell'insieme di chi pubblica; la versione del componente, perché il permesso arrivi anche ai siti già installati |
| `includes/class-riquadro-passaggi.php` | sull'atto pubblicato, il campo del motivo e il pulsante "Annulla l'atto"; sull'atto annullato, numero, date, data e motivo in sola lettura |
| `includes/funzioni-api.php` | `albo_pretorio_annulla( $atto_id, $motivo )` |
| `includes/class-pagina-pubblica.php`, `includes/class-vista-pubblica.php` | l'annullato in elenco, nella ricerca e nella scheda, con l'avviso e senza documenti; il tipo che resta chiuso nella richiesta di un suo documento |
| `includes/class-schermata-scaduti.php` | gli annullati scaduti nella consultazione riservata, segnati come tali |
| `includes/class-analisi-importazione.php` | un atto importato e poi annullato resta "già importato" |
| `tests/AnnullamentoTest.php`, `tests/PaginaPubblicaTest.php`, `tests/ConsultazioneScadutiTest.php`, `tests/ImportazioneVeraTest.php` | le righe A-224..A-230 |
| `docs/requisiti.md`, `docs/collaudo.md`, `docs/dati.md`, `docs/architettura.md`, `docs/sicurezza.md`, l'indice | la sotto-decisione chiusa, le righe nuove, lo stato e il permesso |

### 2. Perché

ALBO-22: la transizione da pubblicato ad annullato, con motivazione obbligatoria, l'atto che
conserva numero, stato e motivo, e annullato terminale. ALBO-10: chi, quando e perché nel
registro. ALBO-09: un atto annullato non si modifica e non si cancella. La sotto-decisione 1
di ALBO-22, che cosa veda il pubblico di un annullato prima della scadenza, è chiusa il
2026-10-22 con "Solo l'avviso".

### 3. Il flusso per chi usa il sito

Chi pubblica apre un atto pubblicato. Nel riquadro **Pubblicazione**, sotto numero e date,
c'è "Annulla l'atto": un campo per il motivo e il pulsante. Senza motivo l'avviso lo dice e
l'atto resta pubblicato. Con il motivo l'atto è annullato, e il riquadro mostra da quel
momento numero, date, data e motivo dell'annullamento, in sola lettura. Una correzione resta
un atto nuovo, con il suo numero e la sua durata.

Il cittadino, fino alla mezzanotte del giorno di fine, vede in elenco la riga dell'atto con
la scritta "Annullato" accanto all'oggetto e, al posto del numero dei documenti, "nessuno:
atto annullato". Nella scheda legge numero, oggetto, tipo, organo, numero e data dell'atto,
periodo di pubblicazione, data e motivo dell'annullamento. I dati del procedimento
(responsabile, ufficio, richiedente) non si mostrano più, perché possono contenere nomi di
persona, e i documenti nemmeno. Dalla mezzanotte la riga e la scheda spariscono come per ogni
atto scaduto.

### 4. Dati letti e scritti

Si scrivono: lo stato `albo_annullato`, con il gettone del passaggio, e la voce `annullamento`
del registro delle modifiche con il motivo, chi e quando, e nei dettagli numero, anno e fine
pianificata. La chiave della voce è una per atto. Il meccanismo comune scrive da sé la voce
automatica `rimozione` del cambio di stato. Le date non cambiano: l'inizio resta un fatto e
la fine resta quella pianificata, perché la scadenza dell'avviso è quella dell'atto.

Il motivo e la data dell'annullamento che il pubblico legge vengono dalla voce del registro,
non da un dato dell'atto: il registro è solo in aggiunta, e un dato in più sull'atto sarebbe
una seconda copia da difendere. Se il registro non risponde, la scheda dice che l'atto è
annullato e che il motivo non si legge in questo momento: non tace l'annullamento e non lo
inventa.

### 5. Permessi

Un permesso nuovo, `albo_pretorio_defissione_atto_albo` (chiave `defissione_atti`), che è il "defissione atti" di `dati.md`:
serve all'annullamento ora e alla defissione anticipata poi. Fa parte dell'insieme di chi
pubblica, quindi lo ricevono l'amministratore, il ruolo dell'albo e i ruoli che possiedono
già il permesso di pubblicare, all'installazione e all'aggiornamento (la versione del
componente sale). È distinto dal permesso di pubblicare perché un ente possa riservarlo a un
responsabile senza toccare il codice: chi ha questo permesso e non quello di pubblicare
annulla; chi ha solo quello di pubblicare no.

### 6. Modi di guasto previsti

- Annullamento senza motivo, o con un motivo fatto solo di spazi o di codice.
- Annullamento da chi non ha il permesso, compreso chi pubblica senza il permesso di
  annullare, e il contrario.
- Annullamento di una bozza, di un atto in verifica, di un atto già annullato, di un atto
  portato a pubblicato scavalcando il passaggio (senza numero).
- **Annullato non terminale**: un ritorno a pubblicato, in bozza o in verifica, un
  risalvataggio, il cestino, la cancellazione, un dato, una voce, un documento o la data di
  fine cambiati, da ogni ingresso di WordPress, amministratore compreso.
- **I documenti ancora consegnati**: dal punto pubblico del meccanismo comune, anche con
  l'atto seguito da lettere e con il tipo aperto da un altro componente.
- **L'annullato che non sparisce alla scadenza**: l'elenco, la ricerca e la scheda che lo
  mostrano dopo la mezzanotte del giorno di fine, perché il filtro del meccanismo comune
  guarda solo gli stati pubblicati.
- **L'annullato come dati del procedimento**: richiedente o responsabile ancora mostrati.
- **Un pezzo del passaggio che fallisce e lascia gli altri scritti**: lo stato senza la
  voce, la voce senza lo stato, la voce automatica mancante.
- La proroga per disservizio che allunga un annullato.
- Un atto importato e poi annullato che un rilancio dell'importazione non riconosce più.
- La consultazione riservata che perde gli annullati scaduti.

### 7. Prove che si aggiungeranno

Righe A-224..A-230 in `collaudo.md`: passaggio e permesso in `tests/AnnullamentoTest.php`, pubblico in `tests/PaginaPubblicaTest.php`, consultazione riservata in `tests/ConsultazioneScadutiTest.php`, rilancio dell'importazione in `tests/ImportazioneVeraTest.php`. Le righe di ALBO-22
nella tabella "Requisito per requisito" sull'annullamento e su annullato terminale restano
"da fare" finché non esiste lo stato defisso: oggi coprono la parte che parte da pubblicato.

### 8. Cosa NON si implementa

- La defissione anticipata, le sue cause, la dichiarazione dell'effetto e l'annullamento da
  defisso: sono l'unità successiva.
- L'importazione degli annullati ancora in pubblicazione: passo a sé, subito dopo.
- Il referto (ALBO-07).
- **I documenti di un annullato per chi ha soltanto il permesso di consultare gli scaduti.**
  La consegna amministrativa del meccanismo comune apre a quel permesso solo i documenti
  degli atti in stato pubblicato. Chi gestisce gli atti (amministratore e ruolo dell'albo)
  li apre comunque; per gli altri la schermata lo dice. Aprirli a tutti chiede una modifica
  del meccanismo comune, insieme allo stato defisso.

### 9. Come si prova sul sito vero

Su un atto pubblicato di prova: annullarlo senza motivo, poi con il motivo. Aprire la pagina
dell'albo da una finestra anonima: la riga dice "Annullato", la scheda mostra il motivo e
nessun documento; incollare l'indirizzo di un documento copiato prima: "non trovato".
Rileggere il registro delle modifiche. Il giorno dopo la fine, l'atto non c'è più.

## Com'è andata davvero

Le sette righe sono verdi, insieme alle prove che c'erano già (resta rossa solo in locale
A-193, per un motivo dell'ambiente che su GitHub non si presenta). Rispetto al piano:

- **Il nome del permesso** è `albo_pretorio_defissione_atto_albo` e non
  `albo_pretorio_defissione_atti`: contiene il nome del tipo come gli altri permessi
  dell'albo, così che la pulizia dei permessi, che toglie solo quelli che nominano il tipo,
  lo trovi senza un caso a parte. La versione del componente è salita a 0.6.0-alpha, e il permesso arriva ai siti già
  installati con l'aggiornamento (A-230).
- **Il passaggio si chiama `annulla_atto()`**: `Passaggi` aveva già un `annulla()` privato,
  quello che riporta indietro una transazione fallita.
- **Ogni rifiuto per guasto** ha in testa il codice `albo_atto_non_annullato`, con il
  messaggio che l'atto è rimasto pubblicato e com'era, e dopo il codice del pezzo che è
  fallito (A-229).
- **Nell'elenco pubblico** la scritta "Annullato" sta subito dopo l'oggetto, prima
  dell'organo, perché si legga insieme a quello che annulla.
- **La consultazione riservata** elenca gli annullati scaduti con la data e il motivo
  dell'annullamento. I documenti si aprono a chi gestisce gli atti; a chi ha il solo
  permesso di consultare la schermata lo dice invece di mostrare un collegamento che la
  consegna rifiuterebbe.
- **L'importazione** riconosce come già suo un atto importato e poi annullato: senza
  questo, togliendo il riconoscimento, la prova del rilancio fallisce (contati sei importati
  invece di sette), ed è stato verificato introducendo il guasto apposta.
