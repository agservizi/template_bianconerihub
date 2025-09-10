<?php
/**
 * Widget Juventus Football Data
 */
class Football_Data_Juventus_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'football_data_juventus_widget',
            __('Juventus Football Data', 'football-data-bp'),
            array('description' => __('Visualizza le prossime partite e la classifica della Juventus', 'football-data-bp'))
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];
        echo $args['before_title'] . __('Juventus Football Data', 'football-data-bp') . $args['after_title'];
        echo do_shortcode('[football_matches limit="5"]');
        echo do_shortcode('[football_standings]');
        echo $args['after_widget'];
    }

    public function form($instance) {
        // Nessuna opzione per ora
        echo '<p>' . __('Questo widget mostra le partite e la classifica della Juventus.', 'football-data-bp') . '</p>';
    }

    public function update($new_instance, $old_instance) {
        return $old_instance;
    }
}

/**
 * Se il widget non appare, puoi visualizzare i dati Juventus anche tramite shortcode.
 * Usa questi shortcode in una pagina, articolo o area widget testuale:
 *
 * [football_matches]
 * [football_standings]
 *
 * Questi mostreranno le partite e la classifica della Juventus filtrate dal plugin.
 */
?>
