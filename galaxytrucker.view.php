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
 * galaxytrucker.view.php
 *
 * This is your "view" file.
 *
 * The method "build_page" below is called each time the game interface is displayed to a player, ie:
 * _ when the game starts
 * _ when a player refreshes the game page (F5)
 *
 * "build_page" method allows you to dynamically modify the HTML generated for the game interface. In
 * particular, you can set here the values of variables elements defined in galaxytrucker_galaxytrucker.tpl (elements
 * like {MY_VARIABLE_ELEMENT}), and insert HTML block elements (also defined in your HTML template file)
 *
 * Note: if the HTML of your game interface is always the same, you don't have to place anything here.
 *
 */
  
  require_once( APP_BASE_PATH."view/common/game.view.php" );
  
  class view_galaxytrucker_galaxytrucker extends game_view
  {
    function getGameName() {
        return "galaxytrucker";
    }    
  	function build_page( $viewArgs )
  	{		
  	    // Get players & players number
        $players = $this->game->loadPlayersBasicInfos();
        $players_nbr = count( $players );
	global $g_user;
	$current_player_id = $g_user->get_id();

	/*********** Place your code below:  ************/

        $this->tpl['MY_SHIP'] = self::_("My ship");
        $this->tpl['CARDS'] = self::_("Round cards, timer and order tiles");
        $this->tpl['PILE'] = self::_("Components pile");
        //$this->tpl['FLIGHT'] = self::_("Flight board");
        $this->tpl['BUILD_MESSAGE'] = self::_("You can pick and place components, look at ".
                "some cards, flip the timer if it is finished, or take an order tile (usually ".
                "the smallest available) to finish your ship.");
        

        $this->page->begin_block( "galaxytrucker_galaxytrucker", "flight_pos_row2" );
	for( $i=6 ; $i >= -13 ; $i-- )
	{
	  if ( $i>=0 ) $space = $i;
	  else $space = $i+40;
	  $this->page->insert_block( "flight_pos_row2", array( "I" => $space ) );
	}
        $this->page->begin_block( "galaxytrucker_galaxytrucker", "flight_pos_row1" );
	for( $i=7 ; $i <= 26 ; $i++ )
	{
	  $this->page->insert_block( "flight_pos_row1", array( "I" => $i ) );
	}

        $this->page->begin_block( "galaxytrucker_galaxytrucker", "order_tile_slot" );
	for( $i=1 ; $i <= $players_nbr ; $i++ )
	  $this->page->insert_block( "order_tile_slot", array( 
							"I" => $i,
							 ) );

//        $this->page->begin_block( "galaxytrucker_galaxytrucker", "cards_reveal" );
//	for( $i=1 ; $i <= 3 ; $i++ )
//	{
//	  $this->page->insert_block( "cards_reveal", array( 
//							"I" => $i
//							 ) );
//	}

        $this->page->begin_block( "galaxytrucker_galaxytrucker", "opponent" );
	foreach( $players as $player_id => $player )
	  if ( $player_id != $current_player_id )
	    {
	      $this->page->insert_block( "opponent", array(
					"PLAYER" => $player_id,
					"PLAYER_NAME" => $player['player_name']
							   ) );
	    }

        $this->page->begin_block( "galaxytrucker_galaxytrucker", "rev_space" );
	for( $i=0 ; $i <= 29 ; $i++ )
	{
	  $this->page->insert_block( "rev_space", array( "I" => $i ) );
	}

        $this->page->begin_block( "galaxytrucker_galaxytrucker", "tile" );
	$i=1;
	for( $y=0 ; $y < 24 ; $y++ )
	  for( $x=0 ; $x < 6 ; $x++ )
	    {
	      $this->page->insert_block( "tile", array( 
						       "I" => $i,
						       "X" => -50*$x,
						       "Y" => -50*$y,
							) );
	      $i++;
	    }


 


        /*********** Do not change anything below this line  ************/
  	}
  }
  

