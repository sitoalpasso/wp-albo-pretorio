# Consultazione riservata degli atti scaduti

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: costruita, in attesa di revisione.** Requisito ALBO-34, righe di collaudo
A-180..A-185. Richiede la versione 1.7.0 del meccanismo comune.

## In tre paragrafi

**Cosa costruiamo.** Una schermata dell'amministrazione, "Atti scaduti", dove chi ne ha il
permesso trova gli atti la cui pubblicazione è finita: numero di registro, oggetto, tipo,
organo, numero proprio, data di adozione, dati del procedimento, date della pubblicazione e
documenti, che si aprono. Si cerca per anno e numero, per oggetto e per tipo. È in sola
lettura: da qui non si prepara, non si pubblica e non si modifica niente. Sul sito pubblico
non cambia niente: l'atto scaduto resta "non trovato" per tutti, anche per chi ha questo
permesso.

**Come la costruiamo.** Il permesso lo crea il meccanismo comune, uno per tipo, e non lo
assegna a nessuno; l'albo lo dà all'amministratore e al proprio ruolo, con lo stesso
meccanismo ripetibile degli altri permessi, e non lo mette fra quelli di chi pubblica: un
altro ruolo che pubblica non lo riceve da sé, glielo dà l'amministrazione (decisione del 17
ottobre 2026, "permesso apposito"). La schermata legge gli scaduti dalla data di fine, come
il meccanismo comune: fine in un giorno già passato nel fuso del sito, quindi anche se il
compito pianificato non è mai partito, atti importati compresi; ogni atto trovato si
ricontrolla con il meccanismo comune. I documenti si aprono dalla consegna amministrativa
del meccanismo comune, che dalla versione 1.7.0 li consegna anche a chi ha soltanto il
permesso di consultare, in sola lettura e solo per gli atti pubblicati. Chi lavora sugli atti
trova la voce nel menu degli atti; chi consulta e basta non vede quel menu, e la trova in
cima al menu dell'amministrazione.

**Come si prova che funziona, e come si prova che il test è vero.** Due atti con fine il 16
e il 20 ottobre 2041 e un atto importato con fine il 30 settembre: all'ultimo secondo del 16
la schermata mostra solo l'importato, dalla mezzanotte anche il primo, il secondo mai. Chi
non ha il permesso riceve il rifiuto; chi lo ha soltanto apre il documento dello scaduto
seguendo il collegamento della schermata, e lo stesso collegamento è rifiutato a chi non ha
il permesso. Mutazioni provate: con la condizione sulla fine rovesciata quattro righe su
cinque diventano rosse; senza il controllo del permesso o senza l'aggancio del menu A-182 diventa rossa; ignorando
la pagina chiesta o perdendo la ricerca nei collegamenti A-185 diventa rossa; ignorando il
tipo o accettando solo nove cifre A-184 diventa rossa. Che la
consultazione non riapra il sito pubblico lo provano A-175, A-177 e A-179, ripetute per chi
ha soltanto il permesso.

## Che cosa non fa

- **Non costruisce il certificato di pubblicazione** (ALBO-07): resta bloccato finché non si
  conferma cosa deve contenere secondo il regolamento dell'ente. Quando arriva, si aggiunge a
  questa schermata.
- **Non mostra proroghe e festivi saltati** riga per riga: la fine mostrata li comprende già,
  e il dettaglio sta nel registro delle modifiche.
- **Non dà una schermata per assegnare il permesso ad altri ruoli**: oggi si assegna con gli
  strumenti di gestione dei ruoli che il sito già usa. Se servisse, è un passo a sé.
- **Non tiene un registro di chi consulta cosa.** La bacheca di carta non lo prevedeva e
  nessuno l'ha chiesto.
- **Considera soltanto gli atti in stato pubblicato.** Quando arriverà lo stato "defisso"
  (ALBO-03) la schermata e la consegna amministrativa del meccanismo comune andranno estese
  a quello stato, insieme.

## Decisioni prese

1. **Si costruisce subito**, come passo a sé dopo gli atti scaduti invisibili (17 ottobre
   2026).
2. **Permesso apposito** (17 ottobre 2026): lo hanno l'amministratore e il ruolo dell'albo,
   l'ente può darlo ad altri; chi ce l'ha soltanto consulta e basta.
3. **Un permesso per tipo**, deciso nel meccanismo comune (18 ottobre 2026).

## File toccati

- `includes/class-schermata-scaduti.php`: la schermata, la ricerca, l'elenco.
- `includes/class-permessi.php`: il nome generico del permesso e la sua assegnazione.
- `includes/class-repertorio.php`: la ricerca degli atti per anno e numero.
- `albo-pretorio-pa.php`: versione 0.5.0-alpha, che fa arrivare il permesso nuovo ai siti
  già installati, e interfaccia del meccanismo comune 1.7.0; `bin/installa-core.sh` fissa la
  revisione 3b33771.
- `tests/ConsultazioneScadutiTest.php`: le sei righe; `tests/ScadutiInvisibiliTest.php`:
  chi consulta aggiunto alle strade pubbliche, e la sentinella di A-178 che autorizza i due
  nomi del permesso.
- `docs/requisiti.md`, `docs/collaudo.md`, questa scheda e l'indice delle schede.

## Come si prova su un sito vero

Creare un utente con un ruolo che abbia soltanto il permesso di consultare. Pubblicare un
atto con durata breve; il giorno dopo la fine, senza toccare niente, entrare con quell'utente:
la voce "Atti scaduti" è in cima al menu, l'atto c'è, il documento si apre. Con un
amministratore la voce è nel menu degli atti. Da una finestra anonima e da quell'utente,
l'indirizzo pubblico dell'atto risponde "pagina non trovata".

## Com'è andata davvero

Il lavoro si è diviso fra due repository. Nel meccanismo comune sono serviti il permesso e
l'apertura dei documenti a chi consulta e basta (versione 1.7.0): senza, chi aveva soltanto
il permesso avrebbe visto l'elenco ma non i documenti, perché la consegna amministrativa li
apriva solo a chi può modificare gli atti. Dal lato dell'albo una cosa da notare: la
sentinella di A-178, che segnala ogni nome di opzione o dato che parli di scaduti, ha
riconosciuto subito il nome del permesso nuovo. È il comportamento voluto: il nome è stato
autorizzato a mano, con la sua ragione, e qualunque altro nome del genere resta segnalato.

**Il primo giro di revisione** ha trovato una cosa nel codice e quattro nelle prove. Nel
codice: un numero di dieci cifre, che il registro ammette, veniva scartato in silenzio e la
ricerca mostrava l'intero anno; ora un campo scritto male ferma la ricerca con un messaggio.
Nelle prove: il rifiuto all'abbonato arrivava dal gettone e non dal permesso; il menu era
costruito a mano invece che dall'aggancio; le pagine e i tipi non erano provati con atti
abbastanza diversi; bozze e privati non erano provati. Tutto sistemato, con le mutazioni
qui sopra.

**Il secondo giro** ha trovato un caso rimasto fuori: un oggetto mandato come elenco invece che
come testo veniva preso per vuoto, e la ricerca mostrava tutti gli scaduti. Ora anche l'oggetto
scritto male ferma la ricerca con il messaggio, e A-184 lo prova.

