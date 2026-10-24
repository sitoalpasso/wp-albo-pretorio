# Effetto della rimozione anticipata sul periodo

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: costruita, in attesa di revisione.** Requisiti ALBO-10 (la terza voce del registro, dopo la defissione
e l'annullamento) e ALBO-22 (la dichiarazione che segue una defissione anticipata). Righe
di collaudo A-243..A-248, che provano le righe di ALBO-22 sulla dichiarazione per la parte
che non dipende da funzioni ancora da costruire. Nessuna versione nuova del meccanismo
comune. Il compito pianificato e il referto (ALBO-07) sono unità a sé.

## In tre paragrafi

**Cosa costruiamo.** Dopo che un atto è stato tolto dall'albo prima della scadenza, chi ha
il permesso di annullare e defiggere dichiara che cosa ne è dell'adempimento: **la
pubblicazione vale per il periodo trascorso** oppure **la pubblicazione va rifatta**. Le
fonti impongono che la diffusione vietata cessi, e tacciono su questo: la scelta è
dell'amministrazione, atto per atto, e il componente non la calcola e non la presume.
Nessuno dei due valori è preselezionato. Si dichiara insieme alla defissione oppure dopo,
anche dopo un annullamento, **una volta sola**, e non si cambia. La rimozione non la
aspetta: senza dichiarazione l'atto è tolto lo stesso. Nessuno dei due valori tocca date,
stato, numero o visibilità, e "va rifatta" non crea nessun atto: la nuova pubblicazione è
un atto nuovo, con il suo numero, che chi pubblica prepara come ogni altro. **Nessuna
impostazione nuova.**

**Come la costruiamo.** La dichiarazione è una voce del registro delle modifiche, azione
`effetto_rimozione`, con il valore, chi e quando, e nei dettagli anno e numero. Non chiede
un perché. La chiave della voce è una per atto, e il registro del meccanismo comune ha il
vincolo di unicità sulla chiave: due dichiarazioni, anche nello stesso istante, non possono
entrare tutte e due. Si accetta solo se il registro ha la voce `defissione_anticipata`
dell'atto: si lega all'evento registrato, non allo stato del momento, così vale anche
sull'atto annullato dopo la defissione, e non vale su un atto scaduto per la sua data, su
uno annullato senza defissione, né su uno con il solo stato riscritto. La dichiarazione resa
insieme alla defissione entra nella stessa transazione della defissione: o tutte e due o
nessuna. La dichiarazione resa dopo non scrive niente dell'atto, che resta fermo: solo la
voce.

**Come si prova che funziona, e come si prova che il test è vero.** Prove vere con atti
portati a pubblicato e defissi dai passaggi veri. Prima e dopo ogni operazione la fotografia
completa dell'atto, registro compreso: la dichiarazione deve aggiungere una voce e
nient'altro. Il riquadro si osserva prima di inviare, per vedere che nessun valore sia
scelto. I rifiuti si provano una condizione per volta, con il controllo positivo che fa
fallire una guardia sul permesso sbagliato o sullo stato invece che sull'evento.

## I nove punti

### 1. File che si pensa di creare o modificare

| File | Cosa conterrà |
|---|---|
| `includes/class-passaggi.php` | i due valori, `dichiara_effetto()`, la lettura `effetto()`, e la dichiarazione facoltativa dentro la defissione |
| `includes/funzioni-api.php` | `albo_pretorio_dichiara_effetto( $atto_id, $effetto )`, e il quarto argomento facoltativo di `albo_pretorio_defiggi()` |
| `includes/class-riquadro-passaggi.php` | i due valori, senza scelta fatta, nel modulo della defissione e, finché manca, sull'atto defisso o annullato dopo una defissione; il valore dichiarato in sola lettura |
| `includes/class-schermata-scaduti.php` | il valore dichiarato, o la sua mancanza, accanto alla defissione |
| `tests/EffettoRimozioneTest.php`, `tests/ConsultazioneScadutiTest.php` | le righe A-243..A-248 |
| `docs/collaudo.md`, `docs/dati.md`, `docs/requisiti.md`, l'indice | le righe nuove e lo stato |

### 2. Perché

ALBO-22, righe "Dichiarazione dell'effetto della rimozione sul periodo", "[attacco]
Tentativi indipendenti di dichiarare l'effetto" e "Dichiarazione e annullamento"; ALBO-10:
la terza voce del registro, che lascia identiche le altre due. `dati.md`, riga "Effetto
della rimozione sul periodo".

### 3. Il flusso per chi usa il sito

Nel modulo "Togli dall'albo prima della scadenza" del riquadro **Pubblicazione**, sotto il
motivo, la domanda "Che cosa ne è della pubblicazione?" con i due valori e nessuno scelto,
e la spiegazione: si può rispondere adesso o dopo, una volta sola; la risposta non cambia
date né visibilità, e "va rifatta" non crea l'atto nuovo. Sull'atto tolto dall'albo, finché
la risposta manca, la stessa domanda con il suo pulsante; quando c'è, il valore con chi e
quando, in sola lettura. Lo stesso sull'atto annullato dopo una defissione. La
consultazione riservata mostra il valore, oppure "non dichiarato".

### 4. Dati letti e scritti

Si scrive soltanto la voce `effetto_rimozione` del registro: valore nei dettagli
(`effetto`), con anno e numero, chiave `<sezione>:effetto_rimozione:<atto>`. Senza
motivazione. Si leggono la voce `defissione_anticipata`, che deve esserci, e la voce
`effetto_rimozione`, che non deve esserci. Una lettura del registro che non risponde ferma
la dichiarazione. Il valore si rilegge dalla voce, come causa e motivo.

### 5. Permessi

`albo_pretorio_defissione_atto_albo` (chiave `defissione_atti`), lo stesso della
defissione: chi ha questo permesso e non quello di pubblicare dichiara; chi ha solo quello
di pubblicare no.

### 6. Modi di guasto previsti

- Un valore preselezionato nel riquadro, inviato come se fosse stato scelto.
- Una dichiarazione che la rimozione aspetta.
- Un valore fuori dai due, o nessun valore.
- Una seconda dichiarazione, anche con lo stesso valore, anche concorrente.
- La dichiarazione su un atto senza defissione anticipata registrata: scaduto per la data,
  annullato senza defissione, con lo stato riscritto a defisso a mano, in bozza o
  pubblicato.
- Una guardia sullo stato invece che sull'evento: rifiuto sull'annullato dopo la defissione.
- Una dichiarazione che cambia qualcosa dell'atto, anche fuori dalla vista pubblica (la data
  di inizio), o che riscrive le voci precedenti.
- Campi estranei mandati con la dichiarazione dal riquadro.
- Insieme alla defissione: la defissione riuscita con la dichiarazione persa, o il
  contrario.

### 7. Prove che si aggiungeranno

Righe A-243..A-248 in `collaudo.md`, in `tests/EffettoRimozioneTest.php`.

### 8. Cosa NON si implementa

- **Il referto** (ALBO-07): le righe di ALBO-22 che ne parlano restano in corso per quella
  parte.
- **Il compito pianificato**: il caso dell'atto defisso per scadenza naturale si prova con
  lo stato scritto a defisso senza defissione anticipata, che è come il compito lo lascerà.
- **La sostituzione per oscuramento** (ALBO-12): il suo caso nella riga d'attacco aspetta
  quella funzione. Il rifiuto è già garantito dalla regola, perché l'oscuramento non scrive
  una voce di defissione.

### 9. Come si prova sul sito vero

Togliere un atto di prova senza rispondere alla domanda: l'atto sparisce. Riaprirlo: la
domanda c'è, senza scelta. Rispondere "va rifatta": il valore compare con chi e quando, la
domanda sparisce, le date restano. Annullare un secondo atto tolto, poi rispondere: riesce.

## Com'è andata davvero

Il piano ha retto. Le cose imparate:

- **Due dichiarazioni nello stesso istante.** Il controllo "non c'è ancora" e la scrittura
  non sono atomici; a fermare la seconda è il vincolo sulla chiave del registro, e il suo
  rifiuto si traduce nello stesso "già dichiarato" del caso normale (A-247).
- **Una lettura del registro che non risponde** non deve sembrare "non dichiarato": il
  meccanismo comune risponde con un elenco vuoto anche a una lettura fallita, quindi
  l'errore si legge dalla connessione, come per la data dell'annullato importato. Il
  riquadro in quel caso non offre la domanda, che forse ha già una risposta.
- **Il gettone del riquadro** si stampa una volta sola per atto: sull'atto tolto i moduli
  della dichiarazione e dell'annullamento sono nella stessa schermata.

Guasti introdotti nel codice per vedere quale prova li vede, tutti tolti:

- senza il controllo della defissione registrata e dello stato: fallisce A-245;
- un valore già scelto nel riquadro: falliscono A-244 e A-246.
