/**
 * Prova della pagina pubblica dell'albo in un browser vero (ALBO-16, ALBO-28).
 *
 * Due controlli, sulle pagine preparate da `prepara-sito.php`:
 *
 * 1. il motore automatico di accessibilita' (axe), regole WCAG 2.0 e 2.1 di
 *    livello A e AA, su ogni pagina, con lo schermo di un telefono e con uno
 *    schermo largo: nessuna violazione;
 * 2. con `--tastiera`, il percorso da sola tastiera: ogni comando si raggiunge
 *    con il tasto Tab, nell'ordine in cui sta nella pagina, con l'indicatore del
 *    fuoco visibile, e dall'ultimo si esce; poi due ricerche fatte senza mouse.
 *
 * Prima delle pagine vere, ogni controllo si fa girare su una pagina guastata
 * apposta: se non vi trova i guasti, non e' un controllo, e la prova si ferma.
 *
 * Tutto da questo computer: le pagine dal sito di prova locale, il motore dalla
 * cartella delle dipendenze, nessuna rete esterna.
 *
 * Uso: node accessibilita.mjs --pagine <file.json> --tema <nome> [--tastiera] [--tutta-la-pagina]
 *
 * Su un tema vero una violazione che sta tutta fuori dal codice dell'albo e'
 * del tema: si annota e non ferma la prova (ALBO-28, limiti dichiarati). Con
 * `--tutta-la-pagina`, per il tema spoglio di prova, ogni violazione ferma.
 * Variabili: CHROME, percorso del browser (Chrome o Chromium).
 *
 * @package AlboPretorioPa
 */

import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { chromium } from 'playwright-core';

const require = createRequire( import.meta.url );
const argomenti = process.argv.slice( 2 );
const valore = ( nome ) => {
	const i = argomenti.indexOf( nome );
	return i >= 0 ? argomenti[ i + 1 ] : undefined;
};

const filePagine = valore( '--pagine' );
const tema = valore( '--tema' ) ?? 'sconosciuto';
const conTastiera = argomenti.includes( '--tastiera' );
const tuttaLaPagina = argomenti.includes( '--tutta-la-pagina' );

if ( ! filePagine || ! process.env.CHROME ) {
	console.error( 'Uso: CHROME=<browser> node accessibilita.mjs --pagine <file.json> --tema <nome> [--tastiera]' );
	process.exit( 2 );
}

const dati = JSON.parse( readFileSync( filePagine, 'utf8' ) );
const PAGINE = [ 'elenco', 'seconda pagina', 'ricerca con un filtro', 'ricerca senza risultati', 'ricerca scritta male', 'scheda con allegati' ];
const SCHERMI = {
	telefono: { width: 390, height: 844 },
	largo: { width: 1280, height: 900 },
};
const REGOLE = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ];
const motore = readFileSync( require.resolve( 'axe-core/axe.min.js' ), 'utf8' );

// La pagina guastata apposta: un'immagine senza testo alternativo, un campo senza etichetta, un fuoco senza indicatore.
const GUASTA = `<!doctype html><html lang="it"><head><meta charset="utf-8"><title>Pagina guastata</title>
<style>.albo-pretorio a:focus { outline: none; }</style></head>
<body><main><div class="albo-pretorio"><h1>Prova</h1><img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=">
<form><input type="text" name="senza-etichetta"></form><a href="#uno">Uno</a> <a href="#due">Due</a></div></main></body></html>`;

let errori = 0;

function sbaglia( messaggio ) {
	errori++;
	console.log( `  ERRORE: ${ messaggio }` );
}

async function esamina( pagina ) {
	await pagina.addScriptTag( { content: motore } );

	return pagina.evaluate( async ( regole ) => {
		// eslint-disable-next-line no-undef
		const esito = await axe.run( document, { runOnly: { type: 'tag', values: regole }, resultTypes: [ 'violations' ] } );

		return esito.violations.map( ( v ) => ( {
			regola: v.id,
			impatto: v.impact,
			cosa: v.help,
			dove: v.nodes.map( ( n ) => n.target.join( ' ' ) ),
			// Almeno un punto dentro il codice dell'albo, o nel documento intero (titolo, lingua).
			albo: v.nodes.some( ( n ) => {
				const nodo = document.querySelector( n.target[ 0 ] );

				return ! nodo || nodo === document.documentElement || nodo.closest( '.albo-pretorio' ) !== null;
			} ),
		} ) );
	}, REGOLE );
}

/**
 * Il percorso con il tasto Tab dall'inizio della pagina.
 *
 * Ogni elemento che si puo' raggiungere riceve il suo posto nell'ordine della
 * pagina; poi si preme Tab una volta per elemento, piu' due, e si annota dove
 * sta il fuoco e se l'indicatore si vede.
 */
async function percorso( pagina ) {
	const elementi = await pagina.evaluate( () => {
		const selettore = 'a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
		const visibili = [ ...document.querySelectorAll( selettore ) ].filter( ( e ) => {
			const s = getComputedStyle( e );
			const r = e.getBoundingClientRect();

			return s.visibility !== 'hidden' && s.display !== 'none' && ( r.width > 0 || r.height > 0 );
		} );

		visibili.forEach( ( e, i ) => e.setAttribute( 'data-ordine-prova', String( i ) ) );

		return visibili.map( ( e ) => ( {
			albo: e.closest( '.albo-pretorio' ) !== null,
			date: [ 'date', 'time', 'datetime-local', 'month', 'week' ].includes( e.getAttribute( 'type' ) ),
			nome: ( e.getAttribute( 'id' ) || e.getAttribute( 'name' ) || e.textContent || e.tagName ).trim().slice( 0, 40 ),
		} ) );
	} );

	await pagina.evaluate( () => {
		document.activeElement?.blur();
		window.scrollTo( 0, 0 );
	} );

	const passi = [];
	// Un campo di data si percorre a pezzi, giorno, mese e anno: fino a tre Tab sullo stesso campo.
	const pressioni = elementi.length + 2 + 2 * elementi.filter( ( e ) => e.date ).length;

	for ( let i = 0; i < pressioni; i++ ) {
		await pagina.keyboard.press( 'Tab' );
		passi.push(
			await pagina.evaluate( () => {
				const a = document.activeElement;

				if ( ! a || a === document.body || ! a.hasAttribute( 'data-ordine-prova' ) ) {
					return null;
				}

				const s = getComputedStyle( a );
				const contorno = s.outlineStyle !== 'none' && parseFloat( s.outlineWidth ) >= 2;
				const ombra = s.boxShadow && s.boxShadow !== 'none';

				return { posto: Number( a.getAttribute( 'data-ordine-prova' ) ), visibile: Boolean( contorno || ombra ) };
			} )
		);
	}

	return { elementi, passi };
}

function controlla_percorso( { elementi, passi: grezzi }, etichetta ) {
	const trovati = [];
	const n = elementi.length;

	// I Tab di seguito sullo stesso campo di data sono i suoi pezzi: contano come uno.
	const passi = grezzi.filter( ( p, i ) => ! ( i > 0 && p && grezzi[ i - 1 ] && p.posto === grezzi[ i - 1 ].posto && elementi[ p.posto ]?.date ) );

	if ( elementi.filter( ( e ) => e.albo ).length === 0 ) {
		sbaglia( `${ etichetta }: nessun comando dell'albo nella pagina, la prova non guarda niente` );
		return trovati;
	}

	for ( let i = 0; i < n; i++ ) {
		const passo = passi[ i ];

		if ( ! passo || passo.posto !== i ) {
			trovati.push( `al Tab numero ${ i + 1 } il fuoco doveva essere su "${ elementi[ i ].nome }" (posto ${ i }), e' su ${ passo ? `"${ elementi[ passo.posto ]?.nome }" (posto ${ passo.posto })` : 'nessun elemento' }` );
			break;
		}

		if ( elementi[ i ].albo && ! passo.visibile ) {
			trovati.push( `"${ elementi[ i ].nome }" riceve il fuoco senza un indicatore visibile` );
		}
	}

	// Dall'ultimo si esce: il Tab dopo l'ultimo lascia la pagina, o ricomincia dal primo.
	const dopo = passi[ n ];

	if ( dopo && dopo.posto !== 0 ) {
		trovati.push( `dopo l'ultimo elemento il fuoco resta in pagina, su "${ elementi[ dopo.posto ]?.nome }"` );
	}

	return trovati;
}

async function tab_fino_a( pagina, id, massimo = 80 ) {
	await pagina.evaluate( () => document.activeElement?.blur() );

	for ( let i = 0; i < massimo; i++ ) {
		await pagina.keyboard.press( 'Tab' );

		if ( await pagina.evaluate( ( atteso ) => document.activeElement?.id === atteso, id ) ) {
			return true;
		}
	}

	return false;
}

async function tab_fino_al_pulsante( pagina, testo, massimo = 20 ) {
	for ( let i = 0; i < massimo; i++ ) {
		await pagina.keyboard.press( 'Tab' );

		if ( await pagina.evaluate( ( t ) => document.activeElement?.tagName === 'BUTTON' && document.activeElement.textContent.trim() === t, testo ) ) {
			return true;
		}
	}

	return false;
}

const browser = await chromium.launch( { executablePath: process.env.CHROME } );

try {
	// Controllo di realta': il motore e il percorso vedono i guasti della pagina guastata.
	{
		const contesto = await browser.newContext( { viewport: SCHERMI.largo } );
		const pagina = await contesto.newPage();

		await pagina.setContent( GUASTA );

		// Trovati, e riconosciuti come dell'albo: altrimenti su un tema vero diventerebbero note.
		const regole = ( await esamina( pagina ) ).filter( ( v ) => v.albo ).map( ( v ) => v.regola );

		for ( const attesa of [ 'image-alt', 'label' ] ) {
			if ( ! regole.includes( attesa ) ) {
				sbaglia( `controllo di realta': sulla pagina guastata il motore non trova "${ attesa }" (trova: ${ regole.join( ', ' ) || 'niente' })` );
			}
		}

		if ( conTastiera ) {
			await pagina.setContent( GUASTA );
			const trovati = controlla_percorso( await percorso( pagina ), 'pagina guastata' );

			if ( ! trovati.some( ( t ) => t.includes( 'senza un indicatore visibile' ) ) ) {
				sbaglia( 'controllo di realta\': sulla pagina guastata il percorso da tastiera non vede il fuoco senza indicatore' );
			}
		}

		await contesto.close();
		console.log( `Controllo di realta' (${ tema }): ${ errori === 0 ? 'il motore e il percorso vedono i guasti' : 'NON RIUSCITO' }` );
	}

	if ( errori > 0 ) {
		throw new Error( 'controllo di realta\' non riuscito: la prova non e\' affidabile' );
	}

	for ( const [ schermo, viewport ] of Object.entries( SCHERMI ) ) {
		const contesto = await browser.newContext( { viewport } );
		const pagina = await contesto.newPage();

		for ( const nome of PAGINE ) {
			const risposta = await pagina.goto( dati[ nome ], { waitUntil: 'load' } );
			const etichetta = `${ tema }, ${ schermo }, ${ nome }`;

			if ( ! risposta || risposta.status() !== 200 ) {
				sbaglia( `${ etichetta }: la pagina risponde ${ risposta?.status() }` );
				continue;
			}

			if ( ( await pagina.locator( '.albo-pretorio' ).count() ) === 0 ) {
				sbaglia( `${ etichetta }: la pagina non contiene l'albo` );
				continue;
			}

			const violazioni = await esamina( pagina );

			for ( const v of violazioni ) {
				const riga = `${ etichetta }: ${ v.regola } (${ v.impatto }) ${ v.cosa } in ${ v.dove.join( '; ' ) }`;

				// Sul tema spoglio tutto e' dell'albo. Su un tema vero, cio' che sta tutto fuori dall'albo e' del tema: si annota, non ferma la prova.
				if ( v.albo || tuttaLaPagina ) {
					sbaglia( riga );
				} else {
					console.log( `  NOTA, del tema e non dell'albo: ${ riga }` );
				}
			}

			if ( conTastiera ) {
				await pagina.goto( dati[ nome ], { waitUntil: 'load' } );

				for ( const t of controlla_percorso( await percorso( pagina ), etichetta ) ) {
					sbaglia( `${ etichetta }, da tastiera: ${ t }` );
				}
			}

			console.log( `${ etichetta }: ${ violazioni.filter( ( v ) => v.albo || tuttaLaPagina ).length } violazioni dell'albo${ conTastiera ? ', percorso da tastiera controllato' : '' }` );
		}

		if ( conTastiera ) {
			// La ricerca per testo senza mouse: Tab fino al campo, si scrive, Invio.
			await pagina.goto( dati.elenco, { waitUntil: 'load' } );

			if ( ! ( await tab_fino_a( pagina, 'albo_testo' ) ) ) {
				sbaglia( `${ tema }, ${ schermo }: il campo di ricerca non si raggiunge con Tab` );
			} else {
				await pagina.keyboard.type( 'manutenzione' );
				await Promise.all( [ pagina.waitForNavigation( { waitUntil: 'load' } ), pagina.keyboard.press( 'Enter' ) ] );

				const righe = await pagina.locator( 'tr.albo-pretorio__atto' ).allTextContents();

				if ( righe.length !== 1 || ! righe[ 0 ].includes( dati[ 'oggetto della scheda' ] ) ) {
					sbaglia( `${ tema }, ${ schermo }: la ricerca per testo da tastiera trova ${ righe.length } atti invece dell'atto atteso` );
				}
			}

			// La ricerca per tipo senza mouse: Tab fino all'elenco dei tipi, si sceglie scrivendo, Tab fino a "Cerca", Invio.
			await pagina.goto( dati.elenco, { waitUntil: 'load' } );

			if ( ! ( await tab_fino_a( pagina, 'albo_tipo' ) ) ) {
				sbaglia( `${ tema }, ${ schermo }: l'elenco dei tipi non si raggiunge con Tab` );
			} else {
				await pagina.keyboard.type( 'Avv' );

				if ( ! ( await tab_fino_al_pulsante( pagina, 'Cerca' ) ) ) {
					sbaglia( `${ tema }, ${ schermo }: il pulsante "Cerca" non si raggiunge con Tab` );
				} else {
					await Promise.all( [ pagina.waitForNavigation( { waitUntil: 'load' } ), pagina.keyboard.press( 'Enter' ) ] );

					const indirizzo = new URL( pagina.url() );
					const conteggio = ( await pagina.locator( '.albo-pretorio__conteggio' ).first().textContent() )?.trim();

					if ( indirizzo.searchParams.get( 'albo_tipo' ) !== String( dati[ 'tipo scelto' ] ) || conteggio !== '10 atti' ) {
						sbaglia( `${ tema }, ${ schermo }: la ricerca per tipo da tastiera da' "${ conteggio }" con tipo ${ indirizzo.searchParams.get( 'albo_tipo' ) }, attesi "10 atti" e tipo ${ dati[ 'tipo scelto' ] }` );
					}
				}
			}

			console.log( `${ tema }, ${ schermo }: ricerca per testo e per tipo fatte da sola tastiera` );
		}

		await contesto.close();
	}
} finally {
	await browser.close();
}

if ( errori > 0 ) {
	console.log( `\n${ errori } problemi su ${ tema }.` );
	process.exit( 1 );
}

console.log( `\nNessun problema su ${ tema }.` );
