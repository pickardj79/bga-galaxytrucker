<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * GalaxyTrucker implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * galaxytrucker.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 */

require_once APP_GAMEMODULE_PATH . 'module/table/table.game.php';
require_once 'modules/GT_ActionsBuild.php';
require_once 'modules/GT_ActionsCard.php';
require_once 'modules/GT_DBCard.php';
require_once 'modules/GT_DBComponent.php';
require_once 'modules/GT_DBPlayer.php';
require_once 'modules/GT_Enemy.php';
require_once 'modules/GT_FlightBoard.php';
require_once 'modules/GT_PlayerBoard.php';
require_once 'modules/GT_PlayerContent.php';
require_once 'modules/GT_StatesCard.php';
require_once 'modules/GT_StatesSetup.php';
require_once 'modules/GT_TestGameState.php';

class Galaxytrucker extends Table
{
  public static $instance = null;

  function __construct()
  {
    parent::__construct();
    self::$instance = $this;

    // Your global variables labels:
    //  Here, you can assign labels to global variables you are using for this game.
    //  You can use any number of global variables with IDs between 10 and 99.
    //  If your game has options (variants), you also have to associate here a label to
    //  the corresponding ID in gameoptions.inc.php.
    // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
    self::initGameStateLabels([
      'flight' => 10,
      'round' => 11, // 'round' is only the round 'level', i.e. 1 for class I,
      // 3 for class III or IIIa, etc., and is used to know which cards
      // to add in the adventure cards deck
      // May be different from 'flight' when using some variants like
      // shorter or longer games
      'shipClass' => 12,
      'cardOrderInFlight' => 13,
      'timerStartTime' => 14,
      'timerPlace' => 15,
      'buildingStartTime' => 16, // (for stats)
      'currentCard' => 20,
      'overlayTilesPlaced' => 21, // used in GetAllDatas to know if the client
      // must place overlay tiles in case of a page reload
      'currentCardProgress' => 22, // which part of card is in progress, e.g. which meteor #
      'currentCardDie1' => 23, // what is the current die roll being resolved
      'currentCardDie2' => 24,
      'currentHazardPlayerTile' => 25,
      'cardArg1' => 30, // args related to cards to pass values to front-end; or between back-end states
      'cardArg2' => 31,
      'cardArg3' => 32,
      'blah-blah' => 33,
      'testGameState' => 99, // use a test scenario from GT_TestGameState

      // flight_variants is a game option (gameoptions.inc.php)
      // if gameoptions.inc.php changes, they must be reloaded through BGA control panel: https://boardgamearena.com/doc/Game_options_and_preferences:_gameoptions.inc.php
      'flight_variants' => 100,
    ]);
  }

  protected function getGameName()
  {
    // Used for translations and stuff. Please do not modify.
    return 'galaxytrucker';
  }

  /*
        setupNewGame:

        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
  protected function setupNewGame($players, $options = [])
  {
    // Set the colors of the players with HTML color code
    // The default below is red/green/blue/orange/brown
    // The number of colors defined here must correspond to the maximum number
    // of players allowed for the game
    $default_colors = ['0000ff', '008000', 'ffff00', 'ff0000'];

    // Create players
    // Note: if you added some extra field on "player" table in the database
    // (dbmodel.sql), you can initialize it there.
    $sql = 'INSERT INTO player (player_id, player_color, player_canal,' . 'player_name, player_avatar) VALUES ';
    $values = [];
    foreach ($players as $player_id => $player) {
      $color = array_shift($default_colors);
      $values[] =
        "('" .
        $player_id .
        "','$color','" .
        $player['player_canal'] .
        "','" .
        addslashes($player['player_name']) .
        "','" .
        addslashes($player['player_avatar']) .
        "')";
    }
    $sql .= implode(',', $values);
    self::DbQuery($sql);
    self::reattributeColorsBasedOnPreferences($players, ['0000ff', '008000', 'ffff00', 'ff0000']);
    self::reloadPlayersBasicInfos();
    $players = self::loadPlayersBasicInfos();

    /************ Start the game initialization *****/
    self::log('Initializing new game');

    // Init global values with their initial values
    //self::setGameStateInitialValue( 'my_first_global_variable', 0 );
    self::setGameStateInitialValue('flight', 1);
    self::setGameStateInitialValue('cardOrderInFlight', 0);
    self::setGameStateInitialValue('currentCard', null);
    self::setGameStateInitialValue('currentCardProgress', null);
    self::setGameStateInitialValue('currentCardDie1', null);
    self::setGameStateInitialValue('currentCardDie2', null);
    self::setGameStateInitialValue('currentHazardPlayerTile', null); // Tile of current player threatened by the hazard
    self::setGameStateInitialValue('cardArg1', null); // Used for subtype of content to lose
    self::setGameStateInitialValue('cardArg2', null); // Used for number of content to lose
    self::setGameStateInitialValue('cardArg3', null); // Used for number of content to lose
    self::setGameStateInitialValue('currentCardDie2', null);
    self::setGameStateInitialValue('round', 1); // will be changed in stPrepareRound
    // in case of a short flight variant that begins with a level 2 flight
    self::setGameStateInitialValue('timerStartTime', 0);
    self::setGameStateInitialValue('timerPlace', -1);
    self::setGameStateInitialValue('overlayTilesPlaced', 0);

    // Init game statistics
    // (note: statistics used in this file must be defined in your stats.inc.php file)
    //self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
    //self::initStat( 'player', 'player_teststat1', 0 );  // Init a player stat (for all pl.)

    // setup the initial game situation

    $sql =
      'INSERT INTO component (component_id, component_player, component_x, component_y, ' .
      'component_orientation, aside_discard) VALUES ';
    $values = [];
    for ($i = 1; $i <= 144; $i++) {
      $values[] = '(' . $i . ',NULL,NULL,NULL,0,NULL)';
    }
    $sql .= implode(',', $values);
    self::DbQuery($sql);

    $sql = 'INSERT INTO card (card_round, card_id) VALUES ';
    $values = [];
    for ($round = 1; $round <= 3; $round++) {
      for ($i = 0; $i <= 19; $i++) {
        $id = $i + 20 * ($round - 1);
        $values[] = '(' . $round . ',' . $id . ')';
      }
    }
    $sql .= implode(',', $values);
    $queryRet = self::DbQuery($sql);
    self::traceExportVar($queryRet, 'queryRet', 'setupNewGame');

    $sql = 'INSERT INTO revealed_pile (space, tile_id) VALUES ';
    $values = [];
    for ($space = 0; $space <= 139; $space++) {
      // Expansions: value '139' must be raised if
      // the total number of available tiles is raised
      $values[] = '(' . $space . ',NULL)';
    }
    $sql .= implode(',', $values);
    self::DbQuery($sql);

    self::log('Game initialized');
    self::setGameStateInitialValue('testGameState', 1);

    /************ End of the game initialization *****/
  }

  /*
        getAllDatas:

        Gather all informations about current game situation (visible by the current player).

        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
  protected function getAllDatas()
  {
    self::log('Starting getAllDatas()');
    $result = [];
    $state = $this->gamestate->state();

    $current_player_id = self::getCurrentPlayerId(); // !! We must only return
    // informations visible by this player !!

    ////// Get information about players
    $sql =
      'SELECT player_id id, player_name name, player_color color, player_score score, credits, turn_order, ' .
      'player_position, card_action_choice, undo_possible, exp_conn, nb_crew, min_eng, max_eng, ' .
      'min_cann_x2, max_cann_x2, still_flying FROM player ';
    $result['players'] = self::getCollectionFromDB($sql);
    $result['nbPlayers'] = count($result['players']);

    ////// Gather all information about current game situation
    ////// (visible by player $current_player_id)
    $flightVariant = self::getGameStateValue('flight_variants');
    // The first, second or third (or fourth?) flight in this game:
    $flight = self::getGameStateValue('flight');
    // The 'level' of current flight, i.e. 1 for class I, 3 for class III or IIIa, etc.
    $result['round'] = $this->flightVariant[$flightVariant][$flight]['round'];
    // Ship class, used to display appropriate ship board and available squares:
    $result['shipClass'] = $this->flightVariant[$flightVariant][$flight]['shipClass'];

    // TODO? Fetch the whole component table once, instead of making 5 different requests?

    // All PLACED TILES from all players, including tiles SET ASIDE / DISCARDED
    $result['placed_tiles'] = self::getCollectionFromDB(
      'SELECT component_id id, component_x x, ' .
        'component_y y, component_player player, component_orientation o, aside_discard ' .
        'FROM component WHERE component_player IS NOT NULL AND component_x IS NOT NULL'
    );
    if (self::getGameStateValue('overlayTilesPlaced') === '1') {
      // We add a property to each cabin to tell the client to place an overlay tile (should we
      // add a column in DB instead?)
      foreach ($result['placed_tiles'] as $tile) {
        if ($this->tiles[$tile['id']]['type'] == 'crew' && $tile['aside_discard'] == null) {
          $result['placed_tiles'][$tile['id']]['placeOverlay'] = true;
        }
      }
    }
    $result['cards'] = []; // This array will stay empty unless we're in buildPhase
    // and current player is still active, but we send it anyway because
    // setupCardsPiles() in setup() expects it

    // Only in waitForPlayers and buildPhase:
    // Pile of face down tiles
    if ($state['name'] == 'waitForPlayers' || $state['name'] == 'buildPhase') {
      $result['tilesLeft'] = self::getUniqueValueFromDB(
        'SELECT COUNT(component_id) ' . 'FROM component WHERE component_player IS NULL'
      );
    }

    ////////// Some informations are only needed during BUILD PHASE (and takeOrderTiles):
    if ($state['name'] == 'buildPhase' || $state['name'] == 'prepareRound') {
      // possible?
      // some informations are only needed when current player is STILL ACTIVE
      if (in_array($current_player_id, $this->gamestate->getActivePlayerList())) {
        // If it's possible that getAllDatas is executed during a "game" gamestate
        //(prepareRound), we must add || $state['name'] == 'prepareRound' to this if condition
        $result['current_tile'] = self::getObjectFromDB(
          'SELECT component_id id, ' .
            "aside_discard aside FROM component WHERE component_player=$current_player_id " .
            'AND component_x IS NULL'
        );
        $result['undoPossible'] = $result['players'][$current_player_id]['undo_possible'];
        // Check if current player has placed at least 1 tile, to allow them to look at
        // adventure cards (may be different for ship classes from expansions)
        $result['atLeast1Tile'] = 0;
        foreach ($result['placed_tiles'] as $tile) {
          if ($tile['player'] == $current_player_id && ($tile['x'] != 7 || $tile['y'] != 7)) {
            // Expansions: need to be changed for expansions' ship classes that don't
            // have a starting component at the beginning
            $result['atLeast1Tile'] = 1;
            break;
          }
        }
        if ($result['atLeast1Tile'] == 1) {
          $result['cards'] = GT_DBCard::getAdvDeckPreview($this);
        }
      }
      $result['revealed_tiles'] = self::getCollectionFromDB(
        'SELECT tile_id id, space ' . 'FROM revealed_pile WHERE tile_id IS NOT NULL ORDER BY space DESC',
        true
      );
      // ordered because we get max space value just below (with current()), to
      // compute number of lines in revealed_pile

      // TIMER
      $timerPlace = self::getGameStateValue('timerPlace');
      if ($timerPlace !== '-1') {
        $result['timerPlace'] = (int) $timerPlace;
      }
      $elapsedTime = time() - self::getGameStateValue('timerStartTime');
      $result['timeLeft'] = $elapsedTime <= 90 ? 90 - $elapsedTime : 0;
    }
    ////////// END of BUILD-PHASE-only informations

    // ORDER TILES:
    if (in_array($state['name'], ['prepareRound', 'waitForPlayers', 'buildPhase', 'takeOrderTiles', 'repairShips'])) {
      // order_tiles array initialization
      $result['order_tiles'] = [];
      for ($pos = 1; $pos <= $result['nbPlayers']; $pos++) {
        $result['order_tiles'][$pos] = 'available';
      }
      // Which tiles have already been taken, and by who (to display them on ship boards)
      foreach ($result['players'] as $player_id => $player) {
        if ($player['turn_order']) {
          $result['order_tiles'][$player['turn_order']] = $player_id;
        }
      }
    }

    // send TILE CONTENT (crew, cells, goods, in content DB table) to client, and also
    // alien choices still to be made
    $result['content'] = self::getObjectListFromDB('SELECT * FROM content');
    $result['tiles'] = array_values($this->tiles);

    $result['currentCard'] = GT_StatesCard::currentCardData($this);

    return $result;
  }

  /*
        getGameProgression:

        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).

        This method is called each time we are in a game state with the "updateGameProgression" property set to true
        (see states.inc.php)
    */
  function getGameProgression()
  {
    // TODO: need to be changed if more or less than 3 flights in game, or if more cards in
    // the adventure deck (expansions)
    switch (self::getGameStateValue('flight')) {
      case 1:
        $before = 2;
        break;
      case 2:
        $before = 15;
        break;
      case 3:
        $before = 34;
        break;
    }
    $card = self::getGameStateValue('cardOrderInFlight');
    return floor(($card + $before) * 2);
  }

  //////////////////////////////////////////////////////////////////////////////
  //////////// Utility functions
  ////////////

  function log($msg)
  {
    self::trace("##### $msg");
  }

  function log_console($msg)
  {
    self::notifyAllPlayers('consoleLog', '', ['msg' => $msg]);
  }

  function dump_var($msg, $var)
  {
    self::trace("##### $msg :: " . var_export($var, true));
  }

  function dump_console($msg, $var)
  {
    self::notifyAllPlayers('consoleLog', '', "$msg :: " . var_export($var, true));
  }

  function user_exception($msg)
  {
    throw new BgaUserException(self::_($msg));
  }

  function throw_bug_report($msg)
  {
    $func = debug_backtrace()[1]['function'];
    $line = debug_backtrace()[1]['line'];
    throw new BgaVisibleSystemException($msg . " (at $func line $line)! " . $this->plReportBug);
  }

  function throw_bug_report_dump($msg, $var)
  {
    $func = debug_backtrace()[1]['function'];
    $line = debug_backtrace()[1]['line'];
    $msg .= var_export($var, true);
    throw new BgaVisibleSystemException($msg . " (at $func line $line)! " . $this->plReportBug);
  }

  function traceExportVar($varToExport, $varName, $functionStr)
  {
    self::trace("###### $functionStr(): $varName is " . var_export($varToExport, true) . ' ');
  }

  // Hooks into protected functions for our "helper" modules
  function myActiveNextPlayer()
  {
    $this->activeNextPlayer();
  }

  function getPlayerBoard($player_id)
  {
    return self::getDoubleKeyCollectionFromDB(
      'SELECT component_x x, component_y y, ' .
        'component_id id, component_orientation o FROM component ' .
        "WHERE component_player=$player_id AND component_x>0"
    );
  }

  function getPlContent($plId)
  {
    return self::getCollectionFromDB("SELECT * FROM content WHERE player_id=$plId");
  }

  function newPlayerBoard(int $player_id, $plBoard = null)
  {
    if ($plBoard) {
      return new GT_PlayerBoard($this, $plBoard, $player_id);
    } else {
      return new GT_PlayerBoard($this, self::getPlayerBoard($player_id), $player_id);
    }
  }

  function newPlayerContent(int $player_id, $plContent = null)
  {
    if ($plContent) {
      return new GT_PlayerContent($this, $plContent, $player_id);
    } else {
      return new GT_PlayerContent($this, self::getPlContent($player_id), $player_id);
    }
  }

  function newFlightBoard($players = null)
  {
    if ($players) {
      return new GT_FlightBoard($this, $players);
    } else {
      return new GT_FlightBoard($this, GT_DBPlayer::getPlayersInFlight($this));
    }
  }

  function getTileType(int $id)
  {
    return $this->tiles[$id]['type'];
  }

  function getTileTypeName(int $id)
  {
    return $this->tileNames[$this->getTileType($id)];
  }

  function getTileHold(int $id)
  {
    $tile = $this->tiles[$id];
    if (array_key_exists('hold', $tile)) {
      return $tile['hold'];
    }
    return $this->tileHoldCnt[$this->getTileType($id)];
  }

  function updNotifPlInfos($plId, $plBoard = null, $plContent = null)
  {
    // Same function as updNotifPlInfos but with raw plBoard (tiles) and plContent data,
    //  not the associated objects
    $brd = $this->newPlayerBoard($plId, $plBoard);
    $plyrContent = $this->newPlayerContent($plId, $plContent);
    return $this->updNotifPlInfosObj($plId, $brd, $plyrContent);
  }

  function updNotifPlInfosObj($plId, $plBrd = null, $plyrContent = null)
  {
    // This function is called each time a player loses batteries, aliens, components
    // (in this last case $bExpConn is true), and once when ships are built and content
    // (including aliens) placed. It gets updated values for this player and uses them
    // to update the player table and notify players so that they can update these
    // informations in BGA's side player boards.

    if (!$plBrd) {
      $plBrd = $this->newPlayerBoard($plId);
    }
    if (!$plyrContent) {
      $plyrContent = $this->newPlayerContent($plId);
    }

    $minMaxCann = $plBrd->getMinMaxStrengthX2($plyrContent, 'cannon');
    $minMaxEng = $plBrd->getMinMaxStrengthX2($plyrContent, 'engine');
    $dblCann = $plBrd->countDoubleCannons();
    $dblEng = $plBrd->countDoubleEngines();
    $items = [
      [
        'type' => 'minMaxCann',
        'value' => $minMaxCann['min'] / 2 . '/' . $minMaxCann['max'] / 2,
      ],
      [
        'type' => 'minMaxEng',
        'value' => $minMaxEng['min'] / 2 . '/' . $minMaxEng['max'] / 2,
      ],
    ];
    $sql = 'UPDATE player SET ';

    $nbCrewMembers = $plyrContent->nbOfCrewMembers();
    $sql .= 'nb_crew=' . $nbCrewMembers . ', ';
    $items[] = ['type' => 'nbCrew', 'value' => $nbCrewMembers];

    $nbExp = $plBrd->nbOfExposedConnectors();
    $sql .= 'exp_conn=' . $nbExp . ', ';
    $items[] = ['type' => 'expConn', 'value' => $nbExp];

    $sql .=
      'min_cann_x2=' .
      $minMaxCann['min'] .
      ', max_cann_x2=' .
      $minMaxCann['max'] .
      ', ' .
      'min_eng=' .
      $minMaxEng['min'] / 2 .
      ', max_eng=' .
      $minMaxEng['max'] / 2 .
      ', ' .
      "num_dbl_eng={$dblEng}, num_dbl_cann={$dblCann} " .
      "WHERE player_id=$plId";
    self::DbQuery($sql);
    self::notifyAllPlayers('updatePlBoardItems', '', [
      'plId' => $plId,
      'items' => $items,
    ]);

    // If player has zero crew, then they have to give up
    if (count($plyrContent->getContent('crew', 'human')) == 0) {
      self::newFlightBoard()->giveUp($plId, 'lost all humans');
    }
  }

  function getConnectorType($tile, $side)
  {
    // compute side presented by this tile
    $tileSide = (360 + $side - $tile['o']) % 360; // we add 360 so that it can't be negative
    // return connector type
    return $this->tiles[$tile['id']][$this->orient[$tileSide]];
    // $this->orient[0] is 'n', $this->orient[90] is 'e', etc.
  }

  function getNbOfCrewMembers($plId, $plContent = null)
  {
    return self::getUniqueValueFromDB(
      'SELECT COUNT(content_type) FROM content ' . "WHERE content_type='crew' AND player_id=$plId"
    );
  }

  //////////////////////////////////////////////////////////////////////////////
  //////////// Player actions
  ////////////

  /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in galaxytrucker.action.php)
    */

  function ImReady()
  {
    self::checkAction('ImReady');
    $player_id = self::getCurrentPlayerId();
    // TODO do we have some checks to do? Maybe not, since this action only de-activate current player
    $this->gamestate->setPlayerNonMultiactive($player_id, 'readyGo');
  }

  function pickTile()
  {
    self::checkAction('pickTile');
    (new GT_ActionsBuild($this, self::getCurrentPlayerId()))->pickTile();
  }

  function pickRevealed($tile_id)
  {
    self::checkAction('pickRevealed');
    (new GT_ActionsBuild($this, self::getCurrentPlayerId()))->pickRevealed($tile_id);
  }

  function pickAside($tile_id)
  {
    self::checkAction('pickAside');
    (new GT_ActionsBuild($this, self::getCurrentPlayerId()))->pickAside($tile_id);
  }

  function pickLastPlaced($tile_id)
  {
    self::checkAction('pickLastPlaced');
    (new GT_ActionsBuild($this, self::getCurrentPlayerId()))->pickLastPlaced($tile_id);
  }

  function dropTile($tile_id)
  {
    self::checkAction('dropTile');
    (new GT_ActionsBuild($this, self::getCurrentPlayerId()))->dropTile($tile_id);
  }

  function placeTile($component_id, $x, $y, $o, $discard)
  {
    self::checkAction('placeTile');
    $plId = self::getCurrentPlayerId();
    if ($discard) {
      (new GT_ActionsBuild($this, $plId))->discardTile($component_id, $x, $y, $o);
    } else {
      $firstTile = (new GT_ActionsBuild($this, $plId))->placeTile($component_id, $x, $y, $o);
      if ($firstTile) {
        $cards = GT_DBCard::getAdvDeckPreview($this);
        self::notifyPlayer($plId, 'cardsPile', '', ['cards' => $cards]);
      }
    }
  }

  function flipTimer($timerPlace)
  {
    // We use checkPossibleAction instead of checkAction because a player can
    // flip the timer when inactive
    $this->gamestate->checkPossibleAction('flipTimer');

    $player_name = self::getCurrentPlayerName();
    (new GT_ActionsBuild($this, self::getCurrentPlayerId()))->flipTimer($timerPlace, $player_name);
  }

  function timeFinished()
  {
    // We can't set a timer that triggers the end of building server-side,
    // so the clients send a timeFinished ajax action when their 90s timer
    // is finished on the last timer space. This method checks if time is
    // really finished, does things, then changes state.

    // But if just after the first timeFinished action, another client sends
    // a second one before having received the state change notif (which
    // prevents it to send a timeFinished action), we must ignore this
    // second timeFinished, but we must still send a quiet notif, because
    // the client consider that an action is still in progress untill it
    // gets an answer from the server.
    if (!self::checkAction('timeFinished', false)) {
      self::notifyPlayer(self::getCurrentPlayerId(), 'timeFinished', '', []);
      return;
    }

    (new GT_ActionsBuild($this, self::getCurrentPlayerId()))->timeFinished();
    $this->gamestate->nextState('timeFinished');
  }

  function finishShip($orderTile, $player_id = null)
  {
    // Many things in galaxy trucker's code are based on the asumption that this
    // function is ALWAYS executed for each player each round.

    if (!$player_id) {
      self::checkAction('finishShip');
      $player_id = self::getCurrentPlayerId();
    }
    (new GT_ActionsBuild($this, $player_id))->finishShip($orderTile);

    $this->gamestate->setPlayerNonMultiactive($player_id, 'shipsDone');
  }

  function finishRepairs()
  {
    self::checkAction('finishRepairs');
    $player_id = self::getCurrentPlayerId();
    $brd = $this->newPlayerBoard($player_id);

    // TODO

    $nbExp = $brd->nbOfExposedConnectors();
    // Maybe we'll include DB update and notif in nbOfExposedConnectors()
    self::DbQuery("UPDATE player SET exp_conn=$nbExp WHERE player_id=$player_id");
    self::notifyAllPlayers('updatePlBoardItems', '', [
      'plId' => $player_id,
      'items' => [['type' => 'expConn', 'value' => $nbExp]],
    ]);
    $this->gamestate->setPlayerNonMultiactive($player_id, 'repairsDone');
  }

  function crewPlacementDone($alienChoices)
  {
    self::checkAction('crewPlacementDone');
    $plId = self::getActivePlayerId();
    $player_name = self::getActivePlayerName();

    (new GT_ActionsBuild($this, $plId))->crewPlacementDone($alienChoices, $player_name);

    // Update of min / max strength (DB and notif)
    self::updNotifPlInfos($plId);
    $this->gamestate->nextState('crewPlacementDone');
  }

  // ############ CARD ACTIONS ###############

  function powerEngines($battChoices)
  {
    // current this is setup only for powering engines during open space
    self::checkAction('contentChoice');
    $plId = self::getActivePlayerId();
    GT_ActionsCard::powerEngines($this, $plId, $battChoices);
    GT_DBPlayer::setCardDone($this, $plId);
    $this->gamestate->nextState('nextPlayer');
  }

  function powerShields($battChoices)
  {
    self::checkAction('contentChoice');
    $plId = self::getActivePlayerId();
    $cardId = self::getGameStateValue('currentCard');
    $card = $this->card[$cardId];

    if ($this->card[$cardId]['type'] == CARD_METEORIC_SWARM) {
      GT_ActionsCard::powerDefense($this, $plId, $card, $battChoices, 'shields');
      $this->gamestate->nextState('nextMeteor');
    } elseif ($this->card[$cardId]['type'] == CARD_PIRATES) {
      GT_ActionsCard::powerDefense($this, $plId, $card, $battChoices, 'shields');
      $this->gamestate->nextState('nextCannon');
    } else {
      $this->gamestate->nextState('notImpl');
    }
  }

  function powerCannons($battChoices)
  {
    self::checkAction('contentChoice');
    $plId = self::getActivePlayerId();
    $cardId = self::getGameStateValue('currentCard');
    $card = $this->card[$cardId];
    $type = $card['type'];

    if ($type == CARD_METEORIC_SWARM) {
      GT_ActionsCard::powerDefense($this, $plId, $card, $battChoices, 'cannons');
      $this->gamestate->nextState('nextMeteor');
    }
    if (in_array($type, [CARD_SLAVERS, CARD_SMUGGLERS, CARD_PIRATES])) {
      $nextState = GT_ActionsCard::powerCannonsEnemy($this, $plId, $card, $battChoices);
      $this->gamestate->nextState($nextState);
    } else {
      $this->gamestate->nextState('notImpl');
    }
  }

  function exploreChoice($choice)
  {
    self::checkAction('exploreChoice');
    $plId = self::getActivePlayerId();
    $cardId = self::getGameStateValue('currentCard');
    $nextState = GT_ActionsCard::exploreChoice($this, $plId, $cardId, $choice);

    $this->gamestate->nextState($nextState);
  }

  function loseContentChoice($type, $ids)
  {
    self::checkAction('contentChoice');
    self::dump_var("Action contentChoice type $type", $ids);
    $plId = self::getActivePlayerId();
    $cardId = self::getGameStateValue('currentCard');
    $card = $this->card[$cardId];
    $nextState = GT_ActionsCard::loseContentChoice($this, $plId, $card, $type, $ids);

    $this->gamestate->nextState($nextState);
  }

  function crewChoice($crewChoices)
  {
    self::checkAction('contentChoice');
    self::dump_var('Action contentChoice ', $crewChoices);
    $plId = self::getActivePlayerId();
    $cardId = self::getGameStateValue('currentCard');
    $nextState = GT_ActionsCard::crewChoice($this, $plId, $cardId, $crewChoices);

    $this->gamestate->nextState($nextState);
  }

  function planetChoice($choice)
  {
    self::checkAction('planetChoice');
    $plId = self::getActivePlayerId();
    $cardId = self::getGameStateValue('currentCard');
    $nextState = GT_ActionsCard::planetChoice($this, $plId, $cardId, $choice);

    $this->gamestate->nextState($nextState);
  }

  function cargoChoice($goodsOnTile)
  {
    self::checkAction('cargoChoice');
    $plId = self::getActivePlayerId();
    $cardId = self::getGameStateValue('currentCard');
    GT_ActionsCard::cargoChoice($this, $plId, $cardId, $goodsOnTile);
    if ($this->card[$cardId]['type'] == CARD_PLANETS) {
      $this->gamestate->nextState('cargoChoicePlanet');
    } else {
      $this->gamestate->nextState('nextCard');
    }
  }

  function goOn()
  {
    self::checkAction('goOn');
    $cardId = self::getGameStateValue('currentCard');
    $this->dump_var("card $cardId is neteoric", $this->card[$cardId]);
    if ($cardId && $this->card[$cardId]['type'] == CARD_METEORIC_SWARM) {
      $this->log('card is meteoric');
      $this->gamestate->nextState('nextMeteor');
    } else {
      $this->log('drawing card');
      $this->gamestate->nextState('nextCard');
    }
  }

  function tempTestNextRound()
  {
    self::checkAction('tempTestNextRound');
    $this->gamestate->nextState('tempTestNextRound');
  }

  //////////////////////////////////////////////////////////////////////////////
  //////////// Temporary test functions
  ////////////

  /*
        These methods are only used to quickly check if game logic works properly, or to debug it.
    */

  function test_countTileType($type, $hold = null, $orientation = null)
  {
    $player_id = self::getCurrentPlayerId();
    $brd = $this->newPlayerBoard($player_id);
    $ret = $brd->countTileType($type, $hold, $orientation);
    $msg = "###### countTileType() $type $hold $orientation returns " . var_export($ret, true) . ' ';
    self::trace($msg);
    self::log_console($msg);
  }

  function test_checkShield($sideToProtect)
  {
    // temp
    $player_id = self::getCurrentPlayerId();
    $brd = $this->newPlayerBoard($player_id);
    $plyrContent = $this->newPlayerContent($player_id);
    $ret = $brd->checkIfPowerableShield($plyrContent, $sideToProtect);
    $msg = '###### checkIfPowerableShield() returns ' . var_export($ret, true) . ' ';
    self::trace($msg);
    self::log_console($msg);
  }

  function test_checkCannon($sideToCheck, $rowOrCol)
  {
    // temp
    $player_id = self::getCurrentPlayerId();
    $brd = $this->newPlayerBoard($player_id);
    $plyrContent = $this->newPlayerContent($player_id);
    $singRet = $brd->checkSingleCannonOnLine($rowOrCol, $sideToCheck);
    $msg = '###### checkIfSingleCannonOnLine() returns ' . var_export($singRet, true) . ' ';
    self::trace($msg);
    self::log_console($msg);

    $doubRet = $brd->checkDoubleCannonOnLine($rowOrCol, $sideToCheck, $plyrContent);
    $msg = '###### checkIfDoubleCannonOnLine() returns ' . var_export($doubRet, true) . ' ';
    self::trace($msg);
    self::log_console($msg);
  }

  function test_checkLine($sideToCheck, $rowOrCol)
  {
    // temp
    $player_id = self::getCurrentPlayerId();
    $brd = $this->newPlayerBoard($player_id);
    $ret = $brd->getLine($rowOrCol, $sideToCheck);
    $msg = '###### getLine() returns ' . var_export($ret, true) . ' ';
    self::trace($msg);
    self::log_console($msg);
  }

  function test_checkConn($sideToCheck, $rowOrCol)
  {
    // temp
    $player_id = self::getCurrentPlayerId();
    $brd = $this->newPlayerBoard($player_id);
    $ret = $brd->checkIfExposedConnector($rowOrCol, $sideToCheck);
    $msg = '###### checkIfExposedConnector returns ' . var_export($ret, true) . ' ';
    self::trace($msg);
    self::log_console($msg);
  }

  //////////////////////////////////////////////////////////////////////////////
  //////////// Game state arguments
  ////////////

  /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */

  function argPowerEngines()
  {
    $plId = self::getActivePlayerId();
    $player = GT_DBPlayer::getPlayer($this, $plId);
    $plyrContent = $this->newPlayerContent($plId);
    $nbCells = $plyrContent->checkIfCellLeft();
    return [
      'baseStr' => $player['min_eng'],
      'maxStr' => $player['max_eng'],
      'maxSel' => min($player['num_dbl_eng'], $nbCells),
      'hasAlien' => $plyrContent->checkIfAlien('brown'),
    ];
  }

  function argPowerCannons()
  {
    $plId = self::getActivePlayerId();
    $player = GT_DBPlayer::getPlayer($this, $plId);
    $plyrContent = $this->newPlayerContent($plId);
    $nbCells = $plyrContent->checkIfCellLeft();
    return [
      'baseStr' => $player['min_cann_x2'] / 2,
      'maxStr' => $player['max_cann_x2'] / 2,
      'maxSel' => min($player['num_dbl_cann'], $nbCells),
      'hasAlien' => $plyrContent->checkIfAlien('purple'),
    ];
  }

  function argPowerShields()
  {
    $plId = self::getActivePlayerId();
    return [];
  }

  function argExploreAbandoned()
  {
    $plId = self::getActivePlayerId();
    $currentCard = self::getGameStateValue('currentCard');
    if (
      $this->card[$currentCard]['type'] == CARD_ABANDONED_SHIP &&
      self::getNbOfCrewMembers($plId) == $this->card[$currentCard]['crew']
    ) {
      $wholeCrewWillLeave = true;
    } else {
      $wholeCrewWillLeave = false;
    }
    return ['wholeCrewWillLeave' => $wholeCrewWillLeave];
  }

  function argChooseCrew()
  {
    $currentCard = self::getGameStateValue('currentCard');
    return ['nbCrewMembers' => self::getGameStateValue('cardArg2')];
  }

  function argLoseGoods()
  {
    $req_idx = self::getGameStateValue('cardArg1');
    return [
      'subtype' => GT_Constants::$ALLOWABLE_SUBTYPES['goods'][$req_idx],
      'nbGoods' => self::getGameStateValue('cardArg2'),
    ];
  }

  function argLoseCells()
  {
    return ['nbCells' => self::getGameStateValue('cardArg2')];
  }

  function argChoosePlanet()
  {
    // Find which planet indexes are available from the planet card
    //    and remove those that have already been chosen by another player
    $currentCard = self::getGameStateValue('currentCard');
    $card = $this->card[$currentCard];
    $alreadyChosen = GT_DBPlayer::getCardChoices($this);

    $availIdx = [];
    $planetIdxs = [];
    foreach (array_keys($card['planets']) as $idx) {
      if ($idx < 1 or $idx > 4) {
        $this->throw_bug_report("planet $currentCard has invalid index $idx");
      }
      $availIdx[] = $idx;
      $planetIdxs[$idx] = array_key_exists($idx, $alreadyChosen) ? $alreadyChosen[$idx]['player_id'] : null;
    }

    // planetIdxs is dict of planetId => player_id for all planet idx.
    // player_id is null for planets not yet chosen
    return ['planetIdxs' => $planetIdxs];
  }

  function argPlaceGoods()
  {
    $currentCard = self::getGameStateValue('currentCard');

    $allPlayerChoices = null;
    if ($this->card[$currentCard]['type'] == CARD_PLANETS) {
      $args = $this->argChoosePlanet();
      $allPlayerChoices = $args['planetIdxs'];
    }

    $plId = self::getActivePlayerId();
    $player = GT_DBPlayer::getPlayer($this, $plId);
    return [
      'playerChoice' => $player['card_action_choice'],
      'allPlayerChoices' => $allPlayerChoices,
      'cardType' => $this->card[$currentCard],
    ];
  }

  //////////////////////////////////////////////////////////////////////////////
  //////////// Game state actions
  ////////////

  /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

  function stPrepareRound()
  {
    // Are globals 'flight' and 'round' updated here or in stJourneysEnd()?
    // flight in stJourneysEnd(), but 'round' must be set here, since for the
    // first round it can't be set in setupNewGame()
    self::log('Starting stPrepareRound');
    $players = $this->loadPlayersBasicInfos();

    GT_StatesSetup::stPrepareRound($this, $players);

    // Setup a test game state
    if (self::getGameStateValue('testGameState')) {
      $gt_state = new GT_TestGameState($this, $players);
      $gt_state->prepareRound();
    } else {
      // Images may take some time to load, so for the first flight, when
      // the page is loading, we wait for players to announce they're ready,
      // using a waitForPlayers multipleactiveplayer gamestate
      $flight = self::getGameStateValue('flight');
      $nextState = $flight == 1 ? 'waitForPlayers' : 'buildPhase';
      $this->gamestate->setAllPlayersMultiactive();
      $this->gamestate->nextState($nextState);
    }
  }

  function stActivatePlayersForBuildPhase()
  {
    $this->gamestate->setAllPlayersMultiactive();
    $this->gamestate->nextState();
  }

  function stBuildPhase()
  {
    // Start timer
    $startTime = time();
    // Globals are stored as signed INT, so this will be problematic around year 2038 :)
    // except if we substract a constant number of seconds each time we call time().
    self::setGameStateValue('timerStartTime', $startTime);
    self::setGameStateValue('buildingStartTime', $startTime);
    self::notifyAllPlayers('startTimer', '', []);
  }

  function stTakeOrderTiles()
  {
    // Players who haven't taken an order tile yet
  }

  function stRepairShips()
  {
    self::log('Starting stRepairShips');
    $players = self::loadPlayersBasicInfos();
    $playersToActivate = [];
    $errors = [];
    $playersShipPartsToKeep = [];

    self::setGameStateValue('timerPlace', -1);
    self::setGameStateValue('timerStartTime', 0);

    foreach ($players as $player_id => $player) {
      $player_name = $player['player_name'];
      $actionNeeded = false;
      $brd = $this->newPlayerBoard($player_id);

      // At first, we check if there are badly oriented engines in the ship, because
      // since we'll remove them without asking player, we can in next steps check for
      // other errors without considering them (so we remove them from $brd), and
      // also check if the ship holds together when these engines are removed
      $engToRemove = $brd->badEngines();
      $brd->removeTiles($engToRemove);
      GT_DBComponent::removeComponents(
        $this,
        $player_id,
        array_map(function ($x) {
          return $x['id'];
        }, $engToRemove)
      );
      if ($engToRemove) {
        $idToRemove = array_map(function ($x) {
          return $x['id'];
        }, $engToRemove);
        self::notifyAllPlayers(
          'loseComponent',
          clienttranslate('${player_name} ' . 'loses ${numbComp} engine(s): wrong orientation'),
          [
            'plId' => $player_id,
            'player_name' => $player_name,
            'numbComp' => count($idToRemove),
            'tileIds' => $idToRemove,
          ]
        );
      }

      // 2nd step: check if this ship holds together, taking into account illegal connections
      // Check if these ship parts are valid (at least one cabin), only needed if
      // more than one part OR with ship classes without starting component
      $shipParts = $brd->checkShipIntegrity();
      $partsToKeep = $brd->removeInvalidParts($shipParts, $player_name);

      if (count($partsToKeep) > 1) {
        $actionNeeded = true;
        $playersShipPartsToKeep[$player_id] = $partsToKeep;
      }

      // 3rd step: check for other tile-based errors
      $myErrors = $brd->checkTilesBuild();
      if ($myErrors) {
        $actionNeeded = true;
        $errors = array_merge($errors, $myErrors);
      }

      if ($actionNeeded) {
        $playersToActivate[] = $player_id;
      } else {
        // This player's ship is ready for the flight, so we count exposed connectors
        // now. Otherwise, we'll count them when they have repaired their ship.
        $nbExp = $brd->nbOfExposedConnectors();
        // Maybe we'll include DB update and notif in nbOfExposedConnectors()
        self::DbQuery("UPDATE player SET exp_conn=$nbExp WHERE player_id=$player_id");
        self::notifyAllPlayers('updatePlBoardItems', '', [
          'plId' => $player_id,
          'items' => [['type' => 'expConn', 'value' => $nbExp]],
        ]);
      }
    } // End of foreach player

    if ($playersToActivate) {
      // Notify players so that the clients can mark ship parts
      // to choose from and/or connections with errors
      self::notifyAllPlayers('buildingErrors', '', [
        'errors' => $errors,
        'shipParts' => $playersShipPartsToKeep,
      ]);
    }

    $this->gamestate->setPlayersMultiactive($playersToActivate, 'repairsDone');
    // maybe this will change if we use turn order instead of everyone at the same time
    self::log('Finished stRepairShips');
  }
  // ######################### END stRepairShips #########3

  function stPrepareFlight()
  {
    $players = self::getCollectionFromDB(
      'SELECT player_id, player_color, turn_order ' . 'FROM player ORDER BY turn_order'
    );
    $nextState = GT_StatesSetup::stPrepareFlight($this, $players);

    if (self::getGameStateValue('testGameState')) {
      $gt_state = new GT_TestGameState($this, $players);
      $gt_state->prepareFlight($nextState);
    } else {
      $this->gamestate->nextState($nextState);
    }
  }

  function stCheckNextCrew()
  {
    // In turn order, we check if a player still has a choice to do
    $nextPlayer = self::getUniqueValueFromDB(
      'SELECT player_id FROM player ' . 'WHERE alien_choice=1 ORDER BY turn_order LIMIT 1'
    );
    if ($nextPlayer) {
      $this->gamestate->changeActivePlayer($nextPlayer);
      $this->gamestate->nextState('nextCrew');
    } else {
      $this->gamestate->nextState('crewsDone');
    }
  }

  // ########### CARD-BASED STATES ##############
  function stDrawCard()
  {
    $nextState = GT_StatesCard::stDrawCard($this);
    $this->gamestate->nextState($nextState);
  }

  function stStardust()
  {
    $nextState = GT_StatesCard::stStardust($this);
    $this->gamestate->nextState($nextState);
  }

  function stOpenspace()
  {
    $nextState = GT_StatesCard::stOpenspace($this);
    $this->gamestate->nextState($nextState);
  }

  function stAbandoned()
  {
    $nextState = GT_StatesCard::stAbandoned($this);
    $this->gamestate->nextState($nextState);
  }

  function stPlanets()
  {
    $nextState = GT_StatesCard::stPlanets($this);
    $this->gamestate->nextState($nextState);
  }

  function stMeteoric()
  {
    $nextState = GT_StatesCard::stMeteoric($this);
    $this->gamestate->nextState($nextState);
  }

  function stEnemy()
  {
    $nextState = GT_StatesCard::stEnemy($this);
    $this->gamestate->nextState($nextState);
  }

  function stEnemyResults()
  {
    $player = GT_DBPlayer::getPlayerCardInProgress($this);
    $cardId = $this->getGameStateValue('currentCard');
    $card = $this->card[$cardId];
    $str = $this->getGameStateValue('cardArg1');

    $enemy = new GT_Enemy($this, $card, $player);
    $nextState = $enemy->fightPlayer($str);

    if (is_null($nextState)) {
      $nextState = 'nextPlayerEnemy';
      GT_DBPlayer::setCardDone($this, $player['player_id']);
      $this->setGameStateValue('cardArg1', -1);
    }
    $this->gamestate->nextState($nextState);
  }

  function stCannonBlasts()
  {
    $nextState = GT_StatesCard::stCannonBlasts($this);
    $this->gamestate->nextState($nextState);
  }

  function stShipDamage()
  {
    $plId = self::getActivePlayerId();
    GT_DBPlayer::setCardDone($this, $plId);
    $this->gamestate->nextState('notImpl');
  }

  // ########### FINAL CLEAN UP STATES ##############
  function stJourneysEnd()
  {
    // Rewards and penalties
    // TODO

    self::DbQuery('UPDATE player SET still_flying=0');

    // Is it the end of the game?
    $flight = self::getGameStateValue('flight');
    $flightVariant = self::getGameStateValue('flight_variants');
    $numberOfFlights = $this->flightVariant[$flightVariant]['nbOfFlights'];
    if ($flight == $numberOfFlights) {
      $this->gamestate->nextState('endGame');
    } else {
      self::incGameStateValue('flight', 1);
      $this->gamestate->nextState('nextRound');
    }
  }

  //////////////////////////////////////////////////////////////////////////////
  //////////// Zombie
  ////////////

  /*
        zombieTurn:

        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
    */

  function zombieTurn($state, $active_player)
  {
    $statename = $state['name'];

    if ($state['type'] == 'activeplayer') {
      switch ($statename) {
        default:
          $this->gamestate->nextState('zombiePass');
          break;
      }

      return;
    }

    if ($state['type'] == 'multipleactiveplayer') {
      // Make sure player is in a non blocking status for role turn
      $sql = "
                UPDATE  player
                SET     player_is_multiactive = 0
                WHERE   player_id = $active_player
            ";
      self::DQuery($sql);

      $this->gamestate->updateMultiactiveOrNextState('');
      return;
    }

    throw new feException('Zombie mode not supported at this game state: ' . $statename);
  }

  ///////////////////////////////////////////////////////////////////////////////////:
  ////////// DB upgrade
  //////////

  /*
        upgradeTableDb:

        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.

    */

  function upgradeTableDb($from_version)
  {
    // $from_version is the current version of this game database, in numerical form.
    // For example, if the game was running with a release of your game named "140430-1345",
    // $from_version is equal to 1404301345

    // Example:
    //        if( $from_version <= 1404301345 )
    //        {
    //            $sql = "ALTER TABLE xxxxxxx ....";
    //            self::DbQuery( $sql );
    //        }
    //        if( $from_version <= 1405061421 )
    //        {
    //            $sql = "CREATE TABLE xxxxxxx ....";
    //            self::DbQuery( $sql );
    //        }
    //        // Please add your future database scheme changes here
    //
    //
  }
}
