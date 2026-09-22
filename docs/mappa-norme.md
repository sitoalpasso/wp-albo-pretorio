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
| L69 | Legge 18 giugno 2009, n. 69, art. 32 | la pubblicazione sul sito ha effetto di pubblicità legale; l'articolo rinvia ai requisiti tecnici di accessibilità dell'art. 11 della legge 4/2004 |
| TUEL | D.lgs. 18 agosto 2000, n. 267, art. 124 | quindici giorni consecutivi per le deliberazioni di comuni e province, salvo specifiche disposizioni di legge |
| GAR | Garante per la protezione dei dati personali, deliberazione 15 maggio 2014, n. 243, in GU n. 134 del 12 giugno 2014 | linee guida sul trattamento di dati personali contenuti in atti pubblicati per finalità di pubblicità e trasparenza: durata limitata, niente indicizzazione, minimizzazione |
| GDPR | Regolamento (UE) 2016/679, artt. 5 e 6 | principi del trattamento e base giuridica |
| COD | D.lgs. 30 giugno 2003, n. 196, artt. 2-ter e 2-septies | condizioni perché un soggetto pubblico possa trattare dati particolari e giudiziari |
| LG-DOC | AgID, linee guida sulla formazione, gestione e conservazione dei documenti informatici, determinazione n. 407/2020, allegati 5 e 6 modificati con determinazione n. 371/2021, obbligo di attuazione dal 1 gennaio 2022 | integrità del documento informatico, metadati, versamento in conservazione |
| ACC | Legge 9 gennaio 2004, n. 4, art. 11, richiamato da L69 art. 32 | requisiti tecnici di accessibilità dei contenuti e dei documenti pubblicati |
| TRASP | D.lgs. 14 marzo 2013, n. 33 | l'amministrazione trasparente è un obbligo distinto, con finalità e durate proprie |
| P-1 | Garante, provvedimento 12 marzo 2026, doc. web 10240362 | atti con dati reddituali rimasti online oltre diciotto mesi |
| P-2 | Garante, provvedimento 26 marzo 2026, doc. web 10246037 | atti che rivelavano dati sulla salute senza una base giuridica che li giustificasse |

P-1 e P-2 non sono fonti di obblighi nuovi: sono la prova di come le norme qui sopra
vengono applicate, e dicono dove si sbaglia per davvero. Vanno letti come criterio di
verifica, non come articoli.

## Norma per norma

### L69 art. 32: la pubblicazione online è quella che produce effetti

L'articolo sposta sul sito la pubblicità legale, e il documento sul sito non è una copia
di cortesia: è la pubblicazione. Da qui due obblighi.

| Obbligo | Requisiti | Copertura |
|---|---|---|
| L'atto pubblicato è identificabile e completo dei suoi estremi | ALBO-01 | scoperto |
| I documenti pubblicati rispettano i requisiti tecnici di accessibilità (rinvio ad ACC) | ALBO-13, ALBO-14 | scoperto |

L'articolo contiene tre date che vanno tenute distinte quando se ne discute: dal 1 gennaio
2010 gli obblighi di pubblicità legale si assolvono sui siti; dal 1 gennaio 2011 la
pubblicazione cartacea perde quell'effetto; dal 1 gennaio 2013 lo stesso vale per gli atti
per cui era prevista la pubblicazione su quotidiani. Restano fuori gli obblighi in Gazzetta
Ufficiale e nella Gazzetta dell'Unione europea, che l'articolo fa salvi.

### TUEL art. 124: la durata esiste, ma non è una sola

Quindici giorni consecutivi per le deliberazioni **di comuni e province**, salvo specifiche
disposizioni di legge. Non è una durata universale, e trattarla come tale rende il
componente inservibile su un ente di tipo diverso.

| Obbligo | Requisiti | Copertura |
|---|---|---|
| La pubblicazione ha una durata, e la durata dipende dal tipo di atto e dalla norma applicabile | ALBO-04 | scoperto |

Due righe di `collaudo.md` chiudono ALBO-04 ed entrambe sono "da fare": la pubblicazione di
un tipo senza durata configurata deve essere bloccata, e un controllo statico deve
dimostrare che la costante 15 non compare nel codice. La seconda è la riga che tiene in
piedi la riusabilità del componente, perché una durata cablata non si vede finché non si
installa il plugin su un ente che ha termini diversi.

### GAR, P-1 e P-2: il tempo è la violazione

È il gruppo che decide l'architettura. I due provvedimenti del marzo 2026 colpiscono la
stessa cosa da due lati: atti rimasti esposti troppo a lungo, e atti che rivelavano dati che
non avrebbero dovuto essere lì.

| Obbligo | Requisiti | Copertura |
|---|---|---|
| La pubblicazione ha un termine e finisce da sola, senza dipendere dal traffico del sito | ALBO-02, ALBO-03, ALBO-19 | avviato per ALBO-02, scoperto per gli altri due |
| L'atto scaduto esce dalla vista pubblica su ogni percorso, allegati compresi | ALBO-05, ALBO-18, ALBO-20 | avviato per ALBO-05 e ALBO-18, scoperto per ALBO-20 |
| Le pagine dell'atto non vengono indicizzate | ALBO-06 | avviato, solo per la parte dichiarativa |
| I dati eccedenti non finiscono nella versione pubblica | ALBO-12 | scoperto |

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
  applica non produce nessun effetto sulle pagine.

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

È l'obbligo che P-2 sanziona. ALBO-11 non decide al posto dell'ente: impone un passaggio
che chiede una conferma esplicita e mostra i casi in cui un atto rivela dati delicati senza
nominarli, per esempio richiami a norme sul collocamento mirato, congedi per assistenza,
graduatorie con punteggi sociali, provvedimenti disciplinari, redditi e ISEE.

### LG-DOC: integrità e conservazione

| Obbligo | Requisiti | Copertura |
|---|---|---|
| Il documento informatico pubblicato è integro e immodificabile | ALBO-09 | scoperto |
| Gli atti sono versabili in conservazione | ALBO-17 | scoperto, e parziale come specifica |

ALBO-17 chiede l'esportazione in formato aperto di atti e metadati. Non dice **quali**
metadati, mentre le linee guida fissano un insieme minimo per il documento informatico.
Finché il requisito non lo nomina, la riga di collaudo può verificare il formato e la
reimportabilità, non la completezza rispetto alla conservazione: è un export che si può
rileggere, non necessariamente un pacchetto che un sistema di conservazione accetta.

### ACC legge 4/2004 art. 11

| Obbligo | Requisiti | Copertura |
|---|---|---|
| I documenti pubblicati sono accessibili alle tecnologie assistive | ALBO-13 | scoperto |
| Nessuna misura anti prelievo che comprometta l'accessibilità | ALBO-14 | scoperto |
| Le funzioni di consultazione sono usabili senza mouse | ALBO-16, che ha l'accessibilità come vincolo | scoperto |

ALBO-13 è dichiarato come avviso e non come blocco: il sistema esamina il PDF caricato e
segnala un indizio (sembra una scansione, manca la struttura), la pubblicazione resta
possibile. È una scelta prudente, perché un controllo automatico sull'accessibilità di un
PDF produce falsi negativi, e bloccare su un indizio significherebbe impedire la
pubblicazione di un atto valido.

### TRASP d.lgs. 33/2013

| Obbligo | Requisiti | Copertura |
|---|---|---|
| Albo e amministrazione trasparente restano due esposizioni distinte, con durate e finalità proprie | ALBO-15 | scoperto |

Qui la regola sull'indicizzazione è **invertita** rispetto all'albo: la trasparenza va
indicizzata, l'albo no. È il motivo per cui il componente comune pretende la politica di
indicizzazione come parametro esplicito e non ha un valore predefinito: un default,
qualunque sia, sarebbe quello sbagliato per metà dei componenti.

## Riepilogo della copertura

| Obbligo, in una riga | Requisiti | Copertura |
|---|---|---|
| L'atto è identificabile e completo | ALBO-01 | scoperto |
| La pubblicazione ha un termine | ALBO-02 | avviato |
| Il termine si applica da solo e in tempo | ALBO-03, ALBO-19 | scoperto |
| Esiste una durata, dipendente dal tipo di atto | ALBO-04 | scoperto |
| L'atto scaduto esce dalla vista pubblica | ALBO-05, ALBO-18 | avviato, manca il percorso dell'allegato |
| L'atto defisso non resta servito da una cache | ALBO-20 | scoperto |
| Le pagine dell'atto non sono indicizzate | ALBO-06 | avviato solo nella dichiarazione |
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

1. **Firma digitale e oscuramento, da verificare.** Gli atti pubblicati sono di norma firmati
   digitalmente. Nessun ALBO-xx dice cosa fa il componente della firma: se la verifica, se la
   conserva, se la espone. Il punto concreto è ALBO-12: una versione oscurata è un file
   diverso dall'originale, quindi **la firma dell'originale non vale più su di essa**, e oggi
   nessuna riga dice come si attesta che la versione pubblicata corrisponde all'atto adottato.
   Fonti da leggere: d.lgs. 82/2005 (codice dell'amministrazione digitale), in particolare gli
   articoli sul documento informatico e sulle copie, e il regolamento (UE) 910/2014. Da
   chiudere prima dell'unità che costruisce ALBO-12.
2. **Metadati per la conservazione, buco parziale.** ALBO-17 dice "formato aperto" e non dice
   quali metadati. Vedi la sezione LG-DOC.
3. **La fonte della durata non si registra, buco.** ALBO-04 rende la durata configurabile per
   tipo di atto, ed è giusto. Nessun requisito chiede però di registrare **perché** quella
   durata è quella: quale norma o quale articolo del regolamento la giustifica. Senza, l'ente
   ha un numero in una casella e non ha come dimostrare da dove viene, che è proprio ciò che
   l'art. 5.2 del GDPR gli chiede. Costa poco: un campo di testo accanto alla durata, riportato
   nell'esportazione della configurazione.
4. **Accessibilità delle pagine dell'albo, buco.** ACC è tradotta in requisiti sui PDF
   (ALBO-13, ALBO-14) e come vincolo della ricerca (ALBO-16). L'elenco degli atti e la scheda
   del singolo atto, che sono pagine web prodotte dal componente, non hanno un requisito di
   accessibilità proprio. Serve almeno una riga che imponga la verifica sulle due pagine
   pubbliche prodotte dal plugin.
5. **La data di inizio non è governata da niente, domanda aperta.** ALBO-01 elenca la data di
   inizio fra i dati dell'atto, e `dati.md` dice che il sistema la propone e il redattore la
   conferma. Nessun requisito e nessuna riga di collaudo dice cosa succede se quella data è
   **nel futuro**: il componente comune conosce la sola data di fine, quindi oggi un atto
   pubblicato con inizio futuro sarebbe visibile subito. Va deciso se è un caso che esiste
   (pubblicazione preparata in anticipo) e, se esiste, chiuso dentro ALBO-22 insieme alle
   transizioni.
6. **Defissione anticipata: tracciata ma non definita, buco.** ALBO-10 chiede che il registro
   contenga chi ha disposto una defissione anticipata e perché. Nessun requisito definisce la
   defissione anticipata come funzione: chi può disporla, con quali effetti sul referto, se
   l'atto resta consultabile all'amministrazione. È la funzione con cui l'ente risponde alla
   richiesta di un interessato, quindi non è un dettaglio. Appartiene ad ALBO-22.
7. **Informativa sul trattamento, da verificare.** Le pagine dell'albo espongono dati personali
   a chiunque. Nessun requisito dice che devono portare un collegamento all'informativa
   dell'ente. Probabilmente è un obbligo dell'ente sul sito e non del componente, ma il
   componente è l'unico che sa quali pagine espongono atti: vale la pena decidere invece di
   lasciarlo implicito.
8. **Il blocco in `robots.txt` non è verificato da nessuna riga, buco piccolo.** La riga di
   collaudo di ALBO-06 ispeziona il meta robots e la mappa per i motori. Il `robots.txt` non
   compare. Non è la misura più importante, perché un motore che ignora `robots.txt`
   rispetta comunque il `noindex`, ma se il requisito lo prevede la riga deve esserci, e se non
   lo prevede va scritto che non lo prevede.

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

## Cosa manca a questo documento

- Le fonti della prima tabella sono quelle già verificate su testo primario. Vanno
  riverificate prima di ogni rilascio, perché una linea guida AgID o una deliberazione del
  Garante possono essere sostituite senza che cambi nulla nel codice.
- Il gruppo di candidati del punto 1 (codice dell'amministrazione digitale, regolamento
  eIDAS) **non è verificato**: è segnalato come domanda, non come obbligo.
- Le norme di settore che fissano termini propri per singoli tipi di atto non sono censite.
  ALBO-04 le rende configurabili, quindi il componente non ha bisogno di conoscerle, ma l'ente
  sì, e un elenco di partenza renderebbe la configurazione un lavoro di mezz'ora invece che di
  una giornata.
- Manca la mappa equivalente per il componente comune: qui i suoi meccanismi compaiono solo
  come dipendenze.
