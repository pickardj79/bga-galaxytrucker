<?php

require_once('GT_DBPlayer.php');

class GT_FlightBoard extends APP_GameClass {

    public function __construct($game, $players) {

        // $players should still be flying
        $erredPlayers = array_filter($players, 
            function($i) { 
                return array_key_exists('still_flying', $i) 
                    && $i['still_flying'] != '1'; 
            } 
        );

        if ($erredPlayers) 
            $game->throw_bug_report_dump("Players on flight board are not flying", $erredPlayers);

        $this->game = $game;
        $this->players = $players;
    }

    function moveShip( int $plId, int $nbDays ) {

        $game = $this->game;
        $plName = $this->players[$plId]['player_name'];
        if ( $nbDays === 0 ) {
            $game->notifyAllPlayers( "onlyLogMessage",
                                    clienttranslate( '${player_name} doesn\'t move'),
                                    array ( 'player_name' => $plName ) );
            return null;
        }
        else {
            $trslStr = ($nbDays>0) ? clienttranslate('${player_name} gains ${numDays} flight days')
                    : clienttranslate('${player_name} loses ${numDays} flight days');
            $newPlPos = $this->computeNewPlayerPos( $plId, $nbDays );
            $game->DbQuery("UPDATE player SET player_position=$newPlPos WHERE player_id=$plId");
            $game->notifyAllPlayers( "moveShip", $trslStr,
                                array(
                                'player_id' => $plId,
                                'player_name' => $plName,
                                'numDays' => abs($nbDays),
                                'newPlPos' => $newPlPos,
                                ) );
            //TODO check if lapping or getting lapped here?

            $this->players[$plId]['player_position'] = $newPlPos;
        }
    }

    function computeNewPlayerPos( int $playerId, int $nbDays ){
        $newPlPos = $this->players[$playerId]['player_position'];
        $otherPlPos = array();
        foreach ( $this->players as $player ) {
            if ( $player['player_id'] != $playerId ) {
                $otherPlPos[] = $player['player_position'];
            }
        }

        // Note: this does not check if players are on the same space but one lap behind / ahead
        for ( $i = 1; $i <= abs($nbDays); $i++ ) {
            do {
                if ( $nbDays > 0 )
                    $newPlPos++;
                else
                    $newPlPos--;
            } while ( in_array( $newPlPos, $otherPlPos ) ); 
        }

        return $newPlPos;
    }

    function giveUp(int $plId, $reason) {
        if (! $this->players[$plId])
            $this->game->throw_bug_report("plId $plId cannot give up, not in obj", $this->players);

        $this->players[$plId]['still_flying'] = 0;
        $this->players[$plId]['player_position'] = null;

        GT_DBPlayer::setPlayerGiveUp($this->game, $plId);
        $this->game->notifyAllPlayers( "giveUp",
            clienttranslate( '${player_name} will have to give up: ${reason}'),
            array( 'player_name' => $this->players[$plId]['player_name'],
                   'player_id' => $plId,
                   'reason' => $reason
            )
        );
    }


}

?>