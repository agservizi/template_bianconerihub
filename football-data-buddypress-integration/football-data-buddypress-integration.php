<?php
/**
 * Plugin Name: Juventus Football Data BuddyPress Integration
 * Plugin URI: https://example.com/football-data-buddypress-integration
 * Description: Integra dati di calcio da football-data.org con BuddyPress, con focus esclusivo sulla Juventus e tutte le sue competizioni reali.
 * Version: 1.0.1
 * Author: Tu Nome
 * License: GPL v2 or later
 * Text Domain: football-data-bp
 */

// Prevenire accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Definire costanti
define('FOOTBALL_DATA_BP_VERSION', '1.0.1');
define('FOOTBALL_DATA_BP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FOOTBALL_DATA_BP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Includere file necessari
require_once FOOTBALL_DATA_BP_PLUGIN_DIR . 'includes/class-football-data-api.php';
require_once FOOTBALL_DATA_BP_PLUGIN_DIR . 'includes/class-buddypress-integration.php';
require_once FOOTBALL_DATA_BP_PLUGIN_DIR . 'includes/class-juventus-widget.php';

// Classe principale del plugin
class Football_Data_BuddyPress_Integration {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    private function init() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('bp_init', array($this, 'init_buddypress_integration'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_football_data_get_matches', array($this, 'ajax_get_matches'));
        add_action('wp_ajax_football_data_get_standings', array($this, 'ajax_get_standings'));
        add_action('wp_ajax_football_data_get_team', array($this, 'ajax_get_team'));
        add_action('wp_ajax_football_data_update_match', array($this, 'ajax_update_match'));
    }

    public function load_textdomain() {
        load_plugin_textdomain('football-data-bp', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    public function init_buddypress_integration() {
        if (!function_exists('buddypress')) {
            return;
        }

        // Inizializzare l'integrazione BuddyPress
        new Football_Data_BuddyPress_Integration_BP();
    }

    public function enqueue_scripts() {
        wp_enqueue_style('football-data-bp', FOOTBALL_DATA_BP_PLUGIN_URL . 'assets/css/football-data-bp.css', array(), FOOTBALL_DATA_BP_VERSION);
        wp_enqueue_script('football-data-bp', FOOTBALL_DATA_BP_PLUGIN_URL . 'assets/js/football-data-bp.js', array('jquery'), FOOTBALL_DATA_BP_VERSION, true);

        wp_localize_script('football-data-bp', 'football_data_bp', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('football_data_bp_nonce')
        ));

        wp_register_script('football-data-push-sw', FOOTBALL_DATA_BP_PLUGIN_URL . 'assets/football-data-push-sw.js', array(), FOOTBALL_DATA_BP_VERSION, true);
        wp_enqueue_script('football-data-push-register', false, array(), FOOTBALL_DATA_BP_VERSION, true);
        echo "<script>
        if ('serviceWorker' in navigator) {
          navigator.serviceWorker.register('" . FOOTBALL_DATA_BP_PLUGIN_URL . "assets/football-data-push-sw.js').then(function(reg) {
            console.log('Service Worker registrato:', reg);
          });
        }
        function urlBase64ToUint8Array(base64String) {
          const padding = '='.repeat((4 - base64String.length % 4) % 4);
          const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
          const rawData = window.atob(base64);
          const outputArray = new Uint8Array(rawData.length);
          for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
          }
          return outputArray;
        }
        window.addEventListener('load', function() {
          if ('serviceWorker' in navigator && window.Notification) {
            navigator.serviceWorker.ready.then(function(reg) {
              Notification.requestPermission().then(function(permission) {
                if (permission === 'granted') {
                  fetch('" . admin_url('admin-ajax.php') . "', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=football_data_register_push&nonce=' + football_data_bp.nonce + '&subscription=' + encodeURIComponent(JSON.stringify(reg.pushManager.subscribe({
                      userVisibleOnly: true,
                      applicationServerKey: urlBase64ToUint8Array('BL_URgSP4KSLyns4JyUx3vE82Ejn6Pd0XCFNmaVuRPhHf8dQDf4Tz8Q9WRUx7yn_efcj6szI4UGObNdcndVTxws')
                    }))
                  })
                }
              });
            });
          }
        });
        </script>";
    }

    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_football-data-settings' !== $hook) {
            return;
        }

        wp_enqueue_style('football-data-bp-admin', FOOTBALL_DATA_BP_PLUGIN_URL . 'assets/css/football-data-bp.css', array(), FOOTBALL_DATA_BP_VERSION);
    }

    public function ajax_get_matches() {
        check_ajax_referer('football_data_bp_nonce', 'nonce');

        $competition = sanitize_text_field($_POST['competition']);
        $limit = intval($_POST['limit']);

        $api = new Football_Data_API();
        $matches = $api->get_matches($competition, array('limit' => $limit));

        if (is_wp_error($matches)) {
            wp_send_json_error(__('Errore nel recupero partite', 'football-data-bp'));
        }

        $html = $this->render_matches_html($matches);
        wp_send_json_success($html);
    }

    public function ajax_get_standings() {
        check_ajax_referer('football_data_bp_nonce', 'nonce');

        $competition = sanitize_text_field($_POST['competition']);

        $api = new Football_Data_API();
        $standings = $api->get_standings($competition);

        if (is_wp_error($standings)) {
            wp_send_json_error(__('Errore nel recupero classifica', 'football-data-bp'));
        }

        $html = $this->render_standings_html($standings);
        wp_send_json_success($html);
    }

    public function ajax_get_team() {
        check_ajax_referer('football_data_bp_nonce', 'nonce');

        $team_id = intval($_POST['team_id']);

        $api = new Football_Data_API();
        $team = $api->get_team($team_id);

        if (is_wp_error($team)) {
            wp_send_json_error(__('Errore nel recupero squadra', 'football-data-bp'));
        }

        $html = $this->render_team_html($team);
        wp_send_json_success($html);
    }

    public function ajax_update_match() {
        check_ajax_referer('football_data_bp_nonce', 'nonce');

        $match_id = intval($_POST['match_id']);

        // Per semplicità, ricarica tutte le partite (in produzione, implementare endpoint specifico)
        $api = new Football_Data_API();
        $matches = $api->get_matches('', array('limit' => 1)); // Placeholder

        if (is_wp_error($matches)) {
            wp_send_json_error(__('Errore nell\'aggiornamento partita', 'football-data-bp'));
        }

        $html = $this->render_match_html($matches['matches'][0]);
        wp_send_json_success($html);
    }

    private function render_matches_html($data) {
        if (!isset($data['matches'])) {
            return '';
        }

        $html = '<div class="football-data-matches">';
        foreach ($data['matches'] as $match) {
            $html .= $this->render_match_html($match);
        }
        $html .= '</div>';

        return $html;
    }

    private function render_match_html($match) {
        $status_class = strtolower($match['status']);
        $html = '<div class="football-data-match ' . esc_attr($status_class) . '" data-match-id="' . esc_attr($match['id']) . '">';
        $html .= '<div class="match-teams">';
        $html .= '<div class="match-team">' . esc_html($match['homeTeam']['name']) . '</div>';
        $html .= '<div class="match-score">' . esc_html($match['score']['fullTime']['home'] ?? '-') . ' - ' . esc_html($match['score']['fullTime']['away'] ?? '-') . '</div>';
        $html .= '<div class="match-team">' . esc_html($match['awayTeam']['name']) . '</div>';
        $html .= '</div>';
        $html .= '<div class="match-status ' . esc_attr($status_class) . '">' . esc_html($match['status']) . '</div>';
        $html .= '</div>';

        return $html;
    }

    private function render_standings_html($data) {
        if (!isset($data['standings'][0]['table'])) {
            return '';
        }

        $html = '<table class="football-data-standings">';
        $html .= '<thead><tr><th>Pos</th><th>Squadra</th><th>Punti</th><th>Giocate</th><th>Vinte</th><th>Pareggiate</th><th>Perse</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($data['standings'][0]['table'] as $team) {
            $html .= '<tr>';
            $html .= '<td>' . esc_html($team['position']) . '</td>';
            $html .= '<td>' . esc_html($team['team']['name']) . '</td>';
            $html .= '<td>' . esc_html($team['points']) . '</td>';
            $html .= '<td>' . esc_html($team['playedGames']) . '</td>';
            $html .= '<td>' . esc_html($team['won']) . '</td>';
            $html .= '<td>' . esc_html($team['draw']) . '</td>';
            $html .= '<td>' . esc_html($team['lost']) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private function render_team_html($team) {
        $html = '<div class="football-data-team-info">';
        if (isset($team['crest'])) {
            $html .= '<img src="' . esc_url($team['crest']) . '" alt="' . esc_attr($team['name']) . '">';
        }
        $html .= '<div>';
        $html .= '<h3>' . esc_html($team['name']) . '</h3>';
        if (isset($team['founded'])) {
            $html .= '<p>' . __('Fondata nel', 'football-data-bp') . ' ' . esc_html($team['founded']) . '</p>';
        }
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}

// Avviare il plugin
Football_Data_BuddyPress_Integration::get_instance();

// Funzione di attivazione
register_activation_hook(__FILE__, 'football_data_bp_activate');
function football_data_bp_activate() {
    // Codice di attivazione, se necessario
}

// Funzione di disattivazione
register_deactivation_hook(__FILE__, 'football_data_bp_deactivate');
function football_data_bp_deactivate() {
    // Codice di disattivazione, se necessario
}
?>
