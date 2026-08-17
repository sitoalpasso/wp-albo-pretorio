# Dati: cosa esiste, dove sta, chi può toccarlo

Questo documento elenca i dati che il plugin gestisce e risponde alle domande di
controllo: dove viene salvata ogni informazione, cosa succede quando cambia, chi ha il
permesso di cambiarla. I nomi tecnici precisi (chiavi dei metadati, nomi delle tabelle)
vengono fissati dalle schede in `docs/implementazione/` man mano che le parti vengono
costruite; questo file tiene la mappa d'insieme e va aggiornato nello stesso commit che
introduce un dato nuovo.

**Stato di oggi.** Nessuno di questi dati esiste ancora: il tipo di contenuto `atto` non
è registrato, non ci sono metadati, tabelle, capability né configurazione. Il repository
contiene l'intestazione del plugin e l'infrastruttura di verifica. Quanto segue è la
mappa che il codice deve realizzare, ed è vincolante nello stesso senso di
[architettura.md](architettura.md): se in implementazione un dato deve cambiare posto o
nome, si cambia prima qui, nello stesso commit.

## L'atto

Un atto è un contenuto WordPress di tipo dedicato (`atto`), con questi dati:

| Dato | Obbligatorio | Dove sta | Chi lo scrive |
|---|---|---|---|
| Oggetto (titolo) | sì | contenuto WordPress | redattore |
| Tipo di atto | sì | tassonomia dedicata | redattore, scelto tra i tipi configurati |
| Organo che ha adottato | sì | tassonomia dedicata | redattore |
| Numero proprio dell'atto (es. determina 45/2026) | no | metadato | redattore. È il numero dell'atto, non quello di pubblicazione |
| Data di adozione | sì | metadato | redattore |
| Data di inizio pubblicazione | sì | metadato | proposta dal sistema, confermata dal redattore |
| Data di fine pubblicazione | sì | metadato | calcolata dalla durata configurata per il tipo; il sistema rifiuta il salvataggio senza |
| Numero di repertorio (es. 123/2026) | dalla pubblicazione | metadato più contatore in tabella dedicata | **solo il sistema**, alla prima pubblicazione, in modo atomico |
| Stato | sì | stato del contenuto | il flusso di pubblicazione, mai a mano nel database |
| Conferma del controllo dati personali | sì per pubblicare | metadato con utente e data | il redattore, tramite la schermata obbligata |
| Allegati | almeno uno | file in cartella protetta più impronta (hash) in metadato | redattore prima della pubblicazione, poi bloccati |
| Motivo di annullamento o defissione anticipata | quando ricorre | metadato più voce di registro | responsabile con permesso dedicato |

Gli **stati** possibili: bozza, in controllo, pubblicato, defisso, annullato. Le
transizioni consentite sono solo quelle del diagramma in `architettura.md`; ogni
transizione scrive una voce nel registro.

Tutte le date si leggono e si scrivono nel fuso orario del sito, ottenuto da
`wp_timezone()`: mai il fuso del server, mai un fuso cablato (ALBO-21). È l'unica regola
di questo documento già collaudata oggi, dal test di scheletro sul fuso orario.

## Le risposte alle domande di controllo

**Dove viene salvata la data di pubblicazione?** In due posti con ruoli diversi: le date
di inizio e fine **previste** sono metadati dell'atto; le date **effettive** vengono
congelate nel referto alla defissione. Se per un guasto la defissione avviene in ritardo,
il referto riporta la verità, non la previsione.

**Cosa succede se cambio un documento già pubblicato?** Non si può. L'atto pubblicato è
immodificabile per chiunque, amministratore compreso: la strada giusta è creare un nuovo
atto di rettifica che rinvia al precedente. Ogni tentativo respinto è comunque tracciato.

**Cosa succede agli allegati?** Vengono caricati in una cartella protetta, non
raggiungibile per URL diretto: si scaricano solo attraverso l'endpoint di consegna di
core, che a ogni richiesta ricontrolla che l'atto sia visibile. Alla defissione il file
non si cancella: resta nell'archivio ad accesso controllato, e la sua impronta sta nel
referto. Sostituire l'allegato di un atto pubblicato è vietato come modificare l'atto.

**Chi può cambiare lo stato?** Solo chi ha la capability della transizione, e solo lungo
le transizioni permesse. Nessuno, nemmeno l'amministratore, può cancellare un atto
pubblicato o riportarlo a bozza.

## I permessi

Il plugin definisce capability dedicate, non riusa quelle generiche dei post. La
corrispondenza tra capability e ruoli WordPress è configurabile dall'amministrazione;
questa è la mappa delle azioni:

| Azione | Capability dedicata | Note |
|---|---|---|
| Creare e modificare bozze | gestione atti | |
| Pubblicare (con controllo dati personali) | pubblicazione atti | la conferma resta registrata con nome e data |
| Defissione anticipata, annullamento | defissione atti | motivo obbligatorio |
| Consultare l'archivio dei defissi | archivio atti | il pubblico non lo vede mai |
| Consultare il registro delle operazioni | lettura registro (di core) | |
| Configurare tipi di atto e durate | amministrazione albo | |
| Cancellare un atto pubblicato | **nessuna**: vietato per tutti | la voce non esiste proprio |
| Vedere un atto pubblicato e non scaduto | nessuna: pubblico | |

## Dati che il plugin scrive fuori dall'atto

- **Contatore di repertorio**: una riga per anno (e per registro, se l'amministrazione
  configura registri separati), aggiornata con un'operazione atomica per impedire due
  atti con lo stesso numero.
- **Referto**: un record congelato per ogni atto defisso: numero, tipo, oggetto, date
  effettive di inizio e fine, impronta del file pubblicato, chi ha pubblicato. Non si
  rigenera e non si modifica; si ristampa.
- **Registro delle modifiche** (tabella di core): una voce per ogni operazione
  rilevante, solo in aggiunta. Contiene l'identificativo dell'utente, mai altri dati
  personali.
- **Battito** (di core): il timestamp dell'ultima esecuzione riuscita del cron.
- **Configurazione**: tipi di atto con durata in giorni (obbligatoria per tipo),
  registri, destinatario degli avvisi del battito. Ogni valore validato al salvataggio.

## Cosa NON viene salvato

Nessun dato di visitatori anonimi: niente tracciamento degli accessi pubblici. Nessuna
copia dei file fuori dalla cartella protetta. Nessun default nascosto: durata o politica
mancante significa configurazione incompleta e blocco, non un valore inventato.
