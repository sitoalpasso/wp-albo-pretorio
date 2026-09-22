# Architettura: quali parti fanno cosa

Questo documento è la mappa del plugin. Serve a capire, quando una modifica tocca un
file, **che cosa sta toccando**, anche senza leggere il PHP riga per riga.

> **Stato di questo documento: mappa progettata, non ancora costruita.** Alla data di
> scrittura il componente contiene lo scheletro del plugin e nient'altro: **nessuna delle
> parti elencate più sotto esiste nel codice**, e dei sette meccanismi comuni ne esiste uno
> solo. Le due tabelle hanno una colonna che lo dice riga per riga. Il documento è
> pubblicato lo stesso, perché serve a decidere dove va una modifica prima di scriverla, ma
> va letto per quello che è: chi cerca oggi una di queste parti non la trova, e non è un
> difetto.
>
> Da quando una parte esiste, il suo nome diventa il contratto: il codice usa quel nome, e
> se deve cambiare si aggiorna prima questo documento, nello stesso commit. **Anche la
> cartella non è ancora decisa**: qui è indicata `src/`, mentre lo scheletro attuale non ha
> nessuna cartella di codice. La sceglie la prima unità che scrive codice.

Il plugin dipende da `conformita-core`, che fornisce i meccanismi comuni. L'albo gli
dichiara le **politiche**: cosa succede alla scadenza (`irraggiungibile`, cioè l'atto esce
dalla vista pubblica) e come si tratta l'indicizzazione (`vietata`).

**Che cosa succede se le politiche non sono dichiarate per intero.** Il meccanismo comune
parte normalmente: è un componente come gli altri e la sua attivazione non dipende da noi. È
la **registrazione della sezione** che viene rifiutata.

I casi in cui rifiuta sono quattro, e sono tutti quelli che oggi esistono davvero: una
politica non dichiarata o con un valore fuori dall'insieme ammesso, un identificativo di
sezione vuoto o con caratteri non ammessi, una sezione già registrata, e **il motore di
scadenza non avviato**. Quest'ultimo è l'unico controllo su un meccanismo, e non è un caso:
il motore è l'unico meccanismo comune che oggi esiste. **Indicizzazione, consegna degli
allegati, registro e battito non vengono verificati alla registrazione**, perché non ci sono
ancora: quando arriveranno, se il loro mancato avvio dovrà bloccare la registrazione sarà una
decisione da prendere allora, non una cosa che il codice già fa.

La conseguenza per l'albo è che senza registrazione riuscita non prosegue l'avvio: non
registra niente e non pubblica niente. Detto così e non come "il meccanismo comune non
parte", perché la differenza conta il giorno in cui si guarda un sito e si cerca di capire
chi non è partito.

## La vita di un atto

```
ATTO
 |
 +--> viene creato come bozza, anche incompleta
 |       la bozza si salva con dati mancanti: e' il passaggio a pubblicato
 |       che li pretende tutti. Documento principale [1..1] e allegati
 |       ulteriori [0..n], in cartella protetta
 |       [TipoContenutoAtto, core: ConsegnaAllegati]
 |
 +--> passa il controllo sui dati personali
 |       schermata con i casi di rivelazione indiretta, conferma esplicita
 |       eventuale versione oscurata accanto all'originale riservato
 |       [FlussoPubblicazione, Oscuramento]
 |
 +--> viene pubblicato
 |       il numero di repertorio viene assegnato ORA, in modo atomico
 |       IPOTESI DA CONFERMARE: il repertorio poggia sulla prassi e non
 |       su una fonte, e la sua unita' di lavoro e' bloccata
 |       voce nel registro: chi, cosa, quando
 |       [Repertorio, core: Registro]
 |
 +--> resta esposto fino alla data di fine
 |       visibile in elenco, scheda, ricerca; noindex su ogni pagina
 |       immodificabile: la rettifica e' un nuovo atto che rinvia a questo
 |       [core: MotoreScadenza (filtro in lettura), core: Indicizzazione]
 |
 +--> viene defisso alla scadenza (o prima, con motivo)
 |       il cron di sistema cambia lo stato; il filtro in lettura lo copre
 |       gia' dal primo istante anche se il cron e' fermo
 |       viene scattato il referto: fotografia immutabile
 |       IPOTESI DA CONFERMARE, come il repertorio
 |       [core: MotoreScadenza, Referto]
 |
 +--> l'indirizzo pubblico smette di rispondere, per chiunque
         l'albo dichiara la politica `irraggiungibile`: dopo la defissione
         quell'indirizzo non risponde piu' a nessuno sui percorsi pubblici,
         nemmeno a chi ha permessi. Non e' la politica `archivio`, che
         l'albo non dichiara
         [PoliticaAlbo]
 |
 +--> resta consultabile dall'amministrazione, e in futuro da un archivio riservato
         **funzione successiva, oggi non costruita**: un archivio amministrativo
         con permesso dedicato. Non cambia la riga sopra, perche' non fa tornare
         a rispondere l'indirizzo pubblico: e' una schermata di amministrazione,
         non una pagina del sito
         [da costruire]
```

## I passaggi fra le cinque situazioni

Il diagramma qui sopra racconta la vita di un atto come la vede chi lo consulta. Questo dice
**chi lo fa muovere**, ed è la decisione ALBO-22, chiusa il 2026-09-21 prima di costruire il
flusso di pubblicazione. La tabella completa, con le condizioni di ciascun passaggio e il
criterio con cui il disegno è stato scelto fra tre possibili, sta nella nota in
`requisiti.md`.

```
  (nuovo)  ---------------------------------->  BOZZA
         chi redige, senza condizioni: una bozza nasce
         anche incompleta

  BOZZA  ------------------------------------>  BOZZA
         chi redige, senza condizioni: una bozza si
         risalva tutte le volte che serve

  BOZZA  ------------------------------------>  IN VERIFICA
         chi redige, e solo se ci sono tutti i dati che
         la pubblicazione pretende, data di fine compresa

  IN VERIFICA  ------------------------------>  BOZZA
         chi pubblica, con motivazione obbligatoria.
         L'atto torna modificabile

  IN VERIFICA  ------------------------------>  PUBBLICATO
         chi pubblica, dopo la conferma esplicita del
         controllo sui dati personali. Tutto o niente

  PUBBLICATO  ------------------------------->  DEFISSO
         il compito pianificato, alla scadenza.
         REGISTRAZIONE, NON INTERRUTTORE: l'atto era gia'
         invisibile dalla mezzanotte per opera del filtro
         in lettura, e se il compito non gira non cambia
         niente di cio' che il pubblico vede

  PUBBLICATO  ------------------------------->  DEFISSO
         chi pubblica, con motivazione, e soltanto su un
         atto NON ancora scaduto: su uno scaduto non c'e'
         niente da anticipare

  PUBBLICATO o DEFISSO  ---------------------->  ANNULLATO
         chi pubblica, con motivazione. L'atto conserva
         numero, stato e motivo

  VIETATO, e non per omissione:
    BOZZA --> PUBBLICATO              saltare la verifica
    PUBBLICATO --> BOZZA              tornare indietro, per chiunque e da
    PUBBLICATO --> IN VERIFICA        ogni ingresso, amministratore compreso
    BOZZA --> DEFISSO                 defiggere o annullare cio' che non
    BOZZA --> ANNULLATO               e' mai stato pubblicato
    IN VERIFICA --> DEFISSO
    IN VERIFICA --> ANNULLATO
    IN VERIFICA --> IN VERIFICA       modificare un atto in verifica
    PUBBLICATO --> PUBBLICATO         modificare un atto pubblicato
    DEFISSO --> tutto tranne ANNULLATO
    ANNULLATO --> qualsiasi cosa      e' terminale
    cancellare un atto PUBBLICATO, DEFISSO o ANNULLATO

    e in generale OGNI coppia che non compare fra i passaggi consentiti
    qui sopra: quell'elenco e' chiuso, e comprende le due permanenze
    nello stesso stato che sono lecite, cioe' il risalvataggio della
    bozza. Questo elenco di divieti nomina le coppie che qualcuno
    potrebbe credere permesse, non tutte quelle che restano
```

**Chi redige e chi pubblica possono essere la stessa persona**, se possiede entrambi i
permessi: i passaggi restano due e il controllo sui dati personali sta nel secondo. Un ente
che vuole due firme distinte ottiene esattamente quel comportamento non assegnando a nessuno
entrambi i permessi, con il meccanismo dei ruoli che il componente già possiede. È il motivo
per cui il disegno non impone la separazione: imporla avrebbe cablato qui una regola
organizzativa che cambia da ente a ente, e reso il componente inservibile dove una persona
sola fa tutto il lavoro.

**Un atto in verifica non è modificabile da nessuno.** Se va corretto torna in bozza con la
sua motivazione: quello che è stato verificato è quello che esce.

**La defissione anticipata non toglie l'atto dalla vista cambiandogli stato.** Gli riporta
indietro la data di fine pubblicazione, e l'atto sparisce per la ragione di sempre: il filtro
in lettura trova una data passata. Siccome la fine della pubblicazione e' un giorno civile e
la scadenza scatta dalla mezzanotte del giorno dopo, la data scritta e' quella del giorno
precedente a quello in cui la defissione viene disposta. Scrivere la data di oggi lascerebbe
l'atto visibile fino a stanotte, che e' l'errore che questa riga esiste per impedire.

**Una transizione rifiutata non lascia stati intermedi.** L'atto resta dov'era e la riga
nella banca dati non attraversa mai lo stato richiesto: nessuna riparazione tardiva, perché
uno stato corretto dopo il fatto è indistinguibile da uno stato mai scritto.

## Le parti del plugin

**Quattro parti esistono oggi.** L'avvio, che dichiara la sezione e le sue politiche; il
tipo atto con i suoi due elenchi di voci; i permessi con il ruolo proprio e il meccanismo di
aggiornamento; e lo sbarramento che tiene chiusa la pubblicazione. Le altre sono previste e
non costruite: la colonna a destra lo dice riga per riga, perché una tabella letta al
presente farebbe credere disponibili funzioni che nessuno ha ancora scritto.

**Il tipo atto esiste e non è pubblico**, e le due cose vanno lette insieme. Un atto si
crea e si salva in bozza dall'amministrazione; il suo indirizzo non risponde a nessuno, il
tipo è fuori dalla ricerca interna e dalla mappa per i motori, e non ha nessuna rotta
nell'interfaccia per programmi. Il motivo non è prudenza generica: il documento principale
di un atto ha bisogno della consegna protetta del meccanismo comune, che non esiste ancora,
e senza di essa un file caricato risponde al proprio indirizzo diretto anche dopo la
defissione. La pubblicazione si aprirà tutta insieme, in una lavorazione dedicata, quando i
controlli che impediscono un'esposizione oltre il termine esisteranno davvero.

| Parte prevista | Responsabilità | Requisiti | Esiste oggi |
|---|---|---|---|
| `TipoAtto` | Registra il tipo di contenuto dell'atto attraverso il meccanismo comune, dentro la sezione dell'albo, e con esso i due elenchi di voci per il tipo di atto e per l'organo. Dichiara esplicitamente l'esposizione per programmi, oggi spenta, e tiene il tipo visibile in amministrazione e non interrogabile dal pubblico. **Non registra ancora i campi dell'atto e non li valida**: quello arriva con la schermata di compilazione | base di ALBO-01 | **sì** |
| `Permessi` | Insiemi di permessi del tipo, ruolo proprio del componente per chi pubblica, assegnazione additiva e ripetibile, avviso quando nessun ruolo li possiede. I nomi dei permessi non li sceglie: li chiede al meccanismo comune, che li ricava dall'identificativo del tipo | ALBO-23, ALBO-25, ALBO-26 | **sì** |
| `Installazione` | Confronta la versione memorizzata sul sito con quella del codice e, se differiscono, rifà il lavoro di installazione. È il motivo per cui un permesso nuovo arriva anche ai siti già installati, dove nessuna attivazione avviene | ALBO-24 | **sì** |
| `ChiusuraPubblicazione` | Elenco ordinato di regole che decide se uno stato richiesto è concesso. Oggi ne contiene una sola, che nega la pubblicazione e la programmazione. Le lavorazioni successive aggiungono la regola sui campi e tolgono questa | nessuno: impedisce di prometterne uno non mantenibile | **sì** |
| Campi dell'atto e schermata di compilazione | I dati dell'atto, il riquadro in cui si compilano, e il controllo campo per campo prima della pubblicazione | ALBO-01, ALBO-02 | no |
| Documento principale e allegati | I file dell'atto, la loro cardinalità e la loro impronta. Dipende dalla consegna protetta del meccanismo comune | ALBO-01 | no |
| `Avvio` | Verifica che il meccanismo comune sia caricato e compatibile, poi gli dichiara la sezione dell'albo con le due politiche per intero: indicizzazione `vietata`, scadenza `irraggiungibile`. Non chiude nessun requisito da sola: è il piano su cui ALBO-05 e ALBO-06 poggeranno. Se una qualsiasi delle tre condizioni non regge il componente resta attivo e inerte e lo segnala in bacheca, tranne nel caso della versione incompatibile, in cui è il meccanismo comune a disattivarlo | base di ALBO-05, ALBO-06 | **sì** |
| Durate di pubblicazione | Le durate per tipo di atto, lette dalla configurazione e senza nessun valore predefinito. Erano scritte nella riga qui sopra e ne sono state separate quando quella riga è stata costruita: la dichiarazione delle politiche esiste, le durate no | ALBO-04 | no |
| `Repertorio` | Assegna il numero progressivo annuale al primo passaggio in pubblicazione, in modo atomico; blocca ogni modifica manuale del numero. **Ipotesi da confermare**, unita' di lavoro bloccata dalla conferma | ALBO-08 | no |
| `FlussoPubblicazione` | Il percorso obbligato verso la pubblicazione: controllo preventivo sui dati personali con conferma esplicita; blocco di modifica e cancellazione dell'atto pubblicato, senza eccezioni per l'amministratore | ALBO-09, ALBO-11 | no |
| `Oscuramento` | Gestisce la coppia versione oscurata pubblica / originale riservato | ALBO-12 | no |
| `Referto` | Alla defissione congela la fotografia dell'esposizione (numero, date effettive, impronta del file, autore) e la rende ristampabile. **Ipotesi da confermare**, unita' di lavoro bloccata dalla conferma | ALBO-07 | no |
| `AvvisoPdf` | All'upload esamina il PDF e segnala gli indizi di inaccessibilità (scansione, assenza di struttura, titolo o lingua) | ALBO-13 | no |
| `Ricerca` | Elenco pubblico, filtri per tipo, organo, date e testo, navigabili da tastiera | ALBO-14, ALBO-16 | no |
| `Esportazione` | Export di atti e metadati di un periodo in formato aperto | ALBO-17 | no |

## Cosa arriva da conformita-core

**Un solo meccanismo esiste oggi: il filtro di scadenza a ogni lettura.** Gli altri sei
sono pianificati e non costruiti, e la colonna a destra lo dice riga per riga. Elencarli al
presente farebbe credere disponibili funzioni che nessuno ha ancora scritto, ed e' il modo
in cui un documento diventa piu' pericoloso della sua assenza.

**Il motore di scadenza va contato per meta'**, ed e' la distinzione che conta di piu' in
questa tabella. Il **filtro a ogni lettura** esiste ed e' quello da cui dipende la
conformita': un atto scaduto non compare, anche se nessun compito pianificato ha mai girato.
Il **compito pianificato** che porta lo stato memorizzato a defisso **non esiste ancora**.
Chiamare "motore" l'insieme delle due parti e segnarlo come costruito farebbe credere
disponibile la meta' che manca.

| Meccanismo di core | Cosa fa per l'albo | Requisiti | Esiste oggi |
|---|---|---|---|
| **Filtro di scadenza a ogni lettura** | A ogni richiesta pubblica l'atto scaduto non compare su nessuno dei percorsi coperti, anche se nessun compito pianificato ha mai girato. È la parte da cui dipende la conformità | ALBO-05, ALBO-18, ALBO-21 | **sì** |
| **Compito pianificato di aggiornamento** | Fa il lavoro pesante alla scadenza: porta lo stato memorizzato a defisso e scrive una voce nel registro delle modifiche per ogni atto toccato. **Non è ancora costruito**, e per esserlo ha bisogno anche del **registro delle modifiche**, che è a sua volta una lavorazione successiva del meccanismo comune: sono due pezzi distinti e il primo dipende dal secondo. La sua assenza non rende visibile un atto scaduto, perché la visibilità dipende dal filtro qui sopra e non da questo | ALBO-03 | **no, pianificato** |
| Battito di controllo | Timestamp a ogni esecuzione del cron, avviso al responsabile se invecchia | ALBO-19 | no, pianificato |
| Consegna allegati | I file stanno in cartella protetta e si scaricano solo da un endpoint che rifà i controlli di visibilità a ogni richiesta | ALBO-05, ALBO-18, ALBO-20 | no, pianificato |
| Registro delle modifiche | Log solo in aggiunta: chi, cosa, quando, perché | ALBO-10 | no, pianificato |
| Controllo indicizzazione | Applica la politica dichiarata: noindex e fuori sitemap | ALBO-06 | no, pianificato |
| Separazione delle esposizioni | Lo stesso documento può stare anche in trasparenza con regole sue, senza duplicare il file | ALBO-15 | no, pianificato |

## Il confine tra albo e core, in una frase

Se una cosa serve anche alla trasparenza ma con regola diversa, sta in core come
meccanismo e l'albo dichiara la sua regola. Se una cosa esiste solo per l'albo
(repertorio, referto, controllo preventivo), sta qui.
