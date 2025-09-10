/**
 * Football Data BuddyPress Integration JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Inizializzazione
        initFootballData();

        // Aggiorna dati calcio periodicamente
        setInterval(updateLiveMatches, 60000); // Ogni minuto
    });

    function initFootballData() {
        // Carica dati iniziali
        loadMatches();
        loadStandings();
    }

    function loadMatches() {
        $('.football-data-matches').each(function() {
            var $container = $(this);
            var competition = $container.data('competition');
            var limit = $container.data('limit') || 10;

            $.ajax({
                url: football_data_bp.ajax_url,
                type: 'POST',
                data: {
                    action: 'football_data_get_matches',
                    competition: competition,
                    limit: limit,
                    nonce: football_data_bp.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $container.html(response.data);
                    }
                }
            });
        });
    }

    function loadStandings() {
        $('.football-data-standings').each(function() {
            var $container = $(this);
            var competition = $container.data('competition');

            $.ajax({
                url: football_data_bp.ajax_url,
                type: 'POST',
                data: {
                    action: 'football_data_get_standings',
                    competition: competition,
                    nonce: football_data_bp.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $container.html(response.data);
                    }
                }
            });
        });
    }

    function updateLiveMatches() {
        // Aggiorna solo partite live
        $('.football-data-match.live').each(function() {
            var $match = $(this);
            var matchId = $match.data('match-id');

            $.ajax({
                url: football_data_bp.ajax_url,
                type: 'POST',
                data: {
                    action: 'football_data_update_match',
                    match_id: matchId,
                    nonce: football_data_bp.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $match.replaceWith(response.data);
                    }
                }
            });
        });
    }

    // Gestione click su squadre
    $(document).on('click', '.football-data-team-link', function(e) {
        e.preventDefault();
        var teamId = $(this).data('team-id');

        // Carica dettagli squadra
        $.ajax({
            url: football_data_bp.ajax_url,
            type: 'POST',
            data: {
                action: 'football_data_get_team',
                team_id: teamId,
                nonce: football_data_bp.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Mostra modal o aggiorna contenuto
                    showTeamModal(response.data);
                }
            }
        });
    });

    function showTeamModal(data) {
        // Implementazione modal per dettagli squadra
        var modal = $('<div class="football-data-modal">' + data + '</div>');
        $('body').append(modal);

        // Chiudi modal
        modal.on('click', '.close', function() {
            modal.remove();
        });
    }

})(jQuery);
