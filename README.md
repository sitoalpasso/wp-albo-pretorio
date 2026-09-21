# Albo Pretorio

Plugin WordPress per la pubblicazione con effetto di pubblicità legale (albo pretorio
online). Il componente implementa requisiti **derivati dalla normativa** applicabile ai
soggetti dell'art. 2-bis del d.lgs. 33/2013, indipendentemente dallo specifico ente.

## Stato: in costruzione, e questa pagina distingue cosa c'è da cosa è previsto

**Oggi il repository contiene lo scheletro del plugin, la documentazione e
l'infrastruttura di verifica. Nessuna delle funzioni descritte più sotto è costruita**, e
il componente non è utilizzabile in produzione.

La distinzione fra "c'è" e "è previsto" è mantenuta in tutta la documentazione, con una
colonna Stato in `docs/requisiti.md` e in `docs/collaudo.md` che si muove **solo dopo la
verifica continua verde**, mai sulla parola di chi scrive. Questa pagina segue la stessa
regola: quello che non c'è è al futuro.

## Cosa farà

L'albo pretorio non è un elenco di documenti: è un sistema a scadenza, in cui il rischio
principale non è pubblicare male ma non smettere di pubblicare. Il componente è progettato
attorno a questo.

**Il cuore, cioè la scadenza.** La scadenza sarà una proprietà del dato applicata **in
lettura**, su ogni percorso pubblico (elenco, scheda, ricerca interna, feed, mappa per i
motori, interfaccia informatica e allegati), quindi indipendente dall'esecuzione del
compito pianificato. Un compito pianificato farà poi il lavoro pesante e un battito di
controllo segnalerà il suo stallo, ma la visibilità pubblica non dipenderà mai da loro.

**L'atto.** Tipo di contenuto dedicato, con i suoi dati, un documento principale e
allegati ulteriori facoltativi. Una bozza si potrà salvare incompleta; **la data di fine
pubblicazione sarà richiesta per pubblicare**, e non esisterà l'atto pubblicato a tempo
indeterminato. La durata sarà configurabile per tipo di atto, senza durate cablate nel
codice e senza valori predefiniti silenziosi.

**Le regole che non si negoziano.** Immodificabilità dell'atto pubblicato, esclusione
dall'indicizzazione dei motori di ricerca (per questo componente è la politica imposta, non
un'opzione), separazione dagli obblighi di pubblicazione del d.lgs. 33/2013, con finalità,
durate e cicli di vita distinti.

**Due funzioni sono ipotesi, non impegni.** La **numerazione di repertorio** progressiva
annuale e il **referto di pubblicazione** poggiano sulla prassi della pubblicità legale, che
non è una fonte normativa: sono scelte operative da confermare contro il regolamento
dell'amministrazione, e le relative lavorazioni sono ferme in attesa di quella conferma.
Sono marcate come ipotesi in `docs/requisiti.md` e in `docs/collaudo.md`.

## Il rapporto con `conformita-core`

Il componente richiede il plugin `conformita-core`, che fornisce i meccanismi comuni e
riceve da qui le politiche come parametro esplicito: indicizzazione vietata e contenuto
scaduto irraggiungibile.

**Di quei meccanismi oggi ne esiste uno**, il filtro che applica la scadenza a ogni lettura.
Consegna degli allegati in cartella protetta, registro delle operazioni, compito pianificato,
battito di controllo, applicazione della politica di indicizzazione e separazione delle
esposizioni sono lavorazioni successive di quel componente, non funzioni già disponibili.
L'elenco riga per riga, con lo stato di ciascuno, è in
[`docs/architettura.md`](docs/architettura.md).

**La dipendenza è controllata in due punti distinti**, e coprono due cose diverse.
L'intestazione `Requires Plugins` di WordPress impedisce l'attivazione quando
`conformita-core` non è installato o non è attivo, ma non guarda le versioni. Il controllo
di compatibilità con la versione `1.2.0` dell'interfaccia avviene **all'avvio del
componente**, su `plugins_loaded`, ed è la lavorazione che apre lo sviluppo del codice.

## Requisiti normativi di riferimento

Le fonti sono citate per estremi. Il **catalogo corrente dei requisiti** (ALBO-01..26), con
fonte e criterio di verifica per ciascuno, è in [`docs/requisiti.md`](docs/requisiti.md).

**Non è un elenco completo, e il documento lo dichiara.** ALBO-07 e ALBO-08 sono ipotesi da
confermare contro il regolamento dell'amministrazione. ALBO-22, che riguarda gli stati
dell'atto e il flusso di pubblicazione, è stato deciso il 2026-09-21 e due sue sotto-decisioni
restano aperte, marcate come tali. La colonna Stato dice a che punto è ciascun requisito:
oggi sono tutti da fare.

Un'altra distinzione che il catalogo tiene ferma: dove c'è una fonte normativa, **la norma
fissa il risultato obbligatorio, non il modo in cui il componente lo ottiene**. Le soluzioni
tecniche sono decisioni di prodotto, marcate come tali, e restano discutibili.

- Legge 18 giugno 2009, n. 69, art. 32: assolvimento degli obblighi di pubblicazione con
  effetto di pubblicità legale mediante pubblicazione sui siti informatici, con rinvio
  espresso ai requisiti tecnici di accessibilità dell'art. 11 della legge 9 gennaio 2004,
  n. 4.
- D.lgs. 18 agosto 2000, n. 267, art. 124: pubblicazione delle deliberazioni per quindici
  giorni consecutivi, salvo specifiche disposizioni di legge. È una durata riferita a
  determinati atti e a determinati enti, non un valore universale: nel componente resta
  configurazione.
- Garante per la protezione dei dati personali, deliberazione 15 maggio 2014, n. 243,
  linee guida in materia di trattamento di dati personali contenuti anche in atti e
  documenti amministrativi effettuato per finalità di pubblicità e trasparenza sul web
  (G.U. n. 134 del 12 giugno 2014).
- Garante per la protezione dei dati personali, provvedimenti 12 marzo 2026
  [doc. web 10240362] e 26 marzo 2026 [doc. web 10246037], in materia di permanenza online
  di atti oltre il termine e di pubblicazione di categorie particolari di dati.
- Regolamento (UE) 2016/679, artt. 5 e 6.
- D.lgs. 30 giugno 2003, n. 196, artt. 2-ter e 2-septies.
- AgID, linee guida sulla formazione, gestione e conservazione dei documenti informatici,
  adottate con determinazione n. 407/2020, allegati 5 e 6 modificati con determinazione
  n. 371/2021.
- D.lgs. 14 marzo 2013, n. 33, art. 2-bis (ambito soggettivo) e disciplina degli obblighi
  di pubblicazione, per la separazione fra albo pretorio e amministrazione trasparente.

## Requisiti tecnici

- WordPress 6.5 o successivo
- PHP 8.1 o successivo
- Sito singolo. **Multisite non supportato.**
- Plugin `conformita-core` attivo

## Licenza

GPL-3.0-or-later. Il testo completo è in [LICENSE](LICENSE). Il componente è disponibile
in licenza aperta, senza costo di licenza né canone ricorrente.

## Documenti collegati

- [SUPPORT.md](SUPPORT.md): come ottenere assistenza e segnalare problemi
- [TRADEMARK.md](TRADEMARK.md): uso del nome
- [publiccode.yml](publiccode.yml): scheda del componente secondo lo standard publiccode.yml
