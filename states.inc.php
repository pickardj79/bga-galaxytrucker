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
 * states.inc.php
 *
 * GalaxyTrucker game states description
 *
 */

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: self::checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

//    !! It is not a good idea to modify this file when a game is running !!

// define contants for state ids
if (!defined('STATE_END_GAME')) {
  // ensure this block is only invoked once, since it is included multiple times
  define('STATE_PREPARE_ROUND', 2);
  define('STATE_WAIT_FOR_PLAYERS', 3);
  define('STATE_ACTIVATE_FOR_BUILD', 4);
  define('STATE_BUILD', 5);
  define('STATE_TAKE_ORDER_TILES', 10);
  define('STATE_REPAIR_SHIPS', 15);
  define('STATE_PREPARE_FLIGHT', 20);
  define('STATE_PLACE_CREW', 25);
  define('STATE_CHECK_NEXT_CREW', 26);
  define('STATE_DRAW_CARD', 30);
  define('STATE_NOT_IMPL', 35);
  // Cards
  define('STATE_STARDUST', 40);
  define('STATE_OPEN_SPACE', 41);
  define('STATE_ABANDONED', 42);
  define('STATE_METEORIC', 43);
  define('STATE_ENEMY', 44); // Loop over players, determine if powering cannons might matter
  define('STATE_ENEMY_RESULTS', 45); // Cannons chosen, apply results
  define('STATE_PLANETS', 49);
  define('STATE_CANNON_BLASTS', 51); // damage from enemy or combat
  define('STATE_EXPLORE_ABANDONED', 52);
  define('STATE_POWER_ENGINES', 60);
  define('STATE_POWER_SHIELDS', 61);
  define('STATE_POWER_CANNONS', 62);
  define('STATE_CHOOSE_PLANET', 63);
  define('STATE_CHOOSE_CREW', 66);
  define('STATE_PLACE_GOODS', 67);
  define('STATE_LOSE_GOODS', 68);
  define('STATE_LOSE_CELLS', 69);
  define('STATE_SHIP_DAMAGE', 70);
  define('STATE_JOURNEYS_END', 80);
  define('STATE_END_GAME', 99);
}

$machinestates = [
  // The initial state. Please do not modify.
  1 => [
    'name' => 'gameSetup',
    'description' => '',
    'type' => 'manager',
    'action' => 'stGameSetup',
    'transitions' => ['' => STATE_PREPARE_ROUND],
  ],

  STATE_PREPARE_ROUND => [
    'name' => 'prepareRound',
    'description' => '',
    'type' => 'game',
    'action' => 'stPrepareRound',
    'updateGameProgression' => false,
    'transitions' => [
      'waitForPlayers' => STATE_WAIT_FOR_PLAYERS,
      'buildPhase' => STATE_BUILD,
    ],
  ],

  STATE_WAIT_FOR_PLAYERS => [
    'name' => 'waitForPlayers',
    'description' => clienttranslate('Some players are not ready yet'),
    'descriptionmyturn' => clienttranslate(
      'Are you ready? (Do you see the starting cabin in the middle of your ship?)'
    ),
    'type' => 'multipleactiveplayer',
    'possibleactions' => ['ImReady', 'pass'],
    'transitions' => ['readyGo' => STATE_ACTIVATE_FOR_BUILD],
  ],

  STATE_ACTIVATE_FOR_BUILD => [
    'name' => 'activatePlayersForBuildPhase',
    'description' => '',
    'type' => 'game',
    'action' => 'stActivatePlayersForBuildPhase',
    'updateGameProgression' => false,
    'transitions' => ['' => STATE_BUILD],
  ],

  STATE_BUILD => [
    'name' => 'buildPhase',
    'description' => clienttranslate('Some players must finish their ship (you can still flip the timer)'),
    'descriptionmyturn' => clienttranslate('Build your ship!'),
    'type' => 'multipleactiveplayer',
    'action' => 'stBuildPhase',
    'possibleactions' => [
      'pickTile',
      'dropTile',
      'pickRevealed',
      'placeTile',
      'lookCards',
      'finishShip',
      'pickAside',
      'pickLastPlaced',
      'flipTimer',
      'timeFinished',
      'pass',
    ],
    //                                        "timeFinished", "pass" ),
    'transitions' => [
      'timeFinished' => STATE_TAKE_ORDER_TILES,
      'shipsDone' => STATE_REPAIR_SHIPS,
    ],
  ],

  STATE_TAKE_ORDER_TILES => [
    'name' => 'takeOrderTiles',
    'description' => clienttranslate('Some players must yet take an order tile'),
    'descriptionmyturn' => clienttranslate('Take an order tile!'),
    'type' => 'multipleactiveplayer',
    'action' => 'stTakeOrderTiles',
    'possibleactions' => ['finishShip', 'pass'],
    'transitions' => ['shipsDone' => STATE_REPAIR_SHIPS],
  ],

  STATE_REPAIR_SHIPS => [
    'name' => 'repairShips',
    'description' => clienttranslate('Some players must yet fix their ship'),
    // "descriptionmyturn" => clienttranslate('${you} must remove components in order to fix your ship'),
    'descriptionmyturn' => clienttranslate('Repairs not implemented yet. Click "Validate repairs"'),
    'type' => 'multipleactiveplayer',
    'action' => 'stRepairShips',
    'possibleactions' => [
      'removeTile',
      'finishRepairs', // "undoChanges",?
      'pass',
    ],
    'transitions' => ['repairsDone' => STATE_PREPARE_FLIGHT],
  ],

  STATE_PREPARE_FLIGHT => [
    'name' => 'prepareFlight',
    'description' => clienttranslate('Embarking crew and batteries'),
    'type' => 'game',
    'action' => 'stPrepareFlight',
    'updateGameProgression' => true,
    'transitions' => [
      'nextCrew' => STATE_PLACE_CREW,
      'crewsDone' => STATE_DRAW_CARD,
    ],
  ],

  STATE_PLACE_CREW => [
    'name' => 'placeCrew',
    'description' => clienttranslate('${actplayer} must place aliens'),
    'descriptionmyturn' => clienttranslate('${you} must select an alien or humans in all relevant cabins'),
    'type' => 'activeplayer',
    'possibleactions' => [
      // "placePurple", "placeBrown",
      'crewPlacementDone',
      'pass',
      'tempTestNextRound',
    ],
    'transitions' => ['crewPlacementDone' => STATE_CHECK_NEXT_CREW],
  ],

  STATE_CHECK_NEXT_CREW => [
    'name' => 'checkNextCrew',
    'description' => '',
    'type' => 'game',
    'action' => 'stCheckNextCrew',
    'updateGameProgression' => false,
    'transitions' => [
      'nextCrew' => STATE_PLACE_CREW,
      'crewsDone' => STATE_DRAW_CARD,
    ],
  ],

  STATE_DRAW_CARD => [
    'name' => 'drawCard',
    'description' => '',
    'type' => 'game',
    'action' => 'stDrawCard',
    'updateGameProgression' => true,
    'transitions' => [
      CARD_STARDUST => STATE_STARDUST,
      CARD_OPEN_SPACE => STATE_OPEN_SPACE,
      CARD_ABANDONED_SHIP => STATE_ABANDONED,
      CARD_ABANDONED_STATION => STATE_ABANDONED,
      CARD_PLANETS => STATE_PLANETS,
      CARD_SLAVERS => STATE_ENEMY,
      CARD_PIRATES => STATE_ENEMY,
      CARD_SMUGGLERS => STATE_ENEMY,
      CARD_METEORIC_SWARM => STATE_METEORIC,
      CARD_COMBAT_ZONE => STATE_NOT_IMPL,
      CARD_EPIDEMIC => STATE_NOT_IMPL,
      CARD_SABOTAGE => STATE_NOT_IMPL,
      NO_CARD => STATE_JOURNEYS_END,
    ],
  ],

  STATE_NOT_IMPL => [
    'name' => 'notImpl',
    'description' => clienttranslate('Not implemented yet. ${actplayer} must click the "Go on" button'),
    'descriptionmyturn' => clienttranslate('Not implemented yet. ${you} must click the "Go on" button'),
    'type' => 'activeplayer',
    'possibleactions' => ['goOn', 'pass'],
    'transitions' => [
      'nextMeteor' => STATE_METEORIC,
      'nextCard' => STATE_DRAW_CARD,
    ],
  ],

  STATE_STARDUST => [
    'name' => 'stardust',
    'description' => '',
    'type' => 'game',
    'action' => 'stStardust',
    'updateGameProgression' => false,
    'transitions' => ['nextCard' => STATE_DRAW_CARD],
  ],

  STATE_OPEN_SPACE => [
    'name' => 'openspace',
    'description' => '',
    'type' => 'game',
    'action' => 'stOpenspace',
    'updateGameProgression' => false,
    'transitions' => [
      'nextCard' => STATE_DRAW_CARD,
      'powerEngines' => STATE_POWER_ENGINES,
    ],
  ],

  STATE_ABANDONED => [
    'name' => 'abandoned',
    'description' => '',
    'type' => 'game',
    'action' => 'stAbandoned',
    'updateGameProgression' => false,
    'transitions' => [
      'nextCard' => STATE_DRAW_CARD,
      'exploreAbandoned' => STATE_EXPLORE_ABANDONED,
    ],
  ],

  STATE_METEORIC => [
    'name' => 'meteoric',
    'description' => '',
    'type' => 'game',
    'action' => 'stMeteoric',
    'updateGameProgression' => false,
    'transitions' => [
      'nextCard' => STATE_DRAW_CARD,
      'shipDamage' => STATE_SHIP_DAMAGE,
      'powerShields' => STATE_POWER_SHIELDS,
      'powerCannons' => STATE_POWER_CANNONS,
    ],
  ],

  STATE_ENEMY => [
    'name' => 'enemy',
    'description' => '',
    'type' => 'game',
    'action' => 'stEnemy',
    'updateGameProgression' => false,
    'transitions' => [
      // "nextCard" => STATE_DRAW_CARD,
      'nextCard' => STATE_NOT_IMPL,
      'powerCannons' => STATE_POWER_CANNONS,
      'enemyResults' => STATE_ENEMY_RESULTS,
    ],
  ],

  STATE_ENEMY_RESULTS => [
    'name' => 'enemyResults',
    'description' => '',
    'type' => 'game',
    'action' => 'stEnemyResults',
    'updateGameProgression' => false,
    'transitions' => [
      'shipDamage' => STATE_SHIP_DAMAGE,
      'chooseCrew' => STATE_CHOOSE_CREW,
      'loseGoods' => STATE_LOSE_GOODS,
      'loseCells' => STATE_LOSE_CELLS,
      'cannonBlasts' => STATE_CANNON_BLASTS,
      'placeGoods' => STATE_PLACE_GOODS,
      'nextPlayerEnemy' => STATE_ENEMY,
    ],
  ],

  STATE_CANNON_BLASTS => [
    'name' => 'cannonBlasts',
    'description' => '',
    'type' => 'game',
    'action' => 'stCannonBlasts',
    'updateGameProgression' => false,
    'transitions' => [
      'shipDamage' => STATE_SHIP_DAMAGE,
      'powerShields' => STATE_POWER_SHIELDS,
      'nextPlayerEnemy' => STATE_ENEMY,
    ],
  ],

  STATE_PLANETS => [
    // Select player for choosePlanet
    'name' => 'planet',
    'description' => '',
    'type' => 'game',
    'action' => 'stPlanets',
    'updateGameProgression' => false,
    'transitions' => [
      'nextCard' => STATE_DRAW_CARD,
      'choosePlanet' => STATE_CHOOSE_PLANET,
    ],
  ],

  STATE_EXPLORE_ABANDONED => [
    'name' => 'exploreAbandoned',
    'description' => clienttranslate('${actplayer} must decide whether to explore this derelict'),
    'descriptionmyturn' => clienttranslate('${you} must decide whether to explore this derelict'),
    'type' => 'activeplayer',
    'args' => 'argExploreAbandoned',
    'possibleactions' => ['exploreChoice'],
    'transitions' => [
      'nextPlayer' => STATE_ABANDONED,
      'chooseCrew' => STATE_CHOOSE_CREW,
      'nextCard' => STATE_DRAW_CARD,
      'placeGoods' => STATE_PLACE_GOODS,
    ],
  ],

  STATE_POWER_ENGINES => [
    'name' => 'powerEngines',
    'description' => clienttranslate('${actplayer} must choose batteries to use to power engines'),
    'descriptionmyturn' => clienttranslate('${you} must choose batteries to use to power engines'),
    'type' => 'activeplayer',
    'args' => 'argPowerEngines',
    'possibleactions' => ['contentChoice'],
    'transitions' => ['nextPlayer' => STATE_OPEN_SPACE],
  ],

  STATE_POWER_SHIELDS => [
    'name' => 'powerShields',
    'description' => clienttranslate('${actplayer} must choose a battery for activating a shield'),
    'descriptionmyturn' => clienttranslate('${you} must choose a battery for activating a shield'),
    'type' => 'activeplayer',
    'args' => 'argPowerShields',
    'possibleactions' => ['contentChoice'],
    'transitions' => [
      'nextMeteor' => STATE_METEORIC,
      'nextCannon' => STATE_CANNON_BLASTS,
    ],
  ],

  STATE_POWER_CANNONS => [
    'name' => 'powerCannons',
    'description' => clienttranslate('${actplayer} must choose a battery for activating cannons'),
    'descriptionmyturn' => clienttranslate('${you} must choose a battery for activating cannons'),
    'type' => 'activeplayer',
    'args' => 'argPowerCannons',
    'possibleactions' => ['contentChoice'],
    'transitions' => [
      'nextMeteor' => STATE_METEORIC,
      'enemyResults' => STATE_ENEMY_RESULTS,
    ],
  ],

  STATE_CHOOSE_PLANET => [
    'name' => 'choosePlanet',
    'description' => clienttranslate('${actplayer} must decide on which planet to land'),
    'descriptionmyturn' => clienttranslate('${you} must decide on which planet to land'),
    'type' => 'activeplayer',
    'args' => 'argChoosePlanet',
    'possibleactions' => ['planetChoice'],
    'transitions' => [
      'nextPlayer' => STATE_PLANETS,
      'placeGoods' => STATE_PLACE_GOODS,
    ],
  ],

  STATE_CHOOSE_CREW => [
    'name' => 'chooseCrew',
    'description' => clienttranslate('${actplayer} must decide which crew to lose'),
    'descriptionmyturn' => clienttranslate('${you} must decide which crew to lose'),
    'type' => 'activeplayer',
    'args' => 'argChooseCrew',
    'possibleactions' => ['contentChoice'],
    'transitions' => [
      'nextCard' => STATE_DRAW_CARD,
      'nextPlayerEnemy' => STATE_ENEMY,
    ],
  ],

  STATE_PLACE_GOODS => [
    'name' => 'placeGoods',
    'description' => clienttranslate('${actplayer} may reorganize their goods'),
    'descriptionmyturn' => clienttranslate('${you} may reorganize your goods'),
    'type' => 'activeplayer',
    'args' => 'argPlaceGoods',
    'possibleactions' => ['cargoChoice'],
    'transitions' => [
      'nextCard' => STATE_DRAW_CARD,
      'cargoChoicePlanet' => STATE_PLANETS,
    ],
  ],

  STATE_LOSE_GOODS => [
    'name' => 'loseGoods',
    'description' => clienttranslate('${actplayer} must decide which goods to lose'),
    'descriptionmyturn' => clienttranslate('${you} must decide which goods to lose'),
    'type' => 'activeplayer',
    'args' => 'argLoseGoods',
    'possibleactions' => ['contentChoice'],
    'transitions' => ['nextPlayerEnemy' => STATE_ENEMY],
  ],

  STATE_LOSE_CELLS => [
    'name' => 'loseCells',
    'description' => clienttranslate('${actplayer} must decide which cells to lose'),
    'descriptionmyturn' => clienttranslate('${you} must decide which cells to lose'),
    'type' => 'activeplayer',
    'args' => 'argLoseCells',
    'possibleactions' => ['contentChoice'],
    'transitions' => ['nextPlayerEnemy' => STATE_ENEMY],
  ],

  // TODO: DO WE NEED THIS STATE? (CLEAN UP galaxytrucker.js too)
  STATE_SHIP_DAMAGE => [
    'name' => 'shipDamage',
    'description' => '',
    'type' => 'game',
    'action' => 'stShipDamage',
    'updateGameProgression' => false,
    'transitions' => [
      'notImpl' => STATE_NOT_IMPL,
    ],
  ],

  STATE_JOURNEYS_END => [
    'name' => 'journeysEnd',
    'description' => '',
    'type' => 'game',
    'action' => 'stJourneysEnd',
    'updateGameProgression' => false,
    'transitions' => ['nextRound' => 2, 'endGame' => 99],
  ],

  // Final state.
  // Please do not modify.
  99 => [
    'name' => 'gameEnd',
    'description' => clienttranslate('End of game'),
    'type' => 'manager',
    'action' => 'stGameEnd',
    'args' => 'argGameEnd',
  ],
];
