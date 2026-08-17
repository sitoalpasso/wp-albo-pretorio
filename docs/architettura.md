# Architettura: quali parti fanno cosa

Questo documento è la mappa del plugin. Serve a capire, quando una modifica tocca un
file, **che cosa sta toccando**, anche senza leggere il PHP riga per riga. I nomi di
classe qui sotto sono il contratto: il codice usa questi nomi, e se un nome deve
cambiare si aggiorna prima questo documento, nello stesso commit.

Il plugin dipende da `conformita-core`, che fornisce i meccanismi comuni (scadenza,
consegna allegati, registro, battito, controllo dell'indicizzazione). L'albo dichiara a
core le **politiche**: cosa succede alla scadenza (l'atto esce dalla vista pubblica) e
come si tratta l'indicizzazione (vietata). Core si rifiuta di partire senza queste
dichiarazioni: così la regola opposta della trasparenza non può mai arrivare qui per
sbaglio.

## Che cosa esiste oggi nel repository

Questa mappa descrive il plugin da costruire. Oggi il repository contiene solo:

| File | Cosa contiene davvero |
|---|---|
| `albo-pretorio-pa.php` | Solo l'intestazione del plugin e cinque costanti nello spazio dei nomi `AlboPretorioPa`: `VERSIONE`, `WP_MINIMA`, `PHP_MINIMA`, `CORE_API_RICHIESTA`, `FILE_PRINCIPALE`. Nessun comportamento: non registra niente, non aggancia niente |
| `tests/` | `bootstrap.php` e `ScheletroTest.php`, che collaudano l'infrastruttura (versioni dichiarate coerenti con l'intestazione, fuso orario letto da `wp_timezone()`), non i requisiti |
| `.github/workflows/ci.yml` | La catena di verifica automatica descritta in [collaudo.md](collaudo.md) |

Due conseguenze da tenere presenti quando comincia il codice:

- **La cartella `src/` non esiste ancora** e `composer.json` non dichiara ancora un
  autoloader. Il primo commit che introduce una classe della tabella qui sotto aggiunge
  anche la sezione `autoload` con lo spazio dei nomi `AlboPretorioPa` mappato su `src/`.
- **Il controllo di compatibilità con core non è ancora scritto.** La costante
  `CORE_API_RICHIESTA` fissa oggi solo il valore atteso: la verifica a runtime, con
  disattivazione e messaggio chiaro se la versione non è compatibile, arriva insieme al
  primo meccanismo che usa core.

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

Tutte le classi vivono nello spazio dei nomi `AlboPretorioPa` e, quando saranno scritte,
in `src/`. Nessuna esiste ancora: la colonna Requisiti rimanda a `requisiti.md`, dove lo
stato di ciascun requisito è oggi "da fare".

| Classe (in `src/`) | Responsabilità | Requisiti |
|---|---|---|
| `TipoContenutoAtto` | Registra il tipo di contenuto "atto": campi obbligatori, validazione al salvataggio, capability dedicate, dichiarazione esplicita dell'esposizione REST | ALBO-01, ALBO-02 |
| `PoliticaAlbo` | Dichiara a core le politiche dell'albo: scadenza "esce dalla vista pubblica", indicizzazione vietata, durate per tipo di atto lette dalla configurazione (nessun default) | ALBO-04, ALBO-05, ALBO-06 |
| `Repertorio` | Assegna il numero progressivo annuale al primo passaggio in pubblicazione, in modo atomico; blocca ogni modifica manuale del numero | ALBO-08 |
| `FlussoPubblicazione` | Il percorso obbligato verso la pubblicazione: controllo preventivo sui dati personali con conferma esplicita; blocco di modifica e cancellazione dell'atto pubblicato, senza eccezioni per l'amministratore | ALBO-09, ALBO-11 |
| `Oscuramento` | Gestisce la coppia versione oscurata pubblica / originale riservato | ALBO-12 |
| `Referto` | Alla defissione congela la fotografia dell'esposizione (numero, date effettive, impronta del file, autore) e la rende ristampabile | ALBO-07 |
| `AvvisoPdf` | All'upload esamina il PDF e segnala gli indizi di inaccessibilità (scansione, assenza di struttura, titolo o lingua) | ALBO-13 |
| `Ricerca` | Elenco pubblico, filtri per tipo, organo, date e testo, navigabili da tastiera | ALBO-14, ALBO-16 |
| `Esportazione` | Export di atti e metadati di un periodo in formato aperto | ALBO-17 |

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
