<?php

require_once('GT_DBPlayer.php');
require_once('GT_Constants.php');

class GT_Enemy extends APP_GameClass {
    public function __construct($game, $card, $player) {
        $this->game = $game;
        $this->card = $card;
        $this->player = $player;
    }

    function playerCannonValue() {
        // If we have a definitive cannon value relative to the current card, return it
        // Null otherwise
        $pl = $this->player;
        if ($pl['min_cann_x2'] == $pl['max_cann_x2'])
            return $pl['min_cann_x2'] / 2;
        
        $enemy_str = $this->card['enemy_strength'];
        
        if ($enemy_str < $pl['min_cann_x2'] / 2)
            return $pl['min_cann_x2'] / 2;
        elseif ($enemy_str > $pl['max_cann_x2'] / 2)
            return $pl['max_cann_x2'] / 2;
        
        return;
    }

    function fightPlayer($playerCannon) {
        if ($playerCannon < 0)
            $this->game->throw_bug_report_dump("playerCannon less than zero: $playerCannon");

        $enemy_str = $this->card['enemy_strength'];
        if ($enemy_str < $playerCannon)
            return $this->giveReward();
        elseif ($enemy_str > $playerCannon)
            return $this->applyPenalty();
        else
            return $this->fightIsTie();
    }

    function fightIsTie() {
        $this->game->notifyAllPlayers("onlyLogMessage",
            clienttranslate('${player_name} fights ${type} to a draw'),
            [ 'player_name' => $this->player['player_name'], 'type' => $this->card['type'] ]
        );
        return;
    }

    function giveReward() {
        // based on type of card give the correct reward and return the needed state
        $this->game->notifyAllPlayers("onlyLogMessage",
            clienttranslate('${player_name} defeats ${type} in battle'),
            [ 'player_name' => $this->player['player_name'], 'type' => $this->card['type'] ]
        );

        $type = $this->card['type'];
        $nextState = NULL;
        $flBrd = $this->game->newFlightBoard();
        $flBrd->moveShip($this->player['player_id'], -$this->card['days_loss']);
        switch($type) {
            case 'slavers': 
            case 'pirates':
                $flBrd->addCredits($this->player['player_id'], $this->card['reward']);
                GT_DBPlayer::setCardAllDone($this->game, $this->player['player_id']);
                break;
            case 'smugglers': 
                $this->game->notifyAllPlayers("onlyLogMessage",
                    clienttranslate('${player_name} must place new cargo'),
                    [ 'player_name' => $this->player['player_name'] ]
                );
                $nextState = 'placeGoods';
                break;
            default:
                $this->game->throw_bug_report("Unknown card type ($type) in GT_Enemy::giveReward");
        }
        return $nextState;
    }

    function applyPenalty() {
        // based on type of card, apply the penalty and return needed state
        $this->game->notifyAllPlayers("onlyLogMessage",
            clienttranslate('${player_name} is defeated by ${type} in battle'),
            [ 'player_name' => $this->player['player_name'], 'type' => $this->card['type'] ]
        );

        $game = $this->game;
        $type = $this->card['type'];
        $flBrd = $game->newFlightBoard();
        switch($type) {
            case 'slavers':
                if ($this->player['nb_crew'] <= $this->card['enemy_penalty']) {
                    $game->notifyAllPlayers("onlyLogMessage",
                        clienttranslate('${player_name} loses all crew to ${type}'),
                        [ 'player_name' => $this->player['player_name'], 'type' => $type ]
                    );
                    $plyrContent = $game->newPlayerContent( $this->player['player_id'] );
                    $allCrewIds = $plyrContent->getContentIds('crew');

                    // loseContent handles players giving up
                    $plyrContent->loseContent($allCrewIds, 'crew', null, TRUE);
                    return;
                }
                else {
                    $game->setGameStateValue('cardArg2', $this->card['enemy_penalty']);
                    $game->setGameStateValue('cardArg3', GT_Constants::$CONTENT_TYPE_INT_MAP['crew']);
                    $game->notifyAllPlayers("onlyLogMessage",
                        clienttranslate('${player_name} must choose crew to lose to ${type}'),
                        [ 'player_name' => $this->player['player_name'], 'type' => $type ]
                    );
                    return 'chooseCrew';
                }
                break;
            case 'pirates':
                return 'cannonBlasts';
            case 'smugglers':
                $penalty = $this->card['enemy_penalty'];
                $plyrContent = $game->newPlayerContent( $this->player['player_id'] );
                $goodsIds = $plyrContent->getContentIds('goods');

                if (count($goodsIds) == $penalty) {
                    $plyrContent->loseContent($goodsIds, 'goods', null, TRUE);
                    return; 
                }

                // lose more valuable goods first. If a tie, let player choose
                if (count($goodsIds) > $penalty) {
                    $toloseIds = [];
                    $left_to_lose = $penalty;
                    foreach (GT_Constants::$ALLOWABLE_SUBTYPES['goods'] as $idx => $subtype) {
                        $cur = $plyrContent->getContentIds('goods', $subtype);
                        if (count($cur) <= $left_to_lose) {
                            // haven't lost enough yet or lost exact amount. Lose them all
                            $toloseIds = array_merge($toloseIds, $cur);
                            $left_to_lose = $left_to_lose - count($cur);
                            if ($left_to_lose == 0) {
                                // lost exact amount, done with player
                                $plyrContent->loseContent($toloseIds, 'goods', null, TRUE);
                                return;
                            }
                        }
                        else {
                            // TODO: MUST STORE IN DB COLOR AND NUMBER LEFT TO LOSE
                            // lost enough and there are excess
                            // player must choose which goods to lose
                            $plyrContent->loseContent($toloseIds, 'goods', null, TRUE);
                            $game->setGameStateValue('cardArg1', $idx);
                            $game->setGameStateValue('cardArg2', $left_to_lose);
                            $game->setGameStateValue('cardArg3', GT_Constants::$CONTENT_TYPE_INT_MAP['goods']);
                            return 'loseGoods'; 
                        }
                    }
                    $game->throw_bug_report_dump("Should not get here. Left: $left_to_lose", $toloseIds);
                }

                // not enough cargo, lose it all then, need to lose batteries
                $plyrContent->loseContent($goodsIds, 'goods', null, TRUE);

                $reqCell = $penalty - count($goodsIds);
                $cellIds = $plyrContent->getContentIds('cell');
                if ( count($cellIds) > $reqCell ) {
                    $game->setGameStateValue('cardArg2', $reqCell);
                    $game->setGameStateValue('cardArg3', GT_Constants::$CONTENT_TYPE_INT_MAP['cell']);
                    return 'loseCells';
                }
                else {
                    // lose all cells
                    $plyrContent->loseContent($cellIds, 'cell', null, TRUE);
                    return;
                }

            default:
                $game->throw_bug_report("Unknown card type ($type) in GT_Enemy::applyPenalty");
        }
    }

}

?>