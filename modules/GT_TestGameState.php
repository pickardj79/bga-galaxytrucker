<?php
/* A collection of quick-to-use game states.
 *
 */

require_once('GT_DBComponent.php');
require_once('GT_DBContent.php');
require_once('GT_DBCard.php');

class GT_TestGameState {
    public function __construct($game, $players) {
        $this->game = $game;
        $this->players = $players;
    }

    function log($msg) {
        $this->game->log($msg);
        $this->game->log_console($msg);
    }

    // ######################### STATE MANIPULATION ########################
    function pauseState() {
        $this->game->gamestate->nextState( "pauseTest" );
    }

    function prepareRound() {
        // runs at the end of stPrepareRound if testGameState GameStateValue is tru-ish (set at end of setupNewGame)
        // setupNewGame moves state to prepareRound
        //    which ends with allPlayersMultiactive going to buildPhase

        $this->log("Running prepareRound for Test GameState");

        list ($tiles, $content) = $this->getComponents();
        $sql = GT_DBComponent::updateTilesSql($tiles);
        $this->game::DbQuery($sql);

        $sql = GT_DBContent::insertContentSql($content);
        $this->game::DbQuery($sql);

        $this->game->gamestate->setAllPlayersMultiactive();
        $this->game->gamestate->nextState('buildPhase');

        // All players finishShip ends the multipleactiveplayer, through stTakeOrderTiles, into repairShips
        // repairShips will move onto prepareFlight if no ships need to be repaired
        $order = 0;
        foreach( array_keys($this->players) as $player_id ) {
            $order++;

            // Uncomment to pause after this step
            // if ($order == 1)
                // continue;

            $this->game->finishShip($order, $player_id);
        }

        // Move to repairShips
        // If nothing to repair, move to prepareFlight
    }

    function prepareFlight($nextState) {
        $this->log("Running prepareFlight for Test GameState");

        // Set cards - put planet card id 11 first
        // $this->setCardOrder(8,1); // meteor card
        // $this->setCardOrder(11,1); // planet card
        // $this->setCardOrder(3,1); // stardust card
        // $this->setCardOrder(4,1); // openspace card
        // $this->setCardOrder(17,1); // abship card
        // $this->setCardOrder(18,1); // abstation card 
        // $this->setCardOrder(0,1); // slavers card 
        $this->setCardOrder(1,1); // smugglers card 
        $this->setCardOrder(2,1); // pirates card 
        $this->setCardOrder(15,1); // combat zone card 

        // Add cargo to ships
        $sql = "INSERT INTO content (player_id, tile_id, square_x, square_y, content_type, content_subtype, place, capacity) VALUES";
        $values = array();
        $i = 1;
        foreach ($this->players as $player_id => $player) {
            if ($i == 1) {
                $values[] = "()";
            }
        }
        $sql .= "()";

        $this->game->gamestate->nextState( $nextState );

        // Move to stCheckNextCrew 
        // If no crew to place, move to stDrawCard
    }

    // ######################### GAME PARTS CHANGES (SHIPS, CARDS) ########################
    function getComponents() {
        // Return array of Tiles ready for inserting into component db

        // build a basic ship for each player
        $all_tiles = array();
        $all_content = array();
        $i = 1;
        foreach( $this->players as $player_id => $player ) {
            if ($i == 1)
                // list($tiles, $content) = $this->repairShipTiles($player['player_color'], $i);
                list($tiles, $content) = $this->basicShipTiles($player['player_color'], $i);
            else
                list($tiles, $content) = $this->basicShipTiles($player['player_color'], $i); 
            $i++;
            foreach( $tiles as &$tile ) {
                $tile['component_player'] = $player_id;
            }
            foreach( $content as &$cont) {
                $cont['player_id'] = $player_id;
            }
            $all_tiles = array_merge($all_tiles, $tiles);
            $all_content = array_merge($all_content, $content);
        }
        return array($all_tiles, $all_content);
    }

    function setCardOrder($card_id, $order) {
        $this->log("Setting card $card_id to order $order");

        if ($order != 1)
            throw new InvalidArgumentException("Only order=1 is implemented");
        $deck = GT_DBCard::getAdvDeckForFlight($this->game);

        // find the requested card, put it first if found, put a new one first if not found
        $card = array_values(
            array_filter($deck, function($c) use($card_id) { return $c['id'] == $card_id; } )
        );
        $the_card = null;
        if ($card) {
            $deck = array_filter($deck, function($c) use($card_id) { return $c['id'] != $card_id; } );
            $the_card = $card[0];
        }
        else {
            $the_card = array('round' => 1, 'id' => $card_id);
        }

        // loop through deck keeping track of original position ($i). Add new card at place $order
        $finaldeck = array();
        $i = 1;
        foreach ( $deck as $card ) {
            if ($i++ == $order)
                $finaldeck[] = $the_card;
            $finaldeck[] = $card;
        }
        array_unshift($deck, $the_card);

        GT_DBCard::updateAdvDeck($this->game, $deck);
    }

    // #########################  SHIP BIULDING ########################
    function newTile($id, $x, $y, $o) {
        if (! in_array($o, array(0,90,180,270) ) )
            throw new InvalidArgumentException("Orientation '$o' not valid");
        return array(
            'component_id' => $id, 'component_x' => $x, 'component_y' => $y, 'component_orientation' => $o
        );
    }

    function newContent($tile, $type, $subtype, $place, $capacity) {
        return array(
            'tile_id' => $tile['component_id'], 
            'square_x' => $tile['component_x'], 'square_y' => $tile['component_y'],
            'content_type' => $type, 'content_subtype' => $subtype, 
            'place' => $place, 'capacity' => $capacity
        );
    }

    // A basic ship with every type of tile
    // Returns array(Tiles['id','x','y','o']) to be inserted into db
    function basicShipTiles($color, $variant) {
        $tiles = array();
        $content = array();

        $start_id = $this->_getStartTile($color);

        array_push($tiles, self::newTile($start_id, 7, 7, 0));

        if ($variant == 1) {
            array_push($tiles, self::newTile(74, 7, 8, 0)); // single engine directly south
            $cargo = self::newTile(22, 7, 6, 0); // cargo north
            array_push($tiles, $cargo);
            array_push($tiles, self::newTile(123, 7, 5, 0)); // double-cannon above cargo 
            array_push($tiles, self::newTile(12, 8, 6, 270)); // battery to east of cargo
            array_push($tiles, self::newTile(115, 6, 7, 0)); // shield west of $startTile
            array_push($tiles, self::newTile(56, 5, 7, 0)); // hazard west of shield 
            // array_push($tiles, self::newTile(98, 5, 8, 90)); // side-facing cannon south of hazard 
            $cargo2 = self::newTile(19, 8, 7, 90); // cargo east of main cabin
            array_push($tiles, $cargo2);
            array_push($content, self::newContent($cargo, 'goods', 'blue', 1, 2));
            array_push($content, self::newContent($cargo, 'goods', 'green', 2, 2));
            array_push($content, self::newContent($cargo2, 'goods', 'blue', 1, 2));
        }
        else if ($variant == 2) {
            array_push($tiles, self::newTile(85, 7, 8, 0)); // double engine directly south
            $cargo = self::newTile(58, 7, 6, 0); // hazard cargo north
            array_push($tiles, $cargo);
            array_push($tiles, self::newTile(88, 7, 5, 0)); // cannon above that
            array_push($tiles, self::newTile(3, 6, 6, 270)); // battery to west of cargo
            array_push($content, self::newContent($cargo, 'goods', 'red', 1, 1));
        }

        // confirm no tiles are used more than once
        $tileIds = array_map(function($x) { return $x['component_id']; }, $tiles );
        if (count(array_unique($tileIds)) != count($tiles))
            $this->game->throw_bug_report_dump("Repeated tiles during setup", $tileIds);

        // confirm no tiles are co-located
        $unqIds = array_map(function($x) { return $x['component_x'] . '_' . $x['component_y']; }, $tiles);
        if (count(array_unique($unqIds)) != count($tiles))
            $this->game->throw_bug_report_dump("Tiles overlap during setup; x_y ids:", $unqIds);

        return array($tiles, $content);
    }

    function repairShipTiles($color, $variant) {
        // Create a ship that needs to be repaired:
        //    a few bad connectors, cannon shooting a component, engine wrong way
        //    part of ship should fall off

        $tiles = array();
        $content = array();
        $start_id = $this->_getStartTile($color);

        array_push($tiles, self::newTile($start_id, 7, 7, 0));
        array_push($tiles, self::newTile(74, 7, 8, 0)); // single engine directly south
        $cargo = self::newTile(22, 7, 6, 0); // cargo north
        array_push($tiles, $cargo);
        array_push($tiles, self::newTile(115, 6, 6, 270)); // shield west of $cargo
        array_push($tiles, self::newTile(123, 7, 5, 0)); // double-cannon above that
        array_push($tiles, self::newTile(12, 8, 6, 270)); // battery to east of cargo
        array_push($tiles, self::newTile(19, 8, 7, 90)); // cargo east of main cabin

        // some "bad" parts (at least for $variant 1)
        array_push($tiles, self::newTile(66, 6, 6, 90)); // side-ways engine to west of cargo
        array_push($tiles, self::newTile(108, 8, 7, 0)); // cannon to east of main cabin, pointing north into battery
        array_push($tiles, self::newTile(37, 8, 8, 270)); // wrong connector (crew) east of southern engine
        array_push($tiles, self::newTile(64, 8, 9, 270)); // wrong-way engine below wrongly connected crew

        // a section that will fall off
        array_push($tiles, self::newTile(10, 6, 8, 180) ); // wrong connector (battery) west side of southern engine
        array_push($tiles, self::newTile(6, 5, 8, 0)); // good connector (battery) west side of wrong connector
        array_push($tiles, self::newTile(16, 5, 7, 270)); // good connector (cargo) north 

        return array($tiles, $content);
    }

    function _getStartTile($color) {
        $start_id = array_search($color, $this->game->start_tiles);
        if (!$start_id)
            throw new InvalidArgumentException("color '$color' not valid");
        return $start_id; 
    }




}
