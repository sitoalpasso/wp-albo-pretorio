# Architettura: quali parti fanno cosa

Questo documento è la mappa del plugin. Serve a capire, quando una modifica tocca un
file, **che cosa sta toccando**, anche senza leggere il PHP riga per riga.

> **Stato di questo documento: mappa progettata, non ancora costruita.** Alla data di
> scrittura il componente contiene lo scheletro del plugin e nient'altro: **nessuna delle
> parti elencate più sotto esiste nel codice**. La mappa è il piano, e le sue caselle si
> riempiono una unità di lavoro per volta. Il documento è pubblicato lo stesso, perché serve
> a decidere dove va una modifica prima di scriverla, ma va letto per quello che è: chi
> cerca una classe di questo elenco oggi non la trova, e non è un difetto.
>
> Da quando una parte esiste, il suo nome diventa il contratto: il codice usa quel nome, e
> se deve cambiare si aggiorna prima questo documento, nello stesso commit. **Anche la
> cartella non è ancora decisa**: qui è indicata `src/`, mentre lo scheletro attuale non ha
> nessuna cartella di codice. La sceglie la prima unità che scrive codice, e questo
> documento la segue.

Il plugin dipende da `conformita-core`, che fornisce i meccanismi comuni (scadenza,
consegna allegati, registro, battito, controllo dell'indicizzazione). L'albo dichiara a
core le **politiche**: cosa succede alla scadenza (l'atto esce dalla vista pubblica) e
come si tratta l'indicizzazione (vietata). Core si rifiuta di partire senza queste
dichiarazioni: così la regola opposta della trasparenza non può mai arrivare qui per
sbaglio.

## La vita di un atto

```
ATTO
 |
 +--> viene creato come bozza
 |       campi obbligatori richiesti, allegati caricati in cartella protetta
 |       [TipoContenutoAtto, core: ConsegnaAllegati]
 |
 +--> passa il controllo sui dati personali
 |       schermata con i casi di rivelazione indiretta, conferma esplicita
 |       eventuale versione oscurata accanto all'originale riservato
 |       [FlussoPubblicazione, Oscuramento]
 |
 +--> viene pubblicato
 |       il numero di repertorio viene assegnato ORA, in modo atomico
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
 |       [core: MotoreScadenza, Referto]
 |
 +--> resta in archivio ad accesso controllato
         raggiungibile solo con permesso dedicato, mai da visitatore anonimo
         il referto e' ristampabile da qui
         [PoliticaAlbo, core: ConsegnaAllegati]
```

## Le parti del plugin

| Parte prevista | Responsabilità | Requisiti | Esiste oggi |
|---|---|---|---|
| `TipoContenutoAtto` | Registra il tipo di contenuto "atto": campi obbligatori, validazione al salvataggio, capability dedicate, dichiarazione esplicita dell'esposizione REST | ALBO-01, ALBO-02 | no |
| `PoliticaAlbo` | Dichiara a core le politiche dell'albo: scadenza "esce dalla vista pubblica", indicizzazione vietata, durate per tipo di atto lette dalla configurazione (nessun default) | ALBO-04, ALBO-05, ALBO-06 | no |
| `Repertorio` | Assegna il numero progressivo annuale al primo passaggio in pubblicazione, in modo atomico; blocca ogni modifica manuale del numero | ALBO-08 | no |
| `FlussoPubblicazione` | Il percorso obbligato verso la pubblicazione: controllo preventivo sui dati personali con conferma esplicita; blocco di modifica e cancellazione dell'atto pubblicato, senza eccezioni per l'amministratore | ALBO-09, ALBO-11 | no |
| `Oscuramento` | Gestisce la coppia versione oscurata pubblica / originale riservato | ALBO-12 | no |
| `Referto` | Alla defissione congela la fotografia dell'esposizione (numero, date effettive, impronta del file, autore) e la rende ristampabile | ALBO-07 | no |
| `AvvisoPdf` | All'upload esamina il PDF e segnala gli indizi di inaccessibilità (scansione, assenza di struttura, titolo o lingua) | ALBO-13 | no |
| `Ricerca` | Elenco pubblico, filtri per tipo, organo, date e testo, navigabili da tastiera | ALBO-14, ALBO-16 | no |
| `Esportazione` | Export di atti e metadati di un periodo in formato aperto | ALBO-17 | no |

## Cosa arriva da conformita-core

| Meccanismo di core | Cosa fa per l'albo | Requisiti |
|---|---|---|
| Motore di scadenza | Filtro a ogni lettura pubblica (l'atto scaduto non appare da nessun percorso) più compito pianificato per il lavoro pesante | ALBO-03, ALBO-18, ALBO-21 |
| Battito di controllo | Timestamp a ogni esecuzione del cron, avviso al responsabile se invecchia | ALBO-19 |
| Consegna allegati | I file stanno in cartella protetta e si scaricano solo da un endpoint che rifà i controlli di visibilità a ogni richiesta | ALBO-05, ALBO-18, ALBO-20 |
| Registro delle modifiche | Log solo in aggiunta: chi, cosa, quando, perché | ALBO-10 |
| Controllo indicizzazione | Applica la politica dichiarata: noindex e fuori sitemap | ALBO-06 |
| Separazione delle esposizioni | Lo stesso documento può stare anche in trasparenza con regole sue, senza duplicare il file | ALBO-15 |

## Il confine tra albo e core, in una frase

Se una cosa serve anche alla trasparenza ma con regola diversa, sta in core come
meccanismo e l'albo dichiara la sua regola. Se una cosa esiste solo per l'albo
(repertorio, referto, controllo preventivo), sta qui.
