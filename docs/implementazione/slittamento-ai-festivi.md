# Slittamento della fine ai giorni festivi

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: costruita, in attesa di revisione.** Requisito ALBO-32, righe di collaudo
A-139..A-143 e A-156..A-164, tutte a fatto. Com'è andata davvero è in fondo.

## In tre paragrafi

**Cosa costruiamo.** Quando la data di fine di un atto cade di domenica o in un giorno di
festa, alcune amministrazioni la spostano al primo giorno seguente non festivo, come le
regole generali sui termini fanno per le scadenze; altre no. Nessun testo dice in modo
esplicito se quelle regole valgano anche per il periodo di affissione, e per questo la
scelta la fa l'amministrazione, in una schermata apposita: **Giorni festivi**, nel menu
dell'albo. La schermata spiega che cosa dice la norma, le due scelte possibili e che cosa
comporta ciascuna per chi consulta l'albo e per chi pubblica. Nessuna delle due è già
segnata: finché l'amministrazione non sceglie, non si pubblica e non si proroga, e la
schermata e la bacheca lo dicono. Nella stessa schermata sta l'elenco dei giorni festivi,
con il patrono, che l'amministrazione tiene aggiornato.

**Come la costruiamo.** La scelta e l'elenco stanno in una sola impostazione del sito,
scritta tutta insieme, con chi l'ha salvata e quando. Il calcolo della fine resta quello di
oggi (il giorno di pubblicazione non conta: 15 giorni dal 1° ottobre danno fine il 16);
dopo il calcolo, se lo slittamento è acceso e il giorno è domenica o un festivo
dell'elenco, la fine passa al giorno dopo, finché non trova un giorno feriale. Vale nei
due soli punti in cui il sistema scrive una data di fine: la pubblicazione (ALBO-27) e la
proroga per disservizio (ALBO-31). La fine calcolata, la scelta in vigore e i festivi
saltati finiscono nella voce di registro della pubblicazione o della proroga, così che ogni
atto dica da solo come è stata decisa la sua data, anche se l'elenco cambia dopo.

**Come si prova che funziona, e come si prova che il test è vero.** Le prove fissano
l'orologio in giorni scelti apposta perché la fine cada di domenica, in un festivo
dell'elenco, in un festivo seguito da una domenica, nel lunedì dopo Pasqua; pubblicano atti
veri con il passaggio già costruito e rileggono la data di fine e il registro dalla banca
dati. Le date attese si scrivono per esteso, mai chiedendole al codice in esame. A lavoro
finito si introducono guasti nel codice, uno per volta, e si guarda quale prova li vede.

## I nove punti

### 1. File che si pensa di creare o modificare

| File | Cosa conterrà |
|---|---|
| `includes/class-festivi.php` | l'impostazione (scelta ed elenco), la lettura dell'elenco, il calcolo di Pasqua, lo slittamento |
| `includes/class-schermata-festivi.php` | la schermata: testo che spiega la scelta, le due opzioni senza preselezione, l'elenco, il gettone di sicurezza, l'avviso in bacheca |
| `includes/class-passaggi.php` | la pubblicazione rifiutata senza scelta; la fine slittata; i dettagli nuovi della voce di conferma |
| `includes/class-proroga.php` | la fine nuova slittata; la proroga rifiutata senza scelta |
| `includes/class-riquadro-passaggi.php` | la frase che annuncia la fine, quando lo slittamento è acceso |
| `albo-pretorio-pa.php` | caricamento e agganci della schermata |
| `tests/FestiviTest.php` | le prove |
| `tests/flusso-di-prova.php`, `tests/ambiente-albo.php` | la scelta "spento" nell'assetto delle prove esistenti, l'azzeramento dell'impostazione, l'avviso escluso dalle osservazioni che non lo chiedono |
| `docs/collaudo.md`, `docs/requisiti.md`, `docs/architettura.md`, `docs/dati.md` | righe e stati |

### 2. Perché

ALBO-32, deciso il 2026-10-08: la scelta spetta all'amministrazione, senza preselezione,
con l'elenco dei festivi in configurazione. Vale la regola sulle impostazioni decisa lo
stesso giorno: la schermata dice la norma, le scelte e gli effetti.

**Le norme, per estremi.** Sono quelle citate in `requisiti.md` per ALBO-32, più l'elenco
delle feste; i testi non sono stati riletti in questa unità sulle fonti ufficiali, e prima
della release si verificano.

- TUEL (d.lgs. 267/2000) art. 124: quindici giorni consecutivi, salvo specifiche
  disposizioni di legge. Non dice che cosa succede se l'ultimo giorno è festivo.
- Codice civile art. 2963 c. 3 e codice di procedura civile art. 155 c. 4: il termine che
  scade in un giorno festivo è prorogato di diritto al primo giorno seguente non festivo.
  Sono regole generali sul computo dei termini; che valgano per l'affissione è una lettura.
- Legge 260/1949 art. 2, con le modifiche successive: l'elenco delle feste nazionali e delle
  domeniche come giorni festivi. Il 4 ottobre è tornato festa nazionale dal 2026 con una
  legge del 2025 che modifica quell'articolo: **gli estremi esatti vanno verificati sul
  testo ufficiale** prima della release.

### 3. Il flusso per chi usa il sito

Chi pubblica, dopo l'aggiornamento, vede in bacheca un avviso: prima di pubblicare va
scelto che cosa succede quando la fine cade in un giorno festivo, con il collegamento alla
schermata. La pubblicazione e la proroga rifiutano con la stessa frase.

La schermata **Albo pretorio, Giorni festivi** ha, dall'alto:

1. **Che cosa dice la norma**, in parole semplici e con gli estremi del punto 2.
2. **La scelta**, due opzioni, nessuna segnata finché non si salva la prima volta:
   - *Spostare la fine al primo giorno non festivo.* Per chi consulta: l'atto resta
     visibile un giorno o più in più quando la fine cadrebbe di domenica o in un festivo; il
     periodo non è mai più corto di quello di legge. Per chi pubblica: la fine può essere
     più tarda di inizio più durata; il registro dice quale era la fine calcolata e quali
     festivi sono stati saltati; l'elenco va tenuto aggiornato, patrono compreso.
   - *Lasciare la fine dove cade.* Per chi consulta: la fine è sempre inizio più durata,
     anche di domenica; l'ultimo giorno può cadere quando gli uffici sono chiusi. Per chi
     pubblica: la data di fine è sempre prevedibile, e l'elenco non viene usato.
3. **L'elenco dei giorni festivi**, un giorno per riga, nella forma `data | descrizione`:
   - `25/04 | Festa della Liberazione (l. 260/1949 art. 2)`: ogni anno quel giorno;
   - `29/06/2027 | Chiusura straordinaria`: solo quel giorno di quell'anno;
   - `Pasqua+1 | Lunedì dell'Angelo (l. 260/1949 art. 2)`: tanti giorni dopo Pasqua.
   Le righe che cominciano con `#` sono note e non contano. Le domeniche sono sempre
   festive e non si scrivono; il sabato non è festivo. La descrizione è obbligatoria: è lì
   che si scrive la norma che istituisce la festa.
4. **Chi ha salvato** l'ultima volta e quando.

Il salvataggio controlla tutto prima di scrivere: una riga sbagliata rifiuta l'intero
invio, con il numero della riga, e non cambia niente. Cambiare la scelta o l'elenco vale per
le fini calcolate da quel momento: le fini già scritte non cambiano.

### 4. Dati letti e scritti

**Si scrive** l'opzione `albo_pretorio_festivi`, un solo valore con quattro parti: la scelta
(`acceso` o `spento`), l'elenco già controllato, l'utente e l'istante del salvataggio. Una
sola opzione, scritta in una volta: scelta ed elenco non possono trovarsi a metà fra due
salvataggi.

**Si legge** l'opzione a ogni calcolo della fine. Un valore assente, o di forma diversa da
quella che il salvataggio scrive (scritto a mano nella banca dati, o rovinato), vale come
"non scelto": la pubblicazione si ferma invece di indovinare.

**Lo slittamento**: dalla fine calcolata, finché il giorno è domenica o è nell'elenco, si
passa al giorno dopo, nel fuso del sito. Al più 366 passi: un elenco che coprisse un anno
intero rifiuta il calcolo invece di girare per sempre.

**Nella voce di conferma della pubblicazione** e nella voce `proroga_atto` si aggiungono la
fine calcolata prima dello slittamento, la scelta in vigore e i festivi saltati con la loro
descrizione.

### 5. Permessi

Salvare la schermata richiede il permesso di pubblicare atti dell'albo, lo stesso della
numerazione e della proroga: la scelta cambia le date che chi pubblica fa scrivere al
sistema. Chi ha il solo permesso di redigere non vede la voce di menu e, se manda la
richiesta direttamente, è rifiutato. Gettone di sicurezza sul modulo. Nessun permesso nuovo.

### 6. Modi di guasto previsti

- Una scelta già pronta: un'opzione segnata, o l'assenza di scelta trattata come "spento".
- Un valore rovinato nell'opzione trattato come una scelta valida.
- Lo slittamento calcolato sul tempo universale, o sul giorno della settimana sbagliato.
- Pasqua calcolata male in un anno particolare.
- Lo slittamento applicato alla pubblicazione e non alla proroga, o viceversa.
- Le fini già scritte ricalcolate quando l'elenco cambia.
- Un invio parziale: la scelta salvata e l'elenco no, o un elenco con righe sbagliate
  salvato a metà.
- Un'anteprima della proroga che mostra una fine e una conferma che ne scrive un'altra
  perché nel frattempo l'elenco è cambiato.

### 7. Prove che si aggiungeranno

Righe A-139..A-143 e A-156..A-164 di `collaudo.md`, in `tests/FestiviTest.php`.

### 8. Cosa NON si implementa

- Un elenco delle feste nazionali che si aggiorna da solo: lo tiene l'amministrazione. La
  schermata propone, solo la prima volta, l'elenco vigente alla data di questa versione,
  da controllare prima di salvare (vedi il punto da decidere 1).
- Il sabato come giorno festivo, e le chiusure degli uffici che non sono feste: chi le
  vuole le scrive come giorno singolo.
- Il ricalcolo delle fini già scritte.
- Il referto (ALBO-07), che riporterà i dati della voce di registro quando ci sarà.

### 9. Come si prova su un'installazione reale

Su una copia del sito: scegliere lo slittamento acceso; creare un tipo di atto con una
durata che porti la fine di domenica e pubblicare un atto; controllare nel riquadro che la
fine sia il lunedì e nel registro la fine calcolata e la domenica saltata. Poi scegliere
"lasciare la fine dove cade", pubblicare un secondo atto e controllare che la fine sia la
domenica, e che quella del primo non sia cambiata.

## Punti da decidere

1. **La proposta dell'elenco.** La prima volta che la schermata si apre, il campo
   dell'elenco contiene già le feste nazionali vigenti, ciascuna con la sua norma, e una
   riga di nota che chiede di aggiungere il patrono. Non è una scelta preselezionata: la
   scelta fra le due opzioni resta vuota e niente vale finché non si salva. È un elenco
   scritto nel codice, che invecchia; ma senza proposta ogni amministrazione dovrebbe
   ricopiarlo a mano, con più errori. **Proposta**: tenerla, con la data della versione
   scritta accanto. Alternativa: campo vuoto e l'elenco come esempio in un riquadro sotto.

## Com'è andata davvero

**Differenze dal piano.**

- La proposta delle feste nazionali non sta nel codice ma nel file di dati
  `dati/feste-nazionali.txt`, nella stessa forma del campo: è un elenco che cambia per
  legge, e così si aggiorna senza toccare il codice.
- Il registro delle modifiche accetta nei dettagli solo testi, numeri o elenchi di questi:
  i festivi saltati si scrivono come elenco di testi `AAAA-MM-GG (descrizione)`, per
  esempio `2041-11-03 (Domenica)`.
- Pasqua si calcola dall'epatta gregoriana nella forma di Knuth. La forma più diffusa
  (Meeus) contiene il numero 15 come costante del calcolo, e la prova statica A-57, che
  cerca nei sorgenti il 15 come durata cablata, la segnalava: le due forme danno la stessa
  data in ogni anno dal 1583 al 9999, verificato confrontandole una per una.
- La scelta mancante si controlla due volte nella proroga: all'ingresso, prima di prendere
  il turno, e nel calcolo degli atti. Tolta una delle due, l'altra regge: per questo il
  guasto che toglie la prima non fa diventare rossa nessuna prova.
- La descrizione di una riga si ripulisce dai segni di formattazione invece di rifiutarla;
  una descrizione che resta vuota si rifiuta.
- Le prove esistenti partono con la scelta "lasciare la fine dove cade" già fatta, così
  le loro date non cambiano; l'avviso dei festivi è escluso dalle osservazioni della
  bacheca che non lo chiedono, come quello della numerazione.

**Prova dei guasti.** Quattordici guasti introdotti uno per volta nel codice; tredici visti
da almeno una prova: scelta mancante trattata come spento (A-139, A-162); domenica non
riconosciuta (A-140, A-161); inizio in tempo universale (A-140 e sette righe della
pubblicazione); Pasqua sbagliata di un giorno (A-157); proroga senza slittamento (A-142,
A-161, A-163); riga non nella forma normale accettata (A-162); riga sbagliata saltata
invece di rifiutare tutto (A-160); scelta preselezionata nella schermata (A-139, A-158);
gettone non controllato e permesso non controllato (A-159); avviso mostrato anche a chi
redige (A-164); fine calcolata non scritta nel registro (A-161); giorno singolo trattato
come annuale (A-140, A-161). Il quattordicesimo è la doppia difesa della proroga detta
sopra. Al primo giro la riga non normale era sfuggita: A-162 costruiva i casi cambiando
l'ordine delle chiavi, e il rifiuto arrivava da quello; ora ogni caso cambia una cosa sola.
