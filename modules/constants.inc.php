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
    const NEXT_TURN = 3;
    const MOVE = 4;
    const RESCUE = 5;

    const GAME_END = 99;
}

interface Globals
{
    const FIRST_MOVE = 'firstMove';
    const FIRST_MOVE_ID = 10;
    const MOVED_SUITS = 'movedSuits';
    const MOVED_SUITS_ID = 11;
    const RESCUER = 'rescuer';
    const RESCUER_ID = 12;
}

interface HexDirection {
    const TOP = [0, -1];
    const TOP_RIGHT = [1, 0];
    const BOTTOM_RIGHT = [1, 1];
    const BOTTOM = [0, 1];
    const BOTTOM_LEFT = [-1, 0];
    const TOP_LEFT = [-1, -1];

    const ALL = [
        self::TOP, self::TOP_RIGHT, self::BOTTOM_RIGHT, self::BOTTOM, self::BOTTOM_LEFT, self::TOP_LEFT
    ];
}

interface Suit {
    const HEARTS = 0b00;
    const DIAMONDS = 0b01;
    const SPADES = 0b10;

    const CLUBS = 0b11;

    const OWNER_MASK = 0b10;
}

const BOARD_SIZE = [17, 15];

const PLAYER_BASES = [
    [[7, 3], [15, 3]],
    [[8, 11], [16, 11]]
];

const PIECE_VALUES = [7, 8, 9, 10, 11, 12, 13, 0];