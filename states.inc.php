<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * QuattuorReges implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 * 
 * states.inc.php
 *
 * QuattuorReges game states description
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

$machinestates = [
    // The initial state. Please do not modify.
    State::GAME_START => [
        Fsm::NAME => "gameSetup",
        Fsm::TYPE => FsmType::MANAGER,
        Fsm::DESCRIPTION => "",
        Fsm::ACTION => 'stGameSetup',
        Fsm::TRANSITIONS => ['' => State::SETUP]
    ],

    State::SETUP => [
        Fsm::NAME => 'setup',
        Fsm::TYPE => FsmType::MULTIPLE_PLAYERS,
        Fsm::DESCRIPTION => clienttranslate('All players must secretly place pieces'),
        Fsm::OWN_DESCRIPTION => clienttranslate('You must secretly place pieces'),
        Fsm::ACTION => 'stMakeEveryoneActive',
        Fsm::POSSIBLE_ACTIONS => ['deploy', 'cancel'],
        Fsm::TRANSITIONS => ['' => State::REVEAL]
    ],

    State::REVEAL => [
        Fsm::NAME => 'reveal',
        Fsm::TYPE => FsmType::GAME,
        Fsm::ACTION => "stReveal",
        Fsm::TRANSITIONS => ['' => State::MOVE]
    ],

    State::NEXT_MOVE => [
        Fsm::NAME => 'nextMove',
        Fsm::TYPE => FsmType::GAME,
        Fsm::ACTION => 'stNextMove',
        Fsm::TRANSITIONS => [
            'move' => State::MOVE,
            'confirm' => State::CONFIRM_TURN,
            'end' => State::GAME_END
        ],
    ],

    State::MOVE => [
        Fsm::NAME => 'move',
        Fsm::TYPE => FsmType::SINGLE_PLAYER,
        Fsm::DESCRIPTION => clienttranslate('${actplayer} must move a piece'),
        Fsm::OWN_DESCRIPTION => clienttranslate('${you} must move a piece'),
        Fsm::ARGUMENTS => 'argMove',
        Fsm::POSSIBLE_ACTIONS => ['move', 'pass', 'undo'],
        Fsm::TRANSITIONS => [
            'next' => State::NEXT_MOVE,
            'rescue' => State::RESCUE,
            'retreat' => State::RETREAT
        ]
    ],

    State::RETREAT => [
        Fsm::NAME => 'retreat',
        Fsm::TYPE => FsmType::SINGLE_PLAYER,
        Fsm::DESCRIPTION => clienttranslate('${actplayer} can retreat with ${pieceIcon}'),
        Fsm::OWN_DESCRIPTION => clienttranslate('${you} can retreat with ${pieceIcon}'),
        Fsm::ARGUMENTS => 'argRetreat',
        Fsm::POSSIBLE_ACTIONS => ["retreat", "undo"],
        Fsm::TRANSITIONS => [
            'next' => State::NEXT_MOVE,
            'rescue' =>  State::RESCUE
        ]
    ],

    State::RESCUE => [
        Fsm::NAME => 'rescue',
        Fsm::TYPE => FsmType::SINGLE_PLAYER,
        Fsm::DESCRIPTION => clienttranslate('${actplayer} must select piece(s) to rescue'),
        Fsm::OWN_DESCRIPTION => clienttranslate('${you} must select piece(s) to rescue'),
        Fsm::ARGUMENTS => 'argRescue',
        Fsm::POSSIBLE_ACTIONS => ['rescue', 'undo'],
        Fsm::TRANSITIONS => ['next' => State::NEXT_MOVE]
    ],

    State::CONFIRM_TURN => [
        Fsm::NAME => 'confirmTurn',
        Fsm::TYPE => FsmType::SINGLE_PLAYER,
        Fsm::DESCRIPTION => clienttranslate('${actplayer} must confirm the end of the turn'),
        Fsm::OWN_DESCRIPTION => clienttranslate('${you} must confirm the end of the turn'),
        Fsm::POSSIBLE_ACTIONS => ['confirm', 'undo'],
        Fsm::TRANSITIONS => ['' => State::NEXT_TURN]
    ],

    State::NEXT_TURN => [
        Fsm::NAME => "nextTurn",
        Fsm::TYPE => FsmType::GAME,
        Fsm::PROGRESSION => true,
        Fsm::ACTION => 'stNextTurn',
        Fsm::TRANSITIONS => ['' => State::MOVE]
    ],

    State::GAME_END => [
        Fsm::NAME => 'gameEnd',
        Fsm::TYPE => FsmType::MANAGER,
        Fsm::DESCRIPTION => clienttranslate('End of game'),
        Fsm::ACTION => 'stGameEnd',
        Fsm::ARGUMENTS => 'argGameEnd'
    ]
];



