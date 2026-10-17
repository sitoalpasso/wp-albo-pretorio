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

Su ogni strada la prova guarda due cose diverse. Per l'atto in corso, che la strada lo
consegni davvero, riconosciuto per numero e oggetto. Per lo scaduto, che non ne esca
**niente**: né il contenuto fra i risultati, né un'anteprima senza titolo ma non vuota, né
una risposta dell'interfaccia diversa dal rifiuto o che porti l'oggetto, né uno
scaricamento diverso dal rifiuto di un indirizzo inesistente. Guardare per lo scaduto la
stessa cosa che si guarda per l'atto in corso lascerebbe passare un'esposizione parziale.

| Strada | Come si prova | Chi la copre |
|---|---|---|
| Scheda dell'atto | richiesta vera dell'indirizzo, dell'indirizzo con numero e tipo e della versione da incorporare (`embed=true`) | filtro in lettura (C-10) |
| Elenco del tipo | richiesta vera dell'elenco, anche per anno e per autore | filtro in lettura (C-11) |
| Ricerca interna | richiesta vera con le parole dell'oggetto | filtro in lettura (C-12) |
| Feed dell'elenco e della ricerca | richiesta vera del feed | filtro in lettura (C-13) |
| Interfaccia per programmi, elenco, singolo e ricerca | richiesta all'interfaccia | filtro in lettura (C-15, C-16) |
| Documenti dell'atto nell'interfaccia per programmi | elenco filtrato per atto | filtro in lettura |
| Scheda del singolo documento nell'interfaccia per programmi | **non provata**: resta aperta, vedi in fondo | meccanismo comune, da correggere |
| Anteprima incorporata dell'atto | dati dell'anteprima e richiesta all'interfaccia `/oembed/1.0/embed` | filtro in lettura (C-17) |
| Anteprima incorporata del documento | **non provata**: resta aperta, vedi in fondo | meccanismo comune, da correggere |
| Feed generale dei commenti, feed della ricerca con i commenti, commenti nell'interfaccia | **non provati**: restano aperti, vedi in fondo | meccanismo comune, da correggere |
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

Sulle strade dell'elenco chiuso nessun buco: con il tipo aperto, l'atto scaduto sparisce
dalla mezzanotte, per il visitatore e per l'amministratore, e l'atto importato già scaduto
non compare mai. Alle strade dell'elenco chiuso la prova aggiunge l'indirizzo con numero e
tipo, l'elenco per anno, quello per autore, la ricerca dell'interfaccia per programmi e
l'elenco dei documenti di un atto nella stessa interfaccia: chiuse anche loro.

**Un buco vero, nel meccanismo comune e non nell'albo.** La scheda del singolo documento
nell'interfaccia per programmi (`/wp/v2/media/<numero>`) risponde ancora dopo la scadenza,
con il nome del file caricato, l'atto a cui appartiene e i suoi indirizzi. Il file non
esce: il suo indirizzo è quello della consegna, che lo rifiuta. Con il tipo chiuso, come
oggi, la risposta arriva solo a chi può modificare gli atti, e al visitatore arriva un
rifiuto che dice "non autorizzato" invece di "non trovato"; con il tipo aperto da un altro
componente arriva a chiunque. Il nome del file è il punto delicato, perché è quello che ha
scelto chi ha caricato il documento e può contenere un nome di persona. La correzione sta
nel filtro di scadenza del meccanismo comune, che per i documenti deve guardare la scadenza
dell'atto a cui appartengono anche su questa strada; qui non si tocca, e A-175 aggiungerà la
strada quando il meccanismo comune la chiuderà.

**Altri due buchi della stessa famiglia, trovati dalla revisione del primo giro.** Tutti e
due nel meccanismo comune, tutti e due rifatti a mano prima di scriverli qui:

- l'**anteprima incorporata del documento** (`/oembed/1.0/embed` con l'indirizzo della pagina
  del documento) risponde dopo la scadenza con il nome del file e il codice da incorporare.
  La causa è la stessa del punto sopra: per un documento il filtro guarda il documento e non
  risale all'atto;
- i **commenti di un atto scaduto** escono ancora dal feed generale dei commenti, dal feed
  della ricerca con i commenti e dall'elenco dei commenti dell'interfaccia per programmi.
  Il filtro toglie l'atto dai risultati, ma i commenti si leggono con un'interrogazione a
  parte. Sull'albo il caso è remoto: gli atti non hanno i commenti, e un commento non si
  scrive su un atto pubblicato perché la barriera lo rifiuta. Un commento ci arriva solo
  scritto direttamente nella banca dati, per esempio da un importatore che li porti con sé.
  Nella prova l'ho dovuto scrivere scavalcando la barriera.

**Una piccola differenza, trovata con la revisione del secondo giro.** Quando l'interfaccia per
programmi rifiuta un atto scaduto, il rifiuto non porta niente dell'atto, ma ha un codice
diverso da quello di un numero mai esistito. Chi prova i numeri uno per uno può quindi capire
che dietro un numero c'era un atto, senza vederne niente. Anche questo sta nel meccanismo
comune, e la prova dell'albo si limita a pretendere il rifiuto esatto del meccanismo comune.

**Una cosa vista di passaggio, che non riguarda la scadenza.** Con il tipo chiuso, sui siti
dove le pagine degli allegati sono accese, la pagina del documento di un atto **in corso**
si apre al visitatore (`?attachment_id=<numero>`): mostra il nome del file, mentre il file
resta rifiutato dalla consegna. Dalla mezzanotte della fine si chiude anche lei. Riguarda la
chiusura del tipo (A-25) e va in un passo a sé.

Quattro cose imparate scrivendo le prove, nessuna nel codice dell'albo:

- per un atto scaduto l'anteprima incorporata restituisce un insieme **vuoto** e non
  "niente": nessun dato esce, e la prova guarda la presenza dell'oggetto, non il tipo della
  risposta;
- l'aiuto `richiesta_pubblica()` riparte da un oggetto di WordPress che non conosce la
  variabile dell'indirizzo di un tipo aperto, e la scheda risulterebbe chiusa anche in corso:
  la prova riporta quella variabile, come accade su un sito vero;
- un'osservazione negativa non deve dipendere dallo stato della risposta: un "non trovato"
  che porta nel corpo il numero dell'atto esporrebbe comunque qualcosa, e la prova lo cerca
  in ogni risposta, con una prova a parte che guasta l'interfaccia apposta per vederlo;
- il server dell'interfaccia per programmi va rifatto dopo aver aperto il tipo, altrimenti
  la rotta degli atti non esiste e la strada sembrerebbe chiusa per il motivo sbagliato.
