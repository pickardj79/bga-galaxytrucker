{OVERALL_GAME_HEADER}

<!--
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- GalaxyTrucker implementation : © <Your name here> <Your email address here>
--
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------
-->

<div id="build_message" class="darkblock">
  <h6>{BUILD_MESSAGE}</h6>
  <div id="close_buildMessage_container">
    <div id="close_buildMessage">X</div>
  </div>
</div>

<div id="flight_wrap">
  <div id="flight" class="darkblock">
    <div id="flight_row1" class="flight_row">
    <!-- BEGIN flight_pos_row1 --><div id="flight_pos_{I}" class="flight_pos"></div><!-- END flight_pos_row1 -->
    </div>
    <div id="flight_row2" class="flight_row">
    <!-- BEGIN flight_pos_row2 --><div id="flight_pos_{I}" class="flight_pos"></div><!-- END flight_pos_row2 -->
    </div>
  </div>
</div>

<div id="timer_cards_order" class="darkblock">
    <div id="timer_board">
        <div id="timerPlace_4" class="timerPlace"></div>
        <div id="timerPlace_3" class="timerPlace"></div>
        <div id="timerPlace_2" class="timerPlace"></div>
        <div id="timerPlace_1" class="timerPlace"><span id="sandTimer"></span></div>
        <div id="timerPlace_0" class="timerPlace"></div>
    </div>
    <div id="in_hand_div">
        <div id="in_hand_inside"></div>
    </div>
    <div id="cards">
        <div id="card_pile_1" class="card_pile"></div>
        <div id="card_pile_2" class="card_pile"></div>
        <div id="card_pile_3" class="card_pile"></div>
    </div>
    <!-- BEGIN order_tile_slot -->
         <div id="order_tile_slot_{I}" class="order_tile_slot"></div>
    <!-- END order_tile_slot -->
</div>

<div id="scpf">

  <div id="left_col">

    <div id="ship_div" class="darkblock">
      <h3>{MY_SHIP}</h3>
      <div id="my_ship" class="ship">
      </div>
    </div>

    <div id="opponents">
      <!-- BEGIN opponent -->
      <div id="ship_{PLAYER}_div" class="opponent darkblock">
        <h3>{PLAYER_NAME}</h3>
        <div id="ship_{PLAYER}" class="ship">
        </div>
      </div>
      <!-- END opponent -->
    </div>

  </div>

  <div id="right_col">

    <div id="pile" class="darkblock">
      <h3>{PILE}</h3>
      <div id="pile_blocks">

        <div id="turns">
            <div id="turn_left" class="turn"></div>
            <div id="turn_right" class="turn"></div>
        </div>

        <div id="basic_pile">
      <!-- BEGIN tile -->
           <div id="tile_{I}" class="tile" style="background-position:{X}px {Y}px;"></div>
      <!-- END tile -->
        </div>
        <div id="clickable_pile"></div>

        <div id="revealed_pile_wrap">
          <div id="revealed_pile"><!-- BEGIN rev_space --><div id="rev_space_{I}" class="rev_space"></div><!-- END rev_space --></div>
        </div>

      </div>
    </div>

    <div id="current_card"><div id="card_rewards"></div></div>
    <div id="info_box" class="darkblock"></div>
    <div id="trash_box" class="darkblock"><h3>{AIR_LOCK}</h3></div>


  </div>

</div>

<div id="dangers"></div>

<div id="cards_reveal_wrap">
  <div id="cards_reveal_shadow"></div>
    <div id="cards_reveal_1" class="cards_reveal"></div>
    <div id="cards_reveal_2" class="cards_reveal"></div>
    <div id="cards_reveal_3" class="cards_reveal"></div>
</div>

<script type="text/javascript">

// Javascript HTML templates

var jstpl_hidden_tile='<div class="hidden_tile" style="-ms-transform: rotate(${deg}deg); -webkit-transform: rotate(${deg}deg); -moz-transform: rotate(${deg}deg); -o-transform: rotate(${deg}deg); transform: rotate(${deg}deg); left:${left}px; top:${top}px;"></div>';
var jstpl_cardBack='<div class="card_back round_${round} nb_${nb}" id="card_back_${pile}_${nb}"></div>';
var jstpl_card='<div class="card" id="card_${id}" style="background-position:${x}px ${y}px;"></div>';
var jstpl_square='<div class="square${cssClasses}" id="square_${plId}_${x}_${y}" style="left:${left}px; top:${top}px;"></div>';
var jstpl_overlay_tile='<div class="overlay_tile" id="overlaytile_${i}"></div>';
var jstpl_rev_space='<div id="rev_space_${i}" class="rev_space additional"></div>';

// markers on card to aid in goods placement; circle - used for planet selection options
var jstpl_circle='<div class="circle ${classes}" id="planet_${idx}" style="left:7px; top:${top}px;"></div>';

var jstpl_order_tile='<div class="order_tile" id="order_tile_${i}"></div>';
var jstpl_ord_tile_slot='<div class="order_tile_slot" id="ordTileSlotOnShip_${id}"></div>';
var jstpl_ship_marker='<div class="ship_marker clr${color}" id="ship_marker_${plId}"></div>';
var jstpl_card_marker='<div class="ship_marker clr${color}" id="card_marker_${plId}"></div>';

var jstpl_content='<div class="content ${classes}" id="content_${content_id}"></div>';
//var jstpl_cell='<div class="content cell ${posClass}" id="tile_${tile_id}_cell_${place}"></div>';
//var jstpl_crew='<div class="content crew ${typeClasses} ${posClass}" id="tile_${tile_id}_crew_${place}"></div>'; // Do we need alien information in id?
//var jstpl_human='<div class="content human ${posClass}" id="tile_${tile_id}_human_${place}"></div>';
//var jstpl_alien='<div class="content alien ${al_color}" id="tile_${tile_id}_${al_color}""></div>'; // TODO change into crew? (common with human, but we must check if al_color in this id is useful)
//var jstpl_alienChoice='<div class="content alien_choice ${al_color} ${posClass}" id="tile_${tile_id}_alChoice_${al_color}"></div>';
//var jstpl_alienChoiceClickDiv='<div class="alien_choice_clickdiv" id="tile_${tile_id}_alChoiceClick_${al_color}"></div>';
//var jstpl_humanChoice='<div class="content human_choice" id="tile_${tile_id}_alChoice_human"></div>';

var jstpl_plBoardItem=
      '<div class="plBoardItem">'+
          '<div id="${type}_${plId}_div" class="plBoardIcon icon_${type}"></div>'+
          '<span id="${type}_${plId}" class="plBoardSpan"></span>'+
      '</div>';

var jstpl_ship_part_nb='<div class="ship_part_nb">${nb}</div>'; // id needed?
var jstpl_tile_error='<div class="tile_error error${side}"></div>'; // id needed?

/*
// Example:
var jstpl_some_game_item='<div class="my_game_item" id="my_game_item_${id}"></div>';

*/

</script>

{OVERALL_GAME_FOOTER}
