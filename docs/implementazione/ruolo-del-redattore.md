# Ruolo del redattore

## In tre paragrafi

Fino alla versione 0.7.0-alpha l'albo creava un solo ruolo proprio, Responsabile della
pubblicazione all'albo, che prepara e pubblica. Chi doveva soltanto preparare gli atti non
aveva un ruolo pronto: l'amministrazione doveva assegnare i permessi di redazione a mano, con
un programma esterno o da riga di comando. La separazione fra chi prepara e chi pubblica
(ALBO-22) dipendeva così da chi installa.

Dalla versione 0.8.0-alpha l'albo crea un secondo ruolo proprio, **Redattore dell'albo**, con
l'insieme di chi redige e nient'altro: scrive le bozze, carica i documenti e le manda in
verifica, ma non pubblica, non legge il registro delle modifiche, non annulla né toglie un
atto, non consulta gli scaduti e non tocca lo storico pubblico. Nessun utente lo riceve da
sé: lo assegna l'amministrazione dal menu Utenti.

Il ruolo nasce **una volta sola**: all'installazione e all'aggiornamento da una versione che
non lo aveva. Un'amministrazione che lo toglie non se lo ritrova al prossimo aggiornamento.
Come il primo ruolo si riconosce dal marcatore del componente, e un ruolo omonimo di un
altro componente non si adotta: il componente resta inerte con un avviso che lo nomina.
Righe di collaudo A-286..A-289.

**Gli scaduti restano riservati anche in amministrazione.** Chi non ha né il permesso di
consultare gli scaduti né quello di pubblicare, cioè chi redige soltanto, non apre, non
legge e non cancella un atto pubblicato, annullato o tolto dall'albo la cui fine è passata,
non ne legge i documenti e non lo trova nell'elenco degli atti. La scadenza è quella del
meccanismo comune, letta nell'istante: una data di fine scritta male, di un giorno che non
esiste o registrata due volte vale come passata. Chi pubblica conserva i passaggi che
partono da un atto scaduto, come l'annullamento. Vale per il redattore e per ogni ruolo che
ha soltanto l'insieme di chi redige. Righe A-290 e A-291. La consegna amministrativa dei
documenti è del meccanismo comune, che dalla sua versione con il controllo sul singolo
contenuto chiede gli stessi permessi.

## File toccati

| File | Perché |
|---|---|
| `albo-pretorio-pa.php` | la costante `RUOLO_REDATTORE` e la versione 0.8.0-alpha |
| `includes/class-permessi.php` | i ruoli propri diventano due; prima si controllano tutti per un omonimo di altri, poi si creano quelli mancanti, ciascuno con la verifica del marcatore; `azzera()` toglie anche il redattore |
| `includes/class-installazione.php` | la nascita del redattore si esclude a chi aggiorna da 0.8.0-alpha in poi, come i permessi che arrivano una volta sola |
| `includes/class-scaduti-riservati.php` | lo scaduto in amministrazione: `map_meta_cap` per l'atto e i suoi documenti, `posts_where` per l'elenco degli atti |
| `tests/RuoloRedattoreTest.php`, `tests/ScadutiRiservatiTest.php`, `tests/ambiente-albo.php` | le prove e la pulizia del secondo ruolo; in `tests/DefissioneTest.php` A-239 prende atto che chi redige soltanto non apre più l'atto tolto dall'albo |

## Modi di guasto previsti

- **Un ruolo omonimo di altri.** Il controllo guarda tutti e due i ruoli prima di crearne
  uno: trovato l'omonimo dopo aver creato il primo, il sito resterebbe con un ruolo nostro e
  nessun permesso assegnato (A-287).
- **Il marcatore che non si scrive.** Il ruolo creato a metà si toglie e la rimozione si
  rilegge; la versione non si memorizza e la richiesta dopo riprova (A-289).
- **Il ruolo tolto dall'amministrazione.** Non torna: la nascita è esclusa da 0.8.0-alpha in
  poi. Un redattore che c'è resta com'è, permessi aggiunti a mano compresi, e riceve quelli
  di chi redige che gli mancano (A-288).

## Cosa non fa

Non assegna il ruolo a nessuno e non toglie a chi pubblica i permessi di redazione. Non cambia
l'insieme di chi redige. I conteggi accanto agli stati, in cima all'elenco degli atti,
contano anche gli scaduti: dicono quanti sono, non quali.

## Come si prova su un'installazione reale

Dopo l'aggiornamento, nel menu Utenti, scegliere un utente e assegnargli il ruolo Redattore
dell'albo. Entrare con quell'utente: si vedono gli atti, si crea una bozza, si manda in
verifica con "Invia per la revisione"; il riquadro Pubblicazione non compare e la voce
della proroga non c'è. Nell'elenco degli atti non compare un atto la cui pubblicazione è
finita, e il suo indirizzo di modifica risponde che non si ha il permesso.
