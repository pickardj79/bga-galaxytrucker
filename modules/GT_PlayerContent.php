<?php

class GT_PlayerContent extends APP_GameClass {

    public function __construct($game, $plContent, $player_id) {
        $this->game = $game;

        // tiles on the player board
        $this->plContent = $plContent;
        $this->player_id = $player_id;
    }

    function checkIfCellLeft () {
        $nbOfCells = 0;
        foreach ( $this->plContent as $content )
            if ( $content['content_type'] == 'cell' )
                $nbOfCells ++;
        return $nbOfCells;
    }
  
    function checkIfAlien ($alColor) {
        foreach ( $this->plContent as $content )
            if ( $content['content_subtype'] === $alColor )
                return true; // No need to continue, there can't be more than 1 alien of each color
        return false;
    }

    function nbOfCrewMembers() {
        $nbCrewMembers = 0;
        foreach ( $this->plContent as $content ) {
            if ( $content['content_type'] == 'crew' )
                $nbCrewMembers++;
        }
        return $nbCrewMembers;
    }
}