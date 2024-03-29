<?php

interface Fsm {
    const NAME = 'name';
    const DESCRIPTION = 'description';
    const OWN_DESCRIPTION = 'descriptionmyturn';
    const TYPE = 'type';
    const ACTION = 'action';
    const TRANSITIONS = 'transitions';
    const PROGRESSION = 'updateGameProgression';
    const POSSIBLE_ACTIONS = 'possibleactions';
    const ARGUMENTS = 'args';
}

interface FsmType {
    const MANAGER = 'manager';
    const GAME = 'game';
    const SINGLE_PLAYER = 'activeplayer';
    const MULTIPLE_PLAYERS = 'multipleactiveplayer';
}

interface State {
    const GAME_START = 1;

    const SETUP = 2;
    const REVEAL = 3;
    const NEXT_MOVE = 4;
    const MOVE = 5;
    const RETREAT = 6;
    const RESCUE = 7;
    const CONFIRM_TURN = 8;
    const NEXT_TURN = 9;

    const GAME_END = 99;
}

interface Globals
{
    const FIRST_MOVE = 'firstMove';
    const FIRST_MOVE_ID = 10;
    const MOVED_SUITS = 'movedSuits';
    const MOVED_SUITS_ID = 11;
    const CAPTURER = 'capturer';
    const CAPTURER_ID = 12;
    const RESCUE_COUNT = 'rescueCount';
    const RESCUE_COUNT_ID = 13;
    const RESCUED_PIECES = 'rescuedPieces';
    const RESCUED_PIECES_ID = 14;
}

interface Stats {
    const TURNS_NUMBER = 'turns_number';

    const TABLE_STATS_LIST = [
        self::TURNS_NUMBER
    ];

    const NUMBER_MOVES = 'number_moves';
    const COURT_MOVES = 'court_moves';
    const ACE_MOVES = 'ace_moves';
    const NUMBER_CAPTURES = 'number_captures';
    const COURT_CAPTURES = 'court_captures';
    const ACE_CAPTURES = 'ace_captures';
    const NUMBER_RESCUES = 'number_rescues';
    const COURT_RESCUES = 'court_rescues';
    const ACE_RESCUES = 'ace_rescues';
    const ACE_RETREATS = 'ace_retreats';
    
    const PLAYER_STATS_LIST = [
        self::NUMBER_MOVES,
        self::COURT_MOVES,
        self::ACE_MOVES,
        self::NUMBER_CAPTURES,
        self::COURT_CAPTURES,
        self::ACE_CAPTURES,
        self::NUMBER_RESCUES,
        self::COURT_RESCUES,
        self::ACE_RESCUES,
        self::ACE_RETREATS,
    ];
}

const HEX_DIRECTIONS = [
    [1, 0], [1, 1], [0, 1], [-1, 0], [-1, -1], [0, -1]
];

interface Suit {
    const HEARTS = 0b00;
    const DIAMONDS = 0b01;
    const SPADES = 0b10;

    const CLUBS = 0b11;

    const OWNER_MASK = 0b10;
}

const BOARD_SIZE = [17, 15, 6];

const PLAYER_BASES = [
    [[7, 3], [15, 3]],
    [[8, 11], [16, 11]]
];

const PIECE_VALUES = [7, 8, 9, 10, 11, 12, 13, 0];

const WINNING_VALUES = [12, 13, 0];