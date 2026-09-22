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
> permessi e il ruolo proprio. **I campi dell'atto non sono ancora registrati** e non c'è
> ancora la schermata in cui si compilano. **Documento principale e allegati non esistono
> affatto**, e non è un rinvio per comodità: senza la consegna protetta del meccanismo
> comune un file caricato risponde al proprio indirizzo diretto, e lo stato dell'atto non lo
> protegge. Chiedere di caricare lì un documento con dati personali non sarebbe accettabile.
> La tabella descrive il perimetro deciso, non la disponibilità.

| Dato | Obbligatorio | Dove sta | Chi lo scrive |
|---|---|---|---|
| Oggetto (titolo) | sì | contenuto WordPress | redattore |
| Tipo di atto | sì | elenco di voci dedicato, `albo_tipo_atto` | redattore, scelto tra i tipi configurati |
| Organo che ha adottato | sì | elenco di voci dedicato, `albo_organo` | redattore |
| Numero proprio dell'atto (es. determina 45/2026) | no | metadato | redattore. È il numero dell'atto, non quello di pubblicazione |
| Data di adozione | sì | metadato | redattore |
| Data di inizio pubblicazione | sì | metadato | **solo il sistema**, al passaggio a pubblicato, e mai nel futuro: la pubblicazione non si programma (ALBO-27). Non è modificabile dopo, perché da essa decorrono termini di legge |
| Data di fine pubblicazione | sì per pubblicare | metadato | calcolata dalla durata configurata per il tipo, che è il caso ordinario; il sistema rifiuta la **pubblicazione** senza, non il salvataggio della bozza. Dove la durata è **scelta dall'amministrazione** e non prescritta da una norma, chi pubblica può indicarne una più breve sul singolo atto **prima** di pubblicarlo, con motivazione registrata; dove la durata è di origine normativa non si accorcia. Il componente distingue i due casi perché ogni durata configurata dichiara la propria origine (ALBO-04, deciso il 2026-09-22). Più lunga non si può in nessuno dei due casi. **Una defissione anticipata la riporta indietro**, ed è così, e non cambiando stato, che l'atto esce dalla vista |
| Numero di repertorio (es. 123/2026). **Ipotesi da confermare** | dalla pubblicazione | metadato più contatore in tabella dedicata | **solo il sistema**, alla prima pubblicazione, in modo atomico |
| Stato | sì | stato del contenuto | il flusso di pubblicazione, mai a mano nel database |
| Conferma del controllo dati personali | sì per pubblicare | metadato con utente e data | **chi pubblica**, nel passaggio da in verifica a pubblicato, tramite la schermata obbligata. Non chi redige: il controllo sta nel secondo dei due passaggi, ed è la ragione per cui i passaggi sono due (ALBO-11) |
| Documento principale | sì per pubblicare | file in cartella protetta più impronta (hash) in metadato | redattore prima della pubblicazione, poi bloccato. È uno e uno solo |
| Allegati ulteriori | no | come sopra | redattore. Possono non esserci: un atto con il solo documento principale si pubblica |
| Motivo di annullamento | quando ricorre | metadato più voce di registro | chi possiede `defissione atti`, che oggi fa parte dell'insieme di chi pubblica. Obbligatorio: senza, il passaggio è rifiutato |
| Motivo di defissione anticipata | quando ricorre | metadato più voce di registro | **non è testo libero**: è una causa scelta da un elenco chiuso, perché accorciare il termine di un atto regolare non è consentito (art. 124 del TUEL; l'art. 134 offre un argomento interpretativo e non disciplina gli effetti di un'interruzione). L'elenco è chiuso per scelta di prodotto, ampliabile per configurazione: le fonti dicono quali cause sono pacifiche, non che altre non possano esistere. Resta **in via provvisoria** su un solo fronte, cioè se la defissione anticipata vada riservata a un responsabile distinto, e la capability separata è ciò che permetterà di riservarla senza toccare il codice |

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
modifica finisce nel registro delle operazioni insieme a chi l'ha disposta e perché.

Tre sotto-decisioni restano aperte e sono marcate nella nota: se un atto annullato prima
della scadenza resti visibile al pubblico; chi disponga la defissione anticipata, che qui
è attribuita a chi pubblica in via provvisoria; e se il giorno civile basti come
granularità del termine, o serva chiedere al meccanismo comune qualcosa di più fine. Una
quarta, su quali cause legittimino la defissione anticipata, è chiusa il 2026-09-22
sull'art. 124 del TUEL, con l'art. 134 come argomento interpretativo e non come disciplina
degli effetti di un'interruzione: il termine di un atto regolare non si accorcia, e la
restrizione ha la sua riga di collaudo.

## Le risposte alle domande di controllo

**Dove viene salvata la data di pubblicazione?** In due posti con ruoli diversi: le date
di inizio e fine **previste** sono metadati dell'atto; le date **effettive** vengono
congelate nel referto alla defissione. Se per un guasto la defissione avviene in ritardo,
il referto riporta la verità, non la previsione.

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
| Defissione anticipata, annullamento | defissione atti | motivo obbligatorio. **Oggi questa capability fa parte dell'insieme di chi pubblica**, ed è per questo che la tabella delle transizioni attribuisce i due passaggi a chi pubblica. Tenerla separata di nome è ciò che permetterà di riservare la defissione anticipata a un responsabile distinto, se la sotto-decisione aperta si chiuderà in quel senso, senza cambiare il codice |
| Consultare gli atti defissi dall'amministrazione | archivio atti | **funzione successiva, oggi non costruita.** Non fa tornare a rispondere l'indirizzo pubblico dell'atto: quello resta irraggiungibile per chiunque, permessi compresi |
| Consultare il registro delle operazioni | lettura registro (di core) | |
| Configurare tipi di atto e durate | amministrazione albo | |
| Cancellare un atto pubblicato | **nessuna**: vietato per tutti | la voce non esiste proprio |
| Vedere un atto pubblicato e non scaduto | nessuna: pubblico | |

## Dati che il plugin scrive fuori dall'atto

> **Attenzione: repertorio e referto sono due ipotesi, non due impegni.** ALBO-07 (referto)
> e ALBO-08 (repertorio) poggiano sulla prassi della pubblicita' legale, che non e' una
> fonte. Sono **scelte operative da confermare** contro il regolamento dell'amministrazione,
> e finche' non sono confermate quello che segue descrive come le faremmo, non che le
> faremo. Le unita' che le costruiscono sono bloccate da quella conferma.


- **Contatore di repertorio**: una riga per anno (e per registro, se l'ente configura
  registri separati), aggiornata con un'operazione atomica per impedire due atti con lo
  stesso numero.
- **Referto**: un record congelato per ogni atto defisso: numero, tipo, oggetto, date
  effettive di inizio e fine, impronta del file pubblicato, chi ha pubblicato. Non si
  rigenera e non si modifica; si ristampa.
- **Registro delle modifiche** (tabella di core): una voce per ogni operazione
  rilevante, solo in aggiunta. Contiene l'identificativo dell'utente, mai altri dati
  personali.
- **Battito** (di core): il timestamp dell'ultima esecuzione riuscita del cron.
- **Configurazione**: tipi di atto con durata in giorni (obbligatoria per tipo),
  registri, destinatario degli avvisi del battito. Ogni valore validato al salvataggio.

## Cosa NON viene salvato

Nessun dato di visitatori anonimi: niente tracciamento degli accessi pubblici. Nessuna
copia dei file fuori dalla cartella protetta. Nessun default nascosto: durata o politica
mancante significa configurazione incompleta e blocco, non un valore inventato.
