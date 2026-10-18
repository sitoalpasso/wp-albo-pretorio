# Importazione a secco dal plugin "Albo Pretorio On line"

Requisito ALBO-35. Righe di collaudo A-186..A-194, provate in `tests/ImportazioneTest.php`.

## Cosa fa, in breve

Legge il backup che il plugin "Albo Pretorio On line" produce dalla sua schermata di utilità
(menu Albo, Utility, Backup) e mostra **cosa si porterebbe** nel nuovo albo: per ogni atto
numero, date, durata, tipo, dati, documenti, se arriverebbe visibile al pubblico, e i
conflitti e i dubbi da risolvere prima. Poi categorie, numerazione, campi non riconosciuti e
file. **Non scrive niente**: né atti, né tipi, né numeri, né file, né impostazioni. È il passo
che precede l'importazione vera, e serve anche da solo, perché mostra lo stato dei dati.

## Il formato letto

Il backup è un archivio zip con dentro:

- **un file SQL alla radice** (`AlboPretorioAAAAMMGG_HHMM.sql`). Per ciascuna tabella c'è
  l'istruzione di creazione come la restituisce MySQL, una cancellazione e una riga
  `INSERT INTO ... VALUES (...)` per ogni record, **senza l'elenco delle colonne**. L'ordine
  delle colonne è quello della tabella sul sito di provenienza, che cambia con la storia delle
  versioni installate (le colonne aggiunte negli aggiornamenti stanno in fondo): si ricava
  quindi dall'istruzione di creazione che precede i dati, mai da un ordine supposto. In fondo
  ci sono le impostazioni del plugin (righe della tabella delle opzioni che cominciano con
  `opt_`) e due aggiornamenti che riscrivono il contatore dei numeri;
- **i file degli allegati**, con il percorso relativo alla cartella degli allegati
  (`2041/10/delibera.pdf` o solo `delibera.pdf`).

Le tabelle lette sono sette, con il prefisso del sito: `albopretorio_atti`,
`albopretorio_allegati`, `albopretorio_categorie`, `albopretorio_enti`,
`albopretorio_resprocedura`, `albopretorio_attimeta`, `albopretorio_unitaorganizzative`. Il
registro delle operazioni non è nel backup.

Si accettano anche il **solo file SQL** (gli allegati risultano allora non verificabili) e
una **copia intera della banca dati** fatta con mysqldump: le righe con l'elenco delle
colonne, più righe per istruzione, i commenti e le impostazioni di sessione si leggono; delle
altre tabelle si annota il nome e basta, e delle impostazioni si tengono solo quelle del
plugin (`opt_AP_`).

Il testo SQL **non si esegue**: si interpreta istruzione per istruzione. I testi fra apici
seguono le regole di MySQL (barra rovesciata davanti ad apice e barra, sequenze `\0 \n \r \Z`,
due apici di fila). Un intero vuoto è scritto con il valore predefinito della colonna, o
`NULL`. Il file si rifiuta, con il motivo, se: non c'è il file SQL alla radice o ce n'è più
d'uno; il testo finisce a metà di un valore; una riga ha più o meno valori delle colonne; i
dati di una tabella arrivano senza colonne note; ci sono le tabelle dell'albo di due siti.

L'archivio non si estrae sul disco: ogni file si legge come flusso e se ne calcola
l'impronta SHA-256, la stessa formula con cui il plugin di provenienza la registra al
caricamento.

## Le regole di traduzione

**Atti.** Un atto con numero 0 è una bozza: non si porta. Gli altri si portano con il loro
numero e anno, che non si ricalcolano. Le date si leggono come giorni di calendario, così come
sono scritte; la data zero vale come assente.

| Situazione nel backup | Nel nuovo albo |
|---|---|
| fine oggi o dopo, inizio oggi o prima | visibile al pubblico fino a tutto il giorno di fine; chi importa deve dare la conferma sui dati personali che il nuovo albo chiede prima di pubblicare (ALBO-11) |
| fine passata | invisibile al pubblico, senza impostazioni (ALBO-05); si consulta dalla schermata riservata degli scaduti |
| fine assente | invisibile: per la scadenza un atto senza fine non si vede. Dubbio |
| inizio futuro | dubbio: il nuovo albo non programma le pubblicazioni |
| fine prima dell'inizio, inizio assente | conflitto |
| data di annullamento | annullato con motivo; cosa veda il pubblico nel periodo di pubblicazione è una sotto-decisione ancora aperta. Dubbio |

"Oggi" è il giorno nel fuso del sito (`wp_timezone()`), lo stesso della scadenza.

**Durata.** La durata scritta è la differenza in giorni fra inizio e fine, la stessa regola
del nuovo albo, e porta la sua origine: "date scritte nel vecchio albo". Si confronta con i
giorni della categoria: più corta è un dubbio; più lunga è spiegata quando tutti i giorni
saltati, dalla fine calcolata in poi, sono festivi (domeniche e l'elenco dei festivi del nuovo
albo, o la proposta con le feste nazionali se l'elenco non è ancora salvato), e allora è una
nota sullo slittamento; altrimenti è un dubbio (una proroga o una correzione a mano).

**Categorie e tipi.** Ogni categoria si confronta per nome, senza maiuscole e spazi ai lati,
con i tipi di atto del nuovo albo. Se il tipo c'è si mostrano durata e origine; se non c'è,
"tipo da creare", e **l'origine della durata la dichiara chi importa, senza valore proposto**.
Una categoria senza giorni darebbe un tipo senza durata. Le categorie annidate si mostrano con
la madre davanti.

**Numerazione.** Conflitti: lo stesso numero e anno su due atti; un numero già dato dal nuovo
albo; un numero oltre l'ultimo numero del vecchio registro dichiarato nel nuovo albo per
quell'anno. Dubbio: l'anno del numero diverso dall'anno di inizio. Per ogni anno si contano
i numeri mancanti nella serie. Dal contatore (`opt_AP_NumeroProgressivo`,
`opt_AP_AnnoProgressivo`) si ricava una **proposta** dell'ultimo numero usato, il più alto fra
contatore meno uno e numeri trovati: la partenza resta una dichiarazione di chi pubblica
(ALBO-08), dalla schermata della numerazione.

**Dati.** `Riferimento` diventa la proposta di numero proprio. Organo e data di adozione non
esistono nel backup: "non indicato", non un errore. Responsabile del procedimento (persona
indicata in `RespProc`), ufficio (`IdUnitaOrganizzativa`) e richiedente si conservano sempre e
si mostrano secondo la scelta corrente dell'amministrazione (ALBO-33); un nome fuori dagli
elenchi del nuovo albo resta come un nome tolto dall'elenco. Senza posto nel nuovo albo, e
quindi nota: le altre persone collegate (`Soggetti`), le note (`Informazioni`), le etichette
di raggruppamento. Un atto di un'altra amministrazione (`Ente` diverso da 0) è un dubbio
finché le amministrazioni ospitate (ALBO-29) non ci sono. `Data` e `DataOblio` si riconoscono
e non servono. Un testo che il nuovo albo non conserverebbe alla lettera, o non in UTF-8, è un
dubbio.

**Documenti.** Il percorso registrato è completo, sul server di provenienza: vince il file
dell'archivio il cui nome coincide con la parte finale più lunga del percorso, così si trova
anche se il sito è stato spostato. Altrimenti un file con lo stesso nome in un'altra cartella,
purché uno solo (nota). Con l'impronta registrata si dice se il file è quello caricato. Un
file mancante o diverso è un conflitto su un atto visibile e un dubbio sugli altri. Natura `D`
è documento, `A` allegato: zero o più documenti sono un dubbio, perché il principale è uno e
lo sceglie chi importa. "Pubblicato per estratto" non ha posto: dubbio. I file dell'archivio
non citati da nessun allegato di un atto restano fuori, e si elencano.

**Campi.** Ogni colonna fuori dall'elenco conosciuto è "non riconosciuta", con il numero di
righe in cui è compilata; ogni colonna conosciuta che manca è "assente", come nelle versioni
precedenti del plugin.

## Esiti

Ogni atto ha un esito: **importabile**, **da decidere** (almeno un dubbio), **conflitto**
(almeno un conflitto: non si porterebbe finché resta), **escluso** (bozza). Le note non
cambiano l'esito.

## File, permessi, percorso

- `includes/class-lettore-backup.php`: lettura dell'archivio e del testo SQL.
- `includes/class-analisi-importazione.php`: stato del nuovo albo (`contesto()`) e rapporto.
- `includes/class-schermata-importazione.php`: voce "Importazione a secco" nel menu degli atti.
- `includes/class-repertorio.php`: `numeri_usati()`, i numeri già dati in un anno.

La schermata la apre chi può **pubblicare** e **consultare gli scaduti**, tutti e due: il
rapporto mostra gli oggetti di tutti gli atti, scaduti compresi. Gettone di sicurezza
sull'invio. Il backup si sceglie fra gli archivi zip che il plugin di provenienza lascia in
`wp-content/AlboOnLine/BackupDatiAlbo` (solo per nome, fra quelli presenti; niente
collegamenti simbolici), oppure si carica: il file caricato si legge dal posto temporaneo di
PHP, che lo cancella da sé, e si accetta solo se PHP lo ha ricevuto davvero come caricamento.
Il rapporto vive solo nella pagina che lo mostra. Ogni testo esce sfuggito.

## Modi di guasto previsti

- Backup troppo grande per il caricamento, anche quando PHP scarta l'intero invio: il
  messaggio dice di lasciarlo nella cartella dei backup e sceglierlo da lì.
- Archivio molto grande: la lettura dei file per l'impronta può superare il tempo massimo
  di una richiesta del server. Il rapporto allora non compare; si riprova con un tempo più
  lungo o sul sito di prova.
- Testo SQL oltre 512 MB, o una singola istruzione oltre 64 MB: rifiuto con il motivo.
- La numerazione del nuovo albo non risponde: conflitto generale, perché non si può dire se i
  numeri sono già stati dati.

## Cosa non fa

Non scrive: l'importazione vera è il passo successivo. Non riconosce dati genetici,
biometrici o di salute nei documenti: per gli atti che arriverebbero visibili chiede la
conferma di chi importa, come la pubblicazione. Non legge il registro delle operazioni, che
il backup non contiene.

## Come si prova su un sito vero

Sul sito di prova con il nuovo albo attivo: dal vecchio albo, Utility, Backup, poi
Importazione a secco nel menu degli atti, scegliere il backup appena fatto e leggere il
rapporto. Confrontare il numero di atti per anno e gli ultimi numeri con il registro del
vecchio albo, e aprire a campione i documenti segnalati come diversi.
