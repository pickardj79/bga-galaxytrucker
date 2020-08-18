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

$machinestates = array(

    // The initial state. Please do not modify.
    1 => array(
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => array( "" => 2 )
    ),

    2 => array(
        "name" => "prepareRound",
        "description" => '',
        "type" => "game",
        "action" => "stPrepareRound",
        "updateGameProgression" => false,
        "transitions" => array( "waitForPlayers" => 3, "buildPhase" => 5 )
    ),

    3 => array(
        "name" => "waitForPlayers",
        "description" => clienttranslate("Some players are not ready yet"),
        "descriptionmyturn" => clienttranslate("Are you ready? (Do you see the starting cabin in the middle of your ship?)"),
        "type" => "multipleactiveplayer",
        "possibleactions" => array( "ImReady", "pass" ),
        "transitions" => array( "readyGo" => 4 )
    ),

    4 => array(
        "name" => "activatePlayersForBuildPhase",
        "description" => '',
        "type" => "game",
        "action" => "stActivatePlayersForBuildPhase",
        "updateGameProgression" => false,
        "transitions" => array( "" => 5 )
    ),


    5 => array(
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
        "transitions" => array( "timeFinished" => 10, "shipsDone" => 15 )
    ),

    10 => array(
        "name" => "takeOrderTiles",
        "description" => clienttranslate('Some players must yet take an order tile'),
        "descriptionmyturn" => clienttranslate('Take an order tile!'),
        "type" => "multipleactiveplayer",
        "action" => "stTakeOrderTiles",
        "possibleactions" => array( "finishShip", "pass" ),
        "transitions" => array( "shipsDone" => 15 )
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

    15 => array(
        "name" => "repairShips",
        "description" => clienttranslate('Some players must yet fix their ship'),
        // "descriptionmyturn" => clienttranslate('${you} must remove components in order to fix your ship'),
        "descriptionmyturn" => clienttranslate('Repairs not implemented yet. Click "Validate repairs"'),
        "type" => "multipleactiveplayer",
        "action" => "stRepairShips",
        "possibleactions" => array( "removeTile", "finishRepairs", // "undoChanges",?
                                "pass" ),
        "transitions" => array( "repairsDone" => 20 )
    ),

    20 => array(
        "name" => "prepareFlight",
        "description" => clienttranslate('Embarking crew and batteries'),
        "type" => "game",
        "action" => "stPrepareFlight",
        "updateGameProgression" => true,
        "transitions" => array( "nextCrew" => 25, "crewsDone" => 30 )
    ),

    25 => array(
        "name" => "placeCrew",
        "description" => clienttranslate('${actplayer} must place aliens'),
        "descriptionmyturn" => clienttranslate('${you} must select an alien or humans in all relevant cabins'),
        "type" => "activeplayer",
        "possibleactions" => array( // "placePurple", "placeBrown",
                                "crewPlacementDone", "pass", "tempTestNextRound" ),
        "transitions" => array( "crewPlacementDone" => 26 )
    ),

    26 => array(
        "name" => "checkNextCrew",
        "description" => '',
        "type" => "game",
        "action" => "stCheckNextCrew",
        "updateGameProgression" => false,
        "transitions" => array( "nextCrew" => 25, "crewsDone" => 30 )
    ),

    30 => array(
        "name" => "drawCard",
        "description" => '',
        "type" => "game",
        "action" => "stDrawCard",
        "updateGameProgression" => true,
        "transitions" => array( "enemies" => 35, "stardust" => 40, "openspace" => 41,
                    "meteoric" => 35, "planets" => 35, "combatzone" => 35, "abandoned" => 42,
                    "epidemic" => 35, "sabotage" => 35, "cardsDone" => 80 )
    ),

    35 => array(
        "name" => "notImpl",
        "description" => clienttranslate('Not implemented yet. ${actplayer} must click the "Go on" button'),
        "descriptionmyturn" => clienttranslate('Not implemented yet. ${you} must click the "Go on" button'),
        "type" => "activeplayer",
        "possibleactions" => array( "goOn", "pass" ),
        "transitions" => array( "goOn" => 30 )
    ),

    40 => array(
        "name" => "stardust",
        "description" => '',
        "type" => "game",
        "action" => "stStardust",
        "updateGameProgression" => false,
        "transitions" => array( "nextCard" => 30 )
    ),

    41 => array(
        "name" => "openspace",
        "description" => '',
        "type" => "game",
        "action" => "stOpenspace",
        "updateGameProgression" => false,
        "transitions" => array( "nextCard" => 30, "powerEngines" => 60 )
    ),

    42 => array(
        "name" => "abandoned",
        "description" => '',
        "type" => "game",
        "action" => "stAbandoned",
        "updateGameProgression" => false,
        "transitions" => array( "nextCard" => 30, "exploreAbandoned" => 58 )
    ),

    58 => array(
        "name" => "exploreAbandoned",
        "description" => clienttranslate('${actplayer} must decide whether to explore this derelict'),
        "descriptionmyturn" => clienttranslate('${you} must decide whether to explore this derelict'),
        "type" => "activeplayer",
        "args" => "argExploreAbandoned",
        "possibleactions" => array( "exploreChoice", "pass" ),
        "transitions" => array( "nextPlayer" => 42, "nextCard" => 30,
        //"chooseCrew" => 66, "placeGoods" => 68,  )
        "chooseCrew" => 66, "placeGoods" => 35,  )
    ),

    60 => array(
        "name" => "powerEngines",
        "description" => clienttranslate('${actplayer} must choose batteries to use'),
        "descriptionmyturn" => clienttranslate('${you} must choose batteries to use'),
        //"descriptionmyturn" => clienttranslate('${you} can use up to ${nbr} batteries'),
        "type" => "activeplayer",
        "args" => "argPowerEngines",
        "possibleactions" => array( "contentChoice", "pass" ),
        "transitions" => array( "battChosen" => 41 ) // or "enginesPowered"?
    ),

    66 => array(
        "name" => "chooseCrew",
        "description" => clienttranslate('${actplayer} must decide which crew to lose'),
        "descriptionmyturn" => clienttranslate('${you} must decide which crew to lose'),
        "type" => "activeplayer",
        "args" => "argChooseCrew",
        "possibleactions" => array( "contentChoice", "cancelExplore", "pass" ),
        "transitions" => array( "nextCard" => 30, "nextPlayer" => 42 )
    ),

    68 => array(
        "name" => "placeGoods",
        "description" => clienttranslate('${actplayer} may reorganize their goods'),
        "descriptionmyturn" => clienttranslate('${you} may reorganize your goods (not implemented yet)'),
        "type" => "activeplayer",
        "possibleactions" => array( "removeGood", "placeGood",
                                        "choiceMade", "pass" ),
        "transitions" => array( "choiceMade" => 42 )
    ),

//    40 => array(
//        "name" => "resolveCard",
//        "description" => '',
//        "type" => "game",
//        "action" => "stResolveCard",
//        "updateGameProgression" => false,
//        "transitions" => array( "nextPlayer" => 40, "cardResolved" => 30,
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

    46 => array(
        "name" => "choosePlanet",
        "description" => clienttranslate('${actplayer} must decide on which planet to land'),
        "descriptionmyturn" => clienttranslate('${you} must decide on which planet to land'),
        "type" => "activeplayer",
        "possibleactions" => array( "choiceMade", "pass", "tempTestNextRound" ),
        "transitions" => array( "choiceMade" => 40, "tempTestNextRound" => 50 )
    ),

    49 => array(
        "name" => "takeReward",
        "description" => clienttranslate('${actplayer} must decide whether to collect a reward'),
        "descriptionmyturn" => clienttranslate('${you} must decide whether to collect a reward'),
        "type" => "activeplayer",
        "possibleactions" => array( "choiceMade", "pass" ),
        "transitions" => array( "choiceMade" => 40 )
    ),

    80 => array(
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


