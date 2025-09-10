<?php
/**
 * Classe per l'integrazione con BuddyPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class Football_Data_BuddyPress_Integration_BP {

    private $api;

    public function __construct() {
        $this->api = new Football_Data_API();
        $this->init_hooks();
    }

    private function init_hooks() {
        // Aggiungere campo profilo per squadra preferita
        add_action('bp_custom_profile_edit_fields', array($this, 'add_favorite_team_field'));
        add_action('xprofile_data_after_save', array($this, 'save_favorite_team_field'));

        // Aggiungere componente per dati calcio
        add_action('bp_setup_components', array($this, 'setup_football_component'), 10);

        // Aggiungere attività per partite
        add_action('bp_activity_register_activity_actions', array($this, 'register_activity_actions'));

        // Shortcode per mostrare partite
        add_shortcode('football_matches', array($this, 'football_matches_shortcode'));
        // Shortcode per mostrare classifica Juventus
        add_shortcode('football_standings', array($this, 'football_standings_shortcode'));

        // Aggiungere menu admin
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Hook di inizializzazione funzionalità avanzate
        add_action('widgets_init', array($this, 'register_widgets'));
        add_action('wp_head', array($this, 'custom_colors_css'));
        add_action('init', array($this, 'export_csv'));
        add_action('init', array($this, 'send_match_email_notification'));

        // Aggiungi selettore partita Juventus al modulo attività BuddyPress
        add_action('bp_activity_post_form_options', array($this, 'add_juventus_match_selector'));
        add_action('bp_activity_posted_update', array($this, 'save_juventus_match_to_activity'), 10, 3);
    }

    /**
     * Aggiungere campo profilo per squadra preferita
     */
    public function add_favorite_team_field() {
        if (!function_exists('bp_is_active') || !bp_is_active('xprofile')) {
            return;
        }

        $field_id = $this->get_favorite_team_field_id();

        if (!$field_id) {
            return;
        }

        $teams = $this->get_teams_list();

        ?>
        <div class="editfield field_<?php echo $field_id; ?> field_favorite_team">
            <label for="field_<?php echo $field_id; ?>"><?php _e('Squadra Preferita', 'football-data-bp'); ?></label>
            <select name="field_<?php echo $field_id; ?>" id="field_<?php echo $field_id; ?>">
                <option value=""><?php _e('Seleziona una squadra', 'football-data-bp'); ?></option>
                <?php foreach ($teams as $team_id => $team_name) : ?>
                    <option value="<?php echo esc_attr($team_id); ?>" <?php selected(bp_get_the_profile_field_edit_value(), $team_id); ?>>
                        <?php echo esc_html($team_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }

    /**
     * Salvare il campo squadra preferita
     */
    public function save_favorite_team_field($field_data) {
        // Implementazione per salvare il campo
    }

    /**
     * Ottenere l'ID del campo squadra preferita
     */
    private function get_favorite_team_field_id() {
        if (!function_exists('xprofile_get_field_id_from_name')) {
            return false;
        }

        $field_id = xprofile_get_field_id_from_name(__('Squadra Preferita', 'football-data-bp'));

        if (!$field_id) {
            // Creare il campo se non esiste
            $field_id = xprofile_insert_field(array(
                'field_group_id' => 1, // Gruppo base
                'name' => __('Squadra Preferita', 'football-data-bp'),
                'description' => __('Seleziona la tua squadra preferita', 'football-data-bp'),
                'type' => 'selectbox',
                'is_required' => false,
                'can_delete' => false
            ));

            if ($field_id) {
                // Aggiungere opzioni squadre
                $teams = $this->get_teams_list();
                foreach ($teams as $team_id => $team_name) {
                    xprofile_insert_field(array(
                        'field_group_id' => 1,
                        'parent_id' => $field_id,
                        'name' => $team_name,
                        'type' => 'option'
                    ));
                }
            }
        }

        return $field_id;
    }

    /**
     * Ottenere lista squadre (solo Juventus)
     */
    private function get_teams_list() {
        $juventus_data = $this->api->get_juventus_data();

        if (is_wp_error($juventus_data)) {
            return array();
        }

        return array(
            $juventus_data['id'] => $juventus_data['name']
        );
    }

    /**
     * Impostare componente calcio
     */
    public function setup_football_component() {
        bp_core_add_subnav_item(array(
            'name' => __('Calcio', 'football-data-bp'),
            'slug' => 'football',
            'parent_url' => bp_loggedin_user_domain() . 'football/',
            'parent_slug' => 'football',
            'screen_function' => array($this, 'football_screen'),
            'position' => 10
        ));
    }

    /**
     * Schermata calcio
     */
    public function football_screen() {
        add_action('bp_template_content', array($this, 'football_content'));
        bp_core_load_template('members/single/plugins');
    }

    /**
     * Contenuto schermata calcio (focus Juventus)
     */
    public function football_content() {
        $juventus_data = $this->api->get_juventus_data();
        $juventus_matches = $this->api->get_juventus_matches(array('limit' => 10));
        $juventus_competitions = $this->api->get_juventus_competitions();

        echo '<div class="football-data-juventus-section">';

        // Header Juventus
        if (!is_wp_error($juventus_data)) {
            echo '<div class="football-data-team-info">';
            if (isset($juventus_data['crest'])) {
                echo '<img src="' . esc_url($juventus_data['crest']) . '" alt="Juventus" style="width: 80px; height: 80px;">';
            }
            echo '<div>';
            echo '<h2>' . esc_html($juventus_data['name']) . '</h2>';
            if (isset($juventus_data['founded'])) {
                echo '<p>' . __('Fondata nel', 'football-data-bp') . ' ' . esc_html($juventus_data['founded']) . '</p>';
            }
            echo '</div>';
            echo '</div>';
        }

        // Competizioni Juventus filtrate
        if (!is_wp_error($juventus_competitions) && isset($juventus_competitions['competitions'])) {
            $filtered = $this->filter_juventus_competitions($juventus_competitions['competitions']);
            echo '<h3>' . __('Competizioni', 'football-data-bp') . '</h3>';
            echo '<ul class="football-data-competitions">';
            foreach ($filtered as $competition) {
                echo '<li>';
                echo '<strong>' . esc_html($competition['name']) . '</strong> (' . esc_html($competition['code']) . ')';
                echo ' - ' . esc_html($competition['area']['name']);
                echo '</li>';
            }
            echo '</ul>';
        }

        // Prossime partite Juventus filtrate
        if (!is_wp_error($juventus_matches) && isset($juventus_matches['matches'])) {
            $filtered_codes = array_map(function($c) { return $c['code']; }, $this->filter_juventus_competitions($juventus_competitions['competitions']));
            echo '<h3>' . __('Prossime Partite', 'football-data-bp') . '</h3>';
            echo '<div class="football-data-matches" data-competition="juventus" data-limit="5">';
            $count = 0;
            foreach ($juventus_matches['matches'] as $match) {
                if (in_array($match['competition']['code'], $filtered_codes)) {
                    $this->render_juventus_match($match);
                    $count++;
                }
                if ($count >= 5) break;
            }
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Render singola partita Juventus
     */
    private function render_juventus_match($match) {
        $compact = get_option('football_data_view_compact', 0);
        $status_class = strtolower($match['status']);
        $is_home = $match['homeTeam']['id'] == 109; // ID Juventus

        echo '<div class="football-data-match ' . esc_attr($status_class) . '" data-match-id="' . esc_attr($match['id']) . '">';
        if ($compact) {
            echo esc_html($match['homeTeam']['name']) . ' vs ' . esc_html($match['awayTeam']['name']) . ' | ' . esc_html($match['score']['fullTime']['home'] ?? '-') . '-' . esc_html($match['score']['fullTime']['away'] ?? '-') . ' | ' . esc_html($match['competition']['name']) . ' | ' . esc_html(date('d/m/Y', strtotime($match['utcDate'])));
        } else {
            echo '<div class="match-teams">';
            echo '<div class="match-team">' . esc_html($match['homeTeam']['name']) . '</div>';
            echo '<div class="match-score">' . esc_html($match['score']['fullTime']['home'] ?? '-') . ' - ' . esc_html($match['score']['fullTime']['away'] ?? '-') . '</div>';
            echo '<div class="match-team">' . esc_html($match['awayTeam']['name']) . '</div>';
            echo '</div>';
            echo '<div class="match-status ' . esc_attr($status_class) . '">' . esc_html($match['status']) . ' - ' . esc_html($match['competition']['name']) . '</div>';
            echo '<div class="match-date">' . esc_html(date('d/m/Y H:i', strtotime($match['utcDate']))) . '</div>';
        }
        echo '</div>';
    }

    /**
     * Ottenere squadra preferita utente
     */
    private function get_user_favorite_team($user_id) {
        // Implementazione
        return false;
    }

    /**
     * Registrare azioni attività
     */
    public function register_activity_actions() {
        bp_activity_set_action(
            'football',
            'match_result',
            __('Risultato Partita', 'football-data-bp')
        );
        add_filter('bp_get_activity_content_body', array($this, 'show_juventus_match_in_activity'), 10, 2);
    }

    /**
     * Aggiungi selettore partita Juventus al modulo attività BuddyPress
     */
    public function add_juventus_match_selector() {
        $competitions = array('SA', 'CL', 'CI', 'SCI');
        $api = new Football_Data_API();
        $matches = $api->get_juventus_matches_filtered($competitions, null, 50);
        if (is_wp_error($matches) || empty($matches['matches'])) return;
        echo '<select name="juventus_match_event" style="margin-top:10px;">';
        echo '<option value="">' . __('Collega una partita Juventus (opzionale)', 'football-data-bp') . '</option>';
        foreach ($matches['matches'] as $match) {
            $label = esc_html($match['homeTeam']['name']) . ' vs ' . esc_html($match['awayTeam']['name']) . ' (' . esc_html(date('d/m/Y', strtotime($match['utcDate']))) . ' - ' . esc_html($match['competition']['name']) . ')';
            echo '<option value="' . esc_attr($match['id']) . '">' . $label . '</option>';
        }
        echo '</select>';
    }

    /**
     * Salva la partita Juventus collegata all'attività BuddyPress
     */
    public function save_juventus_match_to_activity($content, $user_id, $activity_id) {
        if (!empty($_POST['juventus_match_event'])) {
            bp_activity_update_meta($activity_id, 'juventus_match_event', intval($_POST['juventus_match_event']));
        }
    }

    /**
     * Visualizza info partita Juventus nell'attività BuddyPress
     */
    public function show_juventus_match_in_activity($activity_content, $activity) {
        $match_id = bp_activity_get_meta($activity->id, 'juventus_match_event', true);
        if ($match_id) {
            $matches = $this->api->get_team_matches(109, array('limit' => 10));
            if (!is_wp_error($matches) && isset($matches['matches'])) {
                foreach ($matches['matches'] as $m) {
                    if ($m['id'] == $match_id) {
                        $logo = isset($m['homeTeam']['id']) && $m['homeTeam']['id'] == 109 && isset($m['homeTeam']['crest']) ? $m['homeTeam']['crest'] : (isset($m['awayTeam']['crest']) ? $m['awayTeam']['crest'] : '');
                        $result = isset($m['score']['fullTime']['home']) && isset($m['score']['fullTime']['away']) ? esc_html($m['score']['fullTime']['home']) . ' - ' . esc_html($m['score']['fullTime']['away']) : '-';
                        $arbitro = isset($m['referees'][0]['name']) ? esc_html($m['referees'][0]['name']) : __('Non disponibile', 'football-data-bp');
                        $stadio = isset($m['venue']) ? esc_html($m['venue']) : __('Non disponibile', 'football-data-bp');
                        $fase = isset($m['stage']) ? esc_html($m['stage']) : __('Non disponibile', 'football-data-bp');
                        $goal_home = isset($m['score']['fullTime']['home']) ? intval($m['score']['fullTime']['home']) : 0;
                        $goal_away = isset($m['score']['fullTime']['away']) ? intval($m['score']['fullTime']['away']) : 0;
                        // Statistiche avanzate
                        $marcatori = array();
                        $gialli = array();
                        $rossi = array();
                        $rigori = array();
                        $sostituzioni = array();
                        $possesso = isset($m['ballPossession']) ? esc_html($m['ballPossession']) : '';
                        if (isset($m['events']) && is_array($m['events'])) {
                            foreach ($m['events'] as $ev) {
                                if ($ev['type'] === 'GOAL') $marcatori[] = esc_html($ev['player']['name']) . ' (' . esc_html($ev['team']['name']) . ')';
                                if ($ev['type'] === 'YELLOW_CARD') $gialli[] = esc_html($ev['player']['name']) . ' (' . esc_html($ev['team']['name']) . ')';
                                if ($ev['type'] === 'RED_CARD') $rossi[] = esc_html($ev['player']['name']) . ' (' . esc_html($ev['team']['name']) . ')';
                                if ($ev['type'] === 'PENALTY') $rigori[] = esc_html($ev['player']['name']) . ' (' . esc_html($ev['team']['name']) . ')';
                                if ($ev['type'] === 'SUBSTITUTION') $sostituzioni[] = esc_html($ev['player']['name']) . ' (' . esc_html($ev['team']['name']) . ')';
                            }
                        }
                        $info = '<div class="juventus-match-event" style="border:1px solid #ccc;padding:10px;margin-top:10px;background:#f9f9f9;">';
                        if ($logo) $info .= '<img src="' . esc_url($logo) . '" alt="Juventus" style="width:40px;height:40px;vertical-align:middle;margin-right:10px;">';
                        $info .= '<strong>' . __('Evento Juventus:', 'football-data-bp') . '</strong> ';
                        $info .= esc_html($m['homeTeam']['name']) . ' vs ' . esc_html($m['awayTeam']['name']);
                        $info .= ' <span style="font-weight:bold;">' . $result . '</span>';
                        $info .= '<br><span>' . __('Competizione:', 'football-data-bp') . ' ' . esc_html($m['competition']['name']) . ' (' . esc_html($m['competition']['code']) . ')</span>';
                        $info .= '<br><span>' . __('Stadio:', 'football-data-bp') . ' ' . $stadio . '</span>';
                        $info .= '<br><span>' . __('Arbitro:', 'football-data-bp') . ' ' . $arbitro . '</span>';
                        $info .= '<br><span>' . __('Fase:', 'football-data-bp') . ' ' . $fase . '</span>';
                        $info .= '<br><span>' . __('Goal Juventus:', 'football-data-bp') . ' ' . $goal_home . '</span>';
                        $info .= '<br><span>' . __('Goal avversario:', 'football-data-bp') . ' ' . $goal_away . '</span>';
                        $info .= '<br><span>' . __('Stato:', 'football-data-bp') . ' ' . esc_html($m['status']) . '</span>';
                        $info .= '<br><span>' . __('Data/Ora:', 'football-data-bp') . ' ' . esc_html(date('d/m/Y H:i', strtotime($m['utcDate']))) . '</span>';
                        if ($possesso) $info .= '<br><span>' . __('Possesso palla:', 'football-data-bp') . ' ' . $possesso . '%</span>';
                        if (!empty($marcatori)) $info .= '<br><span>' . __('Marcatori:', 'football-data-bp') . ' ' . implode(', ', $marcatori) . '</span>';
                        if (!empty($gialli)) $info .= '<br><span>' . __('Cartellini gialli:', 'football-data-bp') . ' ' . implode(', ', $gialli) . '</span>';
                        if (!empty($rossi)) $info .= '<br><span>' . __('Cartellini rossi:', 'football-data-bp') . ' ' . implode(', ', $rossi) . '</span>';
                        if (!empty($rigori)) $info .= '<br><span>' . __('Rigori:', 'football-data-bp') . ' ' . implode(', ', $rigori) . '</span>';
                        if (!empty($sostituzioni)) $info .= '<br><span>' . __('Sostituzioni:', 'football-data-bp') . ' ' . implode(', ', $sostituzioni) . '</span>';
                        $info .= '<br><a href="https://www.football-data.org/team/' . esc_attr($m['homeTeam']['id']) . '" target="_blank">' . __('Dettagli partita', 'football-data-bp') . '</a>';
                        $info .= '</div>';
                        $activity_content .= $info;
                        break;
                    }
                }
            }
        }
        return $activity_content;
    }

    /**
     * Shortcode per partite (default Juventus)
     */
    public function football_matches_shortcode($atts) {
        $atts = shortcode_atts(array(
            'competition' => 'juventus',
            'limit' => 10
        ), $atts);

        if ($atts['competition'] === 'juventus') {
            $matches = $this->api->get_juventus_matches(array('limit' => $atts['limit']));
        } else {
            $matches = $this->api->get_matches($atts['competition'], array('limit' => $atts['limit']));
        }

        if (is_wp_error($matches)) {
            return __('Errore nel recupero dati', 'football-data-bp');
        }

        ob_start();
        if (isset($matches['matches'])) {
            echo '<div class="football-data-matches">';
            foreach ($matches['matches'] as $match) {
                $this->render_juventus_match($match);
            }
            echo '</div>';
        }
        return ob_get_clean();
    }

    /**
     * Aggiungere menu admin
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Football Data', 'football-data-bp'),
            __('Football Data', 'football-data-bp'),
            'manage_options',
            'football-data-settings',
            array($this, 'admin_settings_page'),
            'dashicons-groups'
        );
    }

    /**
     * Pagina impostazioni admin avanzata con tutte le nuove opzioni
     */
    public function admin_settings_page() {
        if (isset($_POST['submit'])) {
            update_option('football_data_api_key', sanitize_text_field($_POST['api_key']));
            update_option('football_data_show_sa', isset($_POST['show_sa']) ? 1 : 0);
            update_option('football_data_show_cl', isset($_POST['show_cl']) ? 1 : 0);
            update_option('football_data_show_el', isset($_POST['show_el']) ? 1 : 0);
            update_option('football_data_show_ci', isset($_POST['show_ci']) ? 1 : 0);
            update_option('football_data_show_sci', isset($_POST['show_sci']) ? 1 : 0);
            update_option('football_data_enable_profile_field', isset($_POST['enable_profile_field']) ? 1 : 0);
            update_option('football_data_widget_sidebar', isset($_POST['widget_sidebar']) ? 1 : 0);
            update_option('football_data_widget_homepage', isset($_POST['widget_homepage']) ? 1 : 0);
            update_option('football_data_color_bg', sanitize_text_field($_POST['color_bg']));
            update_option('football_data_color_fg', sanitize_text_field($_POST['color_fg']));
            update_option('football_data_view_compact', isset($_POST['view_compact']) ? 1 : 0);
            update_option('football_data_enable_csv', isset($_POST['enable_csv']) ? 1 : 0);
            update_option('football_data_enable_email', isset($_POST['enable_email']) ? 1 : 0);
            echo '<div class="notice notice-success"><p>' . __('Impostazioni salvate', 'football-data-bp') . '</p></div>';
        }

        $api_key = get_option('football_data_api_key', '');
        $show_sa = get_option('football_data_show_sa', 1);
        $show_cl = get_option('football_data_show_cl', 1);
        $show_el = get_option('football_data_show_el', 1);
        $show_ci = get_option('football_data_show_ci', 1);
        $show_sci = get_option('football_data_show_sci', 1);
        $enable_profile_field = get_option('football_data_enable_profile_field', 1);
        $widget_sidebar = get_option('football_data_widget_sidebar', 0);
        $widget_homepage = get_option('football_data_widget_homepage', 0);
        $color_bg = get_option('football_data_color_bg', '#000000');
        $color_fg = get_option('football_data_color_fg', '#ffffff');
        $view_compact = get_option('football_data_view_compact', 0);
        $enable_csv = get_option('football_data_enable_csv', 0);
        $enable_email = get_option('football_data_enable_email', 0);
        ?>
        <div class="wrap">
            <h1><?php _e('Impostazioni Football Data', 'football-data-bp'); ?></h1>
            <?php
            // Diagnostica API
            $api = new Football_Data_API();
            $juve = $api->get_juventus_data();
            if (is_wp_error($juve)) {
                echo '<div class="notice notice-error"><p>' . __('Errore API: ', 'football-data-bp') . esc_html($juve->get_error_message()) . '</p></div>';
            } elseif (isset($juve['id']) && $juve['id'] == 109) {
                echo '<div class="notice notice-success"><p>' . __('Connessione API OK. Dati Juventus ricevuti.', 'football-data-bp') . '</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>' . __('Connessione API riuscita, ma dati Juventus non ricevuti.', 'football-data-bp') . '</p></div>';
            }
            // Diagnostica avanzata
            echo '<h2>' . __('Diagnostica plugin', 'football-data-bp') . '</h2>';
            // BuddyPress
            if (function_exists('buddypress')) {
                echo '<div class="notice notice-success"><p>' . __('BuddyPress attivo e rilevato.', 'football-data-bp') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __('BuddyPress non attivo o non rilevato.', 'football-data-bp') . '</p></div>';
            }
            // Widget
            if (get_option('football_data_widget_sidebar', 0)) {
                echo '<div class="notice notice-success"><p>' . __('Widget Juventus sidebar attivo.', 'football-data-bp') . '</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>' . __('Widget Juventus sidebar non attivo.', 'football-data-bp') . '</p></div>';
            }
            if (get_option('football_data_widget_homepage', 0)) {
                echo '<div class="notice notice-success"><p>' . __('Widget Juventus homepage attivo.', 'football-data-bp') . '</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>' . __('Widget Juventus homepage non attivo.', 'football-data-bp') . '</p></div>';
            }
            // Shortcode
            if (shortcode_exists('football_matches') && shortcode_exists('football_standings')) {
                echo '<div class="notice notice-success"><p>' . __('Shortcode partite/classifica Juventus attivi.', 'football-data-bp') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __('Shortcode non attivi.', 'football-data-bp') . '</p></div>';
            }
            // Attività BuddyPress
            if (has_action('bp_activity_post_form_options', array($this, 'add_juventus_match_selector'))) {
                echo '<div class="notice notice-success"><p>' . __('Collegamento evento partita Juventus nelle attività BuddyPress attivo.', 'football-data-bp') . '</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>' . __('Collegamento evento partita Juventus nelle attività BuddyPress non attivo.', 'football-data-bp') . '</p></div>';
            }
            // Esportazione CSV
            if (get_option('football_data_enable_csv', 0)) {
                echo '<div class="notice notice-success"><p>' . __('Esportazione CSV attiva.', 'football-data-bp') . '</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>' . __('Esportazione CSV non attiva.', 'football-data-bp') . '</p></div>';
            }
            // Notifiche email
            if (get_option('football_data_enable_email', 0)) {
                echo '<div class="notice notice-success"><p>' . __('Notifiche email Juventus attive.', 'football-data-bp') . '</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>' . __('Notifiche email Juventus non attive.', 'football-data-bp') . '</p></div>';
            }
            ?>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Chiave API', 'football-data-bp'); ?></th>
                        <td>
                            <input type="text" name="api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
                            <p class="description"><?php _e('Inserisci la tua chiave API da football-data.org', 'football-data-bp'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Competizioni da mostrare', 'football-data-bp'); ?></th>
                        <td>
                            <label><input type="checkbox" name="show_sa" value="1" <?php checked($show_sa, 1); ?>> Serie A</label><br>
                            <label><input type="checkbox" name="show_cl" value="1" <?php checked($show_cl, 1); ?>> Champions League</label><br>
                            <label><input type="checkbox" name="show_el" value="1" <?php checked($show_el, 1); ?>> Europa League</label><br>
                            <label><input type="checkbox" name="show_ci" value="1" <?php checked($show_ci, 1); ?>> Coppa Italia</label><br>
                            <label><input type="checkbox" name="show_sci" value="1" <?php checked($show_sci, 1); ?>> Supercoppa Italiana</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Campo profilo BuddyPress', 'football-data-bp'); ?></th>
                        <td>
                            <label><input type="checkbox" name="enable_profile_field" value="1" <?php checked($enable_profile_field, 1); ?>> Attiva campo "Squadra Preferita" nel profilo utente</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Widget sidebar', 'football-data-bp'); ?></th>
                        <td>
                            <label><input type="checkbox" name="widget_sidebar" value="1" <?php checked($widget_sidebar, 1); ?>> Mostra widget partite/classifica Juventus nella sidebar</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Widget homepage', 'football-data-bp'); ?></th>
                        <td>
                            <label><input type="checkbox" name="widget_homepage" value="1" <?php checked($widget_homepage, 1); ?>> Mostra widget partite/classifica Juventus in homepage</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Colori visualizzazione', 'football-data-bp'); ?></th>
                        <td>
                            <label><?php _e('Sfondo', 'football-data-bp'); ?> <input type="color" name="color_bg" value="<?php echo esc_attr($color_bg); ?>"></label>
                            <label><?php _e('Testo', 'football-data-bp'); ?> <input type="color" name="color_fg" value="<?php echo esc_attr($color_fg); ?>"></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Visualizzazione compatta', 'football-data-bp'); ?></th>
                        <td>
                            <label><input type="checkbox" name="view_compact" value="1" <?php checked($view_compact, 1); ?>> Mostra partite/classifica in modalità compatta</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Esportazione CSV', 'football-data-bp'); ?></th>
                        <td>
                            <label><input type="checkbox" name="enable_csv" value="1" <?php checked($enable_csv, 1); ?>> Permetti esportazione partite/classifica in CSV</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Notifiche email', 'football-data-bp'); ?></th>
                        <td>
                            <label><input type="checkbox" name="enable_email" value="1" <?php checked($enable_email, 1); ?>> Invia email agli utenti quando la Juventus gioca</label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <form method="post">
                <input type="hidden" name="update_plugin_github" value="1">
                <button type="submit" class="button button-primary" style="margin-top:20px;">
                    <?php _e('Aggiorna plugin da GitHub', 'football-data-bp'); ?>
                </button>
            </form>
        </div>
        <?php
        // Gestione aggiornamento da GitHub
        if (isset($_POST['update_plugin_github'])) {
            $github_url = 'https://github.com/agservizi/template_bianconerihub/archive/refs/heads/main.zip';
            $tmp_file = download_url($github_url);
            if (is_wp_error($tmp_file)) {
                echo '<div class="notice notice-error"><p>' . __('Errore download da GitHub: ', 'football-data-bp') . esc_html($tmp_file->get_error_message()) . '</p></div>';
            } else {
                $plugin_dir = WP_PLUGIN_DIR . '/football-data-buddypress-integration';
                // Estrai e sovrascrivi i file del plugin
                $result = unzip_file($tmp_file, $plugin_dir);
                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>' . __('Errore estrazione ZIP: ', 'football-data-bp') . esc_html($result->get_error_message()) . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>' . __('Plugin aggiornato con successo!', 'football-data-bp') . '</p></div>';
                }
                @unlink($tmp_file);
            }
        }
    }

    /**
     * Shortcode per classifica Juventus
     */
    public function football_standings_shortcode($atts) {
        $atts = shortcode_atts(array(
            'competition' => 'SA' // Default Serie A
        ), $atts);

        $standings = $this->api->get_juventus_standings($atts['competition']);

        if (is_wp_error($standings)) {
            return __('Errore nel recupero classifica', 'football-data-bp');
        }

        ob_start();
        if (isset($standings['standings'][0]['table'])) {
            echo '<table class="football-data-standings">';
            echo '<thead><tr><th>Pos</th><th>Squadra</th><th>Punti</th><th>Giocate</th><th>Vinte</th><th>Pareggiate</th><th>Perse</th></tr></thead>';
            echo '<tbody>';

            foreach ($standings['standings'][0]['table'] as $team) {
                $highlight_class = ($team['team']['id'] == 109) ? 'juventus-highlight' : '';
                echo '<tr class="' . esc_attr($highlight_class) . '">';
                echo '<td>' . esc_html($team['position']) . '</td>';
                echo '<td>' . esc_html($team['team']['name']) . '</td>';
                echo '<td>' . esc_html($team['points']) . '</td>';
                echo '<td>' . esc_html($team['playedGames']) . '</td>';
                echo '<td>' . esc_html($team['won']) . '</td>';
                echo '<td>' . esc_html($team['draw']) . '</td>';
                echo '<td>' . esc_html($team['lost']) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }
        return ob_get_clean();
    }

    /**
     * Filtra le competizioni Juventus in base alle opzioni admin
     */
    private function filter_juventus_competitions($competitions) {
        $show_sa = get_option('football_data_show_sa', 1);
        $show_cl = get_option('football_data_show_cl', 1);
        $show_el = get_option('football_data_show_el', 1);
        $show_ci = get_option('football_data_show_ci', 1);
        $show_sci = get_option('football_data_show_sci', 1);
        $allowed = array();
        foreach ($competitions as $competition) {
            if ($competition['code'] === 'SA' && $show_sa) $allowed[] = $competition;
            if ($competition['code'] === 'CL' && $show_cl) $allowed[] = $competition;
            if ($competition['code'] === 'EL' && $show_el) $allowed[] = $competition;
            if ($competition['code'] === 'CI' && $show_ci) $allowed[] = $competition;
            if ($competition['code'] === 'SCI' && $show_sci) $allowed[] = $competition;
        }
        return $allowed;
    }

    /**
     * Registrazione widget sidebar/homepage
     */
    public function register_widgets() {
        if (get_option('football_data_widget_sidebar', 0)) {
            register_widget('Football_Data_Juventus_Widget');
        }
        if (get_option('football_data_widget_homepage', 0)) {
            add_action('wp_footer', array($this, 'render_homepage_widget'));
        }
    }

    /**
     * Widget homepage
     */
    public function render_homepage_widget() {
        echo '<div class="football-data-homepage-widget">';
        echo do_shortcode('[football_matches limit="5"]');
        echo do_shortcode('[football_standings]');
        echo '</div>';
    }

    /**
     * Esportazione CSV partite/classifica
     */
    public function export_csv() {
        if (!get_option('football_data_enable_csv', 0)) return;
        if (!isset($_GET['football_data_export'])) return;
        $type = $_GET['football_data_export'];
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="juventus_' . $type . '.csv"');
        $output = fopen('php://output', 'w');
        if ($type === 'matches') {
            $matches = $this->api->get_juventus_matches(array('limit' => 50));
            fputcsv($output, array('Data', 'Competizione', 'Casa', 'Trasferta', 'Risultato', 'Stato'));
            foreach ($matches['matches'] as $match) {
                fputcsv($output, array(
                    date('d/m/Y H:i', strtotime($match['utcDate'])),
                    $match['competition']['name'],
                    $match['homeTeam']['name'],
                    $match['awayTeam']['name'],
                    $match['score']['fullTime']['home'] . '-' . $match['score']['fullTime']['away'],
                    $match['status']
                ));
            }
        } elseif ($type === 'standings') {
            $standings = $this->api->get_juventus_standings('SA');
            fputcsv($output, array('Pos', 'Squadra', 'Punti', 'Giocate', 'Vinte', 'Pareggiate', 'Perse'));
            foreach ($standings['standings'][0]['table'] as $team) {
                fputcsv($output, array(
                    $team['position'],
                    $team['team']['name'],
                    $team['points'],
                    $team['playedGames'],
                    $team['won'],
                    $team['draw'],
                    $team['lost']
                ));
            }
        }
        fclose($output);
        exit;
    }

    /**
     * Personalizzazione colori visualizzazione
     */
    public function custom_colors_css() {
        $bg = get_option('football_data_color_bg', '#000000');
        $fg = get_option('football_data_color_fg', '#ffffff');
        echo '<style>.football-data-juventus-section, .football-data-homepage-widget { background:' . esc_attr($bg) . '; color:' . esc_attr($fg) . '; }</style>';
    }

    /**
     * Notifiche email agli utenti
     */
    public function send_match_email_notification() {
        if (!get_option('football_data_enable_email', 0)) return;
        $matches = $this->api->get_juventus_matches(array('limit' => 1));
        if (isset($matches['matches'][0]) && $matches['matches'][0]['status'] === 'SCHEDULED') {
            $match = $matches['matches'][0];
            $subject = 'Juventus: nuova partita in programma!';
            $message = 'Juventus giocherà contro ' . $match['awayTeam']['name'] . ' il ' . date('d/m/Y H:i', strtotime($match['utcDate'])) . ' in ' . $match['competition']['name'] . '.';
            $users = get_users(array('role' => 'subscriber'));
            foreach ($users as $user) {
                wp_mail($user->user_email, $subject, $message);
            }
        }
    }
}
?>
