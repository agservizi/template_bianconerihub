# Juventus Football Data BuddyPress Integration

Questo plugin integra i dati di calcio da football-data.org con BuddyPress, con un focus esclusivo sulla Juventus e tutte le sue competizioni reali (Serie A, Champions League, Coppa Italia, ecc.).

## Caratteristiche

- **Focus Juventus**: Mostra esclusivamente dati della Juventus
- **Tutte le competizioni reali**: Serie A, Champions League, Coppa Italia, Europa League, Supercoppa Italiana
- **Campo profilo**: Gli utenti possono selezionare Juventus come squadra preferita
- **Componente calcio**: Sezione dedicata nel profilo per visualizzare partite e risultati Juventus
- **Attività partite**: Pubblicazione automatica di risultati partite Juventus come attività BuddyPress
- **Shortcode partite**: `[football_matches]` mostra partite Juventus (default)
- **Shortcode classifica**: `[football_standings]` mostra classifica Serie A con Juventus evidenziata
- **Aggiornamenti live**: Le partite Juventus in corso si aggiornano automaticamente
- **Caching**: I dati vengono memorizzati nella cache per migliorare le prestazioni

## Installazione

1. Scarica il plugin e caricalo nella cartella `/wp-content/plugins/`.
2. Attiva il plugin dal pannello amministratore di WordPress.
3. **IMPORTANTE**: Vai su **Impostazioni > Football Data** e inserisci questa chiave API: `4e6fb37ef10a4c228765ee6556d78e4a`
4. Assicurati che BuddyPress sia attivo e configurato.
5. Verifica che il plugin funzioni visitando la sezione "Calcio" di un profilo utente.

## Configurazione API

### Chiave API Disponibile

Il plugin è configurato per utilizzare la seguente chiave API gratuita:

```
4e6fb37ef10a4c228765ee6556d78e4a
```

### Come Configurare la Chiave API

1. **Metodo 1 - Pannello Admin**:

   - Vai su **Impostazioni > Football Data** nel pannello WordPress
   - Inserisci la chiave API: `4e6fb37ef10a4c228765ee6556d78e4a`
   - Salva le impostazioni

2. **Metodo 2 - Database** (se necessario):
   - La chiave viene salvata nell'opzione WordPress: `football_data_api_key`
   - Puoi modificarla direttamente nel database se hai accesso

### Verifica Configurazione

Dopo aver inserito la chiave API:

- Vai alla sezione "Calcio" di un profilo utente
- Dovresti vedere i dati della Juventus caricarsi correttamente
- Se vedi errori, verifica che la chiave API sia corretta

### File di Configurazione

Il plugin include un file `config-example.php` che mostra come configurare correttamente la chiave API. Questo file è solo un esempio e **NON deve essere caricato sul server**.

## Competizioni Juventus

Il plugin mostra automaticamente tutte le competizioni reali a cui partecipa la Juventus:

- **Serie A** (SA)
- **Champions League** (CL)
- **Europa League** (EL)
- **Coppa Italia** (CI)
- **Supercoppa Italiana** (SCI)

## Uso degli Shortcode

### Mostrare Partite Juventus

```
[football_matches limit="5"]
```

Mostra le prossime 5 partite della Juventus in tutte le competizioni.

### Mostrare Classifica Serie A

```
[football_standings]
```

Mostra la classifica della Serie A con la Juventus evidenziata in nero.

### Parametri Shortcode

- `limit`: Numero massimo di partite da mostrare (solo per matches, default 10)
- `competition`: Codice competizione (default Juventus per matches, SA per standings)

### Parametri Shortcode

- `competition`: Codice della competizione (vedi sopra)
- `limit`: Numero massimo di partite da mostrare (solo per matches)

## Funzionalità BuddyPress

- **Profilo utente**: Campo per selezionare squadra preferita
- **Sezione calcio**: Visualizza partite e risultati della squadra preferita
- **Attività**: Condividi risultati partite
- **Gruppi**: Possibilità di creare gruppi per squadre

## Personalizzazione

Il plugin include file CSS e JavaScript che possono essere personalizzati modificando:

- `assets/css/football-data-bp.css`
- `assets/js/football-data-bp.js`

## Requisiti

- WordPress 5.0+
- BuddyPress 10.0+
- PHP 7.0+
- Chiave API da football-data.org

## Limitazioni API

L'API gratuita di football-data.org ha limiti di richieste:

- 10 richieste al minuto
- 100 richieste al giorno

Per uso intensivo, considera un piano a pagamento.

## Troubleshooting

### Problemi Comuni

**1. "Chiave API non configurata"**

- Verifica di aver inserito correttamente la chiave API: `4e6fb37ef10a4c228765ee6556d78e4a`
- Vai su Impostazioni > Football Data nel pannello WordPress

**2. "Errore nel recupero dati"**

- Controlla che BuddyPress sia attivo
- Verifica la connessione internet
- Potresti aver superato i limiti API (10 richieste/minuto, 100/giorno)

**3. Dati non si aggiornano**

- Svuota la cache del browser (Ctrl+F5)
- I dati sono cachati per 1 ora, attendi o svuota la cache WordPress
- Verifica che la chiave API sia valida

**4. Shortcode non funziona**

- Assicurati che il plugin sia attivo
- Verifica che BuddyPress sia configurato correttamente
- Prova a disattivare/riattivare il plugin

**5. Problemi di visualizzazione**

- Verifica che i file CSS e JS siano caricati correttamente
- Controlla conflitti con altri plugin
- Prova a disabilitare temporaneamente altri plugin

### Debug

Per abilitare il debug, aggiungi al file `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

I log degli errori saranno salvati in `wp-content/debug.log`.

## Supporto

Per supporto tecnico, segnala problemi su GitHub o contatta lo sviluppatore.

## Changelog

### 1.0.1

- Aggiunta chiave API pre-configurata: `4e6fb37ef10a4c228765ee6556d78e4a`
- Migliorata documentazione con istruzioni dettagliate per la configurazione
- Aggiunto file di configurazione di esempio
- Sezione troubleshooting completa
- Focus esclusivo su Juventus e competizioni reali

### 1.0.0

- Rilascio iniziale
- Integrazione base con football-data.org
- Campo profilo squadra preferita
- Shortcode per partite e classifica
- Supporto AJAX per aggiornamenti live
