
-- ------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- GalaxyTrucker implementation : © <Your name here> <Your email address here>
--
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

-- dbmodel.sql

-- This is the file where you are describing the database schema of your game
-- Basically, you just have to export from PhpMyAdmin your table structure and copy/paste
-- this export here.
-- Note that the database itself and the standard tables ("global", "stats", "gamelog" and "player") are
-- already created and must not be created here

-- Note: The database schema is created from this file when the game starts. If you modify this file,
--       you have to restart a game to see your changes in database.

-- Example 1: create a standard "card" table to be used with the "Deck" tools (see example game "hearts"):

-- CREATE TABLE IF NOT EXISTS `card` (
--   `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
--   `card_type` varchar(16) NOT NULL,
--   `card_type_arg` int(11) NOT NULL,
--   `card_location` varchar(16) NOT NULL,
--   `card_location_arg` int(11) NOT NULL,
--   PRIMARY KEY (`card_id`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;


-- Example 2: add a custom field to the standard "player" table
-- ALTER TABLE `player` ADD `player_my_custom_field` INT UNSIGNED NOT NULL DEFAULT '0';

CREATE TABLE IF NOT EXISTS `component` (
  `component_id` SMALLINT unsigned NOT NULL AUTO_INCREMENT,
  `component_player` int(11),
  `component_x` TINYINT,
  `component_y` TINYINT,
  `component_orientation` SMALLINT NOT NULL DEFAULT 0,
-- 0, 90, 180 or 270
  `aside_discard` TINYINT DEFAULT NULL,
  PRIMARY KEY (`component_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
-- component_id is the tile_id (from material.inc.php)
-- component_player IS NULL if tile is in the pile
-- component_player = -1 if tile is revealed next to the pile (component_x MUST be null)
-- component_player = 0 if not used this round (component_x MUST be null)
-- component_x IS NULL if tile is in player's hand (TO BE CONFIRMED: and aside_discard = 1 if tile was set aside before, IS NULL otherwise)

-- TO BE CONFIRMED: component_x = -1 or -2 if tile is in the discard zone, space 1 or 2 (because it is set aside (during the build phase) or because it has been destroyed or removed)
-- TO BE CONFIRMED: aside_discard = n (where n is positive and is the layer of this tile in the discard zone) if tile is discarded, or set aside (in this case aside_discard = 1).
-- TO BE CONFIRMED: aside_discard can also be 1, during build phase, if this tile was set aside before (in this case component_x IS NULL if it is in hand, otherwise it is the last tile placed on a ship so we still need to remember it was set aside before because it can still be removed ).

CREATE TABLE IF NOT EXISTS `card` (
  `card_round` TINYINT unsigned NOT NULL,
-- 1 to 3
  `card_id` TINYINT unsigned NOT NULL,
-- 0 to 59
  `card_pile` TINYINT DEFAULT NULL,
-- 1 to 3 for the piles that can be seen by the players during build phase
-- 4 for the secret one
  `card_order` TINYINT DEFAULT NULL,
-- order of resolution during the adventure phase
  `used` TINYINT NOT NULL DEFAULT 0,
-- 1 for cards that have been used in previous rounds, else 0
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;

ALTER TABLE `player` ADD `credits` TINYINT DEFAULT 0;
ALTER TABLE `player` ADD `turn_order` TINYINT DEFAULT NULL;
ALTER TABLE `player` ADD `player_position` INT DEFAULT NULL;
-- 0 should be starting position of the player with number 1
-- apply no modulo so that we can detect laps
ALTER TABLE `player` ADD `still_flying` TINYINT NOT NULL DEFAULT 0;
-- 1 during flight, except if player gave up
ALTER TABLE `player` ADD `alien_choice` TINYINT NOT NULL DEFAULT 0;
-- 1 if we need to ask this player for alien choice, 0 if already done or not needed
ALTER TABLE `player` ADD `undo_possible` SMALLINT unsigned DEFAULT NULL;
-- undo_possible keeps track of the id of the tile that can still be taken back, if any.
-- Must be set back to NULL each time a component is grabbed
ALTER TABLE `player` ADD `nb_crew` TINYINT unsigned DEFAULT NULL ;
ALTER TABLE `player` ADD `exp_conn` TINYINT unsigned DEFAULT NULL ;
-- number of exposed connectors, re-calculated each time a component is removed
ALTER TABLE `player` ADD `min_eng` TINYINT unsigned DEFAULT NULL;
ALTER TABLE `player` ADD `max_eng` TINYINT unsigned DEFAULT NULL;
ALTER TABLE `player` ADD `min_cann_x2` TINYINT unsigned DEFAULT NULL;
ALTER TABLE `player` ADD `max_cann_x2` TINYINT unsigned DEFAULT NULL;
-- Multiplied by 2 in order to avoid float imprecision when comparing
ALTER TABLE `player` ADD `card_line_done` TINYINT unsigned NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `card_action_choice` TINYINT unsigned NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `content` (
  `content_id` SMALLINT unsigned NOT NULL AUTO_INCREMENT,
  `player_id` int(11),
  `tile_id`  SMALLINT unsigned NOT NULL,
  `square_x` TINYINT NOT NULL,
  `square_y` TINYINT NOT NULL,
-- we need square_x and square_y to ease placement in square_x_y divs, for content that won't be rotated along with tiles
  `content_type` varchar(16),
-- can be crew, cell, goods
  `content_subtype` varchar(16),
-- can be human, brown, purple, and sometimes ask_human, ask_brown or ask_purple (for crew), cell (for cell), red, yellow, green, blue (for goods)
  `place` TINYINT,
-- place on the tile (1, 2, 3) so that the UI doesn't display multiple pieces at the same place
  `capacity` TINYINT,
-- number of units that can be placed on this tile (used by CSS to display content at the right place)
  PRIMARY KEY (`content_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `revealed_pile` (
  `space` SMALLINT unsigned NOT NULL,
  `tile_id` SMALLINT unsigned DEFAULT NULL,
  PRIMARY KEY (`space`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;
