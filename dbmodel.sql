
-- ------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- QuattuorReges implementation : © <Your name here> <Your email address here>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

CREATE TABLE IF NOT EXISTS `piece`(
    `piece_id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `suit` TINYINT UNSIGNED NOT NULL,
    `value` TINYINT UNSIGNED NOT NULL
    `x` TINYINT UNSIGNED NOT NULL,
    `y` TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY(`piece_id`),
    UNIQUE KEY `position`(`x`, `y`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1