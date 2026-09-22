# Schede di implementazione

Una scheda per ogni funzione realizzata, con lo stesso nome della funzione. Risponde alla
domanda che ci si fa prima di modificare qualcosa: **com'è fatta davvero questa parte, cosa
fa e cosa deliberatamente non fa, e quali test la sorvegliano.**

Ogni scheda si scrive **prima** di toccare il codice, come piano, e si aggiorna a lavoro
finito con com'è andata davvero. Contiene: quali file tocca e perché, quale requisito serve,
il percorso di chi usa il sito, i dati letti e scritti, i permessi coinvolti, i modi di
guasto previsti, i test aggiunti con il riferimento a `collaudo.md`, cosa non implementa, e
come si prova la funzione su un'installazione reale dopo il rilascio.

Schede presenti:

- [`avvio-e-politiche.md`](avvio-e-politiche.md): avvio del componente, controllo di
  compatibilità con il meccanismo comune, registrazione della sezione e dichiarazione delle
  due politiche. Righe di collaudo A-01..A-10.
- [`tipo-atto-permessi-e-chiusura.md`](tipo-atto-permessi-e-chiusura.md): il tipo di
  contenuto atto, i due elenchi di voci, i permessi, il ruolo proprio del componente e lo
  sbarramento che tiene chiusa la pubblicazione. Righe di collaudo A-11..A-47.
