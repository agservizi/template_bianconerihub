<?php
/**
 * File di configurazione di esempio per Juventus Football Data BuddyPress Integration
 *
 * Questo file mostra come configurare il plugin con la chiave API fornita.
 * NON caricare questo file sul server - è solo un esempio!
 */

// Chiave API da utilizzare nel plugin
$api_key = '4e6fb37ef10a4c228765ee6556d78e4a';

// Esempio di configurazione per il plugin Juventus Football Data
// Inserisci la chiave API qui
$football_data_api_key = '4e6fb37ef10a4c228765ee6556d78e4a';

// Per configurare il plugin:
// 1. Vai su Impostazioni > Football Data nel pannello WordPress
// 2. Inserisci la chiave API sopra nel campo "Chiave API"
// 3. Salva le impostazioni

// La chiave API verrà salvata automaticamente nell'opzione WordPress:
// update_option('football_data_api_key', $api_key);

// Limiti API gratuita:
// - 10 richieste al minuto
// - 100 richieste al giorno
// - Per uso intensivo, considera un piano a pagamento su football-data.org

// ID Juventus su football-data.org: 109
$juventus_id = 109;

// Competizioni Juventus supportate:
// - SA: Serie A
// - CL: Champions League
// - EL: Europa League
// - CI: Coppa Italia
// - SCI: Supercoppa Italiana
?>
