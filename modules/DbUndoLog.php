<?php

trait DbUndoLog {
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

    function logSaveUndo($undo): void
    {
        $json = str_replace("\\", "\\\\", json_encode($undo));
        self::DbQuery(<<<EOF
            INSERT INTO undo_log(state) VALUES ('$json')
            EOF);
    }

    function logMove(
        array $piece, array $target,
        bool $retreat, bool $rescue): void
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

        if (!$retreat) {
            self::DbQuery(<<<EOF
                UPDATE piece
                SET x = $target[x], y = $target[y]
                WHERE x = $piece[x] AND y = $piece[y]
                EOF);
            $args['moves'][] = [
                'suit' => $piece['suit'],
                'value' => $piece['value'],
                'x' => $target['x'],
                'y' => $target['y']
            ];

            $undo['queries'][] = <<<EOF
                UPDATE piece
                SET x = $piece[x], y = $piece[y]
                WHERE x = $target[x] AND y = $target[y]
                EOF;
            $undo['notification']['args']['moves'][] = [
                'suit' => $piece['suit'],
                'value' => $piece['value'],
                'x' => $piece['x'],
                'y' => $piece['y']
            ];
        }

        $movedSuitBit = 1 << ((int)$piece['suit'] & (~Suit::OWNER_MASK));
        self::logUpdateGlobal($undo, Globals::MOVED_SUITS, $movedSuitBit);

        if ($rescue) {
            $position = $retreat ? $piece : $target;
            $rescuerValue = (int)$position['x'] + ((int)$position['y'] << 8)
                + ((int)$piece['suit'] << 16) + ((int)$piece['value'] << 24);
            self::logUpdateGlobal($undo, Globals::RESCUER, $rescuerValue);
        }

        $this->logSaveUndo($undo);

        $message = $captured ?
            clienttranslate('${player_name} moves ${pieceIcon} to (${x},${y}) and captures ${pieceIconC}') :
            clienttranslate('${player_name} moves ${pieceIcon} to (${x},${y})');
        self::notifyAllPlayers('update', $message, $args);
    }

    function logPass(int $state): void
    {
        $undo = ['state' => $state];
        if ($state === State::MOVE) {
            $this->logUpdateGlobal($undo, Globals::MOVED_SUITS, 0b11, false);
        }
        $this->logSaveUndo($undo);
    }

    function logRescue(array $piece, array $rescues): void
    {
        $undo = [
            'state' => state::RESCUE,
        ];

        if (count($rescues) > 0) {
            self::DbQuery(
                "DELETE FROM piece WHERE x = $piece[x] AND y = $piece[y]");
            $inserts = [];
            $moves = [[
                'suit' => $piece['suit'],
                'value' => $piece['value']
            ]];
            $icons = [];

            $playerName = self::getActivePlayerName();
            $undo['notification'] = [
                'message' => '${player_name} undoes a rescue',
                'args' => [
                    'player_name' => $playerName,
                    'moves' => []
                ]
            ];
            $undo['queries'] = [<<<EOF
                INSERT INTO piece(suit, value, x, y) 
                VALUES ($piece[suit], $piece[value], 
                    $piece[x], $piece[y])
                EOF];
            $deletes = [];

            foreach ($rescues as $rescuedPiece)
            {
                ['suit' => $suit, 'value' => $value, 'x' => $x, 'y' => $y] =
                    $rescuedPiece;
                $inserts[] = "($suit, $value, $x, $y)";
                $moves[] = $rescuedPiece;
                $icons[] = "$suit,$value";

                $undo['notification']['args']['moves'][] =
                    ['suit' => $suit, 'value' => $value];
                $deletes[] = "x = $x AND y = $y";
            }

            $undo['notification']['args']['moves'][] = $piece;

            $deleteArgs = implode(" OR ", $deletes);
            $undo['queries'][] =
                "DELETE FROM piece WHERE $deleteArgs";

            $insertArgs = implode(',', $inserts);
            self::DbQuery(
                "INSERT INTO piece(suit, value, x, y) VALUES $insertArgs");

            self::notifyAllPlayers('update', clienttranslate('${player_name} exchanges ${pieceIcon} for ${pieceIcons}'), [
                'player_name' => self::getActivePlayerName(),
                'moves' => $moves,
                'pieceIcon' => "$piece[suit],$piece[value]",
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