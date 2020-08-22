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
        // runs at the end of stPrepareRound if testGameState GameStateValue is tru-ish (set at end of setupNewGame)
        // setupNewGame moves state to prepareRound
        //    which ends with allPlayersMultiactive going to buildPhase

        $tiles = $this->getComponents();
        $sql = GT_DBComponent::updateTilesSql($tiles);
        $this->game::DbQuery($sql);

        $this->game->gamestate->setAllPlayersMultiactive();
        $this->game->gamestate->nextState('buildPhase');

        $order = 1;
        foreach( array_keys($this->players) as $player_id ) {
            if ($order == 2) 
                $this->game->finishShip($order, $player_id);
            $order++;
        }
        // All players finishShip ends the multipleactiveplayer, through stTakeOrderTiles, into repairShips
        // repairShips will move onto prepareFlight if no ships need to be repaired
        
        // stPrepareFlight will shuffle cards if the top card is not for the current round

        // prepareFlight will move to drawCard if no aliens need to be placed (via 'crewsDone')

        // so here we end in stDrawCard
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

    function repairShip($color, $variant) {
        // start with basicShip
        $tiles = $this->basicShip($color, $variant);

        // some "bad" parts (at least for $variant 1)
        array_push($tiles, self::newTile(66, 6, 6, 90)); // side-ways engine to west of cargo
        array_push($tiles, self::newTile(108, 8, 7, 0)); // cannon to east of main cabin, pointing north into battery
        array_push($tiles, self::newTile(37, 8, 8, 270)); // wrong connector (crew) east of southern engine
        array_push($tiles, self::newTile(10, 6, 8, 180) ); // wrong connector (battery) west side of southern engine
        array_push($tiles, self::newTile(6, 5, 8, 0)); // good connector (battery) west side of wrong connector

        return $tiles;
    }

    function getComponents() {
        // Return array of Tiles ready for inserting into component db

        // build a basic ship for each player
        $all_tiles = array();
        $i = 1;
        foreach( $this->players as $player_id => $player ) {
            if ($i == 1)
                $tiles = $this->repairShip($player['player_color'], $i);
            else
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

