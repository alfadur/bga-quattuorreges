<?php

trait DbUndoLog {

    static function packPiece(array $piece, ?array $position = null): int {
        $x = $position ? $position['x'] : $piece['x'];
        $y = $position ? $position['y'] : $piece['y'];
        return (int)$x
            | ((int)$y << 8)
            | ((int)$piece['suit'] << 16)
            | ((int)$piece['value'] << 24);
    }

    static function unpackPiece(int $value): array {
        return [
            'x' => $value & 0xFF,
            'y' => ($value >> 8) & 0xFF,
            'suit' => ($value >> 16) & 0xFF,
            'value' => ($value >> 24) & 0xFF
        ];
    }

    function logUpdateGlobal(array &$undo, string $name, int $value, bool $increment = true): void
    {
        $undo['globals'][] = [
            $name,
            self::getGameStateValue($name)
        ];
        if ($increment) {
            self::incGameStateValue($name, $value);
        } else {
            self::setGameStateValue($name, $value);
        }
    }

    function logUpdateStat(
        array &$undo, string $name, int $value = 1, bool $increment = true): void
    {
        $playerId = self::getActivePlayerId();
        $undo['stats'][] = [
            $name,
            $playerId,
            self::getStat($name, $playerId)
        ];
        if ($increment) {
            self::incStat($value, $name, $playerId);
        } else {
            self::setStat($value, $name, $playerId);
        }
    }
    function logSaveUndo($undo): void
    {
        $json = str_replace("\\", "\\\\", json_encode($undo));
        self::DbQuery(<<<EOF
            INSERT INTO undo_log(state) VALUES ('$json')
            EOF);
    }

    function logCapture(array &$undo, array $piece, int $rescueCount)
    {
        self::DbQuery(<<<EOF
                DELETE FROM piece
                WHERE x = $piece[x] AND y = $piece[y]
                EOF);
        $undo['queries'][] = <<<EOF
            INSERT INTO piece(suit, value, x, y) 
            VALUES ($piece[suit], $piece[value], $piece[x], $piece[y])
            EOF;
        self::logUpdateGlobal(
            $undo, Globals::RESCUE_COUNT,
            $rescueCount, false);
        self::notifyAllPlayers('update', clienttranslate('${pieceIcon} is captured'), [
            'moves' => [['suit' => $piece['suit'], 'value' => $piece['value']]],
            'pieceIcon' => "$piece[suit],$piece[value]",
            'preserve' => ['pieceIcon']
        ]);
    }

    function logMove(
        array $piece, array $target,
        bool $retreat, int $rescueCount): void
    {
        $playerName = self::getActivePlayerName();
        $undo = [
            'state' => state::MOVE,
            'queries' => [],
            'globals' => [],
            'notification' => [
                'message' => '${player_name} undoes a move',
                'args' => [
                    'player_name' => $playerName,
                    'moves' => []
                ]
            ]
        ];

        $captured = array_key_exists('value', $target);

        $args = [
            'player_name' => self::getActivePlayerName(),
            'moves' => [],
            'x' => $target['x'],
            'y' => $target['y'],
            'pieceIcon' => "$piece[suit],$piece[value]",
            'preserve' => ['pieceIcon', 'x', 'y']
        ];

        if ($captured) {
            self::DbQuery(<<<EOF
                DELETE FROM piece
                WHERE x = $target[x] AND y = $target[y]
                EOF);

            $undo['queries'][] = <<<EOF
                INSERT INTO piece(suit, value, x, y) 
                VALUES ($target[suit], $target[value], 
                    $target[x], $target[y])
                EOF;

            $args['moves'][] = [
                'suit' => $target['suit'],
                'value' => $target['value']
            ];
            $args['pieceIconC'] = "$target[suit],$target[value]";
            $args['preserve'][] = ['pieceIconC'];

            $undo['notification']['args']['moves'][] = [
                'suit' => $target['suit'],
                'value' => $target['value'],
                'x' => $target['x'],
                'y' => $target['y']
            ];
        }

        $args['moves'][] = [
            'suit' => $piece['suit'],
            'value' => $piece['value'],
            'x' => $target['x'],
            'y' => $target['y']
        ];

        $undo['notification']['args']['moves'][] = [
            'suit' => $piece['suit'],
            'value' => $piece['value'],
            'x' => $piece['x'],
            'y' => $piece['y']
        ];

        $message = $captured ?
            clienttranslate('${player_name} moves ${pieceIcon} to (${x},${y}) and captures ${pieceIconC}') :
            clienttranslate('${player_name} moves ${pieceIcon} to (${x},${y})');
        self::notifyAllPlayers('update', $message, $args);

        $value = (int)$piece['value'];
        $moveStat = $value === 0 ? Stats::ACE_MOVES :
            ($value <= 10 ? Stats::NUMBER_MOVES : Stats::COURT_MOVES);
        self::logUpdateStat($undo, $moveStat);
        if ($captured) {
            $captureStat = $value === 0 ? Stats::COURT_CAPTURES :
                ($value <= 10 ? Stats::NUMBER_CAPTURES : Stats::COURT_CAPTURES);;
            self::logUpdateStat($undo, $captureStat);
        }

        if (!$retreat && $rescueCount > 0) {
            $this->logCapture($undo, $piece, $rescueCount);
        } else {
            self::DbQuery(<<<EOF
                UPDATE piece
                SET x = $target[x], y = $target[y]
                WHERE x = $piece[x] AND y = $piece[y]
                EOF);

            $undo['queries'][] = <<<EOF
                UPDATE piece
                SET x = $piece[x], y = $piece[y]
                WHERE x = $target[x] AND y = $target[y]
                EOF;

            self::logUpdateGlobal(
                $undo, Globals::CAPTURER,
                self::packPiece($piece), false);
        }

        $movedSuitBit = 1 << ((int)$piece['suit'] & (~Suit::OWNER_MASK));
        self::logUpdateGlobal($undo, Globals::MOVED_SUITS, $movedSuitBit);

        $this->logSaveUndo($undo);
    }

    function logPass(int $state): void
    {
        $undo = ['state' => $state];
        if ($state === State::MOVE) {
            $this->logUpdateGlobal($undo, Globals::MOVED_SUITS, 0b11, false);
        }
        $this->logSaveUndo($undo);
    }

    function logRetreat(array $piece, array $target, bool $retreat, bool $rescueCount): void {
        $undo = [
            'state' => state::RETREAT,
            'queries' => []
        ];

        if ($retreat) {
            $playerName = self::getActivePlayerName();
            self::DbQuery(<<<EOF
                UPDATE piece 
                SET x = $target[x], y = $target[y]
                WHERE suit = $piece[suit] AND value = $piece[value]                    
                EOF);
            self::notifyAllPlayers('update', '${player_name} retreats with ${pieceIcon}', [
                'player_name' => $playerName,
                'moves' => [$target],
                'pieceIcon' => "$piece[suit],$piece[value]",
                'preserve' => ['pieceIcon'],
            ]);

            $undo['queries'][] = <<<EOF
                UPDATE piece 
                SET x = $piece[x], y = $piece[y]
                WHERE suit = $piece[suit] AND value = $piece[value]
                EOF;
            $undo['notification'] = [
                'message' => '${player_name} undoes a retreat',
                'args' => [
                    'player_name' => $playerName,
                    'moves' => [$piece]
                ]
            ];

            self::logUpdateStat($undo, Stats::ACE_RETREATS);
        } else if ($rescueCount > 0) {
            $this->logCapture($undo, $piece, $rescueCount);
            $undo['notification'] = [
                'message' => '',
                'args' => [
                    'moves' => [$piece]
                ]
            ];
        }

        $this->logSaveUndo($undo);
    }

    function logRescue(array $rescues, int $rescuedPieces): void
    {
        $undo = [
            'state' => state::RESCUE,
        ];

        if (count($rescues) > 0) {
            $inserts = [];
            $moves = [];
            $icons = [];

            $playerName = self::getActivePlayerName();
            $undo['notification'] = [
                'message' => '${player_name} undoes a rescue',
                'args' => [
                    'player_name' => $playerName,
                    'moves' => []
                ]
            ];
            $undo['queries'] = [];
            $deletes = [];

            $rescueStats = [
                Stats::ACE_RESCUES => 0,
                Stats::COURT_RESCUES => 0,
                Stats::NUMBER_RESCUES => 0
            ];

            foreach ($rescues as $rescuedPiece)
            {
                ['suit' => $suit, 'value' => $value, 'x' => $x, 'y' => $y] =
                    $rescuedPiece;
                $value = (int)$value;

                $inserts[] = "($suit, $value, $x, $y)";
                $moves[] = $rescuedPiece;
                $icons[] = "$suit,$value";

                $undo['notification']['args']['moves'][] =
                    ['suit' => $suit, 'value' => $value];
                $deletes[] = "x = $x AND y = $y";

                $rescueStat = $value === 0 ? Stats::ACE_RESCUES :
                    ($value <= 10 ? Stats::NUMBER_RESCUES : Stats::COURT_RESCUES);
                ++$rescueStats[$rescueStat];
            }

            foreach($rescueStats as $stat => $count) {
                self::logUpdateStat($undo, $stat, $count);
            }

            $deleteArgs = implode(" OR ", $deletes);
            $undo['queries'][] =
                "DELETE FROM piece WHERE $deleteArgs";

            $insertArgs = implode(',', $inserts);
            self::DbQuery(
                "INSERT INTO piece(suit, value, x, y) VALUES $insertArgs");

            $this->logUpdateGlobal($undo, Globals::RESCUED_PIECES, $rescuedPieces, false);

            self::notifyAllPlayers('update', clienttranslate('${player_name} rescues ${pieceIcons}'), [
                'player_name' => self::getActivePlayerName(),
                'moves' => $moves,
                'pieceIcons' => implode(',', $icons),
                'preserve' => ['pieceIcon', 'pieceIcons']
            ]);
        }

        $this->logSaveUndo($undo);
    }

    function logCanUndo(): bool {
        $undoCount = self::getUniqueValueFromDb(
            'SELECT COUNT(*) FROM undo_log');
        return (int)$undoCount > 0;
    }

    function logUndo(): void
    {
        $json = self::getNonEmptyObjectFromDb(
            'SELECT * FROM undo_log ORDER BY id DESC LIMIT 1');
        $undo = json_decode($json['state'], true);

        if (array_key_exists('globals', $undo)) {
            foreach ($undo['globals'] as [$name, $value]) {
                self::setGameStateValue($name, $value);
            }
        }

        if (array_key_exists('stats', $undo)) {
            foreach ($undo['stats'] as [$name, $playerId, $value]) {
                self::setStat($value, $name, $playerId);
            }
        }

        if (array_key_exists('queries', $undo)) {
            foreach (array_reverse($undo['queries']) as $query) {
                self::DbQuery($query);
            }
        }

        if (array_key_exists('notification', $undo)) {
            $notification = $undo['notification'];
            self::notifyAllPlayers(
                'update',
                $notification['message'],
                $notification['args']);
        }

        self::DbQuery("DELETE FROM undo_log WHERE id = $json[id]");

        $this->gamestate->jumpToState((int)$undo['state']);
    }

    function logClear(): void
    {
        self::DbQuery('DELETE FROM undo_log');
    }
}