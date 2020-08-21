<?php
/* A collection of quick-to-use game states.
 *
 */

require_once('GT_DBComponent.php');

class GT_GameState {
    public function __construct($game, $players) {
        $this->game = $game;
        $this->players = $players;
    }

    function setState() {
        // runs $game functions to set appropriate gamestate
        // setGameStateValues
        // set state machine to correct state

//         $this->game->gamestate->nextState();

        // set state to repairPhase
//         $this->game->stPrepareRound(); # ends in either waitForPlayers or buildPhase
//         if ( ($this->game->gamestate->state())['name'] == 'waitForPlayers' )
//             $this->game->gamestate->nextState('readyGo');

        // Should be in buildPhase now with timer counting down

        $tiles = $this->getComponents();
        $sql = GT_DBComponent::updateTilesSql($tiles);
        $this->game::DbQuery($sql);

        $this->game->gamestate->setAllPlayersMultiactive();
        $this->game->gamestate->nextState('buildPhase');

        $order = 1;
        foreach( array_keys($this->players) as $player_id ) {
//             $this->game->finishShip($order, $player_id);
            $order++;
//             $this->game->gamestate->setPlayerNonMultiactive( $player_id, "shipsDone" );
        }
    }

    function newTile($id, $x, $y, $o) {
        if (! in_array($o, array(0,90,180,270) ) )
            throw new InvalidArgumentException("Orientation '$o' not valid");
        return array(
            'component_id' => $id, 'component_x' => $x, 'component_y' => $y, 'component_orientation' => $o
        );
    }

    // A basic ship with every type of tile
    // Returns array(Tiles['id','x','y','o']) to be inserted into db
    function basicShip($color, $variant) {
        $tiles = array();

        // start tile
        $start_id = array_search($color, $this->game->start_tiles);
        if (!$start_id)
            throw new InvalidArgumentException("color '$color' not valid");

        array_push($tiles, self::newTile($start_id, 7, 7, 0));

        if ($variant == 1) {
            array_push($tiles, self::newTile(74, 7, 8, 0)); // single engine directly south
            array_push($tiles, self::newTile(22, 7, 6, 0)); // cargo north
            array_push($tiles, self::newTile(123, 7, 5, 0)); // double-laser above that
            array_push($tiles, self::newTile(12, 8, 6, 270)); // battery to east of cargo
        }
        else if ($variant == 2) {
            array_push($tiles, self::newTile(68, 7, 8, 0)); // single engine directly south
            array_push($tiles, self::newTile(58, 7, 6, 0)); // hazard north
            array_push($tiles, self::newTile(88, 7, 5, 0)); // laser above that
            array_push($tiles, self::newTile(3, 8, 6, 0)); // battery to east of cargo
        }
        return $tiles;
    }

    function getComponents() {
        // Return array of Tiles ready for inserting into component db

        // build a basic ship for each player
        $all_tiles = array();
        $i = 1;
        foreach( $this->players as $player_id => $player ) {
            $tiles = $this->basicShip($player['player_color'], $i);
            $i++;
            foreach( $tiles as &$tile ) {
                $tile['component_player'] = $player_id;
            }
            $all_tiles = array_merge($all_tiles, $tiles);
        }
        return $all_tiles;
    }

    function getContent() {
        // Return array of content-tiles ready for inserting into content db
    }

    function getCards() {
        // Return array of cards ready for inserting into the card db
    }


}

