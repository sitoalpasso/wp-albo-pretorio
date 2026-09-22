# Mappa delle norme sui requisiti

`requisiti.md` va dalla funzione alla fonte: per ogni ALBO-xx dice da dove viene. Questo
documento fa il giro opposto. Parte dalle norme e, per ciascuna, dice quale obbligo
produce per un albo pretorio online, quali requisiti lo traducono e a che punto è la
copertura oggi.

Serve a rispondere a una domanda sola: se qualcuno chiede "questa norma chi la rispetta",
qui c'è la riga. E serve a far vedere il verso opposto, che una tabella per requisito
nasconde: gli obblighi che **nessun requisito traduce**. Stanno nella sezione "Obblighi e
domande senza requisito", che è la parte di questo documento che vale la pena leggere per
prima.

Le fonti sono state ricontrollate sui siti che le pubblicano il 22 settembre 2026, e i
cinque articoli di legge su cui il documento poggia sono stati letti nel testo vigente lo
stesso giorno. La sezione finale dice, per ciascuna fonte, che cosa si è potuto leggere e
che cosa no: sono due risposte diverse e la seconda non va nascosta.

## Come si legge la copertura

Tre valori. Il criterio è lo stato delle righe di `collaudo.md`, non l'impressione di chi
guarda il codice.

- **coperto**: tutte le righe di `collaudo.md` che chiudono il requisito sono "fatto",
  cioè verdi nella verifica continua.
- **avviato**: esiste ed è verde qualcosa su cui il requisito poggia, per esempio una
  politica dichiarata o un meccanismo del componente comune, ma la riga che chiude il
  requisito non è verde. Un requisito "avviato" **non è rispettato**: è sostenuto da un
  pezzo che regge.
- **scoperto**: nessun codice e nessun test.

**Il numero che conta: oggi nessun obbligo di norma è coperto.** I quattro requisiti verdi
(ALBO-23, ALBO-24, ALBO-25, ALBO-26) riguardano l'assegnazione dei permessi, che è una
scelta di prodotto e non l'attuazione di un obbligo. Cinque requisiti risultano "avviati"
(ALBO-02, ALBO-05, ALBO-06, ALBO-18, ALBO-21), perché il motore di scadenza del componente
comune è costruito e verde e l'albo gli dichiara già le sue due politiche. Tutto il resto è
scoperto.

## Le fonti

| Sigla | Fonte | Cosa fissa per l'albo |
|---|---|---|
| L69 | Legge 18 giugno 2009, n. 69, art. 32 | la pubblicazione sul sito ha effetto di pubblicità legale; la pubblicazione si effettua applicando i requisiti tecnici di accessibilità dell'art. 11 della legge 4/2004, e la mancata pubblicazione in quei termini rileva sulla valutazione del dirigente responsabile; gli adempimenti possono essere assolti sul sito di un'altra amministrazione |
| TUEL | D.lgs. 18 agosto 2000, n. 267, artt. 124 e 134 | quindici giorni consecutivi per le deliberazioni di comuni e province, salvo specifiche disposizioni di legge (art. 124); esecutività della deliberazione dopo il decimo giorno dalla pubblicazione, e dichiarazione di immediata eseguibilità in caso di urgenza (art. 134) |
| GAR | Garante per la protezione dei dati personali, deliberazione 15 maggio 2014, n. 243, in GU n. 134 del 12 giugno 2014 | linee guida sul trattamento di dati personali contenuti in atti pubblicati per finalità di pubblicità e trasparenza: durata limitata, niente indicizzazione, minimizzazione |
| GDPR | Regolamento (UE) 2016/679, artt. 5 e 6 | principi del trattamento e base giuridica |
| COD | D.lgs. 30 giugno 2003, n. 196, artt. 2-ter e 2-septies | cosa è diffusione e su quali basi un soggetto pubblico può diffondere (2-ter); divieto assoluto di diffondere dati genetici, biometrici e relativi alla salute (2-septies comma 8) |
| LG-DOC | AgID, linee guida sulla formazione, gestione e conservazione dei documenti informatici. Il testo pubblicato porta in copertina "Maggio 2021" e gli allegati sono datati 27 giugno 2024. Le linee guida si applicano dal duecentosettantesimo giorno dopo la loro entrata in vigore, senza una data di calendario nel testo | integrità del documento informatico, metadati (allegato 5), formati di file (allegato 2), versamento in conservazione |
| ACC | Legge 9 gennaio 2004, n. 4, art. 11, richiamato da L69 art. 32. L'articolo è stato **sostituito** dall'art. 1 comma 10 del d.lgs. 106/2018 e oggi rinvia alle linee guida AgID sull'accessibilità degli strumenti informatici, adottate con determinazione n. 437/2019 del 20 dicembre 2019, nella versione del 21 dicembre 2022 rettificata con determinazione DG n. 354 del 22 dicembre 2022. Per i soggetti privati dell'art. 3 comma 1-bis valgono linee guida distinte, adottate con determinazione n. 117/2022 | requisiti tecnici di accessibilità dei contenuti e dei documenti pubblicati, ancorati alla norma armonizzata EN 301 549 e al livello AA delle WCAG 2.1 |
| TRASP | D.lgs. 14 marzo 2013, n. 33 | l'amministrazione trasparente è un obbligo distinto, con finalità e durate proprie |
| P-1 | Garante, provvedimento 12 marzo 2026, doc. web 10240362 | atti su una procedura di mobilità che rivelavano dati sulla salute per il richiamo alla legge 68/1999, senza una base giuridica che ne autorizzasse la pubblicazione |
| P-2 | Garante, provvedimento 26 marzo 2026, doc. web 10246037 | dati reddituali e patrimoniali rimasti online oltre un anno e mezzo, oltre il termine dell'art. 124 del TUEL |
| P-3 | Garante, provvedimento 14 maggio 2026, doc. web 10259523 | atti rimasti online per anni oltre il termine dei quindici giorni, e la conferma che GAR è ancora applicabile |
| P-4 | Garante, provvedimento 6 giugno 2024, doc. web 10032683 | archivio storico degli atti sul sito: si può tenere, purché i dati personali siano oscurati |
| P-5 | Garante, provvedimento 10 aprile 2025, doc. web 10140369 | un regolamento dell'amministrazione non è una base giuridica sufficiente per diffondere dati personali |

I provvedimenti non sono fonti di obblighi nuovi: sono la prova di come le norme qui sopra
vengono applicate, e dicono dove si sbaglia per davvero. Vanno letti come criterio di
verifica, non come articoli. Sono cinque e non due perché la riverifica del 22 settembre
2026 ne ha trovati tre che l'elenco non aveva, fra cui uno più recente di tutti gli altri:
vedi la sezione finale sulle verifiche.

## Norma per norma

### L69 art. 32: la pubblicazione online è quella che produce effetti

L'articolo sposta sul sito la pubblicità legale, e il documento sul sito non è una copia
di cortesia: è la pubblicazione. Da qui due obblighi.

| Obbligo | Requisiti | Copertura |
|---|---|---|
| L'atto pubblicato è identificabile e completo dei suoi estremi | ALBO-01 | scoperto |
| La pubblicazione è effettuata applicando i requisiti tecnici di accessibilità dell'art. 11 della legge 4/2004 (comma 1, secondo periodo) | ALBO-13, ALBO-14 | scoperto, e l'obbligo è più largo dei requisiti che lo coprono: vedi qui sotto |
| Gli adempimenti possono essere assolti sul sito di un'altra amministrazione o di una loro associazione (comma 3) | nessuno | scoperto |

L'articolo contiene tre date che vanno tenute distinte quando se ne discute: dal 1 gennaio
2010 gli obblighi di pubblicità legale si assolvono sui siti; dal 1 gennaio 2011 la
pubblicazione cartacea perde quell'effetto; dal 1 gennaio 2013 lo stesso vale per gli atti
per cui era prevista la pubblicazione su quotidiani. Tutte e tre sono ora verificate sul
testo vigente, letto il 22 settembre 2026. Restano fuori gli obblighi in Gazzetta Ufficiale
e nella Gazzetta dell'Unione europea, che il comma 7 fa salvi insieme a quelli sul sito del
Ministero delle infrastrutture e dell'osservatorio dei contratti pubblici.

**La lettura del testo ha però trovato nel comma 1 due periodi che la citazione dentro GAR
non riportava, e sono la parte più importante dell'articolo per noi.**

Il primo: "La pubblicazione è effettuata nel rispetto dei principi di eguaglianza e di non
discriminazione, applicando i requisiti tecnici di accessibilità di cui all'articolo 11
della legge 9 gennaio 2004, n. 4". Non è un obbligo di accessibilità del sito posato accanto
all'albo: è una qualità **dell'atto del pubblicare**. La norma che istituisce la pubblicità
legale online dice come va fatta, e il come include l'accessibilità. Il destinatario
dell'obbligo restano però "le amministrazioni e gli enti pubblici obbligati", che è quello
che l'articolo dice: la norma non ripartisce il lavoro fra plugin, tema e infrastruttura.
Che il componente debba rispondere dell'accessibilità delle pagine che genera è quindi una
scelta di attuazione, non una conseguenza del testo, e va presa come scelta.

Il secondo: "La mancata pubblicazione nei termini di cui al periodo precedente è altresì
rilevante ai fini della misurazione e della valutazione della performance individuale dei
dirigenti responsabili". Qui va detto che la frase si presta a due letture, perché "termini"
può voler dire scadenze oppure modi. Riferita al periodo precedente, che parla di modi e non
di scadenze, la lettura naturale è che **una pubblicazione non accessibile conti come
pubblicazione mancata ai fini di quella valutazione**. Per la regola di cantiere sui dubbi
si tiene questa lettura, dichiarandola.

Attenzione a dove si ferma. La norma collega quella mancanza alla valutazione della
performance del dirigente, e non dice altro: **nessuno dei testi letti dice che un difetto
di accessibilità privi la pubblicazione dei suoi effetti di pubblicità legale**. Il primo
periodo rende quindi l'accessibilità un requisito obbligatorio del pubblicare, con una
conseguenza sul dirigente; che cosa succeda alla validità della pubblicazione è una domanda
a cui questi testi non rispondono, e che non va data per risolta.

Il comma 1-bis aggiunge un caso che il catalogo non aveva: gli elaborati tecnici allegati
alle delibere urbanistiche e alle loro varianti si pubblicano sul sito del comune. Sono
allegati pesanti e per lo più grafici, cioè il caso peggiore sia per la consegna protetta
degli allegati sia per l'accessibilità, e il legislatore li ha voluti pubblicati "senza
nuovi o maggiori oneri".

Il comma 3 chiude infine una domanda rimasta aperta nella sezione sul TUEL: gli adempimenti
possono essere attuati usando il sito di un'altra amministrazione obbligata, o di una loro
associazione. Il caso dell'atto di un ente ospitato sull'albo di un altro non è quindi una
stranezza dell'art. 124 comma 2, è una facoltà generale. Nessun requisito dice chi, in quel
caso, governa la scadenza e chi risponde dei dati personali.

### TUEL artt. 124 e 134: la durata esiste, non è una sola, e serve a qualcosa

Quindici giorni consecutivi per le deliberazioni **di comuni e province**, salvo specifiche
disposizioni di legge. Non è una durata universale, e trattarla come tale rende il
componente inservibile su un ente di tipo diverso.

Il testo vigente, letto il 22 settembre 2026, cambia una parola rispetto alla citazione del
2014 su cui questo documento poggiava: le deliberazioni sono pubblicate "mediante
pubblicazione all'albo pretorio", dove il testo originario diceva "mediante affissione".
La sostanza resta identica, compreso "per quindici giorni consecutivi, salvo specifiche
disposizioni di legge", ma è il segno che l'articolo è stato allineato alla pubblicazione
online e non è rimasto fermo al 2000.

**Il comma 2 dice una cosa che il catalogo non aveva registrato**, e che riguarda proprio
gli enti diversi dai comuni: le deliberazioni degli altri enti locali si pubblicano all'albo
pretorio **del comune dove l'ente ha sede**, sempre per quindici giorni consecutivi, salvo
specifiche disposizioni. Per un componente riusabile la conseguenza è concreta: esiste un
caso, previsto dalla legge, in cui l'atto di un ente compare sull'albo di un altro ente. Il
catalogo lo sfiora soltanto, nominando fra i tipi di atto quelli "di altro ente ospitato",
ma nessun requisito dice chi ne governa la scadenza e chi risponde dei dati personali.

**L'art. 134 dice a cosa serve quella durata**, ed è l'articolo che mancava per rispondere
alla domanda se il termine si possa accorciare. Il comma 3: le deliberazioni non soggette a
controllo "diventano esecutive dopo il decimo giorno dalla loro pubblicazione". Il comma 4:
in caso di urgenza il consiglio o la giunta possono dichiarare la deliberazione
immediatamente eseguibile, con il voto della maggioranza dei componenti. I commi 1 e 2
riguardano il controllo del comitato regionale, che la riforma costituzionale del 2001 ha
lasciato senza fondamento: il comma 3 è quindi oggi la regola generale e non il caso
residuale, ed è una lettura da confermare, non un dato letto.

| Obbligo | Requisiti | Copertura |
|---|---|---|
| La pubblicazione ha una durata, e la durata dipende dal tipo di atto e dalla norma applicabile | ALBO-04 | scoperto |
| La data di pubblicazione fa decorrere un termine con effetti propri fuori dal componente: per le deliberazioni indicate dall'art. 134 comma 3, cioè quelle non soggette a controllo necessario né sottoposte a controllo eventuale, l'esecutività interviene dopo il decimo giorno dalla pubblicazione. Distinta l'immediata eseguibilità del comma 4 | ALBO-01, ALBO-07 | scoperto, ed è una conseguenza scoperta oggi: non tocca al componente calcolare l'esecutività, ma la data di inizio che registra e il referto che ne attesta le date diventano il presupposto di un effetto giuridico, non due campi qualsiasi |

Due righe di `collaudo.md` chiudono ALBO-04 ed entrambe sono "da fare": la pubblicazione di
un tipo senza durata configurata deve essere bloccata, e un controllo statico deve
dimostrare che la costante 15 non compare nel codice. La seconda è la riga che tiene in
piedi la riusabilità del componente, perché una durata cablata non si vede finché non si
installa il plugin su un ente che ha termini diversi.

### GAR e i provvedimenti: il tempo è la violazione

È il gruppo che decide l'architettura. I provvedimenti colpiscono la stessa cosa da due
lati: atti rimasti esposti troppo a lungo (P-2, P-3, P-4) e atti che rivelavano dati che non
avrebbero dovuto essere lì (P-1, P-4). Il Garante ha dichiarato nel maggio 2026 che GAR è
**in corso di aggiornamento ma ancora attuale nella parte sostanziale** (P-3), quindi resta
la fonte di riferimento, e vale la pena controllare se l'aggiornamento è uscito prima di
rilasciare il componente.

| Obbligo | Requisiti | Copertura |
|---|---|---|
| Alla scadenza del periodo la diffusione cessa. Che ciò avvenga da solo e senza dipendere dal traffico del sito è scelta di prodotto: la nota 53 di GAR 2.b propone la rimozione automatica "a titolo esemplificativo" e contempla, in sua assenza, verifiche periodiche | ALBO-02 per l'obbligo, ALBO-03 e ALBO-19 per il modo scelto | avviato per ALBO-02, scoperto per gli altri due |
| L'atto scaduto esce dalla vista pubblica su ogni percorso, allegati compresi | ALBO-05, ALBO-18, ALBO-20 | avviato per ALBO-05 e ALBO-18, scoperto per ALBO-20 |
| Si adottano accorgimenti idonei a evitare l'indicizzazione nei motori generalisti. GAR 3.a lo "consiglia" e GAR 2.a dice "ove possibile": è una raccomandazione, e le misure concrete sono scelta di prodotto | ALBO-06 | avviato, solo per la parte dichiarativa |
| I dati eccedenti non finiscono nella versione pubblica | ALBO-12 | scoperto |
| Il regime di pubblicità non autorizza da solo a pubblicare qualunque atto | ALBO-11 | scoperto |

Cosa vuol dire "avviato", qui, in concreto.

- **ALBO-02** poggia su un comportamento del componente comune già verde: un contenuto
  gestito **senza** data di fine, o con una data corrotta, risulta scaduto, quindi
  invisibile. Il meccanismo sbaglia nella direzione sicura. Quello che manca è il rifiuto
  esplicito al passaggio a pubblicato, con il messaggio che dice qual è il dato mancante:
  oggi un atto senza data di fine non sarebbe visibile, ma nessuno avvisa chi lo ha scritto.
- **ALBO-05 e ALBO-18** poggiano sul filtro di scadenza del componente comune, che è
  costruito e verde sui dodici percorsi di lettura (C-10..C-21), e sulla politica
  `irraggiungibile` che l'albo dichiara e che viene riletta dall'interfaccia pubblica del
  componente comune (riga A-07, verde). Manca il tredicesimo percorso, l'URL diretto
  dell'allegato (C-33), perché la consegna protetta dei file non è ancora costruita nel
  componente comune. Finché manca, un allegato caricato nella cartella predefinita di
  WordPress resta scaricabile anche dopo la defissione, ed è esattamente il caso
  sanzionato.
- **ALBO-06** è avviato solo a metà: la politica `vietata` è dichiarata e riletta (A-07),
  ma il meccanismo che la applica, cioè il `noindex` sulle pagine e l'esclusione dalla mappa
  per i motori, non esiste ancora nel componente comune. Dichiarare una politica che nessuno
  applica non produce nessun effetto sulle pagine. **E il requisito è scritto stretto**: GAR
  indica due accorgimenti tecnici, i metatag `noindex` **e `noarchive`** nelle intestazioni
  delle pagine, oppure le regole di esclusione nel file `robots.txt` secondo il Robot
  Exclusion Protocol, e raccomanda di privilegiare la ricerca interna al sito invece di quella
  dei motori. ALBO-06 nomina il solo `noindex`. Avverte anche che nessuno dei due
  accorgimenti agisce su ciò che è già stato indicizzato, la cui rimozione va chiesta al
  singolo motore: è una cosa che l'ente deve sapere e che nessun requisito oggi gli dice.

**Una precisazione che cambia il peso di ALBO-06.** In P-1 l'ente aveva impedito
l'indicizzazione per tutta la durata della pubblicazione, e il Garante lo ha sanzionato lo
stesso: la non indicizzazione è pesata solo come circostanza nella gravità, non come
esimente. Lo stesso in P-5. Quindi `noindex` non è un sostituto della rimozione, ed è
sbagliato trattare ALBO-06 come una misura che compra tempo su ALBO-18.

**Una precisazione che allarga ALBO-05, e va decisa.** In P-4 il Garante dice che togliere
l'archivio storico degli atti dal sito **non è un obbligo** di protezione dei dati, e che
l'ente può continuare a tenere gli atti pubblicati in una sezione di archivio, purché i dati
personali siano oscurati. L'obbligo di norma, quindi, è che dopo la scadenza non siano più
diffusi i dati personali, non che l'indirizzo smetta di rispondere. Rendere l'atto
irraggiungibile è una scelta nostra, più prudente di quanto la norma pretenda, e la riga di
`requisiti.md` che marca ALBO-05 come "norma" andrebbe ristretta al risultato vero,
lasciando `irraggiungibile` nella parte di prodotto.

### Il paragrafo di GAR dedicato proprio all'albo pretorio

GAR ha, nella parte seconda, un paragrafo 3.a intitolato "Albo pretorio online degli enti
locali". È il testo più vicino al componente che esista in tutta la normativa, e vale la
pena leggerne le regole una per una, perché quasi ognuna corrisponde a un requisito.

| Cosa dice GAR 3.a | Requisiti |
|---|---|
| Prima di pubblicare un atto con dati personali l'ente verifica che esista una norma che prescriva l'affissione di **quell'atto**, e per i dati particolari e giudiziari deve essere una norma di legge | ALBO-11 |
| Trascorso il periodo di pubblicazione, gli enti locali **non possono continuare a diffondere** i dati personali contenuti negli atti | ALBO-05, ALBO-18 |
| La permanenza oltre i quindici giorni può integrare una violazione, se non esiste un diverso parametro che preveda quella diffusione | ALBO-04 |
| Se la norma non indica una durata, l'amministrazione **deve individuare un congruo periodo**, non superiore a quello necessario allo scopo per cui l'atto è stato adottato | ALBO-02, ALBO-04 |
| All'albo **non si applica** l'arco dei cinque anni della trasparenza | ALBO-15 |
| Se l'ente vuole tenere gli atti nel sito dopo la scadenza, per esempio in una sezione di archivio, deve oscurare i dati che identificano anche indirettamente le persone | ALBO-05 |
| Si **consiglia** di adottare accorgimenti tecnici per evitare l'indicizzazione nei motori generalisti, perché la reperibilità indiscriminata è sproporzionata rispetto alla finalità | ALBO-06 |

Tre osservazioni che cambiano qualcosa nel catalogo.

**ALBO-02 e ALBO-04 hanno finalmente una fonte diretta.** Finora la data di fine
obbligatoria era motivata con i provvedimenti sanzionatori, che sono la prova di come va a
finire e non la regola. Qui la regola c'è, scritta: dove la norma non fissa un termine, è
l'amministrazione a doverne individuare uno, e non può superare il tempo necessario allo
scopo. È esattamente il comportamento di ALBO-04, cioè una durata obbligatoria per ogni
tipo di atto senza valore predefinito, e di ALBO-02, cioè nessun atto pubblicato senza data
di fine.

**ALBO-03 e ALBO-19 non sono soltanto scelte nostre.** La nota 53 del paragrafo 2.b
suggerisce, **a titolo esemplificativo**, sistemi di pubblicazione capaci di attribuire alla
documentazione un intervallo di permanenza tramite metadati, con rimozione anche automatica,
e aggiunge che in assenza di meccanismi automatizzati vanno previste procedure di verifica
periodica della validità temporale.

Va letta per quello che è. L'obbligo della fonte è uno solo, cioè che alla scadenza la
diffusione cessi; l'automatismo e la verifica periodica sono due modi alternativi di
arrivarci, proposti come esempio. La nota non impone quindi né il compito pianificato né il
battito di controllo, e soprattutto non impone la coppia: nel testo del Garante la verifica
periodica è l'alternativa a chi l'automatismo non ce l'ha, non il controllore
dell'automatismo altrui. ALBO-03 e ALBO-19 restano scelte di prodotto, e la colonna di
`requisiti.md` che li dà come tali va lasciata com'è. Quello che la nota aggiunge è che la
scelta non è eccentrica: la fonte la contempla.

**L'indicizzazione è raccomandata, non imposta.** GAR usa "si consiglia", e la ragione data
è la sproporzione. Questo non indebolisce ALBO-06, lo colloca: è una misura che riduce il
danno, mentre l'obbligo vero e proprio è smettere di diffondere. Va letto insieme al
provvedimento in cui l'ente aveva impedito l'indicizzazione ed è stato sanzionato lo stesso.

Una nota su ALBO-14. Il paragrafo 2.c è prescrittivo e non consultivo: "Devono essere
adottate opportune cautele per ostacolare operazioni di duplicazione massiva dei file
contenenti dati personali". Quello che resta aperto è **quali** cautele, perché il Garante
non impone una misura tecnica e fa esempi. Il paragrafo non dice niente sull'accessibilità,
quindi il bilanciamento fra le due cose, che è il contenuto di ALBO-14, è una decisione
nostra fra due obblighi, non fra un consiglio e un obbligo, e va scritto così.

### Se la durata si possa accorciare, e quando un atto si toglie prima

Due domande arrivate dal lavoro su ALBO-22, cioè dalla decisione ancora aperta sugli stati
dell'atto. Sono qui e non lì perché la risposta non è una scelta di prodotto: dipende da
cosa dicono le norme, ed è questo il documento che le legge.

**Prima domanda: un ente può accorciare la pubblicazione di un atto regolare?**

Dai testi disponibili la risposta è no, e la durata va letta come un adempimento che
produce effetti propri, non come un tetto massimo che il redattore può abbassare. Tre
appoggi, in ordine di solidità.

Il primo è il testo dell'art. 124 del TUEL, che GAR cita per esteso: le deliberazioni "sono
pubblicate mediante affissione all'albo pretorio ... per quindici giorni consecutivi, salvo
specifiche disposizioni di legge". La frase prescrive una durata, non un limite: "sono
pubblicate per quindici giorni" non è "possono restare fino a quindici giorni", e
"consecutivi" esclude che il periodo si interrompa. La valvola di sfogo esiste ma è
intestata ad altri: "salvo specifiche disposizioni di legge" ammette che **un'altra norma**
fissi un termine diverso, non che lo accorci l'amministrazione che pubblica.

Il secondo è l'art. 32 della legge 69/2009: gli obblighi di pubblicazione con effetto di
pubblicità legale "si intendono assolti con la pubblicazione nei propri siti informatici".
Il verbo è quello di un adempimento, e un adempimento interrotto a metà non è assolto.
È anche la ragione per cui questa domanda non è la stessa che si pone sulla trasparenza:
lì la pubblicazione **è** la finalità, qui la pubblicazione è la forma con cui un atto
produce i suoi effetti verso i terzi.

Il terzo serve soprattutto a chiudere una porta che sembrava aperta. Nella parte prima, al
paragrafo 7, GAR scrive che i dati personali pubblicati per finalità di trasparenza "devono
essere oscurati, anche prima del termine di cinque anni, quando sono stati raggiunti gli
scopi per i quali essi sono stati resi pubblici e gli atti stessi hanno prodotto i loro
effetti". Letta fuori contesto sembra la regola che autorizza a togliere prima.

Quella regola è però formulata per la trasparenza, dentro il paragrafo che governa la durata
quinquennale dell'art. 8 del d.lgs. 33/2013, e GAR 3.a dice che all'albo quell'arco dei
cinque anni non si applica. Questo basta a dire che la regola **non si trasferisce da sola**
all'albo: non basta a dire che sull'albo un oscuramento anticipato sia escluso. La ragione
che GAR dà al paragrafo 7 non è il calendario ma la necessità e la proporzionalità, che sono
principi generali, quindi l'esclusione del quinquennio non chiude la questione. Il rapporto
fra il termine di legge dell'albo e un oscuramento anticipato **resta aperto**, e questo
appoggio vale meno degli altri tre: serve a togliere di mezzo un'obiezione, non a fondare la
conclusione.

Il quarto appoggio è arrivato dopo, ed è quello che trasforma la risposta da prudente in
confermata. L'art. 134 del TUEL, letto nel testo vigente il 22 settembre 2026, dice al comma
3 che le deliberazioni "non soggette a controllo necessario o non sottoposte a controllo
eventuale" diventano esecutive "dopo il decimo giorno dalla loro pubblicazione". Va preso
per quello che dice, senza allargarlo: **la pubblicazione è il riferimento temporale di un
effetto giuridico**, quindi la durata non è un contenitore vuoto e indifferente. L'obbligo
di tenere l'atto esposto quindici giorni resta però fondato sull'art. 124, non su questo
comma, perché l'art. 134 non dice nulla su che cosa accada se la pubblicazione si
interrompe.

Un argomento e un fatto rendono la conclusione più solida di quanto un solo comma farebbe
pensare, e conviene tenerli distinti perché il primo è ragionamento e il secondo è testo.

L'argomento, che è di chi scrive e non di una fonte: i due termini partono insieme e hanno
lunghezza diversa, dieci giorni per l'esecutività e quindici per la pubblicazione. Se la
durata fosse un tetto da abbassare una volta servito lo scopo, il decimo giorno sarebbe il
taglio naturale, e la legge invece lascia correre la pubblicazione fino al quindicesimo. A
che cosa servano quei cinque giorni in più i testi letti non lo dicono, e qui non si
inventa: quello che l'argomento mostra è che la durata non coincide con il tempo necessario
all'efficacia, non quale sia la sua funzione.

Il fatto, che sta nel testo: l'urgenza ha già la sua valvola, ed è
il comma 4, cioè la dichiarazione di immediata eseguibilità votata dalla maggioranza dei
componenti. Opera sull'**efficacia dell'atto**, non sulla durata della pubblicazione, che
resta quella. L'ordinamento ha quindi previsto il caso "serve che valga subito" e vi ha
risposto senza toccare l'albo. Un componente che offrisse di accorciare il termine
offrirebbe una scorciatoia a un problema che la legge risolve altrove.

Resta una sola incertezza, e riguarda il peso del comma 3, non il suo contenuto. I commi 1 e
2 dell'art. 134 parlano del controllo preventivo del comitato regionale, che la riforma
costituzionale del 2001 ha privato di fondamento: se è così, oggi quasi tutte le
deliberazioni ricadono nel comma 3, che da caso residuale diventa la regola. Questa parte è
una lettura mia e va confermata, ma rafforza la conclusione invece di indebolirla.

Conclusione operativa: **accorciare il termine di un atto regolare non è una funzione che il
componente offre**, e nessuna maschera deve permettere di farlo come normale operazione di
redazione. Regge in primo luogo sull'art. 124, che è testo vigente letto e non citazione di
seconda mano: "sono pubblicate ... per quindici giorni consecutivi". Gli altri appoggi la
circondano, e quello sull'oscuramento anticipato vale meno degli altri, per la ragione detta
sopra.

**Seconda domanda: quali rimozioni anticipate sono invece pacifiche?**

Sono pacifiche per una ragione precisa, e la ragione è più utile dell'elenco: **in nessuna
di esse la rimozione è una scelta di durata**. Ognuna toglie dalla vista una pubblicazione
che in quella forma non doveva esserci, e lo fa perché una diffusione vietata deve cessare,
non perché si sia deciso che quindici giorni erano troppi. È la differenza che tiene insieme
le due risposte.

Va però detto subito dove questa ricostruzione si ferma, perché il confine è sottile. I
testi letti dicono che quelle diffusioni devono cessare. **Non dicono che cosa accada al
periodo di pubblicazione**: se continui a correre, se resti sospeso, se l'atto vada
ripubblicato in forma corretta per compiere il termine. Su questo non c'è fonte fra quelle
lette, e non è una domanda che il componente possa risolvere per conto dell'ente: la
risposta dipende dalla disciplina applicabile all'atto o, nel caso dell'ordine, dal suo
contenuto. Quello che il componente deve fare è registrare l'interruzione e la sua causa in
modo che l'ente possa decidere, non decidere al suo posto.

| Causa | Cosa dice la fonte | Cosa fa il sistema |
|---|---|---|
| Dati eccedenti, non pertinenti o non necessari | GAR 3.a richiama per l'albo il "divieto di diffondere dati personali non necessari, non pertinenti o eccedenti" della parte seconda, par. 1 | non si toglie l'atto: si sostituisce il file con la versione oscurata. È esattamente ALBO-12. Se questo basti a far compiere il termine, o se la sostituzione incida sul periodo, i testi non lo dicono |
| Dati genetici, biometrici o relativi alla salute | art. 2-septies comma 8 del d.lgs. 196/2003, letto nel testo vigente: "I dati personali di cui al comma 1 non possono essere diffusi". Divieto assoluto, senza rinvio a norme che lo deroghino. GAR 3.a richiama per l'albo lo stesso divieto nella formulazione del Codice di allora | la diffusione è vietata, quindi l'oscuramento è l'unico stato lecito. Se il documento non è oscurabile, il file esce |
| Pubblicazione priva di base normativa | GAR 3.a: prima di pubblicare, l'ente verifica l'esistenza di una norma che prescriva l'affissione **di quell'atto** | non c'è un termine da accorciare, perché non c'era un obbligo di pubblicare quell'atto: la pubblicazione non doveva iniziare |
| Ordine di un'autorità | esterna al componente (Garante, autorità giudiziaria). Nessuno dei testi letti la disciplina, e l'ordine può avere contenuti diversi fra loro | si esegue e si registra chi l'ha disposto. Gli effetti sul periodo li determina l'ordine, non il sistema |

Fuori dall'elenco resta il caso che non è pacifico, ed è bene che resti fuori: la richiesta
dell'interessato su un atto pubblicato legittimamente e con dati pertinenti. Lì il diritto
alla cancellazione dell'art. 17 del GDPR incontra l'eccezione dello stesso articolo per gli
obblighi di legge, e il bilanciamento lo fa l'ente con il suo responsabile della protezione
dei dati. **Il componente non decide: registra la decisione e chi l'ha presa**, che è quello
che ALBO-10 già chiede.

Conseguenza per la forma stretta di ALBO-22: la rimozione anticipata non è uno stato che il
redattore raggiunge quando vuole, ma due transizioni distinte, la sostituzione con versione
oscurata e la rimozione per le cause in cui la pubblicazione non doveva esistere in quella
forma. Entrambe chiedono una causa fra quelle dell'elenco e la registrano. Quello che nessuna
delle due deve fare è decidere da sola che il termine è compiuto: l'effetto sul periodo è
una valutazione dell'ente, quindi va chiesto e registrato, non calcolato. La terza
possibilità, cioè accorciare il termine di un atto regolare, non è una transizione e non va
prevista.

**Conseguenza per ALBO-07.** Se una rimozione anticipata può esistere, il periodo davvero
compiuto non coincide più con la data di fine memorizzata, e l'unico posto in cui quel
periodo vive per intero è il registro delle operazioni. Il referto deve quindi essere
costruito sul registro, non sulla data di fine dell'atto: altrimenti attesta un'esposizione
che non c'è stata. Vale anche nel caso della sostituzione con versione oscurata, dove non è
il periodo a cambiare ma il file, e il referto porta l'impronta di un file che per una parte
del termine non era quello esposto. È un vincolo sul contenuto di ALBO-07 che si aggiunge al
fatto che ALBO-07 è ancora un requisito da confermare.

### GDPR artt. 5 e 6

| Obbligo | Requisiti | Copertura |
|---|---|---|
| Minimizzazione: si pubblica il minimo necessario (art. 5.1.c) | ALBO-12 | scoperto |
| Limitazione della conservazione: i dati restano identificabili per il tempo necessario (art. 5.1.e) | ALBO-02, ALBO-03, ALBO-05, ALBO-18 | vedi sopra |
| Responsabilizzazione: l'ente deve poter dimostrare cosa è stato fatto (art. 5.2) | ALBO-10 | scoperto |
| Base giuridica del trattamento (art. 6) | ALBO-11 come passaggio che la fa dichiarare, la valutazione resta all'ente | scoperto |

Una precisazione sulla tabella delle fonti di `requisiti.md`: l'art. 5.1.e è il principio
che regge l'intera architettura della scadenza, ed è l'articolo su cui poggiano i due
provvedimenti del marzo 2026. Oggi "GDPR art. 5" compare come fonte del solo ALBO-10, e i
requisiti della scadenza citano il risultato tramite i provvedimenti. Vale la pena nominare
l'art. 5.1.e per esteso su ALBO-02 e ALBO-18: cambia la solidità dell'argomento quando la
riga va discussa con chi la deve approvare.

### COD artt. 2-ter e 2-septies

| Obbligo | Requisiti | Copertura |
|---|---|---|
| Un soggetto pubblico tratta dati particolari e giudiziari solo alle condizioni previste, e la pubblicazione è un trattamento | ALBO-11 | scoperto |
| La pubblicazione su una pagina aperta a chiunque è "diffusione" ai sensi dell'art. 2-ter comma 4 lettera b. Le basi che il comma 1 elenca sono la norma di legge, quella di regolamento e l'atto amministrativo generale; i commi 1-bis e 3 ne aggiungono un'altra per casi la cui applicabilità all'albo resta da chiarire | ALBO-11 | scoperto |
| I dati genetici, biometrici e relativi alla salute **non possono essere diffusi** (art. 2-septies comma 8), senza eccezioni e senza rinvio a una norma che li autorizzi | ALBO-11, ALBO-12 | scoperto |

**L'art. 2-ter è stato letto nel testo vigente il 22 settembre 2026, e sposta l'appoggio di
ALBO-11.** Il comma 4 lettera b definisce "diffusione" il dare conoscenza di dati personali
a soggetti indeterminati, in qualunque forma: pubblicare un atto sull'albo è diffusione
nel senso proprio del termine, e questo non era mai stato scritto nero su bianco qui.

Il comma 1 dice poi che la base giuridica dell'art. 6 paragrafo 3 lettera b del GDPR è
costituita "da una norma di legge o di regolamento o da atti amministrativi generali". Sono
tre canali, non due: GAR cita per l'albo una formula che si ferma a legge e regolamento,
quella del vecchio art. 19 comma 3 del Codice. Che quell'articolo sia stato abrogato nel
2018 **non risulta dai testi letti**, ed è un'affermazione fatta a memoria: qui si dice solo
che la formula citata da un documento del 2014 e quella del comma 1 vigente sono diverse, e
che ALBO-11 va scritto contro la seconda.

Il comma 1 non è però l'elenco chiuso di ogni diffusione pubblica, e presentarlo così
sarebbe lo stesso errore. Il comma 3 aggiunge "o se necessarie ai sensi del comma 1-bis",
con notizia al Garante almeno dieci giorni prima, e riguarda la diffusione e la
comunicazione "a soggetti che intendono trattarli per altre finalità". Che questa strada non
copra la pubblicazione istituzionale dell'albo è la lettura di chi scrive, non confermata da
nessun provvedimento fra quelli letti, ed è annotata come tale anche più avanti: non può
quindi diventare una regola certa nella tabella né dentro ALBO-11.

Qui però si apre una domanda che vale la pena tenere aperta invece che risolvere in fretta,
perché è esattamente il punto su cui P-5 ha sanzionato. Se un atto amministrativo generale
basta come base giuridica, sembrerebbe che il regolamento dell'ente possa reggere la
pubblicazione. P-5 dice di no dove la materia è già regolata in modo uniforme a livello
nazionale, perché non sono ammessi livelli di tutela diversi da un ente all'altro. Le due
cose stanno insieme, ma solo se ALBO-11 chiede all'ente **quale** norma o atto invoca e non
si accontenta di una spunta: il canale esiste, non è libero, e la differenza fra i due casi
non la può decidere il componente.

Un avvertimento su cosa non è stato verificato. Il comma 1-bis, aggiunto nel 2021, consente
alle amministrazioni il trattamento necessario a un compito di interesse pubblico anche
fuori dai tre canali del comma 1, e il comma 3 vi collega una diffusione verso terzi
subordinata a un avviso al Garante dieci giorni prima. Riguarda la diffusione per finalità
**diverse** da quella originaria, quindi non sembra toccare la pubblicazione istituzionale
dell'albo, ma la lettura è mia e non è confermata da nessun provvedimento fra quelli letti.

**L'art. 2-septies è stato letto nel testo vigente il 22 settembre 2026, e il comma 8 è una
riga sola che vale più di tutto il resto della sezione**: "I dati personali di cui al comma 1
non possono essere diffusi". Il comma 1 sono i dati genetici, biometrici e relativi alla
salute. Il divieto è assoluto: non dice "salvo che una norma lo preveda", che è invece la
struttura di tutto il resto del Codice.

La conseguenza su ALBO-11 è che il requisito non può avere una logica sola. Ne servono tre,
per tre categorie di dati.

| Categoria | Cosa deve verificare ALBO-11 |
|---|---|
| Dati comuni | che esista una delle basi elencate dall'art. 2-ter comma 1, cioè una norma di legge, di regolamento o un atto amministrativo generale che prescriva la pubblicazione di quell'atto. Resta aperto se la strada dei commi 1-bis e 3 valga anche per l'albo: finché non è chiarita, ALBO-11 non può trattare quelle tre come l'elenco completo |
| Dati genetici, biometrici, relativi alla salute | niente da verificare, perché la risposta è sempre no: non si diffondono. L'unica pubblicazione possibile è quella oscurata, e se il documento non è oscurabile non si pubblica |
| Altre categorie particolari dell'art. 9 del GDPR e dati su condanne e reati dell'art. 10 | restano fuori dal divieto specifico del comma 8, il che **non vuol dire** che basti la base giuridica generale: hanno condizioni e cautele proprie, che GAR 3.a richiama per l'albo chiedendo una norma di legge per i dati allora detti sensibili e giudiziari. Questa lettura non le ricostruisce, e ALBO-11 non può equipararle ai dati comuni |

Questa distinzione oggi non c'è, né in ALBO-11 né nella sua riga di collaudo, e non è un
raffinamento: il catalogo tratta "dati particolari e giudiziari" come un blocco unico, cioè
come se per tutti bastasse trovare la norma giusta. Per una delle tre categorie non basta
mai. Ed è esattamente il caso che P-1 ha sanzionato, dove l'atto rivelava dati sulla salute
per il richiamo a una norma sul collocamento mirato.

Un secondo punto dello stesso articolo, che non riguarda la pubblicazione ma va saputo: il
comma 1 subordina il trattamento di questi dati anche alle misure di garanzia che il Garante
adotta con cadenza almeno biennale. Sono un atto che cambia nel tempo e che il componente
non può inseguire, il che è un'altra ragione per non scrivere politiche sui dati delicati
dentro il codice.

È l'obbligo che P-1 sanziona. ALBO-11 non decide al posto dell'ente: impone un passaggio
che chiede una conferma esplicita e mostra i casi in cui un atto rivela dati delicati senza
nominarli, per esempio richiami a norme sul collocamento mirato, congedi per assistenza,
graduatorie con punteggi sociali, provvedimenti disciplinari, redditi e ISEE.

**Il punto che P-5 aggiunge, e che tocca più di un requisito.** Un regolamento
dell'amministrazione **non è** una base giuridica sufficiente per diffondere dati personali
dove la materia è già regolata in modo uniforme a livello nazionale: il Garante lo dice
perché non sono ammessi livelli di tutela diversi da un ente all'altro. Ha due conseguenze
per noi. La prima: il regolamento resta la fonte della **durata** di un tipo di atto, ma non
diventa per questo la base giuridica della **pubblicazione** di quell'atto, e ALBO-11 deve
continuare a chiedere quest'ultima. La seconda: quando si chiederà all'amministrazione di
confermare ALBO-07 e ALBO-08 contro il proprio regolamento, la conferma varrà per il come e
non per il se.

### LG-DOC: integrità e conservazione

| Obbligo | Requisiti | Copertura |
|---|---|---|
| Il documento informatico pubblicato è integro e immodificabile | ALBO-09 | scoperto |
| Gli atti sono versabili in conservazione | ALBO-17 | scoperto, e parziale come specifica |

ALBO-17 chiede l'esportazione in formato aperto di atti e metadati. Non dice **quali**
metadati, mentre l'allegato 5 delle linee guida ne fissa un insieme obbligatorio per il
documento informatico. Finché il requisito non lo nomina, la riga di collaudo può verificare
il formato e la reimportabilità, non la completezza rispetto alla conservazione: è un export
che si può rileggere, non necessariamente un pacchetto che un sistema di conservazione
accetta.

**E il punto non riguarda solo l'esportazione.** Letto l'allegato 5, diversi metadati
obbligatori sono informazioni che esistono **solo al momento della pubblicazione** e che
nessun requisito oggi chiede di catturare. Un export scritto dopo non se le può inventare.
Quelli che toccano l'albo da vicino:

- **impronta crittografica del documento**, con algoritmo (il valore predefinito è SHA-256).
  È la stessa impronta di ALBO-07, che oggi è un requisito da confermare: qui però serve per
  la conservazione, non per il referto, e il suo algoritmo è indicato.
- **dati di registrazione**, con tipo di registro (nessuno, protocollo, oppure repertorio o
  registro), numero e data del documento. Il numero di repertorio di ALBO-08, che il
  catalogo marca come ipotesi fondata sulla prassi, corrisponde a un metadato previsto: non
  lo rende obbligatorio, ma smentisce che sia soltanto prassi.
- **verifica**, cioè se il documento è firmato digitalmente, sigillato, marcato
  temporalmente, e se una copia immagine è conforme. È il posto in cui la questione della
  versione oscurata (punto 1 dei buchi) diventa un campo da valorizzare, non una discussione.
- **tracciature delle modifiche**, con tipo (annullamento, rettifica, integrazione,
  annotazione), autore, data e identificativo della versione precedente. È esattamente la
  rettifica come atto nuovo che rinvia al precedente di ALBO-09, e il modello dei metadati la
  prevede già: conviene adottare quei quattro tipi invece di inventarne altri.
- **modalità di formazione, tipologia documentale, soggetti con il loro ruolo, oggetto,
  numero di allegati, riservatezza, formato, nome e versione del documento**.

La conseguenza pratica è una dipendenza che oggi non è scritta da nessuna parte: **ALBO-17
non è un requisito a valle, vincola ALBO-01**. I formati di file, PDF compresi, stanno
nell'allegato 2 e non nel 6, che riguarda invece lo scambio di documenti protocollati.

### ACC legge 4/2004 art. 11, che oggi vuol dire linee guida AgID

| Obbligo | Requisiti | Copertura |
|---|---|---|
| I documenti pubblicati sono accessibili alle tecnologie assistive | ALBO-13 | scoperto |
| Nessuna misura anti prelievo che comprometta l'accessibilità | ALBO-14 | scoperto |
| Le funzioni di consultazione sono usabili senza mouse | ALBO-16, che ha l'accessibilità come vincolo | scoperto |

Una nota su ALBO-16, che sta qui solo per l'accessibilità ma ha una seconda ragione di
esistere: GAR raccomanda di privilegiare la ricerca interna al sito rispetto a quella dei
motori generalisti, proprio per gli atti pubblicati con finalità diverse dalla trasparenza.
La ricerca interna dell'albo non è quindi soltanto una comodità, è il modo in cui si rende
consultabile un archivio che non deve essere indicizzato.

ALBO-13 è dichiarato come avviso e non come blocco: il sistema esamina il PDF caricato e
segnala un indizio (sembra una scansione, manca la struttura), la pubblicazione resta
possibile. È una scelta prudente, perché un controllo automatico sull'accessibilità di un
PDF produce falsi negativi, e bloccare su un indizio significherebbe impedire la
pubblicazione di un atto valido.

**L'accessibilità dell'albo non arriva da fuori, sta dentro la norma che istituisce
l'albo.** È la scoperta del 22 settembre 2026, leggendo il testo vigente dell'art. 32 della
legge 69/2009: il comma 1 dice che la pubblicazione "è effettuata ... applicando i requisiti
tecnici di accessibilità di cui all'articolo 11 della legge 9 gennaio 2004, n. 4", e
aggiunge che la mancata pubblicazione in quei termini pesa sulla valutazione del dirigente
responsabile. Cambia due cose. La prima: l'accessibilità non è un obbligo generale del sito
che per caso ospita anche l'albo, è un modo di fare la pubblicazione, quindi riguarda tutto
ciò che il componente pubblica e non i soli PDF. La seconda: il catalogo dell'albo copre
oggi solo i documenti allegati (ALBO-13, ALBO-14) e la consultazione da tastiera (ALBO-16).
Le pagine che il componente genera, cioè l'elenco degli atti e la scheda del singolo atto,
non le copre nessuno. Vedi il punto 4 dei buchi.

**Citare l'art. 11 e fermarsi lì non basta più.** Quell'articolo è stato sostituito nel
2018 e oggi non contiene nessun requisito tecnico: dice che è AgID a emanare le linee
guida, e sono quelle a fissare i requisiti, le metodologie di verifica, il modello di
dichiarazione, il monitoraggio e i casi di onere sproporzionato. Il decreto ministeriale
dell'8 luglio 2005, che conteneva i requisiti tecnici, è abrogato. Per l'albo la
conseguenza è pratica: il criterio di ALBO-13 sui documenti allegati è il capitolo della
norma armonizzata europea dedicato ai documenti non web, non un articolo di legge, e chi
scriverà quel collaudo deve avere davanti le linee guida, non la legge 4/2004.

### TRASP d.lgs. 33/2013

| Obbligo | Requisiti | Copertura |
|---|---|---|
| Albo e amministrazione trasparente restano due esposizioni distinte, con durate e finalità proprie | ALBO-15 | scoperto |

Due articoli che i provvedimenti citano e che `requisiti.md` non nomina: l'**art. 7-bis
comma 3**, che impone di pubblicare i soli dati personali necessari alla finalità di
trasparenza, e l'**art. 8 comma 3**, che fissa in cinque anni la permanenza sul sito dei
documenti soggetti agli obblighi di trasparenza. Il secondo è la ragione concreta per cui
ALBO-15 esiste: in P-1 il termine dei quindici giorni dell'albo era stato rispettato, e la
violazione è arrivata dalla **seconda esposizione**, quella in amministrazione trasparente,
che è rimasta su. Due cicli di vita indipendenti non sono un'eleganza architetturale: sono il
modo in cui quel caso non si ripete.

Qui la regola sull'indicizzazione è **invertita** rispetto all'albo: la trasparenza va
indicizzata, l'albo no. È il motivo per cui il componente comune pretende la politica di
indicizzazione come parametro esplicito e non ha un valore predefinito: un default,
qualunque sia, sarebbe quello sbagliato per metà dei componenti.

## Riepilogo della copertura

| Obbligo, in una riga | Requisiti | Copertura |
|---|---|---|
| L'atto è identificabile e completo | ALBO-01 | scoperto |
| La pubblicazione ha un termine | ALBO-02 | avviato |
| Il termine si applica da solo e in tempo, che è il modo scelto e non un obbligo della fonte | ALBO-03, ALBO-19 | scoperto |
| Esiste una durata, dipendente dal tipo di atto | ALBO-04 | scoperto |
| L'atto scaduto esce dalla vista pubblica | ALBO-05, ALBO-18 | avviato, manca il percorso dell'allegato |
| L'atto defisso non resta servito da una cache | ALBO-20 | scoperto |
| Si adottano accorgimenti contro l'indicizzazione, che la fonte raccomanda | ALBO-06 | avviato solo nella dichiarazione |
| Il documento pubblicato è integro | ALBO-09 | scoperto |
| Le operazioni sono tracciate | ALBO-10 | scoperto |
| I dati particolari hanno una base giuridica dichiarata | ALBO-11 | scoperto |
| I dati eccedenti non finiscono nel pubblico | ALBO-12 | scoperto |
| I documenti sono accessibili | ALBO-13, ALBO-14, ALBO-16 | scoperto |
| Albo e trasparenza restano separati | ALBO-15 | scoperto |
| Gli atti sono versabili in conservazione | ALBO-17 | scoperto, specifica parziale |
| La scadenza si calcola sull'ora civile | ALBO-21 | avviato, verde nel componente comune (C-23, C-24) |

## Cosa blocca la copertura

Nessuno dei requisiti scoperti è fermo per indecisione. La maggior parte aspetta un
meccanismo del componente comune che non è ancora costruito. Quel componente oggi ha le
sezioni con le politiche, i tipi di contenuto, il contratto della data di fine, il filtro di
scadenza sui dodici percorsi di lettura e la guardia di compatibilità. Manca il resto.

| Meccanismo comune che manca | Requisiti che tiene fermi |
|---|---|
| Consegna protetta degli allegati | ALBO-01 nella parte dei file, ALBO-12, e il tredicesimo percorso di ALBO-05 e ALBO-18 |
| Applicazione della politica di indicizzazione | ALBO-06 |
| Compito pianificato e registro delle modifiche | ALBO-03, ALBO-10 |
| Battito di controllo | ALBO-19 |

Due requisiti non dipendono dal componente comune e sono comunque fermi: ALBO-22, che è una
decisione incompleta e va chiusa prima di scrivere il flusso di pubblicazione, e ALBO-07 e
ALBO-08, che aspettano una conferma sul regolamento dell'amministrazione.

## Obblighi e domande senza requisito

Questa è la parte che la tabella per requisito non può mostrare. Ogni voce dice cosa manca
e quanto è solida: **buco** vuol dire che l'obbligo esiste ed è scoperto, **da verificare**
vuol dire che la fonte va letta prima di decidere se produce un obbligo per il componente.

1. **Versione oscurata e corrispondenza con l'atto adottato, buco.** Gli atti pubblicati
   sono di norma firmati digitalmente, ma apporre o verificare una firma non è compito del
   componente: la firma è un atto dell'ente e resta fuori dal sistema. Il punto scoperto è
   un altro. ALBO-12 prevede di pubblicare una versione oscurata, che è **un file diverso**
   dall'originale, quindi la firma apposta sull'originale non vale su di essa. L'unica cosa
   che oggi legherebbe il file esposto all'atto adottato è l'impronta del file pubblicato,
   che sta dentro ALBO-07, cioè dentro un requisito **da confermare**: se ALBO-07 non viene
   confermato, ALBO-12 pubblica un documento che nessun elemento del sistema lega
   all'originale. Non serve codice nuovo per chiuderlo, serve decidere se la coppia
   originale più oscurata la governa una procedura dell'ente, e scriverlo dentro ALBO-12.
   L'allegato 5 delle linee guida AgID dà anche il posto in cui la risposta va registrata:
   fra i metadati obbligatori c'è la **verifica**, cioè se il documento è firmato,
   sigillato, marcato temporalmente, e se una copia immagine è conforme all'originale.
2. **Metadati per la conservazione, buco parziale.** ALBO-17 dice "formato aperto" e non
   dice quali metadati, e alcuni di quelli obbligatori si possono catturare solo al momento
   della pubblicazione. Vedi la sezione LG-DOC: non è un buco di ALBO-17 soltanto, è una
   dipendenza su ALBO-01.
3. **La fonte della durata non si registra, buco.** ALBO-04 rende la durata configurabile
   per tipo di atto, ed è giusto. Nessun requisito chiede però di registrare **perché**
   quella durata è quella: quale norma o quale articolo del regolamento la giustifica.
   Senza, l'ente ha un numero in una casella e non ha come dimostrare da dove viene, che è
   proprio ciò che l'art. 5.2 del GDPR gli chiede. Costa poco: un campo di testo accanto
   alla durata, riportato nell'esportazione della configurazione.
4. **Accessibilità delle pagine prodotte dal componente, assegnazione mancante, e il
   prezzo è salito.** L'obbligo non è in discussione e ora ha un appoggio diretto invece che
   generale: l'art. 32 comma 1 della legge 69/2009, letto il 22 settembre 2026, dice che la
   pubblicazione "è effettuata ... applicando i requisiti tecnici di accessibilità"
   dell'art. 11 della legge 4/2004, e che la mancata pubblicazione in quei termini rileva
   sulla valutazione del dirigente responsabile. Non è l'obbligo di accessibilità del sito
   applicato anche all'albo: è un requisito del pubblicare, e riguarda quindi tutto ciò che
   si pubblica, non i soli PDF. Due precisazioni che il rilievo di una revisione ha imposto.
   L'obbligo grava sull'amministrazione, non sul software: che il componente risponda
   dell'accessibilità delle pagine che genera è una scelta di attuazione da fare, e la
   ripartizione fra componente, tema e infrastruttura va definita, non dedotta dalla norma.
   E la norma collega la mancanza alla valutazione del dirigente: che cosa accada alla
   validità della pubblicazione questi testi non lo dicono.
   Nel catalogo dell'albo l'accessibilità compare però solo sui PDF (ALBO-13, ALBO-14) e
   come vincolo della ricerca (ALBO-16). L'elenco degli atti e la scheda del singolo atto
   sono pagine generate dal componente, e nessuna riga dice se ne risponde il componente o
   il tema del sito che lo ospita. Non è una norma che manca, è un'assegnazione che manca, e
   finché non c'è al collaudo non la verifica nessuno dei due. Nota sulle fonti: i requisiti
   di accessibilità hanno fonti più recenti dell'art. 11 della legge 4/2004 richiamato qui,
   a partire dalle linee guida AgID e dalla norma tecnica europea. Questo documento non le
   censisce perché l'accessibilità si specifica una volta sola per tutti i componenti, non
   una volta per ciascuno.
5. **La data di inizio non è governata da niente, domanda aperta.** ALBO-01 elenca la data
   di inizio fra i dati dell'atto, e `dati.md` dice che il sistema la propone e il redattore
   la conferma. Nessun requisito e nessuna riga di collaudo dice cosa succede se quella data
   è **nel futuro**: il componente comune conosce la sola data di fine, quindi oggi un atto
   pubblicato con inizio futuro sarebbe visibile subito. Va deciso se è un caso che esiste
   (pubblicazione preparata in anticipo) e, se esiste, chiuso dentro ALBO-22 insieme alle
   transizioni. L'art. 134 comma 3 del TUEL, letto il 22 settembre 2026, alza il prezzo di
   questo buco: è dalla data di pubblicazione che decorre il decimo giorno oltre il quale la
   deliberazione diventa esecutiva. Una data di inizio sbagliata non produce solo una pagina
   visibile nel momento sbagliato, sposta la data in cui un atto acquista efficacia.
6. **Defissione anticipata: tracciata ma non definita, buco, con una parte della domanda
   normativa risolta e una ancora aperta.** ALBO-10 chiede che il registro contenga chi ha
   disposto una defissione
   anticipata e perché. Nessun requisito definisce la defissione anticipata come funzione:
   chi può disporla, con quali effetti sul referto, se l'atto resta consultabile
   all'amministrazione. La sezione "Se la durata si possa accorciare, e quando un atto si
   toglie prima" risponde alle due domande su cui il buco poggiava: accorciare il termine di
   un atto regolare non è una funzione da offrire, e le cause pacifiche di rimozione
   anticipata sono quattro, elencate lì con la fonte di ciascuna. Resta aperta la domanda
   che viene dopo, e che nessuno dei testi letti risolve: che cosa accada al periodo di
   pubblicazione quando una diffusione vietata viene interrotta. È una valutazione dell'ente,
   quindi il sistema deve registrarla e non calcolarla. Resta poi la parte di prodotto, cioè
   scrivere quelle transizioni dentro ALBO-22, con il vincolo per il referto annotato nella
   stessa sezione.
7. **Informativa sul trattamento, da verificare.** Le pagine dell'albo espongono dati
   personali a chiunque. Nessun requisito dice che devono portare un collegamento
   all'informativa dell'ente. Probabilmente è un obbligo dell'ente sul sito e non del
   componente, ma il componente è l'unico che sa quali pagine espongono atti: vale la pena
   decidere invece di lasciarlo implicito.
8. **Gli accorgimenti contro l'indicizzazione sono scritti a metà, scarto interno.** GAR ne
   nomina due **a titolo esemplificativo**, i metatag `noindex` e `noarchive` oppure il
   `robots.txt` secondo il Robot Exclusion Protocol, e aggiunge che nessuno dei due tocca
   quello che è già indicizzato. Non è quindi un elenco minimo obbligatorio, e la loro
   assenza non è di per sé una lacuna rispetto alla fonte: è uno scarto fra quello che
   ALBO-06 promette e quello che la sua riga di collaudo verifica.
   ALBO-06 nomina il solo `noindex`, la sua riga di collaudo ispeziona il meta robots e la
   mappa per i motori, e di `noarchive`, di `robots.txt` e della rimozione di ciò che è già
   stato indicizzato non parla nessuno. Non è la misura più importante, perché la rimozione
   resta l'unica cosa che conta davvero, ma qui la fonte è precisa e il requisito le sta
   dietro.

### Su cosa poggia ciascuno dei punti qui sopra

Perché la domanda giusta, davanti a un elenco di buchi, è sempre "e questo chi lo dice".

| Punto | Fonte | Stato della fonte |
|---|---|---|
| 1. Versione oscurata | ALBO-09 e ALBO-12 letti insieme, ALBO-07 come unico appiglio, e il campo di verifica dell'allegato 5 di LG-DOC | incoerenza interna al catalogo, più una fonte esterna letta il 22 settembre 2026 che dice dove va registrata la risposta |
| 2. Metadati di conservazione | LG-DOC, allegato 5, riletto il 22 settembre 2026 | fonte verificata e contenuto letto: l'insieme obbligatorio esiste ed è elencato nella sezione LG-DOC |
| 3. Fonte della durata | GDPR art. 5.2 | fonte verificata, ma il collegamento fra responsabilizzazione e "registrare da dove viene la durata" è una lettura prudente, non una prescrizione testuale |
| 4. Accessibilità delle pagine | art. 32 comma 1 della legge 69/2009, che rinvia all'art. 11 della legge 4/2004, cioè oggi alle linee guida AgID | **l'obbligo è solido**, perché sta nella norma stessa che istituisce la pubblicità legale online, letta nel testo vigente. È invece scelta di attuazione, e non conseguenza del testo, che ne risponda il componente: la norma si rivolge alle amministrazioni. E gli effetti di un difetto di accessibilità sulla validità della pubblicazione non sono determinati da questi testi |
| 5. Data di inizio | nessuna fonte esterna | incoerenza interna, verificata leggendo il codice: l'interfaccia pubblica del componente comune espone la sola data di fine |
| 6. Defissione anticipata | ALBO-10, che la nomina senza definirla. Per il se e il quando: artt. 124 e 134 del TUEL e art. 32 della legge 69/2009 sul termine, GAR 3.a e il par. 1 della parte seconda sulle cause di rimozione | fonti lette nel testo vigente e citate nella sezione dedicata. Regge la parte sul se, cioè che il termine non si accorcia; **non è coperta da nessuna fonte letta** la parte sugli effetti che una rimozione produce sul periodo di pubblicazione, e la sezione ora lo dice. Il diritto dell'interessato sta nell'art. 17 del GDPR, non censito nella tabella delle fonti |
| 7. Informativa | GDPR artt. 13 e 14 | **non censiti** nella tabella delle fonti: è il punto più debole dell'elenco |
| 8. `robots.txt` | nessuna fonte esterna | scarto fra il requisito e la sua riga di collaudo, verificato nei due documenti |

## Requisiti che non vengono da una norma

Il verso opposto, per non far passare per obbligo di legge quello che è una decisione nostra.

- **Ipotesi da confermare**: ALBO-07 (referto) e ALBO-08 (repertorio). Poggiano sulla prassi
  della pubblicità legale, che non è una fonte, e le unità che li costruiscono sono bloccate
  dalla conferma sul regolamento dell'amministrazione. Una nota utile su ALBO-07: la sua parte
  di integrità e di data certa trova un appoggio in LG-DOC, mentre l'obbligo di produrre il
  referto no. Sono due cose diverse e conviene tenerle distinte quando si chiede la conferma.
- **Decisione incompleta**: ALBO-22. Mancano le transizioni consentite, chi può compierle e
  cosa succede a una transizione rifiutata. Non ha righe di collaudo, e non è una
  dimenticanza.
- **Scelte di prodotto**: ALBO-16 (ricerca e filtri), ALBO-19 (battito), ALBO-21 (fuso orario),
  ALBO-23, ALBO-24, ALBO-25, ALBO-26 (permessi e ruolo). Sono gli unici quattro requisiti verdi
  del componente, e vale la pena ripeterlo: sono scelte di prodotto, non obblighi coperti.

## Incoerenze trovate nei documenti

1. `requisiti.md` chiude dicendo che **tutti** i requisiti sono a "da fare", mentre la tabella
   dello stesso documento ne porta quattro a "fatto" (ALBO-23..26). La frase è rimasta
   indietro rispetto alla tabella.
2. ALBO-06 e `robots.txt`: vedi il punto 8 della sezione precedente.

## Le verifiche del 22 settembre 2026

Ogni fonte della prima tabella è stata ricontrollata. La colonna dell'esito dice cosa si è
potuto leggere davvero, perché "verificato" e "non sono riuscito a leggerlo" sono due
risposte diverse e la seconda non va nascosta. Dove una fonte è arrivata in copia, la
colonna lo dice.

| Fonte | Esito | Come |
|---|---|---|
| GAR | **confermata, ancora applicabile e letta per intero**. Nessun provvedimento la sostituisce, e uno del maggio 2026 la dichiara in corso di aggiornamento ma ancora attuale nella parte sostanziale. Contiene un paragrafo, il 3.a della parte seconda, dedicato proprio all'albo pretorio | letto il PDF ufficiale di 47 pagine, fornito in copia perché il sito ne blocca lo scaricamento automatico. I paragrafi 2.a, 2.b, 2.c, 3.a e 8 sono stati letti alla lettera |
| P-1, P-2 | **confermati, ma erano descritti al contrario**. Il 12 marzo 2026 riguarda dati sulla salute, il 26 marzo dati reddituali rimasti online oltre un anno e mezzo | lette per intero le due pagine del Garante |
| P-3, P-4, P-5 | **fonti nuove**, che l'elenco non aveva. P-3 è più recente di tutte le altre | lette le tre pagine del Garante |
| LG-DOC | **confermata nella sostanza, imprecisa nella scheda**. Il testo pubblicato porta in copertina maggio 2021 e gli allegati sono datati giugno 2024. I formati di file sono nell'allegato 2, non nel 6 | letto il PDF delle linee guida e per intero l'allegato 5 |
| LG-DOC, determinazioni | **non verificate**. Né le pagine di AgID né i PDF citano la determinazione 407/2020 o la 371/2021, e la data del 1 gennaio 2022 non compare da nessuna parte: le linee guida fissano un termine mobile di duecentosettanta giorni | cercate sul sito di AgID. Per chiudere il punto serve l'albo delle determinazioni di AgID |
| ACC, determinazioni | **verificate**. Le linee guida sull'accessibilità sono state adottate con la determinazione n. 437/2019 del 20 dicembre 2019 e rettificate con la n. 354 del 22 dicembre 2022; la n. 117/2022 adotta quelle per i soggetti privati | dalla determinazione n. 117/2022, fornita in copia, che cita le altre |
| ACC | **confermata e da riscrivere**. L'art. 11 è stato sostituito nel 2018 e oggi rinvia alle linee guida AgID: i requisiti tecnici non sono più in un decreto ministeriale | lette le pagine di AgID sull'accessibilità e il capitolo primo delle linee guida |
| GDPR | **confermato per via indiretta**. Gli articoli citati dal catalogo sono gli stessi che i provvedimenti del Garante del 2024, 2025 e 2026 applicano e dichiarano violati | dalle pagine dei provvedimenti. Il testo del regolamento non è stato riletto in questa sessione |
| COD art. 2-septies | **letto nel testo vigente, ed è più largo di come il catalogo lo tratta**. Il comma 8 vieta la diffusione dei dati genetici, biometrici e relativi alla salute, senza deroghe. Il catalogo tratta "dati particolari e giudiziari" come un blocco unico | testo vigente da Normattiva, fornito in copia |
| COD art. 2-ter | **letto nel testo vigente, e cambia l'appoggio di ALBO-11**. La definizione di diffusione del comma 4 e i tre canali della base giuridica del comma 1 sostituiscono il vecchio art. 19 comma 3 che GAR cita | testo vigente da Normattiva, fornito in copia |
| TUEL art. 124 | **letto nel testo vigente e confermato, con un comma in più e una parola cambiata**. I commi 1 e 2 dicono quello che GAR ne citava, salvo "pubblicazione" al posto di "affissione". Il comma 2, sugli enti locali diversi dai comuni, non era nel catalogo | testo vigente da Normattiva, fornito in copia |
| TUEL art. 134 | **letto, ed è la fonte che mancava**. Il comma 3 lega l'esecutività al decimo giorno dalla pubblicazione, il comma 4 dà all'urgenza una valvola che non tocca la durata della pubblicazione. Rende confermata la risposta sul termine che prima era solo prudente | testo vigente da Normattiva, fornito in copia. I commi 1 e 2 riguardano un controllo regionale che si ritiene superato dal 2001: quella parte è una lettura, non un dato |
| L69 | **letta nel testo vigente e confermata, con due periodi in più**. Le tre date ci sono tutte. Il comma 1 contiene l'obbligo di applicare i requisiti di accessibilità e la rilevanza della mancata pubblicazione sulla valutazione del dirigente, che la citazione dentro GAR non riportava. Nuovi anche il comma 1-bis sugli elaborati urbanistici e il comma 3 sull'albo ospitato da un'altra amministrazione | testo vigente da Normattiva, fornito in copia |
| TRASP | **confermata per via indiretta**, con due articoli in più che il catalogo non nominava: il 7-bis comma 3 e l'8 comma 3 | dalle pagine dei provvedimenti |

**Il documento è stato passato a una revisione indipendente** il 22 settembre 2026, con i
testi di legge allegati e la consegna di cercare le affermazioni che quei testi non
reggono. Ha prodotto dodici rilievi, accolti tutti. Sei riguardavano la stessa cosa: una
conseguenza ragionevole presentata come se stesse nel testo. Le correzioni più pesanti sono
tre. L'esclusione dell'oscuramento anticipato dall'albo non discende dall'esclusione
dell'arco dei cinque anni, quindi quell'appoggio è stato ridimensionato e la questione
dichiarata aperta. Dalla rilevanza sulla valutazione del dirigente non discende che una
pubblicazione non accessibile sia priva di effetti, e il documento non lo dice più. E le
quattro cause di rimozione anticipata non provano che il termine continui a correre: i testi
dicono che una diffusione vietata deve cessare e tacciono sugli effetti sul periodo, che
restano una valutazione dell'ente. Sono state inoltre marcate come non verificate due
affermazioni fatte a memoria, l'abrogazione del vecchio art. 19 del Codice e la
corrispondenza fra i vecchi artt. 19-22 e gli attuali 2-ter e 2-septies.

**Due avvertenze sul metodo.** La prima: Normattiva non risponde agli strumenti di questa
sessione, quindi i cinque articoli di legge letti sono arrivati in copia, incollati dal
portale. Sono testo vigente con la data di vigenza in testa, ed è la ragione per cui la
colonna "come" lo dice riga per riga. Dove invece c'è scritto "per via indiretta" vuol dire
che la norma è citata e applicata da un provvedimento del Garante recente, il che prova che
è vigente e come viene interpretata, non che qualcuno ne abbia riletto il testo. La seconda:
su alcune pagine lunghe lo strumento ha
restituito la prima parte alla lettera e il resto in sintesi. Dove la differenza conta è
scritto.

## Cosa manca a questo documento

- **I cinque articoli che mancavano sono stati letti** nel testo vigente il 22 settembre
  2026: gli artt. 124 e 134 del TUEL, l'art. 32 della legge 69/2009, gli artt. 2-ter e
  2-septies del Codice. Nessun testo di legge centrale a questo documento resta non letto.
  Quello che resta aperto qui sotto sono letture da confermare e fonti secondarie, non
  articoli mancanti.
- Nell'art. 134 i commi 1 e 2 parlano del controllo preventivo del comitato regionale. Che la
  riforma costituzionale del 2001 li abbia privati di fondamento, e che quindi il comma 3 sia
  oggi la regola generale, è una lettura che regge la sezione sul termine e **non è
  verificata**: va confermata su una fonte, non data per buona.
- Il comma 1-bis dell'art. 2-ter apre una strada al trattamento pubblico fuori dai tre canali
  della base giuridica. Si ritiene che non tocchi la pubblicazione istituzionale dell'albo,
  ma nessuno dei provvedimenti letti lo dice: è la lettura più fragile del documento.
- Non sono censiti gli artt. 17 e 21 del GDPR, cioè il diritto alla cancellazione e il
  diritto di opposizione, che sono la fonte della richiesta dell'interessato: è il caso
  lasciato fuori dall'elenco chiuso delle rimozioni anticipate.
- GAR cita le vecchie disposizioni del Codice, cioè gli artt. 19, 20, 21 e 22, e il Codice di
  oggi ha invece gli artt. 2-ter e 2-septies, che questo documento ha letto. **Che i primi
  siano stati abrogati o sostituiti dai secondi non risulta da nessuno dei testi letti**: è
  un'affermazione fatta a memoria, e la corrispondenza articolo per articolo non è
  ricostruita. Quello che si può dire è che le formule sono diverse e che i provvedimenti
  recenti applicano gli articoli di oggi, quindi il documento cita questi ultimi.
- GAR rinvia a un vademecum di DigitPA del luglio 2011 sulle modalità di pubblicazione dei
  documenti nell'albo online. Non è censito e ha quindici anni: va guardato una volta, se
  esiste ancora, perché è l'unico documento tecnico dedicato all'albo.
- Le determinazioni con cui le linee guida AgID sui documenti informatici sono state adottate
  e aggiornate vanno trovate sull'albo delle determinazioni di AgID.
- Il punto 7 poggia sugli artt. 13 e 14 del GDPR, che non sono nella tabella delle fonti qui
  sopra: è segnalato come domanda, non come obbligo accertato.
- Il codice dell'amministrazione digitale (d.lgs. 82/2005) e il regolamento (UE) 910/2014 non
  sono censiti. Per la firma non servono, perché il componente non firma, ma vanno letti prima
  di dire che l'albo non ha niente a che fare con la validità del documento informatico.
- Le norme di settore che fissano termini propri per singoli tipi di atto non sono censite.
  ALBO-04 le rende configurabili, quindi il componente non ha bisogno di conoscerle, ma l'ente
  sì, e un elenco di partenza renderebbe la configurazione un lavoro di mezz'ora invece che di
  una giornata.
- Manca la mappa equivalente per il componente comune: qui i suoi meccanismi compaiono solo
  come dipendenze.
