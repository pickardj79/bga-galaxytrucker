<?php

require_once('GT_DBPlayer.php');
require_once('GT_Constants.php');

class GT_Enemy extends APP_GameClass {
    public function __construct($game, $card) {
        $this->game = $game;
        $this->card = $card;
    }

    function fightPlayer($player) {
        if ($this->card['enemy_strength'] < $player['min_cann_x2'] / 2) {
            $this->game->notifyAllPlayers("onlyLogMessage",
                clienttranslate('${player_name} defeats ${type} in battle'),
                [ 'player_name' => $player['player_name'], 'type' => $this->card['type'] ]
            );
            return $this->giveReward($player);
        }
        elseif ($this->card['enemy_strength'] > $player['min_cann_x2'] / 2) {
            $this->game->notifyAllPlayers("onlyLogMessage",
                clienttranslate('${player_name} is defeated by ${type} in battle'),
                [ 'player_name' => $player['player_name'], 'type' => $this->card['type'] ]
            );
            return $this->applyPenalty($player);
        }
        else {
            // player must choose to power cannons (or not)
            $game->notifyAllPlayers("onlyLogMessage", 
                clienttranslate('${player_name} must decide whether to activate a cannon against ${type}'),
                ['player_name' => $player['player_name'], 'type' => $this->card['type']]
            );
            return 'powerCannons';
        }
    }

    function giveReward($player) {
        // based on type of card give the correct reward and return the needed state
        $type = $this->card['type'];
        $nextState = NULL;
        $flBrd = $this->game->newFlightBoard();
        $flBrd->moveShip($player['player_id'], -$this->card['days_loss']);
        switch($type) {
            case 'slavers': 
            case 'pirates':
                $flBrd->addCredits($player['player_id'], $this->card['reward']);
                GT_DBPlayer::setCardAllDone($game, $plId);
                break;
            case 'smugglers': 
                $this->game->notifyAllPlayers("onlyLogMessage",
                    clienttranslate('${player_name} must place new cargo'),
                    [ 'player_name' => $player['player_name'] ]
                );
                $nextState = 'placeGoods';
                break;
            default:
                $this->game->throw_bug_report("Unknown card type ($type) in GT_Enemy::giveReward");
        }
        return $nextState;
    }

    function applyPenalty($player) {
        // based on type of card, apply the penalty and return needed state
        $game = $this->game;
        $type = $this->card['type'];
        $flBrd = $game->newFlightBoard();
        switch($type) {
            case 'slavers':
                if ($player['nb_crew'] <= $this->card['enemy_penalty']) {
                    $game->notifyAllPlayers("onlyLogMessage",
                        clienttranslate('${player_name} loses all crew to ${type}'),
                        [ 'player_name' => $player['player_name'], 'type' => $type ]
                    );
                    $plyrContent = $game->newPlayerContent( $player['player_id'] );
                    $allCrewIds = $plyrContent->getContentIds('crew');

                    // loseContent handles players giving up
                    $plyrContent->loseContent($allCrewIds, 'crew', TRUE);
                    return;
                }
                else {
                    $game->notifyAllPlayers("onlyLogMessage",
                        clienttranslate('${player_name} must choose crew to lose to ${type}'),
                        [ 'player_name' => $player['player_name'], 'type' => $type ]
                    );
                    return 'chooseCrew';
                }
                break;
            case 'pirates':
            case 'smugglers':
                $penalty = $this->card['enemy_penalty'];
                $plyrContent = $game->newPlayerContent( $player['player_id'] );
                $goodsIds = $plyrContent->getContentIds('goods');

                // lose more valuable goods first. If a tie, let player choose
                if (count($goodsIds) > $penalty) {
                    $toloseIds = [];
                    $left = count($goodsIds);
                    foreach (GT_Constants::$ALLOWABLE_SUBTYPES['goods'] as $idx => $subtype) {
                        $cur = $plyrContent->getContentIds('goods', $subtype);
                        if (count($cur) <= $left) {
                            // haven't lost enough yet or lost exact amount. Lose them all
                            $toloseIds = array_merge($toloseIds, $cur);
                            $left = $left - count($cur);
                            if ($left == 0) {
                                // lost exact amount, done with player
                                $plyrContent->loseContent($toloseIds, 'goods', TRUE);
                                return;
                            }
                        }
                        else {
                            // TODO: MUST STORE IN DB COLOR AND NUMBER LEFT TO LOSE
                            // lost enough and there are excess
                            // player must choose which goods to lose
                            $plyrContent->loseContent($toloseIds, 'goods', TRUE);
                            $game->setGameStateValue('cardArg1', $idx);
                            $game->setGameStateValue('cardArg2', $left);
                            return 'loseGoods'; 
                        }
                    }
                    $game->throw_bug_report_dump("Should not get here. Left: $left", $toloseIds);
                }

                if (count($goodsIds) == $penalty) {
                    $plyrContent->loseContent($goodsIds, 'goods', TRUE);
                    return; 
                }

                // not enough cargo, lose it all then, need to lose batteries
                $plyrContent->loseContent($goodsIds, 'goods', TRUE);

                $reqCell = $penalty - count($goodsIds);
                $cellIds = $plyrContent->getContentIds('cell');
                if ( count($cellIds) > $reqCell ) {
                    $game->setGameStateValue('cardArg2', $left);
                    return 'loseCells';
                }
                else {
                    // lose all cells
                    $plyrContent->loseContent($cellIds, 'cell', TRUE);
                    return;
                }

            default:
                $game->throw_bug_report("Unknown card type ($type) in GT_Enemy::applyPenalty");
            // if total amount of cargo or crew is < penalty just lose it automatically
            // otherwise go to select content to lose
        }
    }

}

?>