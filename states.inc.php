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
if (!defined('STATE_END_GAME')) { // ensure this block is only invoked once, since it is included multiple times
    define("STATE_PREPARE_ROUND", 2);
    define("STATE_WAIT_FOR_PLAYERS", 3);
    define("STATE_ACTIVATE_FOR_BUILD", 4);
    define("STATE_BUILD", 5);
    define("STATE_TAKE_ORDER_TILES", 10);
    define("STATE_REPAIR_SHIPS", 15);
    define("STATE_PREPARE_FLIGHT", 20);
    define("STATE_PLACE_CREW", 25);
    define("STATE_CHECK_NEXT_CREW", 26);
    define("STATE_DRAW_CARD", 30);
    define("STATE_NOT_IMPL", 35);
    // Cards
    define("STATE_STARDUST", 40);
    define("STATE_OPEN_SPACE", 41);
    define("STATE_ABANDONED", 42);
    define("STATE_PLANETS", 46);
    define("STATE_EXPLORE_ABANDONED", 58);
    define("STATE_POWER_ENGINES", 60);
    define("STATE_CHOOSE_PLANET", 61);
    define("STATE_CHOOSE_CREW", 66);
    define("STATE_PLACE_GOODS", 68);
    define("STATE_JOURNEYS_END", 80);
    define("STATE_END_GAME", 99);
 }

$machinestates = array(

    // The initial state. Please do not modify.
    1 => array(
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => array( "" => STATE_PREPARE_ROUND)
    ),

    STATE_PREPARE_ROUND => array(
        "name" => "prepareRound",
        "description" => '',
        "type" => "game",
        "action" => "stPrepareRound",
        "updateGameProgression" => false,
        "transitions" => array( 
            "waitForPlayers" => STATE_WAIT_FOR_PLAYERS, 
            "buildPhase" => STATE_BUILD )
    ),

    STATE_WAIT_FOR_PLAYERS => array(
        "name" => "waitForPlayers",
        "description" => clienttranslate("Some players are not ready yet"),
        "descriptionmyturn" => clienttranslate("Are you ready? (Do you see the starting cabin in the middle of your ship?)"),
        "type" => "multipleactiveplayer",
        "possibleactions" => array( "ImReady", "pass" ),
        "transitions" => array( "readyGo" => STATE_ACTIVATE_FOR_BUILD )
    ),

    STATE_ACTIVATE_FOR_BUILD => array(
        "name" => "activatePlayersForBuildPhase",
        "description" => '',
        "type" => "game",
        "action" => "stActivatePlayersForBuildPhase",
        "updateGameProgression" => false,
        "transitions" => array( "" => STATE_BUILD )
    ),


    STATE_BUILD => array(
        "name" => "buildPhase",
        "description" => clienttranslate('Some players must finish their ship (you can still flip the timer)'),
        "descriptionmyturn" => clienttranslate('Build your ship!'),
        "type" => "multipleactiveplayer",
        "action" => "stBuildPhase",
        "possibleactions" => array( "pickTile", "dropTile",
                                        "pickRevealed", "placeTile",
                                        "lookCards", "finishShip",
                                        "pickAside", "pickLastPlaced",
                                        "flipTimer", "timeFinished", "pass" ),
//                                        "timeFinished", "pass" ),
        "transitions" => array( 
            "timeFinished" => STATE_TAKE_ORDER_TILES, 
            "shipsDone" => STATE_REPAIR_SHIPS )
    ),

    STATE_TAKE_ORDER_TILES => array(
        "name" => "takeOrderTiles",
        "description" => clienttranslate('Some players must yet take an order tile'),
        "descriptionmyturn" => clienttranslate('Take an order tile!'),
        "type" => "multipleactiveplayer",
        "action" => "stTakeOrderTiles",
        "possibleactions" => array( "finishShip", "pass" ),
        "transitions" => array( "shipsDone" => STATE_REPAIR_SHIPS )
    ),

    // May be removed
    // 14 => array(
    //     "name" => "checkShips",
    //     "description" => '',
    //     "type" => "game",
    //     "action" => "stCheckShips",
    //     "updateGameProgression" => true,
    //     "transitions" => array( "shipsNotOk" => 15, "shipsOk" => 20 )
    // ),

    STATE_REPAIR_SHIPS => array(
        "name" => "repairShips",
        "description" => clienttranslate('Some players must yet fix their ship'),
        // "descriptionmyturn" => clienttranslate('${you} must remove components in order to fix your ship'),
        "descriptionmyturn" => clienttranslate('Repairs not implemented yet. Click "Validate repairs"'),
        "type" => "multipleactiveplayer",
        "action" => "stRepairShips",
        "possibleactions" => array( "removeTile", "finishRepairs", // "undoChanges",?
                                "pass" ),
        "transitions" => array( "repairsDone" => STATE_PREPARE_FLIGHT )
    ),

    STATE_PREPARE_FLIGHT => array(
        "name" => "prepareFlight",
        "description" => clienttranslate('Embarking crew and batteries'),
        "type" => "game",
        "action" => "stPrepareFlight",
        "updateGameProgression" => true,
        "transitions" => array(
            "pauseTest" => STATE_NOT_IMPL,
            "nextCrew" => STATE_PLACE_CREW,
            "crewsDone" => STATE_DRAW_CARD )
    ),

    STATE_PLACE_CREW => array(
        "name" => "placeCrew",
        "description" => clienttranslate('${actplayer} must place aliens'),
        "descriptionmyturn" => clienttranslate('${you} must select an alien or humans in all relevant cabins'),
        "type" => "activeplayer",
        "possibleactions" => array( // "placePurple", "placeBrown",
                                "crewPlacementDone", "pass", "tempTestNextRound" ),
        "transitions" => array( "crewPlacementDone" => STATE_CHECK_NEXT_CREW )
    ),

    STATE_CHECK_NEXT_CREW => array(
        "name" => "checkNextCrew",
        "description" => '',
        "type" => "game",
        "action" => "stCheckNextCrew",
        "updateGameProgression" => false,
        "transitions" => array( 
            "nextCrew" => STATE_PLACE_CREW, 
            "crewsDone" => STATE_DRAW_CARD )
    ),

    STATE_DRAW_CARD => array(
        "name" => "drawCard",
        "description" => '',
        "type" => "game",
        "action" => "stDrawCard",
        "updateGameProgression" => true,
        "transitions" => array( 
            "stardust" => STATE_STARDUST, 
            "openspace" => STATE_OPEN_SPACE,
            "abandoned" => STATE_ABANDONED,
            "planets" => STATE_CHOOSE_PLANET, 

            "enemies" => STATE_NOT_IMPL, 
            "meteoric" => STATE_NOT_IMPL, 
            "combatzone" => STATE_NOT_IMPL, 
            "epidemic" => STATE_NOT_IMPL, 
            "sabotage" => STATE_NOT_IMPL, 

            "cardsDone" => STATE_JOURNEYS_END )
    ),

    STATE_NOT_IMPL => array(
        "name" => "notImpl",
        "description" => clienttranslate('Not implemented yet. ${actplayer} must click the "Go on" button'),
        "descriptionmyturn" => clienttranslate('Not implemented yet. ${you} must click the "Go on" button'),
        "type" => "activeplayer",
        "possibleactions" => array( "goOn", "pass" ),
        "transitions" => array( "goOn" => STATE_DRAW_CARD )
    ),

    STATE_STARDUST => array(
        "name" => "stardust",
        "description" => '',
        "type" => "game",
        "action" => "stStardust",
        "updateGameProgression" => false,
        "transitions" => array( "nextCard" => STATE_DRAW_CARD )
    ),

    STATE_OPEN_SPACE => array(
        "name" => "openspace",
        "description" => '',
        "type" => "game",
        "action" => "stOpenspace",
        "updateGameProgression" => false,
        "transitions" => array(
            "nextCard" => STATE_DRAW_CARD,
            "powerEngines" => STATE_POWER_ENGINES )
    ),

    STATE_ABANDONED => array(
        "name" => "abandoned",
        "description" => '',
        "type" => "game",
        "action" => "stAbandoned",
        "updateGameProgression" => false,
        "transitions" => array( 
            "nextCard" => STATE_DRAW_CARD,
            "exploreAbandoned" => STATE_EXPLORE_ABANDONED )
    ),

    STATE_PLANETS => array(
        // Select player for choosePlanet
        "name" => "planet",
        "description" => '',
        "type" => "game",
        "action" => "stPlanets",
        "updateGameProgression" => false,
        "transitions" => array( 
            "nextCard" => STATE_DRAW_CARD,
            "choosePlanet" => STATE_CHOOSE_PLANET)
    ),


    STATE_EXPLORE_ABANDONED => array(
        "name" => "exploreAbandoned",
        "description" => clienttranslate('${actplayer} must decide whether to explore this derelict'),
        "descriptionmyturn" => clienttranslate('${you} must decide whether to explore this derelict'),
        "type" => "activeplayer",
        "args" => "argExploreAbandoned",
        "possibleactions" => array( "exploreChoice" ),
        "transitions" => array( 
            "nextPlayer" => STATE_ABANDONED,
            "chooseCrew" => STATE_CHOOSE_CREW,
            "nextCard"   => STATE_DRAW_CARD,
            "placeGoods" => STATE_PLACE_GOODS,  )
    ),

    STATE_POWER_ENGINES => array(
        "name" => "powerEngines",
        "description" => clienttranslate('${actplayer} must choose batteries to use'),
        "descriptionmyturn" => clienttranslate('${you} must choose batteries to use'),
        "type" => "activeplayer",
        "args" => "argPowerEngines",
        "possibleactions" => array( "contentChoice" ),
        "transitions" => array( "nextPlayer" => STATE_OPEN_SPACE ) // or "enginesPowered"?
    ),

    STATE_CHOOSE_PLANET => array(
        "name" => "choosePlanet",
        "description" => clienttranslate('${actplayer} must decide on which planet to land'),
        "descriptionmyturn" => clienttranslate('${you} must decide on which planet to land'),
        "type" => "activeplayer",
        "args" => "argChoosePlanet",
        "possibleactions" => array( "planetChoice" ),
        "transitions" => array(
            "nextPlayer" => STATE_PLANETS,
            "placeGoods" => STATE_PLACE_GOODS )
    ),

    STATE_CHOOSE_CREW => array(
        "name" => "chooseCrew",
        "description" => clienttranslate('${actplayer} must decide which crew to lose'),
        "descriptionmyturn" => clienttranslate('${you} must decide which crew to lose'),
        "type" => "activeplayer",
        "args" => "argChooseCrew",
        "possibleactions" => array( "contentChoice", "cancelExplore", "pass" ),
        "transitions" => array( 
            "nextCard" => STATE_DRAW_CARD,
            "nextPlayer" => STATE_ABANDONED )
    ),

    STATE_PLACE_GOODS => array(
        "name" => "placeGoods",
        "description" => clienttranslate('${actplayer} may reorganize their goods'),
        "descriptionmyturn" => clienttranslate('${you} may reorganize your goods'),
        "type" => "activeplayer",
        "args" => "argPlaceGoods",
        "possibleactions" => array( "cargoChoice" ),
        "transitions" => array( 
            "nextCard" => STATE_DRAW_CARD,
            "cargoChoicePlanet" => STATE_PLANETS)
    ),

//    40 => array(
//        "name" => "resolveCard",
//        "description" => '',
//        "type" => "game",
//        "action" => "stResolveCard",
//        "updateGameProgression" => false,
//        "transitions" => array( "nextPlayer" => 40, "cardResolved" => STATE_DRAW_CARD,
//                                "powerShield" => 41, "powerCannons" => 42,
//                                "powerEngines" => 43, "loseGoods" => 44,
//                                "loseCrews" => 45, "choosePlanet" => 46,
//                                "exploreAbandoned" => 47, "placeGoods" => 48,
//                                "takeReward" => 49  )
//    ),

//    41 => array(
//        "name" => "powerShield",
//        "description" => clienttranslate('${actplayer} must decide whether to activate a shield'),
//        "descriptionmyturn" => clienttranslate('${you} must decide whether to activate a shield'),
//        "type" => "activeplayer",
//        "possibleactions" => array( "choiceMade", "pass" ),
//        "transitions" => array( "choiceMade" => 40 )
//    ),

//    42 => array(
//        "name" => "powerCannons",
//        "description" => clienttranslate('${actplayer} must decide which cannons to activate'),
//        "descriptionmyturn" => clienttranslate('${you} must decide which cannons to activate'),
//        "type" => "activeplayer",
//        "possibleactions" => array( "powerCannon", "choiceMade",
//                                        "pass" ),
//        "transitions" => array( "choiceMade" => 40 )
//    ),

//    43 => array(
//        "name" => "powerEngines",
//        "description" => clienttranslate('${actplayer} must decide which engines to activate'),
//        "descriptionmyturn" => clienttranslate('${you} must decide which engines to activate'),
//        "type" => "activeplayer",
//        "possibleactions" => array( "powerEngine", "choiceMade",
//                                        "pass" ),
//        "transitions" => array( "choiceMade" => 40 )
//    ),

    44 => array(
        "name" => "loseGoods",
        "description" => clienttranslate('${actplayer} must decide which goods to lose'),
        "descriptionmyturn" => clienttranslate('${you} must decide which goods to lose'),
        "type" => "activeplayer",
        "possibleactions" => array( "loseGood", "choiceMade",
                                        "pass" ),
        "transitions" => array( "choiceMade" => 40 )
    ),

    

    49 => array(
        "name" => "takeReward",
        "description" => clienttranslate('${actplayer} must decide whether to collect a reward'),
        "descriptionmyturn" => clienttranslate('${you} must decide whether to collect a reward'),
        "type" => "activeplayer",
        "possibleactions" => array( "choiceMade", "pass" ),
        "transitions" => array( "choiceMade" => 40 )
    ),

    STATE_JOURNEYS_END => array(
        "name" => "journeysEnd",
        "description" => '',
        "type" => "game",
        "action" => "stJourneysEnd",
        "updateGameProgression" => false,
        "transitions" => array( "nextRound" => 2, "endGame" => 99 )
    ),

/*
    Examples:

    2 => array(
        "name" => "nextPlayer",
        "description" => '',
        "type" => "game",
        "action" => "stNextPlayer",
        "updateGameProgression" => true,
        "transitions" => array( "endGame" => 99, "nextPlayer" => 10 )
    ),

    10 => array(
        "name" => "playerTurn",
        "description" => clienttranslate('${actplayer} must play a card or pass'),
        "descriptionmyturn" => clienttranslate('${you} must play a card or pass'),
        "type" => "activeplayer",
        "possibleactions" => array( "playCard", "pass" ),
        "transitions" => array( "playCard" => 2, "pass" => 2 )
    ),

*/

    // Final state.
    // Please do not modify.
    99 => array(
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd"
    )

);


