<?php
/* Collection of utilities to interface with card table */
class GT_DBPlayer extends APP_GameClass {
    public function __construct() {
    }

    function getPlayer($game, $plId) {
        return $game->getNonEmptyObjectFromDB ( "
            SELECT player_id, player_name, player_color, player_position, nb_crew, 
                card_line_done, card_action_choice, min_eng, max_eng
            FROM player WHERE player_id = $plId");
    }

    function addCredits($game, $plId, $credits) {
        $game->DbQuery("UPDATE player SET credits = GREATEST(0, credits + ($credits)) WHERE player_id = $plId");
    }

    ########### Card-based queries ##########

    function getPlayersForCard($game) {
        return self::getPlayersInFlight($game, ' AND card_line_done != 2');
    }

    function getPlayersInFlight($game, $extra_where="", $order="DESC") {
        if ($order != "DESC" && $order != "ASC")
            $game->throw_bug_report("Order must be DESC or ASC");

        return $game->getCollectionFromDB ( "
            SELECT player_id, player_name, player_color, player_position, nb_crew, 
                card_line_done, card_action_choice,
                min_eng, max_eng, min_cann_x2, max_cann_x2, exp_conn
            FROM player 
            WHERE still_flying=1 $extra_where
            ORDER BY player_position $order" );
    }

    function setPlayerGiveUp($game, $plId) {
        $game->DbQuery( "UPDATE player SET still_flying = 0, player_position = null WHERE player_id = $plId" );
    }

    function clearCardProgress($game) {
        $game->DbQuery( "UPDATE player SET card_line_done=0, card_action_choice=0" );
    }

    function setCardChoice($game, $plId, $choice) {
        $game->DbQuery( "UPDATE player SET card_action_choice=$choice WHERE player_id=$plId" );
    }

    function getCardChoices($game) {
        return $game->getCollectionFromDB("SELECT card_action_choice, player_id player_id FROM player");
    }

    function getCardProgress($game) {
        return $game->getCollectionFromDB("
            SELECT player_id player_id, card_line_done, card_action_choice, still_flying 
            FROM player");
    }

    function setCardDone($game, $plId) {
        $game->DbQuery( "UPDATE player SET card_line_done=2 WHERE player_id=$plId" );
    }

    function setCardAllDone($game) {
        $game->DbQuery( "UPDATE player SET card_line_done=2" );
    }

    function setCardInProgress($game, $plId) {
        $game->DbQuery( "UPDATE player SET card_line_done=1 WHERE player_id=$plId" );
    }
}

?>
