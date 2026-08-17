# Collaudo: come si dimostra che funziona

Questo documento spiega come si prova, **automaticamente**, che il plugin fa quello che
`requisiti.md` promette. È il contratto: un requisito non è chiuso finché il suo test non
passa in CI, e una modifica che tocca un requisito aggiorna prima il test.

## Come funziona il collaudo, in breve

- I test girano con **PHPUnit** dentro un WordPress vero, con database: non simulazioni.
  In locale l'ambiente è `wp-env` (`npx wp-env start`, configurazione in `.wp-env.json`);
  in CI la suite di test di WordPress viene installata da `bin/installa-suite-test.sh`
  contro un servizio MySQL, che è la stessa suite senza l'involucro di `wp-env`.
- La **CI** (GitHub Actions, `.github/workflows/ci.yml`) parte a ogni push su qualunque
  ramo e su ogni pull request. Se qualcosa è rosso, non si mergia.
- Tre famiglie di test:
  1. **di funzionamento**: il comportamento giusto avviene (l'atto si pubblica, il
     referto si genera);
  2. **di attacco**: il comportamento sbagliato è impossibile (contrassegnati
     [attacco]; molti riproducono difetti reali osservati in soluzioni esistenti, e
     devono fallire sul comportamento sbagliato, non solo passare su quello giusto);
  3. **statici**: cercano nel codice sorgente le cose che non devono esserci (una
     durata cablata, una rotta senza controllo dei permessi).

I percorsi di lettura citati come C-10..C-21 e C-33 sono l'elenco chiuso definito nel
collaudo di `conformita-core`: pagina singola, elenco, ricerca, feed, sitemap, REST per
collezione e per ID, oEmbed, XML-RPC, pagina allegato, navigazione precedente e
successivo, query di terzi, URL diretto dell'allegato.

## Cosa gira davvero in CI oggi

| Lavoro | Cosa esegue | C'è oggi |
|---|---|---|
| Standard di codifica | `composer lint`, cioè PHPCS con WordPress Coding Standards e PHPCompatibilityWP (`phpcs.xml.dist`) | sì |
| Test | `composer test`, cioè PHPUnit su due combinazioni: WordPress 6.5 con PHP 8.1 (le versioni minime dichiarate) e ultima stabile con PHP 8.3 | sì |
| Validazione publiccode | validatore ufficiale di Developers Italia su `publiccode.yml` | sì |
| Test statici sui sorgenti | ricerca della durata cablata, delle superfici senza controllo dei permessi, degli altri controlli automatici di `sicurezza.md` | **no, da aggiungere** |
| Analisi statica (PHPStan) | regole WordPress | **no, da aggiungere** |

I test che esistono oggi sono in `tests/ScheletroTest.php` e collaudano l'infrastruttura,
non i requisiti: che il plugin si carichi e dichiari la sua versione, che le versioni
minime dell'intestazione coincidano con le costanti, che il fuso orario si legga da
`wp_timezone()`. Nessuna riga della tabella "requisito per requisito" è ancora scritta:
è la ragione per cui la colonna Stato è tutta a "da fare".

## Il test più importante

**ALBO-18, il filtro alla lettura.** Si crea un atto, lo si fa scadere ieri, si tiene il
cron **spento** e si verifica che l'atto sia irraggiungibile su tutti i percorsi
dell'elenco chiuso, compreso il download dell'allegato. È il test che separa un albo
conforme da uno che sembra funzionare finché nessuno guarda.

## Requisito per requisito

La colonna **Stato** ha gli stessi tre valori di `requisiti.md`, applicati qui al test e
non al codice: **da fare** (il test non è scritto), **in corso** (scritto e rosso, cioè
il comportamento non c'è ancora), **fatto** (scritto e verde in CI). Un requisito è
"fatto" in `requisiti.md` solo se tutte le sue righe qui sono "fatto".

| Req | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| ALBO-01 | da fare | Si prova a salvare un atto senza uno dei campi obbligatori, campo per campo | salvataggio rifiutato ogni volta |
| ALBO-02 | da fare | Si prova a creare un atto senza data di fine da ogni ingresso possibile: interfaccia, REST, inserimento diretto | sempre bloccato |
| ALBO-03 | da fare | Si invoca il compito dall'esterno con il cron interno di WordPress disattivato | defissione eseguita, log scritto |
| ALBO-04 | da fare | Si prova a pubblicare un atto di un tipo senza durata configurata; si cambia la durata di un tipo senza toccare codice | pubblicazione bloccata; cambio possibile |
| ALBO-04 | da fare | [attacco, statico] si cerca la costante 15 usata come durata nel codice | assente |
| ALBO-05 | da fare | Atto defisso, visitatore anonimo, tutti i percorsi C-10..C-21 | irraggiungibile; in archivio entra solo chi ha il permesso |
| ALBO-06 | da fare | Si ispezionano le pagine dell'albo: meta robots, sitemap | noindex presente, URL fuori dalla sitemap |
| ALBO-07 | da fare | Si genera il referto di un atto defisso | contiene numero, date effettive, impronta del file, autore; ristampato, è identico |
| ALBO-08 | da fare | Si pubblicano più atti in parallelo (test di concorrenza) | numeri progressivi senza buchi né doppioni |
| ALBO-08 | da fare | [attacco] si invia una richiesta manipolata che tenta di scrivere il numero di repertorio | il numero resta quello del sistema |
| ALBO-09 | da fare | Si tenta di sostituire l'allegato di un atto pubblicato | bloccato; la rettifica è un nuovo atto |
| ALBO-10 | da fare | Si pubblica e si defigge in anticipo con motivo | due voci di registro: chi, quando, perché |
| ALBO-11 | da fare | Si percorre il flusso di pubblicazione saltando la conferma sui dati personali | il flusso non si conclude; la schermata elenca i casi di rivelazione indiretta |
| ALBO-12 | da fare | Si pubblica un atto con versione oscurata | il pubblico vede solo l'oscurata; l'originale richiede il permesso |
| ALBO-13 | da fare | Si carica un PDF che sembra una scansione | avviso al redattore, dichiarato come indizio; pubblicare resta possibile |
| ALBO-14 | da fare | Si legge un atto pubblicato con una sonda di accessibilità | testo vero, niente immagini al posto del testo, niente blocchi di copia |
| ALBO-15 | da fare | Lo stesso documento sta in albo e in trasparenza | due esposizioni, cicli di vita indipendenti, un solo file |
| ALBO-16 | da fare | Ricerca per tipo, organo, date, testo, navigando da sola tastiera | funziona, ordinata per data di pubblicazione |
| ALBO-17 | da fare | Export di un anno di atti con metadati | formato aperto, completo, reimportabile |
| ALBO-18 | da fare | Cron mai eseguito, atto scaduto ieri, tutti i percorsi più l'URL diretto dell'allegato (C-33) | tutto irraggiungibile |
| ALBO-19 | da fare | Si retrodata il timestamp del battito oltre soglia | l'avviso parte verso il responsabile configurato |
| ALBO-20 | da fare | Cache di pagina simulata attiva, poi defissione | la pagina dell'atto non resta servita dalla cache |
| ALBO-21 | da fare | Atti in scadenza nei giorni dei due cambi d'ora, fuso del sito su Roma | la scadenza cade nell'istante civile giusto |
| [attacco] | da fare | Un amministratore tenta di cancellare un atto pubblicato | bloccato: nessuna esenzione |
| [attacco, statico] | da fare | Si cerca ogni rotta REST o AJAX registrata senza nonce o `permission_callback` | nessuna |

Le due righe [statico] e il test statico di ALBO-04 richiedono il lavoro di CI ancora
mancante segnalato sopra: il test si scrive insieme al meccanismo che deve sorvegliare,
non dopo.

## Collaudo congiunto con la trasparenza

Con entrambi i plugin attivi sullo stesso sito: le pagine dell'albo hanno noindex e sono
fuori sitemap, quelle della trasparenza non hanno noindex e sono in sitemap. Un solo
test, due asserzioni: è l'errore invisibile a occhio, e per questo ha un test dedicato.

## La regola di crescita

Ogni bug scoperto diventa un test che prima riproduce il bug (e fallisce), poi ne
dimostra la correzione (e passa), e resta per sempre. La batteria può solo crescere: un
test non si cancella e non si disattiva per far passare la CI.
