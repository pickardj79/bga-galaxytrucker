<?php
/* Collection of utilities to interface with card table */
class GT_DBPlayer extends APP_GameClass {
    public function __construct() {
    }

    function getPlayer($game, $plId) {
        return $game->getNonEmptyObjectFromDB ( "
            SELECT player_id, player_name, player_position, nb_crew, card_line_done, min_eng, max_eng
            FROM player ");
    }

    function getPlayersForCard($game) {
        return getPlayersInFlight($game, ' AND card_line_done != 2');
    }

    function getPlayersInFlight($game, $extra_where="", $order="DESC") {
        if ($order != "DESC" && $order != "ASC")
            $game->throw_bug_report("Order must be DESC or ASC");

        return $game->getCollectionFromDB ( "
            SELECT player_id, player_name, player_position, nb_crew, card_line_done, min_eng, max_eng
            FROM player 
            WHERE still_flying=1 $extra_where
            ORDER BY player_position $order" );
    }

    function clearCardProgress($game) {
        $game->DbQuery( "UPDATE player SET card_line_done=0" );
    }

    function setCardDone($game, $plId) {
        $game->DbQuery( "UPDATE player SET card_line_done=2 WHERE player_id=$plId" );
    }

    function setCardInProgress($game, $plId) {
        $game->DbQuery( "UPDATE player SET card_line_done=1 WHERE player_id=$plId" );
    }
}

?>