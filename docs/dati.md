# Dati: cosa esiste, dove sta, chi può toccarlo

Questo documento elenca i dati che il plugin gestisce e risponde alle domande di
controllo: dove viene salvata ogni informazione, cosa succede quando cambia, chi ha il
permesso di cambiarla. I nomi tecnici precisi (chiavi dei metadati, nomi delle tabelle)
vengono fissati dalle schede in `docs/implementazione/` man mano che le parti vengono
costruite; questo file tiene la mappa d'insieme e va aggiornato nello stesso commit che
introduce un dato nuovo.

## L'atto

Un atto è un contenuto WordPress di tipo dedicato (`atto_albo`), con questi dati. Il nome
non è `atto` e basta: da quell'identificativo il meccanismo comune ricava i nomi dei
permessi, e un nome generico è un nome che un altro componente può avere già preso, nel
qual caso la registrazione verrebbe rifiutata. L'indirizzo pubblico degli atti non viene da
lì e resta una scelta separata.

> **Quello che esiste oggi è meno di questa tabella.** Sono costruiti il tipo, i due
> elenchi di voci per il tipo di atto (`albo_tipo_atto`) e per l'organo (`albo_organo`), i
> permessi e il ruolo proprio. Dei dati che fornisce chi redige esistono l'oggetto, il tipo
> di atto, l'organo, la data di adozione e il numero proprio, con il riquadro **Dati
> dell'atto** in cui si compilano e la lettura che li valida. Il numero di repertorio ha il
> suo meccanismo e non è ancora chiamato da nessuno. Non esistono ancora la durata propria,
> le date di pubblicazione, lo stato governato e la conferma del controllo sui dati
> personali. **Documento principale e allegati esistono**, depositati dalla consegna
> protetta del meccanismo comune, con il riquadro **Documenti dell'atto**; il blocco dopo il
> passaggio in verifica arriva con i passaggi di stato, e fino ad allora si cambiano solo in
> bozza. La tabella descrive il perimetro deciso, non la disponibilità.

| Dato | Obbligatorio | Dove sta | Chi lo scrive |
|---|---|---|---|
| Oggetto (titolo) | sì | contenuto WordPress | redattore |
| Tipo di atto | sì, una e una sola voce | elenco di voci dedicato, `albo_tipo_atto`; due voci valgono come nessuna | redattore, scelto tra i tipi configurati senza preselezione. Le voci nuove le crea solo chi governa l'elenco |
| Organo che ha adottato | sì, una e una sola voce | elenco di voci dedicato, `albo_organo`; come sopra | redattore, come sopra |
| Amministrazione che ha adottato l'atto, se è un atto ospitato | sì per andare in verifica, **solo se** l'elenco delle amministrazioni ospitate ha almeno una voce | metadato con **il nome copiato alla pubblicazione**, oltre al riferimento alla voce dell'elenco | redattore, scegliendo fra "questa amministrazione" e le voci dell'elenco, senza preselezione. Dopo la pubblicazione non cambia, nemmeno se la voce viene rinominata o tolta (ALBO-29) |
| Numero proprio dell'atto (es. determina 45/2026) | no | metadato protetto `_albo_pretorio_numero_proprio`, testo su una riga | redattore. È il numero dell'atto, non quello di pubblicazione |
| Data di adozione | sì | metadato protetto `_albo_pretorio_data_adozione`, testo `AAAA-MM-GG`; letto come assente se non è una data vera del calendario o se le righe sono più di una | redattore |
| Data di inizio pubblicazione | sì | metadato | **solo il sistema**, al passaggio a pubblicato, e mai nel futuro: la pubblicazione non si programma (ALBO-27). Non è modificabile dopo, perché da essa decorrono termini di legge |
| Data di fine pubblicazione | sì per pubblicare | metadato | **solo il sistema**, al passaggio a pubblicato e nello stesso istante dell'inizio, come inizio più durata applicabile (ALBO-27), dove la durata applicabile è quella **vigente nell'istante della pubblicazione**, del tipo o propria dell'atto: non la fornisce nessuno, e una data fornita a mano non supplisce a una durata assente. Il sistema rifiuta la **pubblicazione** di un atto per cui non è calcolabile, non il salvataggio della bozza, dove non esiste ancora. **Una defissione anticipata la riporta indietro**, ed è così, e non cambiando stato, che l'atto esce dalla vista |
| Durata propria dell'atto | no | metadato più voce di registro | Il contratto è uno solo e vale per ogni ingresso. **Chi**: soltanto chi possiede il permesso di pubblicare; chi ha il solo permesso di redazione non la scrive, non la cambia e non la toglie, anche su una bozza che può modificare. **Quando**: soltanto in bozza, perché in verifica l'atto è bloccato e da pubblicato non si torna indietro. **Dove**: soltanto se il tipo ha durata di origine dell'amministrazione; dove l'origine è una norma non esiste. **Quanto**: un numero intero di giorni, almeno 1 e **strettamente minore** della durata configurata; zero, valori negativi, frazioni e valori uguali o maggiori sono rifiutati. Uguale non è un errore grave ma non è una scelta, e più lunga non si può per scelta di prodotto. **Perché**: motivazione obbligatoria, che finisce nel registro con chi l'ha disposta (ALBO-04, ALBO-10), e così ogni cambio e ogni rimozione. **Alla pubblicazione** la durata propria si riverifica contro la configurazione vigente in quell'istante: se nel frattempo il tipo è diventato di origine normativa, o la durata configurata non è più maggiore di quella propria, la pubblicazione è rifiutata con quella ragione e l'atto resta in verifica, da rimandare in bozza per una nuova valutazione |
| Numero di repertorio (es. 123/2026) | dalla pubblicazione | riga nella tabella delle assegnazioni, più il contatore dell'anno | **solo il sistema**, alla prima pubblicazione, in modo atomico. Nel primo anno d'uso solo dopo la dichiarazione della partenza (ALBO-08) |
| Stato | sì | stato del contenuto | il flusso di pubblicazione, mai a mano nel database |
| Conferma del controllo dati personali | sì per pubblicare | metadato con utente e data | **chi pubblica**, nel passaggio da in verifica a pubblicato, tramite la schermata obbligata. Non chi redige: il controllo sta nel secondo dei due passaggi, ed è la ragione per cui i passaggi sono due (ALBO-11) |
| Documento principale | sì per pubblicare | file in cartella protetta più impronta (hash) in metadato | redattore prima della pubblicazione, poi bloccato. È uno e uno solo |
| Allegati ulteriori | no | come sopra | redattore. Possono non esserci: un atto con il solo documento principale si pubblica |
| Motivo di annullamento | quando ricorre | metadato più voce di registro | chi possiede `defissione atti`, che oggi fa parte dell'insieme di chi pubblica. Obbligatorio: senza, il passaggio è rifiutato |
| Motivo di defissione anticipata | quando ricorre | metadato più voce di registro | **non è testo libero**: è una causa scelta da un elenco chiuso, perché accorciare il termine di un atto regolare non è consentito (art. 124 del TUEL; l'art. 134 offre un argomento interpretativo e non disciplina gli effetti di un'interruzione). L'elenco è chiuso per scelta di prodotto, ampliabile per configurazione: le fonti dicono quali cause sono pacifiche, non che altre non possano esistere. Resta **in via provvisoria** su un solo fronte, cioè se la defissione anticipata vada riservata a un responsabile distinto, e la capability separata è ciò che permetterà di riservarla senza toccare il codice |
| Effetto della rimozione sul periodo | quando ricorre, cioè solo dopo una defissione anticipata | voce di registro | chi possiede `defissione atti`. **Lo decide l'amministrazione, non il componente**: le fonti impongono che la diffusione vietata cessi e tacciono su che cosa ne sia dell'adempimento, quindi il componente non lo calcola e non lo presume. È una scelta fra due valori, **la pubblicazione vale per il periodo trascorso** oppure **la pubblicazione va rifatta**, senza valore preselezionato. **La rimozione non la aspetta**: si rende insieme alla defissione anticipata oppure dopo, una volta sola, e non si cambia. Finché manca, il referto dice che non è stata dichiarata (ALBO-07). Nessuno dei due valori tocca date, stato, numero di repertorio o visibilità, e "va rifatta" non crea da sé un atto nuovo: la nuova pubblicazione è un atto nuovo con il suo numero. Non si rende dove non c'è stata una rimozione anticipata: non dopo la scadenza naturale, non dopo una sola sostituzione per oscuramento, dove il conteggio non riparte per scelta di prodotto (ALBO-12). Si lega all'evento della defissione anticipata registrato e non allo stato del momento, quindi resta possibile dopo un annullamento; il compito pianificato non la tocca. Non chiede un perché: basta il valore |

Gli **stati** possibili: bozza, in verifica, pubblicato, defisso, annullato.

**Lo stato memorizzato non è la condizione di scadenza, e confonderli è l'errore che questo
componente esiste per evitare.** Alla mezzanotte del giorno dopo la data di fine l'atto
diventa **immediatamente invisibile al pubblico**, per opera del filtro in lettura, anche se
nella banca dati il suo stato è ancora "pubblicato". Il compito pianificato porterà poi lo
stato memorizzato a "defisso", e se per un guasto non gira, quel passaggio non avviene e
**non cambia nulla di ciò che il pubblico vede**. Nessuna decisione sulla visibilità si
prende leggendo lo stato memorizzato: si prende leggendo la data.

**Quali passaggi siano consentiti fra questi stati, chi possa compierli e cosa accada a un
passaggio rifiutato** è ALBO-22, chiuso il 2026-09-21: la tabella delle transizioni sta nella
nota in `requisiti.md`, il diagramma in `architettura.md`. Chi redige e chi pubblica possono
essere la stessa persona se possiede entrambi i permessi; i due passaggi restano due, e il
controllo sui dati personali sta nel secondo.

**Un atto in verifica non è modificabile**, né da chi redige né da chi pubblica: se va
corretto torna in bozza con una motivazione, perché quello che è stato verificato è quello
che esce.

**La defissione anticipata non fa sparire l'atto cambiandogli stato**: gli riporta indietro
la data di fine pubblicazione, e l'atto esce dalla vista per la ragione di sempre, cioè
perché il filtro legge una data passata. Siccome la fine della pubblicazione è un giorno
civile e la scadenza scatta dalla mezzanotte del giorno dopo, la data scritta è quella del
giorno precedente a quello in cui la defissione è disposta: scrivere la data di oggi
lascerebbe l'atto visibile fino a stanotte. La data pianificata non si perde, perché la
modifica finisce nel registro delle operazioni insieme a chi l'ha disposta e perché. Che cosa
la rimozione comporti per l'adempimento, se valga il periodo trascorso o se la pubblicazione
vada rifatta, lo dichiara l'amministrazione, anche dopo, e il registro lo conserva: il
componente non lo calcola (decisione del 2026-09-23).

Tre sotto-decisioni restano aperte e sono marcate nella nota: se un atto annullato prima
della scadenza resti visibile al pubblico; chi disponga la defissione anticipata, che qui
è attribuita a chi pubblica in via provvisoria; e se il giorno civile basti come
granularità del termine, o serva chiedere al meccanismo comune qualcosa di più fine. Una
quarta, su quali cause legittimino la defissione anticipata, è chiusa il 2026-09-22
sull'art. 124 del TUEL, con l'art. 134 come argomento interpretativo e non come disciplina
degli effetti di un'interruzione: il termine di un atto regolare non si accorcia, e la
restrizione ha la sua riga di collaudo.

## Le risposte alle domande di controllo

**Dove viene salvata la data di pubblicazione?** Tre cose diverse, da non confondere.
L'**inizio effettivo** e la **fine pianificata** sono metadati dell'atto, scritti tutti e
due dal sistema nell'istante della pubblicazione (ALBO-27): l'inizio è un fatto, la fine è
una previsione che una defissione anticipata può portare indietro. Gli **eventi** del
registro delle operazioni dicono che cosa è accaduto davvero e quando. Il referto (ALBO-07)
ricostruisce il periodo di esposizione da questi tre con una regola sola: l'esposizione
comincia all'inizio effettivo e finisce al **primo** fra la fine pianificata vigente e la
defissione anticipata registrata; le sostituzioni per oscuramento registrate la dividono in
periodi, uno per versione. Dopo una defissione anticipata il referto riporta anche la causa
e l'effetto sul periodo dichiarato dall'amministrazione, con chi l'ha dichiarato e quando,
oppure dice che non è stato dichiarato: non lo deduce mai dalla causa, perché è una
valutazione che i testi lasciano all'amministrazione. Una dichiarazione resa dopo che il
referto è stato congelato vi si aggiunge come appendice, congelata a sua volta, e non lo
riscrive. La registrazione con cui il compito pianificato annota la
defissione **non è mai** la fine: se il compito passa in ritardo, l'atto era già invisibile
dalla fine pianificata, perché la scadenza si applica alla lettura e non dipende dal
compito, e il referto riporta quella fine.

**Cosa succede se cambio un documento già pubblicato?** Non si può, salvo un caso solo.
L'atto pubblicato è immodificabile per chiunque, amministratore compreso, e la strada giusta
è creare un nuovo atto di rettifica che rinvia al precedente. L'unica eccezione è la
**sostituzione di un allegato con la sua versione oscurata** (ALBO-12): si dichiara come
oscuramento, il termine continua a correre, il numero di repertorio non cambia, e restano
registrate le impronte del file uscente e di quello entrante. Che il termine continui a
correre è una scelta di prodotto e non una regola letta in una fonte: le fonti impongono che
la diffusione vietata cessi e sull'effetto sul periodo tacciono. Serve al caso dell'atto già
esposto che diffonde dati che non potevano essere diffusi, dove togliere e ripubblicare
guasterebbe l'adempimento per rimediare alla diffusione.

**Cosa succede agli allegati?** Vengono caricati in una cartella protetta, non
raggiungibile per URL diretto: si scaricano solo attraverso l'endpoint di consegna di
core, che a ogni richiesta ricontrolla che l'atto sia visibile. Alla defissione il file
non si cancella e la sua impronta sta nel referto, ma **non e' piu' scaricabile dal
pubblico**: l'endpoint rifa' i controlli e l'atto e' scaduto. Resta raggiungibile
dall'amministrazione, e in futuro da un archivio riservato. Il resto del referto sta nel
referto. Sostituire l'allegato di un atto pubblicato è vietato come modificare l'atto,
tranne che per oscurarlo: quella sostituzione conserva l'impronta di tutte e due le
versioni, perché il referto deve poter dire quale file era esposto in quale periodo.

**Chi può cambiare lo stato?** Solo chi ha la capability della transizione, e solo lungo
le transizioni permesse. Nessuno, nemmeno l'amministratore, può cancellare un atto
pubblicato o riportarlo a bozza.

## I permessi

Gli atti usano permessi dedicati, non quelli generici degli articoli. Li **ricava il
meccanismo comune** dall'identificativo del tipo, ma non li assegna a nessuno: **è questo
componente che li assegna ai ruoli**. Finché l'assegnazione non avviene, **nessun ruolo può
gestire il tipo atto dall'amministrazione di WordPress**: non lo vede nei menu e non può
crearne, e in quel caso la bacheca lo dice a chi può rimediare.

**Quando avviene l'assegnazione.** Non all'attivazione, ma ogni volta che la versione
memorizzata sul sito è diversa da quella del codice. Copre così con una sola riga la prima
attivazione e ogni aggiornamento successivo: un componente che assegna solo all'attivazione
lascia scoperti i siti già installati il giorno in cui una versione nuova aggiunge un
permesso. L'assegnazione è additiva e non toglie mai niente, nemmeno un permesso che
l'amministrazione avesse aggiunto per conto suo. Sui ruoli che il componente non nomina
guarda il permesso distintivo dell'insieme: chi possiede quello per pubblicare riceve
l'insieme di chi pubblica, chi possiede solo quello per redigere riceve l'insieme di chi
redige, così completare i permessi mancanti non promuove nessuno.

Il componente crea inoltre un **ruolo proprio**, Responsabile della pubblicazione all'albo,
che riceve l'insieme di chi pubblica e nessuno dei permessi degli articoli.

**Attenzione a cosa questi permessi non governano**, perché è la parte che si tende a dare
per compresa: governano la gestione del contenuto, non la sua consultazione pubblica. Un
atto pubblicato e non scaduto resta consultabile da chiunque anche quando nessun ruolo ha
ricevuto i permessi, perché ad aprire l'atto al pubblico sono la politica della sezione e
l'avvenuta pubblicazione, non i permessi. **Il venir meno della consultabilità per scadenza
è un'altra cosa e segue un'altra regola**: dipende dalla data di fine letta nell'istante
della richiesta, mai dallo stato memorizzato, come è fissato più sopra. Le due frasi non si
contraddicono perché parlano di due momenti diversi: un atto non ancora pubblicato non è mai
arrivato al pubblico, un atto scaduto ne esce per effetto della data e non perché qualcuno
abbia scritto "defisso". Che cosa il pubblico veda di un atto **annullato** prima della
scadenza resta la sotto-decisione aperta, e non la decide questo paragrafo.

Questa è la mappa delle azioni:

| Azione | Capability dedicata | Note |
|---|---|---|
| Creare e modificare bozze | gestione atti | |
| Pubblicare (con controllo dati personali) | pubblicazione atti | la conferma resta registrata con nome e data |
| Defissione anticipata, annullamento, dichiarazione dell'effetto della rimozione sul periodo | defissione atti | motivo obbligatorio per i primi due; per la dichiarazione, uno dei due valori ammessi. **Oggi questa capability fa parte dell'insieme di chi pubblica**, ed è per questo che la tabella delle transizioni attribuisce i due passaggi a chi pubblica. Tenerla separata di nome è ciò che permetterà di riservare la defissione anticipata a un responsabile distinto, se la sotto-decisione aperta si chiuderà in quel senso, senza cambiare il codice |
| Consultare gli atti defissi dall'amministrazione | archivio atti | **funzione successiva, oggi non costruita.** Non fa tornare a rispondere l'indirizzo pubblico dell'atto: quello resta irraggiungibile per chiunque, permessi compresi |
| Consultare il registro delle operazioni | lettura registro (di core) | |
| Configurare tipi di atto e durate | amministrazione albo | |
| Cancellare un atto pubblicato | **nessuna**: vietato per tutti | la voce non esiste proprio |
| Vedere un atto pubblicato e non scaduto | nessuna: pubblico | |

## Dati che il plugin scrive fuori dall'atto

> **Il referto è ancora un'ipotesi, il repertorio no.** ALBO-07 (referto) poggia sulla
> prassi della pubblicità legale, che non è una fonte, ed è una **scelta operativa da
> confermare**: quello che se ne dice qui descrive come lo faremmo, non che lo faremo.
> ALBO-08 (repertorio) è una **scelta di prodotto** dal 2026-09-23.


- **Repertorio**: due tabelle proprie. La prima, `albo_pretorio_repertorio_anni`, ha una
  riga per anno: ultimo numero usato, numero dichiarato alla partenza, come l'anno si è
  aperto (dichiarazione dell'amministrazione oppure prosecuzione di un anno già tenuto qui),
  chi e quando. L'ultimo numero avanza con un'unica istruzione che legge e incrementa
  insieme. La seconda, `albo_pretorio_repertorio`, ha una riga per assegnazione: anno,
  numero, atto, istante, con due vincoli della banca dati, la coppia anno e numero unica e
  l'atto unico. Nessun metadato dell'atto: il numero non si scrive da nessuna richiesta che
  scrive metadati. Una sola numerazione: registri separati non sono costruiti.
- **Referto**: un record congelato per ogni atto defisso: numero, tipo, oggetto, date
  effettive di inizio e fine, impronta del file pubblicato, chi ha pubblicato. Non si
  rigenera e non si modifica; si ristampa.
- **Registro delle modifiche** (tabella di core): una voce per ogni operazione
  rilevante, solo in aggiunta. Contiene l'identificativo dell'utente, mai altri dati
  personali.
- **Battito** (di core): il timestamp dell'ultima esecuzione riuscita del cron.
- **Configurazione**: tipi di atto con durata in giorni (obbligatoria per tipo),
  elenco delle amministrazioni ospitate (vuoto finché non se ne aggiunge una, ALBO-29),
  destinatario degli avvisi del battito. Ogni valore validato al salvataggio.
  La durata di un tipo è **costruita**: un solo metadato sulla voce dell'elenco dei tipi
  di atto, `albo_pretorio_durata_tipo`, con giorni, origine ed estremi insieme, così che
  non esista un tipo con i giorni e senza l'origine. Lo scrive chi può governare l'elenco
  dei tipi, cioè chi pubblica, dalla schermata dei tipi; non è esposto all'interfaccia per
  programmi. Si valida al salvataggio e **a ogni lettura**: una durata malformata scritta
  di lato vale come durata assente (righe A-48..A-57 di `collaudo.md`).

## Cosa NON viene salvato

Nessun dato di visitatori anonimi: niente tracciamento degli accessi pubblici. Nessuna
copia dei file fuori dalla cartella protetta. Nessun default nascosto: durata o politica
mancante significa configurazione incompleta e blocco, non un valore inventato.
