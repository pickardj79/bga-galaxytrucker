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
 * galaxytrucker.action.php
 *
 * GalaxyTrucker main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/galaxytrucker/galaxytrucker/myAction.html", ...)
 *
 */


  class action_galaxytrucker extends APP_GameAction
  {
    // Constructor: please do not modify
   	public function __default()
  	{
  	    if( self::isArg( 'notifwindow') )
  	    {
            $this->view = "common_notifwindow";
  	        $this->viewArgs['table'] = self::getArg( "table", AT_posint, true );
  	    }
  	    else
  	    {
            $this->view = "galaxytrucker_galaxytrucker";
            self::trace( "Complete reinitialization of board game" );
      }
  	}

  	// TODO: defines your action entry points there

  public function ImReady() {
      self::setAjaxMode();
      $this->game->ImReady( );
      self::ajaxResponse( );
  }

  public function pickTile() {
      self::setAjaxMode();
      $this->game->pickTile( );
      self::ajaxResponse( );
  }

  public function pickRevealed() {
      self::setAjaxMode();
      $tile_id = self::getArg( "tile_id", AT_posint, true );
      $this->game->pickRevealed( $tile_id );
      self::ajaxResponse( );
  }

  public function pickAside() {
      self::setAjaxMode();
      $tile_id = self::getArg( "tile_id", AT_posint, true );
      $this->game->pickAside( $tile_id );
      self::ajaxResponse( );
  }

  public function pickLastPlaced() {
      self::setAjaxMode();
      $tile_id = self::getArg( "tile_id", AT_posint, true );
      $this->game->pickLastPlaced( $tile_id );
      self::ajaxResponse( );
  }

  public function dropTile() {
      self::setAjaxMode();
      $tile_id = self::getArg( "tile_id", AT_posint, true );
      $this->game->dropTile( $tile_id );
      self::ajaxResponse( );
  }

  public function placeTile() {
      self::setAjaxMode();
      $component_id = self::getArg( "component_id", AT_posint, true );
      $x = self::getArg( "x", AT_int, true );
      $y = self::getArg( "y", AT_int, true );
      $o = self::getArg( "o", AT_posint, true );
      $discard = self::getArg( "discard", AT_posint, true );
      $this->game->placeTile( $component_id, $x, $y, $o, $discard );
      self::ajaxResponse( );
  }

  public function flipTimer() {
      self::setAjaxMode();
      $timerPlace = self::getArg( "timerPlace", AT_posint, true );
      $this->game->flipTimer( $timerPlace );
      self::ajaxResponse( );
  }

  public function finishShip() {
      self::setAjaxMode();
      $orderTile = self::getArg( "orderTile", AT_posint, true );
      $this->game->finishShip( $orderTile );
      self::ajaxResponse( );
  }

  public function timeFinished() {
      self::setAjaxMode();
      $this->game->timeFinished( );
      self::ajaxResponse( );
  }

  public function finishRepairs() {
      self::setAjaxMode();
      $this->game->finishRepairs( );
      self::ajaxResponse( );
  }

  public function crewPlacementDone() {
      self::setAjaxMode();
//      $alienChoices = array();
//      $nbAliens = self::getArg( "nbAliens", AT_posint, true );
//      while ( $nbAliens > 0 ) {
//          $tileId = self::getArg( "tile".$nbAliens, AT_posint, true );;
//          $alienChoices[ $tileId ] = self::getArg( "alColor".$nbAliens, AT_alphanum, true );
//          $nbAliens--;
//      }
      $alChoiceRaw = self::getArg( "alienChoices", AT_numberlist, true );
      if ( $alChoiceRaw === "" ) {
          $alienChoices = array();
      }
      else {
          $alienChoices = explode( ',', $alChoiceRaw );
      }
      $this->game->crewPlacementDone( $alienChoices );
      self::ajaxResponse( );
  }

  public function contentChoice() {
      self::setAjaxMode();
      $contChoiceRaw = self::getArg( "contList", AT_numberlist, true );
      if ( $contChoiceRaw === "" ) {
          $contChoices = array();
      }
      else {
          $contChoices = explode( ',', $contChoiceRaw );
//          $contChoiceSplit = explode( ';', $contChoiceRaw );
//          foreach ( $contChoiceSplit as $contStr ) {
//              $cont = explode ( ',', $contStr );
//              $contChoices[] = array( 'tile_id' => $cont[0],
//                                    'place' => $cont[1] );
//          }
      }
      switch ( self::getArg( "actionName", AT_alphanum, true ) ) {
        case 'battChoice':
          $this->game->battChoice( $contChoices );
          break;
        case 'crewChoice':
          $this->game->crewChoice( $contChoices );
          break;
      }
      self::ajaxResponse( );
  }

  public function exploreChoice() {
      self::setAjaxMode();
      $choice = self::getArg( "explChoice", AT_posint, true );
      $this->game->exploreChoice( $choice );
      self::ajaxResponse( );
  }

  public function cancelExplore() {
      self::setAjaxMode();
      $this->game->cancelExplore( );
      self::ajaxResponse( );
  }

  public function planetChoice() {
      self::setAjaxMode();
      $choice = self::getArg("idx", AT_posint, false);
      $this->game->planetChoice($choice);
      self::ajaxResponse( );
  }

  public function cargoChoice() {
      self::setAjaxMode();
      $encoded = self::getArg("goodsOnTile", AT_base64, true);
      $decoded = (array)json_decode(base64_decode($encoded));
      $this->game->chooseCargo($decoded);
      self::ajaxResponse( );
  }

  public function goOn() {
      self::setAjaxMode();
      $this->game->goOn( );
      self::ajaxResponse( );
  }

  public function tempTestNextRound() {
      self::setAjaxMode();
      $this->game->tempTestNextRound( );
      self::ajaxResponse( );
  }

    /*

    Example:

    public function myAction()
    {
        self::setAjaxMode();

        // Retrieve arguments
        // Note: these arguments correspond to what has been sent through the javascript "ajaxcall" method
        $arg1 = self::getArg( "myArgument1", AT_posint, true );
        $arg2 = self::getArg( "myArgument2", AT_posint, true );

        // Then, call the appropriate method in your game logic, like "playCard" or "myAction"
        $this->game->myAction( $arg1, $arg2 );

        self::ajaxResponse( );
    }

    */

  }


