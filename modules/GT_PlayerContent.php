<?php

require_once('GT_DBPlayer.php');
require_once('GT_DBComponent.php');
require_once('GT_DBContent.php');

class GT_PlayerContent extends APP_GameClass {


    public function __construct($game, $plContent, $player_id) {
        $this->game = $game;

        $this->plContent = $plContent;
        $this->player_id = $player_id;
        $this->ALLOWABLE_SUBTYPES = array(
        "crew" => array("human", "brown", "purple", "ask_human", "ask_brown", "ask_purple"),
        "cell" => array("cell"),
        "goods" => array("red", "yellow", "green", "blue")
    );
    }

    ################### CHECK HELPERS #########################

    // checkAll - validates all content against tiles from db (GT_PlayerBoard)
    function checkAll($brd) {

        $tilePlaces = array();
        foreach ($this->plContent as $cont) {
            $x = $cont['square_x'];
            $y = $cont['square_y'];
            if (!array_key_exists($x, $brd->plTiles) || !array_key_exists($y, $brd->plTiles[$x]))
                $this->game->throw_bug_report_dump("No tile at content location ($x,$y)", $cont);

            $tile = $brd->plTiles[$x][$y];
            $this->checkContentTile($cont, $tile['id']);

            // check tile_id/place is unique
            $tile_place = $cont['tile_id'] . "_" . $cont['place'];
            if (array_key_exists($tile_place, $tilePlaces))
                $this->game->throw_bug_report("Place used multiple times on tile (tile_place $tile_place)");
            else
                $tilePlaces[$tile_place] = 1;
        }
    }

    // Check that content id $id is valid relative to expected type $type and this player's content
    function checkContentById($id, $type=null) {
        if ( ! array_key_exists($id, $this->plContent) )
            $this->game->throw_bug_report("Wrong id $id: no content with this id.");
        
        
        if ($type)
            $this->checkContent($this->plContent[$id], $type);
    }

    function checkContent($content, $type) {
        if ( $content['content_type'] != $type )
            $this->game->throw_bug_report_dump("Wrong content: not a $type.", $content);
        
        if (!in_array($content['content_subtype'], $this->ALLOWABLE_SUBTYPES[$type]))
            $this->game->throw_bug_report_dump("Wrong content subtype: {$content['content_subtype']} not allowed with type $type.", $content);

        if ( $content['player_id'] != $this->player_id)
            $this->game->throw_bug_report("Wrong content: not in your ship.", $content);
    }

    // Check that content id $id is valid for tile $tileId
    function checkContentTileById($id, $tileId, $checkHold=TRUE) {
        $this->checkContentById($id);
        $this->checkContentTile($this->plContent[$id], $tileId, $checkHold);
    }

    function checkContentTile($content, $tileId, $checkHold=TRUE) {
        $tileType = $this->game->getTileType($tileId);
        $tileHold = $this->game->getTileHold($tileId);
        $tileHoldType = $this->game->tileHoldTypes[$tileType];
        $this->checkContent($content, $tileHoldType);

        if (   $content['content_type'] == 'goods' 
            && $content['content_subtype'] == 'red'
            && $tileType != 'hazard')
            $this->game->throw_bug_report_dump("Red goods must go in hazards tiles (id $tileId)", $content);
        
        if ($checkHold && $content['place'] > $tileHold)
            $this->game->throw_bug_report_dump("Too many {$content['content_type']} on tile (id $tileId)", $content);
        
        if ($content['capacity'] != $tileHold)
            $this->game->throw_bug_report_dump("Content capacity does not match tile hold (id $tileId)", $content);
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

    function getContent($type) {
        return array_filter($this->plContent, 
            function($c) use ($type) { return $c['content_type'] == $type; }
        );
    }

    function nbOfCrewMembers() {
        return count($this->getContent("crew"));
    }

    function nextPlace($tileId) {
        // does not obey tile hold limits
        $place = 1;
        foreach ($this->plContent as $cont)
            if ($cont['tile_id'] == $tileId && $cont['place'] == $place)
                $place++;
        return $place;
    }

    ################### CONTENT MANIPULATION #########################

    function clearAllPlaces($type) {
        // clear all places in preparation for a full reload
        foreach ($this->plContent as &$cont) {
            if ($cont['content_type'] != $type)
                continue;
            $cont['place'] = null;
        }
    }

    function moveContent($tileId, $type, $goodsIds) {
        // Moves content ids $goodsIds to tile $tileId (both are numbers)
        $tile = GT_DBComponent::getActiveComponent($this->game, $tileId);
        
        if ($tile['component_player'] != $this->player_id)
            $this->game->throw_bug_report("Wrong player for tile", $tile);

        $rows = array();
        foreach ( $goodsIds as $id) {
            $this->checkContentTileById($id, $tileId, $checkHold=FALSE);
            $capacity = $this->game->getTileHold($tileId);
            $place = $this->nextPlace($tileId);
            $rows[] = array(
                "content_id" => $id,
                "tile_id" => $tileId,
                "square_x" => $tile['component_x'], 
                "square_y" => $tile['component_y'],
                "place" => $place,
                "capacity" => $capacity
            );
            $this->plContent[$id]['tile_id'] = $tileId;
            $this->plContent[$id]['square_x'] = $tile['component_x'];
            $this->plContent[$id]['square_y'] = $tile['component_y'];
            $this->plContent[$id]['place'] = $place;
            $this->plContent[$id]['capacity'] = $capacity;
        }
        if ($rows) {
            $sql = GT_DBContent::insertContentSql($rows);
            $this->game->log("moving content with $sql");
            $this->game->DbQuery($sql);
        }
        return $rows;
    }

    function newContent($tileId, $type, $cnt, $subtypes) {
        // Creates newContent on $tileId (a number)
        // Specify either $cnt (e.g. for cells) or array of $subtypes (e.g. for cargo)
        // Does NOT update local content (should repull from DB)
        if ($cnt)
            $this->game->throw_bug_report("newContent by cnt not implemented");
        
        $tile = GT_DBComponent::getActiveComponent($this->game, $tileId);

        $newContent = array();
        foreach ($subtypes as $subtype) {
            $place = $this->nextPlace($tileId);
            $content = array(
                'player_id' => $this->player_id,
                'content_type' => $type,
                'content_subtype' => $subtype,
                'tile_id' => $tileId,
                'square_x' => $tile['component_x'],
                'square_y' => $tile['component_y'],
                'place' => $place,
                'capacity' => $this->game->getTileHold($tileId) 
            );
            $this->checkContentTile($content, $tileId, $checkHold=FALSE);
            $sql = GT_DBContent::insertContentSql(array($content), $update=FALSE);
            $this->game->log("adding content with $sql");
            $this->game->DbQuery($sql);
            $id = $this->game->DbGetLastId();
            $content['content_id'] = $id;
            $newContent[] = $content;
            $this->plContent[$id] = $content;
        }

        return $newContent;
    }

    function loseContent($ids, $expType, $toCard) {
        $contentLost = array();
        $contentHtml = "";
        $tileOrient = $this->game->getCollectionFromDB( "SELECT component_id, component_orientation ".
                    "FROM component WHERE component_player={$this->player_id}", true );
        $player = GT_DBPlayer::getPlayer($this->game, $this->player_id);
        foreach ( $ids as $id) {
            $this->checkContentById($id, $expType);
            $curCont = $this->plContent[$id];
            $tileId = $curCont['tile_id'];
            $contentLost[] = array ( 'orient' => $tileOrient[$tileId],
                            'id' => $id,
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