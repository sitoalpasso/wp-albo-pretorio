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

## Le parti del plugin

| Parte prevista | Responsabilità | Requisiti | Esiste oggi |
|---|---|---|---|
| `TipoContenutoAtto` | Registra il tipo di contenuto "atto": campi obbligatori, validazione al salvataggio, capability dedicate, dichiarazione esplicita dell'esposizione REST | ALBO-01, ALBO-02 | no |
| `PoliticaAlbo` | Dichiara a core le politiche dell'albo: scadenza "esce dalla vista pubblica", indicizzazione vietata, durate per tipo di atto lette dalla configurazione (nessun default) | ALBO-04, ALBO-05, ALBO-06 | no |
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
| **Compito pianificato di aggiornamento** | Fa il lavoro pesante alla scadenza: porta lo stato memorizzato a defisso e scrive nel registro. **Non è ancora costruito** (unità S6 del meccanismo comune). La sua assenza non rende visibile un atto scaduto, perché la visibilità dipende dal filtro qui sopra e non da questo | ALBO-03 | **no, pianificato** |
| Battito di controllo | Timestamp a ogni esecuzione del cron, avviso al responsabile se invecchia | ALBO-19 | no, pianificato |
| Consegna allegati | I file stanno in cartella protetta e si scaricano solo da un endpoint che rifà i controlli di visibilità a ogni richiesta | ALBO-05, ALBO-18, ALBO-20 | no, pianificato |
| Registro delle modifiche | Log solo in aggiunta: chi, cosa, quando, perché | ALBO-10 | no, pianificato |
| Controllo indicizzazione | Applica la politica dichiarata: noindex e fuori sitemap | ALBO-06 | no, pianificato |
| Separazione delle esposizioni | Lo stesso documento può stare anche in trasparenza con regole sue, senza duplicare il file | ALBO-15 | no, pianificato |

## Il confine tra albo e core, in una frase

Se una cosa serve anche alla trasparenza ma con regola diversa, sta in core come
meccanismo e l'albo dichiara la sua regola. Se una cosa esiste solo per l'albo
(repertorio, referto, controllo preventivo), sta qui.
