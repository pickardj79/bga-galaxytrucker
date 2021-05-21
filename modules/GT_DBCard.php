<?php
/* Collection of utilities to interface with card table */

class GT_DBCard extends APP_GameClass
{
  public function __construct()
  {
  }

  function getAdvDeckPreview($game)
  {
    // gets piles 1,2,3 for previewing
    return self::getAdvDeck($game, 'card_pile IN (1,2,3)');
  }

  function getAdvDeckPile($game, $pile)
  {
    return self::getAdvDeck($game, "card_pile = $pile");
  }

  function getAdvDeckForPrep($game)
  {
    return self::getAdvDeck($game, 'card_pile IS NOT NULL');
  }

  function getAdvDeckForFlight($game)
  {
    return self::getAdvDeck($game, 'card_order IS NOT NULL');
  }

  private function getAdvDeck($game, $where_sql)
  {
    return $game->getObjectListFromDB("
            SELECT card_round round, card_id id, card_pile pile
            FROM card WHERE $where_sql
            ORDER BY card_order ");
  }

  function updateAdvDeck($game, $deck)
  {
    // update the current adventure deck based on order in $deck array
    $sql = 'REPLACE INTO card (card_round, card_id, card_order) VALUES ';
    // REPLACE so that we remove card_pile information and
    // set card_order in a simple single sql request
    $values = [];
    foreach ($deck as $order => $card) {
      $vals = [$card['round'], $card['id'], $order + 1];
      $values[] = '(' . implode(',', $vals) . ')';
    }
    $sql .= implode(',', $values);
    $game->DbQuery($sql);
  }
}

?>
