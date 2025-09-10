<?php
/**
 * Classe per gestire l'API di football-data.org
 */

if (!defined('ABSPATH')) {
    exit;
}

class Football_Data_API {

    private $api_key;
    private $base_url = 'https://api.football-data.org/v4/';
    private $juventus_id = 109; // ID Juventus su football-data.org

    public function __construct() {
        $this->api_key = get_option('football_data_api_key', '');
    }

    /**
     * Effettua una richiesta all'API con caching
     */
    private function make_request($endpoint, $params = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Chiave API non configurata', 'football-data-bp'));
        }

        $cache_key = 'football_data_' . md5($endpoint . serialize($params));
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return $cached_data;
        }

        $url = $this->base_url . $endpoint;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $response = wp_remote_get($url, array(
            'headers' => array(
                'X-Auth-Token' => $this->api_key
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_error', __('Errore nella decodifica JSON', 'football-data-bp'));
        }

        // Cache per 1 ora
        set_transient($cache_key, $data, HOUR_IN_SECONDS);

        return $data;
    }

    /**
     * Ottieni le competizioni disponibili
     */
    public function get_competitions() {
        return $this->make_request('competitions');
    }

    /**
     * Ottieni i dettagli di una competizione
     */
    public function get_competition($id) {
        return $this->make_request('competitions/' . intval($id));
    }

    /**
     * Ottieni le partite di una competizione
     */
    public function get_matches($competition_id, $params = array()) {
        return $this->make_request('competitions/' . intval($competition_id) . '/matches', $params);
    }

    /**
     * Ottieni i dettagli di una squadra
     */
    public function get_team($id) {
        return $this->make_request('teams/' . intval($id));
    }

    /**
     * Ottieni le partite di una squadra
     */
    public function get_team_matches($team_id, $params = array()) {
        return $this->make_request('teams/' . intval($team_id) . '/matches', $params);
    }

    /**
     * Ottieni la classifica di una competizione
     */
    public function get_standings($competition_id) {
        return $this->make_request('competitions/' . intval($competition_id) . '/standings');
    }

    /**
     * Ottieni dati Juventus
     */
    public function get_juventus_data() {
        return $this->get_team($this->juventus_id);
    }

    /**
     * Ottieni partite Juventus
     */
    public function get_juventus_matches($params = array()) {
        return $this->get_team_matches($this->juventus_id, $params);
    }

    /**
     * Ottieni competizioni Juventus (tutte quelle a cui partecipa)
     */
    public function get_juventus_competitions() {
        $competitions = $this->get_competitions();

        if (is_wp_error($competitions) || !isset($competitions['competitions'])) {
            return array();
        }

        $juventus_competitions = array();

        foreach ($competitions['competitions'] as $competition) {
            // Per ogni competizione, controlliamo se la Juventus è iscritta
            $competition_details = $this->get_competition($competition['id']);
            if (!is_wp_error($competition_details) && isset($competition_details['teams'])) {
                foreach ($competition_details['teams'] as $team) {
                    if ($team['id'] == $this->juventus_id) {
                        $juventus_competitions[] = $competition;
                        break;
                    }
                }
            }
        }

        return array('competitions' => $juventus_competitions);
    }

    /**
     * Ottieni classifica Juventus in una competizione
     */
    public function get_juventus_standings($competition_id) {
        $standings = $this->get_standings($competition_id);

        if (is_wp_error($standings) || !isset($standings['standings'][0]['table'])) {
            return $standings;
        }

        // Trova la posizione della Juventus nella classifica
        foreach ($standings['standings'][0]['table'] as $position) {
            if ($position['team']['id'] == $this->juventus_id) {
                return array(
                    'standings' => array(
                        array(
                            'stage' => $standings['standings'][0]['stage'],
                            'type' => $standings['standings'][0]['type'],
                            'group' => $standings['standings'][0]['group'],
                            'table' => array($position)
                        )
                    )
                );
            }
        }

        return $standings;
    }

    /**
     * Ottieni partite Juventus filtrate per competizione e data futura
     */
    public function get_juventus_matches_filtered($competitions = array(), $from_date = null, $limit = 50) {
        $params = array('limit' => $limit);
        if ($from_date) {
            $params['dateFrom'] = $from_date;
        }
        $all_matches = $this->get_team_matches($this->juventus_id, $params);
        if (is_wp_error($all_matches) || !isset($all_matches['matches'])) return $all_matches;
        $filtered = array();
        foreach ($all_matches['matches'] as $match) {
            if ((!empty($competitions) && in_array($match['competition']['code'], $competitions)) && substr($match['utcDate'], 0, 10) >= $from_date) {
                $filtered[] = $match;
            }
        }
        return array('matches' => $filtered);
    }

    /**
     * Recupera una partita specifica per ID
     */
    public function get_match_by_id($match_id) {
        return $this->make_request('matches/' . intval($match_id));
    }
}
?>
