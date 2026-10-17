# Atti scaduti invisibili al pubblico

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: prove scritte, in attesa di revisione.** Requisiti ALBO-05 e ALBO-18, righe di
collaudo A-175..A-178. Nessuna riga di codice dell'albo cambia: la regola c'era già, e qui
si prova dal lato dell'albo che regge davvero.

## In tre paragrafi

**Cosa costruiamo.** Un atto scaduto non si vede più dal sito pubblico, da nessuna strada e
per nessuno: né dal cittadino né dall'addetto collegato che guarda il sito come lo guarda il
cittadino. **Non c'è nessuna impostazione** per tenerlo visibile, nemmeno per un periodo:
è la decisione dell'8 ottobre 2026, presa sapendo che altri albi lasciano gli scaduti
consultabili per anni e che questo è il caso sanzionato. Vale anche per gli atti che
arriveranno da un albo precedente: un atto la cui fine è già passata non entra mai nella
vista pubblica. Chi lavora sull'albo continua a trovare gli scaduti nell'amministrazione,
dove restano con il loro registro; una consultazione riservata dedicata sarà una schermata
dell'amministrazione e non riaprirà l'indirizzo pubblico.

**Come la costruiamo.** L'albo dichiara al meccanismo comune la politica di scadenza
`irraggiungibile`, scritta in una costante e non letta da un'opzione. Il meccanismo comune
applica la scadenza in lettura su ogni strada dell'elenco chiuso (scheda, elenco, ricerca,
feed, mappa per i motori, interfaccia per programmi, anteprima incorporata, precedente e
successivo, interrogazioni di altri componenti, pagina del documento, scaricamento del
documento), guardando la data di fine e non lo stato memorizzato, quindi senza bisogno che
il compito pianificato giri. Oggi il tipo atto è chiuso al pubblico (A-25), e la pagina
pubblica dell'albo non esiste ancora: per questo le prove aprono il tipo come farebbe un tema
o un altro componente, che è anche lo scenario più pericoloso.

**Come si prova che funziona, e come si prova che il test è vero.** Due atti pubblicati con
il passaggio vero, uno con fine il 16 ottobre e uno il 20. All'ultimo secondo del 16 tutti e
due si vedono su ogni strada; dalla mezzanotte il primo non si vede su nessuna e il secondo
su tutte, sia per il visitatore sia per l'amministratore. Il secondo atto è il controllo che
in quell'istante le strade rispondono: senza, un'assenza potrebbe dire soltanto che la strada
era rotta. Spegnendo il filtro del meccanismo comune la prova diventa rossa, perché l'atto scaduto
ricompare sulle strade del filtro: la prova misura il filtro, non la chiusura del tipo.

## Le strade, una per una

| Strada | Come si prova | Chi la copre |
|---|---|---|
| Scheda dell'atto | richiesta vera dell'indirizzo | filtro in lettura (C-10) |
| Elenco del tipo | richiesta vera dell'elenco | filtro in lettura (C-11) |
| Ricerca interna | richiesta vera con le parole dell'oggetto | filtro in lettura (C-12) |
| Feed dell'elenco e della ricerca | richiesta vera del feed | filtro in lettura (C-13) |
| Interfaccia per programmi, elenco e singolo | richiesta all'interfaccia | filtro in lettura (C-15, C-16) |
| Anteprima incorporata | dati dell'anteprima | filtro in lettura (C-17) |
| Pagina del documento | richiesta vera della pagina dell'allegato | filtro in lettura (C-19) |
| Precedente e successivo | vicini di ciascun atto | filtro in lettura (C-20) |
| Interrogazioni di altri componenti | interrogazione sul tipo | filtro in lettura (C-21) |
| Scaricamento del documento | punto pubblico di consegna | consegna degli allegati (C-33) |
| Mappa per i motori | elenco della mappa | indicizzazione vietata (ALBO-06): l'atto manca anche in corso |

Due strade restano fuori dalla prova dell'albo, e lo si dice: l'interfaccia di
pubblicazione remota (XML-RPC), che chiede un accesso autenticato e che il meccanismo comune
prova da sé (C-18), e l'indirizzo con il solo numero del contenuto (`?p=`), che nelle prove non apre un atto
nemmeno in corso, perché WordPress lo risolve solo per gli articoli.

## Che cosa non fa

- **Non costruisce la pagina pubblica** dell'albo (elenco, scheda, ricerca, ALBO-16 e
  ALBO-28). Quando arriverà, le righe A-175 e A-177 si ripetono sulle sue strade.
- **Non costruisce il compito pianificato** che porta l'atto a defisso (ALBO-03) né il
  battito (ALBO-19): la regola non ne dipende, e le prove lo mostrano con lo stato che resta
  pubblicato.
- **Non tocca le memorie di pagina** (ALBO-20): oggi l'albo non ha pagine pubbliche da
  memorizzare, e lo scaricamento esce già con le intestazioni che vietano di conservarlo.
- **Non costruisce la consultazione riservata** degli scaduti per gli addetti.
- **Non costruisce l'importatore**: A-177 prova l'atto scritto come lo scriverà lui.

## Punti da decidere

1. **Cosa vede chi apre l'indirizzo di un atto scaduto.** I requisiti dicono che
   l'indirizzo smette di rispondere, cioè la pagina "non trovata" del sito, identica a quella
   di un indirizzo mai esistito; lo scaricamento del documento fa già così. La mappa di
   compatibilità proponeva, per i vecchi indirizzi dell'albo precedente, una pagina "non più
   in pubblicazione". Proposta: "non trovata" per tutti. Decide l'utente.
2. **Quando costruire la consultazione riservata** degli scaduti per gli addetti: oggi chi ha
   i permessi dell'albo li trova già nell'elenco degli atti dell'amministrazione. Proposta:
   un passo a sé dopo la pagina pubblica. Decide l'utente.

## File toccati

- `tests/ScadutiInvisibiliTest.php`: le quattro righe.
- `docs/requisiti.md`, `docs/collaudo.md`, questa scheda e l'indice delle schede.

## Come si prova su un sito vero

Pubblicare un atto con durata breve, annotare l'indirizzo della scheda e del documento;
il giorno dopo la fine, senza toccare niente, aprire i due indirizzi da una finestra
anonima e da un amministratore collegato: pagina non trovata e documento non trovato.
Cercare l'oggetto nella ricerca del sito: nessun risultato. Nell'amministrazione l'atto c'è.

## Com'è andata davvero

Nessun buco trovato: con il tipo aperto, l'atto scaduto sparisce da ogni strada dalla
mezzanotte, per il visitatore e per l'amministratore, e l'atto importato già scaduto non
compare mai. Tre cose imparate scrivendo le prove, nessuna nel codice dell'albo:

- per un atto scaduto l'anteprima incorporata restituisce un insieme **vuoto** e non
  "niente": nessun dato esce, e la prova guarda la presenza dell'oggetto, non il tipo della
  risposta;
- l'aiuto `richiesta_pubblica()` riparte da un oggetto di WordPress che non conosce la
  variabile dell'indirizzo di un tipo aperto, e la scheda risulterebbe chiusa anche in corso:
  la prova riporta quella variabile, come accade su un sito vero;
- il server dell'interfaccia per programmi va rifatto dopo aver aperto il tipo, altrimenti
  la rotta degli atti non esiste e la strada sembrerebbe chiusa per il motivo sbagliato.
