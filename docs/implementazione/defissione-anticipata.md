# Defissione anticipata di un atto pubblicato

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: costruita, in attesa di revisione.** Requisito ALBO-22 (la transizione da pubblicato a defisso
disposta da chi pubblica, la sotto-decisione 4 sulle cause, chiusa il 2026-09-22, e la
transizione da defisso ad annullato), con ALBO-10 per causa e motivo nel registro e ALBO-09
per il defisso che non si tocca più. Righe di collaudo A-235..A-242. Nessuna versione nuova
del meccanismo comune. La dichiarazione dell'effetto sul periodo e il compito pianificato
che registra le scadenze naturali sono le unità successive.

## In tre paragrafi

**Cosa costruiamo.** Chi ha il permesso di annullare e defiggere (oggi chi pubblica) apre
un atto pubblicato e non ancora scaduto, sceglie una delle quattro cause per cui la
pubblicazione non doveva esserci in quella forma, scrive il motivo e lo toglie dall'albo.
L'atto sparisce dalla vista pubblica **subito**, non da stanotte: elenco, scheda, ricerca e
documenti rispondono come per un atto scaduto. Resta nel registro con il suo numero e la sua
data di inizio; la fine pianificata non si perde, perché finisce nel registro insieme alla
causa, al motivo, a chi ha deciso e a quando. Le cause sono quelle che la lettura delle fonti
del 2026-09-22 dice pacifiche: dati sulla salute in un documento che non si può oscurare,
nessuna norma che prescriva l'affissione di quell'atto, dati inesatti o non aggiornati,
ordine di un'autorità. Accorciare il termine di un atto regolare non è fra queste e non si
può fare (art. 124 del TUEL). Un atto defisso si può ancora annullare, con il suo motivo.
**Nessuna impostazione nuova.**

**Come la costruiamo.** Defisso diventa uno stato vero dell'atto, `albo_defisso`,
registrato come stato non pubblico, come l'annullato. Ma lo stato **non è** ciò che fa
sparire l'atto: il passaggio riporta la data di fine al giorno **prima** di quello in cui la
defissione è disposta, e da lì l'atto è scaduto per la ragione di sempre, cioè la data
letta. Scrivere la data di oggi lo lascerebbe visibile fino a mezzanotte. Il passaggio è in
`Passaggi`, come gli altri: permesso, causa, motivo, tabelle che annullano, transazione,
scrittura della fine, scrittura dello stato con il gettone, voci automatiche del meccanismo
comune, voce dell'albo `defissione_anticipata`, revoca delle concessioni e controllo finale
che niente altro sia cambiato. Il guardiano tratta il defisso come il pubblicato e
l'annullato: fermo per ogni scrittura, per ogni ingresso e per ogni ruolo; l'unica uscita è
l'annullamento.

**Come si prova che funziona, e come si prova che il test è vero.** Prove vere con atti
portati a pubblicato dai passaggi veri e l'orologio fissato. L'atto rimosso si cerca sulla
pagina pubblica e sul punto di consegna dei documenti un istante dopo la defissione, accanto
a un atto in corso che si vede con i suoi documenti. Una riga d'attacco riporta lo stato a
pubblicato scrivendolo dritto nella banca dati e verifica che l'atto resti invisibile: la
sparizione dipende dalla data, non dallo stato. Il rifiuto sull'atto già scaduto si prova su
un atto ancora pubblicato nella banca dati, così che non arrivi dal divieto di passare da
defisso a defisso. Il tutto o niente si prova facendo fallire un pezzo per volta. Guasti
introdotti nel codice per vedere quale prova li vede: elencati sotto, in "Com'è andata
davvero".

## I nove punti

### 1. File che si pensa di creare o modificare

| File | Cosa conterrà |
|---|---|
| `includes/class-tipo-atto.php` | la registrazione dello stato `albo_defisso`, non pubblico, con il suo nome e il conteggio nell'elenco degli atti |
| `includes/class-chiusura-pubblicazione.php` | il defisso fra gli stati fermi; la concessione del passaggio da pubblicato a defisso al solo gettone della defissione, e da defisso ad annullato al solo gettone dell'annullamento |
| `includes/class-passaggi.php` | il passaggio `defiggi_atto()` e le quattro cause; l'annullamento che parte anche da defisso |
| `includes/class-riquadro-passaggi.php` | sull'atto pubblicato e non scaduto, le quattro cause senza nessuna scelta fatta, il motivo e il pulsante "Togli dall'albo"; sull'atto defisso, causa, motivo, data, fine pianificata e annullamento |
| `includes/funzioni-api.php` | `albo_pretorio_defiggi( $atto_id, $causa, $motivo )` |
| `includes/class-schermata-scaduti.php` | i defissi nella consultazione riservata, segnati come tali |
| `includes/class-analisi-importazione.php` | un atto importato e poi defisso resta "già importato" |
| `tests/DefissioneTest.php`, `tests/PaginaPubblicaTest.php`, `tests/ScadutiInvisibiliTest.php`, `tests/ConsultazioneScadutiTest.php`, `tests/ImportazioneVeraTest.php` | le righe A-235..A-242 |
| `docs/requisiti.md`, `docs/collaudo.md`, `docs/dati.md`, `docs/architettura.md`, l'indice | le righe nuove, lo stato e la voce del registro |

### 2. Perché

ALBO-22: la transizione da pubblicato a defisso disposta da chi pubblica, con motivazione
obbligatoria, solo su un atto non ancora scaduto; la sotto-decisione 4 (la causa è scelta
da un elenco chiuso, accorciare il termine di un atto regolare non è una funzione del
componente); la transizione da defisso ad annullato. ALBO-10: chi, quando, perché e la fine
pianificata riportata indietro, nel registro. ALBO-09: un atto defisso non si modifica e non
si cancella.

### 3. Il flusso per chi usa il sito

Chi pubblica apre un atto pubblicato e non ancora scaduto. Nel riquadro **Pubblicazione**,
sotto "Annulla l'atto", c'è "Togli dall'albo prima della scadenza": una spiegazione di una
riga (si toglie solo se la pubblicazione non doveva esserci in quella forma; il termine di
un atto regolare non si accorcia), le quattro cause con nessuna scelta fatta, il campo del
motivo e il pulsante. Senza causa o senza motivo l'avviso lo dice e l'atto resta pubblicato.
Con causa e motivo l'atto è tolto; il riquadro mostra da quel momento numero, inizio, fine
pianificata, data della defissione, causa e motivo, in sola lettura, e sotto "Annulla
l'atto" per chi deve anche annullarlo. Sull'atto già scaduto il modulo non c'è: è già
invisibile.

Il cittadino non trova più l'atto, dall'istante della defissione: niente riga in elenco o
nella ricerca, la scheda risponde come un atto mai esistito, i documenti come quelli di un
atto scaduto. Se poi l'atto viene annullato resta invisibile: l'avviso dell'annullato si
mostra fino alla data di fine, e la data di fine è già passata.

### 4. Dati letti e scritti

Si scrivono: la data di fine, con la funzione del meccanismo comune, uguale al giorno civile
**precedente** a quello della defissione nell'ora del sito; lo stato `albo_defisso`, con il
gettone del passaggio; la voce `defissione_anticipata` del registro delle modifiche, con il
motivo ripulito, chi e quando, e nei dettagli la causa, numero, anno, la fine pianificata e
la fine scritta. La chiave della voce è una per atto. Il meccanismo comune scrive da sé la
voce automatica del cambio di stato (`rimozione`) e quella della data di fine
(`modifica_fine_pubblicazione`). L'inizio non cambia.

Causa, motivo e data della defissione si rileggono da quella voce, non da un dato
dell'atto, come per l'annullamento: `dati.md` diceva "metadato più voce di registro", e
diventa "solo voce di registro", per la stessa ragione (il registro è solo in aggiunta, una
seconda copia sull'atto sarebbe un dato in più da difendere). Se il registro non risponde,
il riquadro e la consultazione dicono che l'atto è defisso e che causa e motivo non si
leggono in questo momento.

Un atto pubblicato oggi e defisso oggi ha la fine scritta al giorno prima del suo inizio: è
la conseguenza della forma provvisoria del giorno civile (sotto-decisione 3), e il registro
dice il fatto esatto. Il riquadro e la consultazione mostrano la data della defissione
accanto al periodo, così che non si legga un periodo impossibile senza spiegazione.

### 5. Permessi

Nessun permesso nuovo: è `albo_pretorio_defissione_atto_albo` (chiave `defissione_atti`),
già assegnato con l'annullamento e già pensato per questo. Chi ha questo permesso e non
quello di pubblicare toglie un atto; chi ha solo quello di pubblicare no. Chi disponga la
defissione anticipata resta la sotto-decisione 2, aperta: qui è chi pubblica **in via
provvisoria**, e il permesso separato permette di riservarla a un responsabile senza toccare
il codice.

### 6. Modi di guasto previsti

- Defissione senza causa, con una causa fuori elenco (la sola volontà dell'amministrazione,
  una causa scritta a mano, un valore che non è testo), senza motivo o con un motivo fatto
  solo di spazi o di codice.
- Defissione da chi non ha il permesso, compreso chi pubblica senza il permesso di
  defiggere, e il contrario.
- Defissione di una bozza, di un atto in verifica, di un annullato, di un defisso, di un atto
  senza numero, di un atto **già scaduto ma ancora pubblicato nella banca dati**.
- **La data di oggi al posto di quella di ieri**: l'atto resterebbe visibile fino a
  mezzanotte.
- **La sparizione affidata allo stato**: riportato lo stato a pubblicato, l'atto tornerebbe
  visibile.
- **Defisso non fermo**: un ritorno a pubblicato, in bozza o in verifica, un risalvataggio,
  il cestino, la cancellazione, un dato, una voce, un documento o la data di fine cambiati,
  da ogni ingresso di WordPress, amministratore compreso; la proroga per disservizio che lo
  allunga.
- **Un pezzo del passaggio che fallisce e lascia gli altri scritti**: la fine senza lo stato,
  lo stato senza la voce, una voce automatica mancante; un altro componente che riscrive la
  data di fine dopo le scritture.
- L'annullamento che non parte da defisso, o che riporta l'atto in vista.
- Un atto importato e poi defisso che un rilancio dell'importazione non riconosce più.
- La consultazione riservata che perde i defissi.

### 7. Prove che si aggiungeranno

Righe A-235..A-242 in `collaudo.md`: passaggio, permesso, cause, guardiano e annullamento da
defisso in `tests/DefissioneTest.php`, la pagina pubblica in `tests/PaginaPubblicaTest.php`,
la riga d'attacco su ogni strada in `tests/ScadutiInvisibiliTest.php`, consultazione riservata in
`tests/ConsultazioneScadutiTest.php`, rilancio dell'importazione in
`tests/ImportazioneVeraTest.php`. Le righe di ALBO-22 nella tabella "Requisito per
requisito" sulla defissione anticipata, sulla riga d'attacco dello stato, sulla causa fuori
elenco e sull'annullamento da tutti e due gli stati passano a "fatto" e rinviano qui.

### 8. Cosa NON si implementa

- **La dichiarazione dell'effetto sul periodo** ("vale per il periodo trascorso" o "va
  rifatta"): è l'unità successiva, con le sue righe già scritte. Fino ad allora la
  defissione riesce senza, come le righe prescrivono: la rimozione non la aspetta.
- **Il compito pianificato** che porta a defisso gli atti scaduti per la loro data: unità a
  sé. Fino ad allora un atto scaduto resta "pubblicato" nella banca dati, ed è invisibile lo
  stesso, perché la visibilità non dipende dallo stato.
- **Cause aggiunte dall'amministrazione per configurazione.** Le fonti non dicono che l'elenco
  sia tassativo, e la nota di ALBO-22 lo dice ampliabile; ma finché nessuno chiede una causa
  in più, l'elenco resta quello delle quattro cause lette nelle fonti, che non dipendono
  dall'ente. Il caso dei dati eccedenti non è una rimozione: è la sostituzione per
  oscuramento di ALBO-12.
- Il referto (ALBO-07) e la memoria di pagina (ALBO-20).
- **I documenti di un defisso per chi ha soltanto il permesso di consultare gli scaduti**:
  come per l'annullato, la consegna amministrativa del meccanismo comune apre a quel permesso
  solo i documenti degli atti in stato pubblicato. Chi gestisce gli atti li apre; per gli
  altri la schermata lo dice.

### 9. Come si prova sul sito vero

Su un atto pubblicato di prova: toglierlo senza causa, poi con una causa e senza motivo,
poi con tutti e due. Aprire subito la pagina dell'albo da una finestra anonima: l'atto non
c'è, la sua scheda e i suoi documenti rispondono "non trovato". Rileggere il registro delle
modifiche: causa, motivo, fine pianificata. Annullare poi lo stesso atto: resta invisibile.

## Com'è andata davvero

Il piano ha retto. Le differenze e le cose imparate:

- **Il meccanismo comune registra l'uscita da defisso come "cambio di stato".** La voce
  automatica `rimozione` la scrive solo quando lo stato di partenza è pubblicato: annullando
  un defisso scrive `cambio_stato`. L'annullamento controlla quindi la voce giusta per lo
  stato da cui parte.
- **Il rifiuto generico del guardiano non nominava il defisso.** Una scrittura fermata da
  `wp_publish_post()` diceva "in verifica, pubblicato, annullato": ora dice anche "tolto
  dall'albo". La prova A-239 lo pretende per ogni ingresso.
- **La riga d'attacco ha bisogno di scavalcare la barriera.** Riscrivere lo stato dritto
  nella banca dati, da dentro WordPress, è fermato dalla barriera dell'albo (ed è giusto:
  A-239 lo prova). Per simulare un altro programma, A-236 stacca la barriera per quella sola
  istruzione, come le altre prove d'attacco.
- **Limite trovato e non chiuso qui.** Su un sito dove un altro componente apre il tipo
  atto al pubblico, il meccanismo comune toglie di mezzo lo scaduto solo se lo stato è
  pubblicato. Per un atto defisso, o annullato e scaduto, WordPress risponde al visitatore
  "non hai il permesso" invece di "non trovato" su alcune strade dell'interfaccia per
  programmi (dice che l'atto esiste, senza mostrarne niente), e all'amministratore consegna
  da quell'interfaccia documenti e commenti dell'atto. Con il tipo chiuso, com'è
  nell'albo, il caso non si presenta. Riguarda già l'annullato, quindi non nasce qui: va
  chiuso nel meccanismo comune, facendo valere la data di fine per ogni stato del tipo. A-236
  prova per intero il caso dello stato riscritto a pubblicato, e per lo stato defisso solo
  che niente si consegna al visitatore e a chi consulta gli scaduti.

Guasti introdotti nel codice per vedere quale prova li vede, tutti tolti:

- la data di fine scritta a oggi invece che a ieri: falliscono A-235 (da codice e sulla
  pagina pubblica), A-236, A-238, A-239, A-240 e A-241;
- il defisso tolto dagli stati riconosciuti dall'importazione: fallisce A-242 sul rilancio.
