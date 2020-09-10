<?php
/* A collection of quick-to-use game states.
 *
 */

require_once('GT_DBComponent.php');
require_once('GT_DBContent.php');
require_once('GT_DBCard.php');

class GT_GameState {
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
        // $this->setCardOrder(11,1); // planet card
        // $this->setCardOrder(3,1); // stardust card
        // $this->setCardOrder(4,1); // openspace card
        $this->setCardOrder(17,1); // abship card
        // $this->setCardOrder(18,1); // abstation card 

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
            $this->log("EXISTING CARD");
            $deck = array_filter($deck, function($c) use($card_id) { return $c['id'] != $card_id; } );
            $this->game->dump_var("existing card, deck", $deck);
            $the_card = $card[0];
            $this->game->dump_var("existing card, the_card", $card);
            $this->game->dump_var("existing card, the_card", $the_card);
        }
        else {
            $this->log("NEW CARD");
            $the_card = array('round' => 1, 'id' => $card_id);
        }
        $this->game->dump_var("existing card", $the_card);
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

        // start tile
        $start_id = array_search($color, $this->game->start_tiles);
        if (!$start_id)
            throw new InvalidArgumentException("color '$color' not valid");

        array_push($tiles, self::newTile($start_id, 7, 7, 0));

        if ($variant == 1) {
            array_push($tiles, self::newTile(74, 7, 8, 0)); // single engine directly south
            $cargo = self::newTile(22, 7, 6, 0); // cargo north
            array_push($tiles, $cargo);
            array_push($tiles, self::newTile(123, 7, 5, 0)); // double-laser above that
            array_push($tiles, self::newTile(12, 8, 6, 270)); // battery to east of cargo
            array_push($tiles, self::newTile(19, 8, 7, 90)); // cargo east of main cabin
            array_push($content, self::newContent($cargo, 'goods', 'blue', 1, 2));
        }
        else if ($variant == 2) {
            array_push($tiles, self::newTile(85, 7, 8, 0)); // double engine directly south
            $cargo = self::newTile(58, 7, 6, 0); // hazard cargo north
            array_push($tiles, $cargo);
            array_push($tiles, self::newTile(88, 7, 5, 0)); // laser above that
            array_push($tiles, self::newTile(3, 6, 6, 270)); // battery to west of cargo
            array_push($content, self::newContent($cargo, 'goods', 'red', 1, 1));
        }
        return array($tiles, $content);
    }

    function repairShipTiles($color, $variant) {
        // Create a ship that needs to be repaired
        // start with basicShip
        list($tiles, $content) = $this->basicShipTiles($color, $variant);

        // some "bad" parts (at least for $variant 1)
        array_push($tiles, self::newTile(66, 6, 6, 90)); // side-ways engine to west of cargo
        array_push($tiles, self::newTile(108, 8, 7, 0)); // cannon to east of main cabin, pointing north into battery
        array_push($tiles, self::newTile(37, 8, 8, 270)); // wrong connector (crew) east of southern engine
        array_push($tiles, self::newTile(10, 6, 8, 180) ); // wrong connector (battery) west side of southern engine
        array_push($tiles, self::newTile(6, 5, 8, 0)); // good connector (battery) west side of wrong connector

        return array($tiles, $content);
    }




}

