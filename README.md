# Albo Pretorio

Plugin WordPress per la pubblicazione con effetto di pubblicità legale (albo pretorio
online). Il componente implementa requisiti **derivati dalla normativa** applicabile ai
soggetti dell'art. 2-bis del d.lgs. 33/2013, indipendentemente dallo specifico ente.

**Stato: in sviluppo.** Non ancora utilizzabile in produzione. Questa fase apre il
repository e ne fissa licenza, documentazione minima e infrastruttura di verifica.

## Cosa fa

L'albo pretorio non è un elenco di documenti: è un sistema a scadenza, in cui il rischio
principale non è pubblicare male ma non smettere di pubblicare. Il componente è costruito
attorno a questo:

- tipo di contenuto dedicato all'atto, con metadati obbligatori e data di fine
  pubblicazione sempre valorizzata;
- durata della pubblicazione configurabile per tipo di atto, senza durate cablate nel
  codice e senza default silenziosi;
- scadenza applicata come proprietà del dato in lettura, su ogni percorso pubblico
  (elenco, scheda, ricerca interna, feed, sitemap, REST e allegati), quindi indipendente
  dall'effettiva esecuzione del compito pianificato;
- defissione automatica con registro delle operazioni e battito di controllo che segnala
  lo stallo del pianificatore;
- numerazione di repertorio progressiva annuale assegnata dal sistema, immodificabilità
  dell'atto pubblicato, referto di pubblicazione;
- esclusione dall'indicizzazione dei motori di ricerca, che per questo componente è la
  politica imposta;
- separazione netta dagli obblighi di pubblicazione del d.lgs. 33/2013: finalità, durate e
  cicli di vita sono distinti.

Il componente richiede il plugin `conformita-core`, che fornisce i meccanismi comuni
(scadenza, consegna degli allegati, registro) e riceve da qui la politica come parametro
esplicito. La compatibilità di versione è verificata all'attivazione.

## Requisiti normativi di riferimento

Le fonti sono citate per estremi. L'elenco completo dei requisiti (ALBO-01..21), con
fonte e criterio di verifica per ciascuno, viene pubblicato in `docs/requisiti.md` nel
corso dello sviluppo.

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
