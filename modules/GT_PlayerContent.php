<?php

require_once('GT_DBPlayer.php');

class GT_PlayerContent extends APP_GameClass {

    public function __construct($game, $plContent, $player_id) {
        $this->game = $game;

        $this->plContent = $plContent;
        $this->player_id = $player_id;
    }

    function checkContent($id, $type) {
        if ( ! array_key_exists($id, $this->plContent) )
            $this->game->throw_bug_report("Wrong id $id: no content with this id.");

        if ( $this->plContent[$id]['content_type'] != $type )
            $this->game->throw_bug_report("Wrong id $id: not a $type.");

        if ( $this->plContent[$id]['player_id'] != $this->player_id)
            $this->game->throw_bug_report("Wrong id $id: not in your ship.");
    }

    function checkIfCellLeft () {
        $nbOfCells = 0;
        foreach ( $this->plContent as $content )
            if ( $content['content_type'] == 'cell' )
                $nbOfCells ++;
        return $nbOfCells;
    }
  
    function checkIfAlien ($alColor) {
        if ($alColor != 'brown' and $alColor !='purple')
            $this->game->throw_bug_report("Invalid alien type $alColor");

        foreach ( $this->plContent as $content )
            if ( $content['content_subtype'] === $alColor )
                return true; // No need to continue, there can't be more than 1 alien of each color
        return false;
    }

    function getCrew() {
        $crewMembers = array();
        foreach ( $this->plContent as $ctId => $content ) {
            if ( $content['content_type'] == 'crew' )
                $crewMembers[] = $ctId;
        }
        return $crewMembers;
    }

    function nbOfCrewMembers() {
        return count($this->getCrew());
    }

    function loseContent($ids, $expType, $toCard) {
        $contentLost = array();
        $contentHtml = "";
        $tileOrient = $this->game->getCollectionFromDB( "SELECT component_id, component_orientation ".
                    "FROM component WHERE component_player={$this->player_id}", true );
        $player = GT_DBPlayer::getPlayer($this->game, $this->player_id);
        foreach ( $ids as $id) {
            $this->checkContent($id, $expType);
            $curCont = $this->plContent[$id];
            $tileId = $curCont['tile_id'];
            $contentLost[] = array ( 'orient' => $tileOrient[$tileId],
                            'divId' => 'content_'.$id,
                            'toCard' => $toCard);
            $type = $curCont['content_subtype'] 
                ? $curCont['content_subtype'] : $curCont['content_type'];
            $contentHtml .= "<img class='content $type'></img> ";
        }
        $sql = "DELETE FROM content WHERE content_id IN (".implode(',', $ids).")";
        $this->game->DbQuery( $sql );
        $this->game->notifyAllPlayers( "loseContent",
                                clienttranslate( '${player_name} loses ${content_icons}'),
                                array( 'player_name' => $player['player_name'],
                                        'content' => $contentLost,
                                        'content_icons' => $contentHtml,
                                    )
                            );
        $this->game->updNotifPlInfos( $this->player_id );
    }
}