#!/usr/bin/env bash
#
# Prova della pagina pubblica dell'albo in un browser vero (ALBO-16, ALBO-28).
#
# Installa un sito WordPress di prova, attiva il componente comune e l'albo,
# pubblica gli atti con i passaggi veri (tests/browser/prepara-sito.php), lo
# serve con il server di PHP su questo computer e lo esamina con
# tests/browser/accessibilita.mjs: motore automatico di accessibilita' su due
# temi, il predefinito di WordPress e un tema spoglio, e percorso da sola
# tastiera sul tema spoglio. Nessuna rete esterna durante la prova: il sito, il
# motore e il browser sono tutti su questo computer.
#
# Uso: bin/prova-nel-browser.sh
#
# Variabili (con il valore che si usa se mancano):
#   WP_DIR       cartella del sito di prova (/tmp/wordpress-pagine); se non
#                contiene WordPress, lo scarica wp-cli nella versione WP_VERSION
#   WP_VERSION   versione da scaricare (latest)
#   WP_CLI       comando di wp-cli (wp)
#   DB_NAME, DB_USER, DB_PASS, DB_HOST  banca dati (albo_pagine, root, root, 127.0.0.1)
#   CHROME       percorso del browser (obbligatoria)
#   PORTA        porta del server di prova (8889)
#   TEMA_PREDEFINITO  tema di WordPress da esaminare (twentytwentyfive)

set -euo pipefail

RADICE="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
WP_DIR="${WP_DIR:-/tmp/wordpress-pagine}"
WP_VERSION="${WP_VERSION:-latest}"
read -r -a WP <<< "${WP_CLI:-wp}"
DB_NAME="${DB_NAME:-albo_pagine}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-root}"
DB_HOST="${DB_HOST:-127.0.0.1}"
PORTA="${PORTA:-8889}"
TEMA_PREDEFINITO="${TEMA_PREDEFINITO:-twentytwentyfive}"
TEMA_SPOGLIO="albo-prova-tema-spoglio"
INDIRIZZO="http://127.0.0.1:${PORTA}"
PAGINE="$( mktemp )"

: "${CHROME:?indicare il percorso del browser nella variabile CHROME}"

wp_sito() {
	"${WP[@]}" --path="${WP_DIR}" --url="${INDIRIZZO}" "$@"
}

if [ ! -f "${WP_DIR}/wp-load.php" ]; then
	"${WP[@]}" core download --path="${WP_DIR}" --version="${WP_VERSION}"
fi

wp_sito config create --dbname="${DB_NAME}" --dbuser="${DB_USER}" --dbpass="${DB_PASS}" --dbhost="${DB_HOST}" --skip-check --force
wp_sito db reset --yes 2> /dev/null || wp_sito db create
wp_sito core install --url="${INDIRIZZO}" --title="Sito di prova" --admin_user=amministratore --admin_password="$( openssl rand -hex 16 )" --admin_email=amministratore@example.org --skip-email

# L'indirizzo del sito lo si scrive: wp-cli lo indovinerebbe dalla cartella.
wp_sito option update siteurl "${INDIRIZZO}"
wp_sito option update home "${INDIRIZZO}"

# Copiati e non collegati: con un collegamento simbolico WordPress calcola male
# l'indirizzo del foglio di stile dell'albo, e la pagina uscirebbe senza.
rm -rf "${WP_DIR}/wp-content/plugins/albo-pretorio-pa" "${WP_DIR}/wp-content/themes/${TEMA_SPOGLIO}"
rsync -a --exclude=.git --exclude=vendor --exclude=node_modules --exclude=tests "${RADICE}/" "${WP_DIR}/wp-content/plugins/albo-pretorio-pa/"
cp -r "${RADICE}/tests/browser/tema-spoglio" "${WP_DIR}/wp-content/themes/${TEMA_SPOGLIO}"

if [ ! -f "${WP_DIR}/wp-content/plugins/conformita-core/conformita-core.php" ]; then
	WP_CORE_DIR="${WP_DIR}" "${RADICE}/bin/installa-core.sh"
fi

wp_sito plugin activate conformita-core albo-pretorio-pa
wp_sito eval-file "${RADICE}/tests/browser/prepara-sito.php" > "${PAGINE}"

php -S "127.0.0.1:${PORTA}" -t "${WP_DIR}" > "${WP_DIR}/server-di-prova.log" 2>&1 &
SERVER=$!
trap 'kill "${SERVER}" 2> /dev/null || true; rm -f "${PAGINE}"' EXIT

for _ in $( seq 1 50 ); do
	curl -fs -o /dev/null "${INDIRIZZO}/" && break
	sleep 0.2
done

( cd "${RADICE}/tests/browser" && npm ci --no-audit --no-fund --silent )

esito=0

wp_sito theme activate "${TEMA_PREDEFINITO}"
CHROME="${CHROME}" node "${RADICE}/tests/browser/accessibilita.mjs" --pagine "${PAGINE}" --tema "${TEMA_PREDEFINITO}" || esito=1

wp_sito theme activate "${TEMA_SPOGLIO}"
CHROME="${CHROME}" node "${RADICE}/tests/browser/accessibilita.mjs" --pagine "${PAGINE}" --tema "${TEMA_SPOGLIO}" --tastiera --tutta-la-pagina || esito=1

exit "${esito}"
