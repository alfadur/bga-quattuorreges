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
  * quattuorreges.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */


require_once(APP_GAMEMODULE_PATH.'module/table/table.game.php');
require_once('modules/constants.inc.php');

class QuattuorReges extends Table
{
    function __construct( )
    {
        parent::__construct();
        self::initGameStateLabels([
            Globals::FIRST_MOVE => Globals::FIRST_MOVE_ID,
            Globals::MOVED_SUITS => Globals::MOVED_SUITS_ID,
            Globals::RESCUER => Globals::RESCUER_ID,
        ]);
    }

    protected function getGameName()
    {
        // Used for translations and stuff. Please do not modify.
        return 'quattuorreges';
    }

    protected function setupNewGame($players, $options = array())
    {
        $data = self::getGameinfos();
        $defaultColors = $data['player_colors'];
        $playerValues = [];

        foreach ($players as $playerId => $player) {
            $color = array_shift($defaultColors);

            $name = addslashes($player['player_name']);
            $avatar = addslashes($player['player_avatar']);
            $playerValues[] = "('$playerId','$color','$player[player_canal]','$name','$avatar')";
        }

        $args = implode(',', $playerValues);
        $query = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES $args";
        self::DbQuery($query);

        self::reloadPlayersBasicInfos();

        /************ Start the game initialization *****/

        $settings = [
            [Globals::FIRST_MOVE, 1],
            [Globals::MOVED_SUITS, 0],
            [Globals::RESCUER, 0]
        ];

        foreach ($settings as [$name, $value]) {
            self::setGameStateInitialValue($name, $value);
        }
    }

    protected function getAllDatas()
    {
        $result = [
            'players' => self::getCollectionFromDb(
                'SELECT player_id AS id, player_score AS score, player_color AS color, player_no AS no FROM player')
        ];

        if ((int)$this->gamestate->state_id() !== State::SETUP) {
            $result['pieces'] = self::getObjectListFromDb(
                'SELECT * FROM piece');
        } else if (!self::isSpectator()) {
            $playerId = self::getCurrentPlayerId();
            $side = self::getPlayerNoById($playerId) - 1;
            $suitOwner = $side << 1;
            $ownerMask = Suit::OWNER_MASK;

            $result['pieces'] = self::getObjectListFromDb(
                "SELECT * FROM piece WHERE (suit & $ownerMask) = $suitOwner");
        } else {
            $result['pieces'] = [];
        }

        return $result;
    }

    function getGameProgression()
    {
        return 50;
    }

    static function getRange(int $value): int
    {
        switch ($value) {
            case 0:
            case 9: return 3;
            case 8: return 4;
            default: return 2;
        }
    }

    static function canCapture(array $piece, array $target): bool
    {
        if (((int)$piece['suit'] & Suit::OWNER_MASK) === ((int)$target['suit'] & Suit::OWNER_MASK)) {
            return false;
        }

        $pieceValue = (int)$piece['value'];
        $targetValue = (int)$target['value'];
        return $pieceValue === 0
            || 11 <= $pieceValue && $pieceValue <= 13 && 11 <= $targetValue && $targetValue <= 13
                && ($pieceValue - $targetValue + 3) % 3 === 1
            || 11 <= $pieceValue && $pieceValue <= 13 && 7 <= $targetValue && $targetValue <= 10
                && !($pieceValue === 11 && $targetValue === 7)
            || 7 <= $pieceValue && $pieceValue <= 10 && $targetValue === 0
            || 7 <= $pieceValue && $pieceValue <= 10 && 7 <= $targetValue && $targetValue < $pieceValue
            || $pieceValue === 7 && $targetValue === 11;
    }

    static function isValidSpace(int $x, int $y): bool
    {
        $offset = ($y + 1) >> 1;
        $width = BOARD_SIZE[0] - ($y & 0x1);
        return 0 <= $y && $y < BOARD_SIZE[1]
            && $offset <= $x && $x < $offset + $width;
    }

    static function isValidDeploymentSpace(int $x, int $y, int $side): bool
    {
        $offset = ($y + 1) >> 1;
        $width = BOARD_SIZE[0] - ($y & 0x1);
        $y += $side * (BOARD_SIZE[1] - 1 - 2 * $y);

        return 0 <= $y && $y < BOARD_SIZE[2]
            && $offset <= $x && $x < $offset + $width;
    }

    static function getRescueCount(int $x, int $y, int $side, int $value): int
    {
        $lastSpace = BOARD_SIZE[1] - 1;
        if ($y === $lastSpace * (1 - $side)) {
            if (!in_array($value, WINNING_VALUES, true)) {
                return 2;
            }
        } else {
            foreach (PLAYER_BASES[(1 - $side)] as [$sx, $sy]) {
                if ($sx === $x && $sy === $y) {
                    return 1;
                }
            }
        }
        return 0;
    }

    static function canRescue(int $side): bool {
        $checks = [];
        foreach (PLAYER_BASES[$side] as [$x, $y]) {
            $checks[] = "x = $x AND y = $y";
        }
        $args = implode(' OR ', $checks);

        $ownerMask = Suit::OWNER_MASK;
        $suitOwner = $side << 1;

        ['bases' => $bases, 'pieces' => $pieces] = self::getObjectFromDb(<<<EOF
            SELECT 
                2 - (SELECT COUNT(*) FROM piece WHERE $args) AS bases,
                (SELECT COUNT(*) FROM piece WHERE (suit & $ownerMask) = $suitOwner) AS pieces
            EOF);

        return (int)$bases > 0 && (int)$pieces < 2 * count(PIECE_VALUES);
    }

    function deploy(array $deployments) {
        self::checkAction('deploy');
        $playerId = (int)self::getCurrentPlayerId();

        if (count($deployments) !== 2 * count(PIECE_VALUES)) {
            throw new BgaUserException('Invalid piece count');
        }

        $values = [];
        $side = self::getPlayerNoById($playerId) - 1;
        $suitOwner = $side << 1;

        foreach ($deployments as $i => [$x, $y]) {
            if (!self::isValidDeploymentSpace($x, $y, $side)) {
                throw new BgaUserException('Invalid space');
            }
            $suit = $suitOwner + intdiv($i, count(PIECE_VALUES));
            $value = PIECE_VALUES[$i % count(PIECE_VALUES)];
            $values[] = "($suit, $value, $x, $y)";
        }

        $args = implode(',', $values);
        self::DbQuery("INSERT INTO piece(suit, value, x, y) VALUES $args");
        $this->gamestate->setPlayerNonMultiactive($playerId, '');
    }

    function cancel(): void
    {
        $this->gamestate->checkPossibleAction('cancel');
        if (!(self::isSpectator() || self::isCurrentPlayerZombie())) {
            $playerId = (int)self::getCurrentPlayerId();
            if (!$this->gamestate->isPlayerActive($playerId)) {
                $suitOwner = (self::getPlayerNoById($playerId) - 1) << 1;
                self::DbQuery(
                    "DELETE FROM piece WHERE suit & $suitOwner <> 0");
                $this->gamestate->setPlayersMultiactive([$playerId], '');
            }
        }
    }

    function move(int $x, int $y, array $steps, bool $retreat): void
    {
        self::checkAction("move");

        $playerId = self::getActivePlayerId();
        $tx = $x;
        $ty = $y;
        $checks = [];

        foreach ($steps as $i => $step) {
            if ($i > 0) {
                $checks[] = "x = $tx AND y = $ty";
            }
            [$dx, $dy] = HEX_DIRECTIONS[$step];
            $tx += $dx;
            $ty += $dy;
            if (!self::isValidSpace($tx, $ty)) {
                throw new BgaUserException('Invalid path');
            }
        }

        if ($tx === $x && $ty === $y) {
            throw new BgaUserException('Empty move path');
        }

        if (count($checks) > 0) {
            $args = implode(' OR ', $checks);
            $obstacles =  (int)self::getUniqueValueFromDb(
                "SELECT COUNT(*) FROM piece WHERE $args");
            if ($obstacles > 0) {
                throw BgaUserException('Path blocked');
            }
        }

        $side = self::getPlayerNoById($playerId) - 1;
        $suitOwner = $side << 1;
        $ownerMask = Suit::OWNER_MASK;
        $king = 13;

        $pieces = self::getObjectListFromDb(<<<EOF
            SELECT * FROM piece AS move
            WHERE x = $x AND y = $y AND (suit & $ownerMask) = $suitOwner 
               AND (value = 0 OR (SELECT COUNT(*) FROM piece AS kings
                    WHERE kings.suit = move.suit AND kings.value = $king) <> 0)
               OR x = $tx AND y = $ty
            ORDER BY ABS(CAST(x AS SIGNED) - $x) 
                     + ABS(CAST(y AS SIGNED) - $y) ASC
            LIMIT 2
            EOF);

        $movedPiece = $pieces[0] ?? null;
        $capturedPiece = $pieces[1] ?? null;

        if ($movedPiece === null
            || (int)$movedPiece['x'] !== $x
            || (int)$movedPiece['y'] !== $y)
        {
            throw new BgaUserException('Invalid piece');
        }

        $movedSuitBit = 1 << ((int)$movedPiece['suit'] & (~Suit::OWNER_MASK));

        if ((int)self::getGameStateValue(Globals::MOVED_SUITS) & $movedSuitBit) {
            throw new BgaUserException('Suit already moved');
        }

        if (self::getRange($movedPiece['value']) < count($steps)) {
            throw new BgaUserException('Path out of range');
        }

        if ($capturedPiece !== null) {
            if (!self::canCapture($movedPiece, $capturedPiece)) {
                throw new BgaUserException('Capture not allowed');
            }

            self::DbQuery(<<<EOF
                DELETE FROM piece
                WHERE x = $tx AND y = $ty
                EOF
            );
        }

        self::incGameStateValue(Globals::MOVED_SUITS, $movedSuitBit);

        if ($capturedPiece === null || (int)$movedPiece['value'] <> 0 || !$retreat) {
            self::DbQuery(<<<EOF
            UPDATE piece
            SET x = $tx, y = $ty
            WHERE x = $x AND y = $y
            EOF);
        } else {
            $tx = $x;
            $ty = $y;
        }

        $rescue = self::getRescueCount($tx, $ty, $side, $movedPiece['value']) > 0
            && self::canRescue($side) > 0;
        if ($rescue) {
            $rescuerValue = $tx + ($ty << 8)
                + ((int)$movedPiece['suit'] << 16)
                + ((int)$movedPiece['value'] << 24);
            self::setGameStateValue(Globals::RESCUER, $rescuerValue);
        }

        $message = $capturedPiece ?
            clienttranslate('${player_name} moves ${pieceIcon} to (${x},${y}) and captures ${pieceIconC}') :
            clienttranslate('${player_name} moves ${pieceIcon} to (${x},${y})');

        $args = [
            'player_name' => self::getActivePlayerName(),
            'movedPiece' => [
                'suit' => $movedPiece['suit'],
                'value' => $movedPiece['value']
            ],
            'x' => $tx,
            'y' => $ty,
            'pieceIcon' => "$movedPiece[suit],$movedPiece[value]",
            'preserve' => ['pieceIcon', 'x', 'y']
        ];

        if ($capturedPiece) {
            $args['capturedPiece'] = [
                'suit' => $capturedPiece['suit'],
                'value' => $capturedPiece['value']
            ];
            $args['pieceIconC'] = "$capturedPiece[suit],$capturedPiece[value]";
            $args['preserve'][] = ['pieceIconC'];
        }

        self::notifyAllPlayers('move', $message, $args);

        $this->gamestate->nextState($rescue ? 'rescue' : 'next');
    }

    function rescue(array $pieces): void
    {
        self::checkAction('rescue');

        $rescuer = (int)self::getGameStateValue(Globals::RESCUER);
        $x = $rescuer & 0xFF;
        $y = ($rescuer >> 8) & 0xFF;
        $rescuerSuit = ($rescuer >> 16) & 0xFF;
        $rescuerValue = ($rescuer >> 24) & 0xFF;

        $side = self::getPlayerNoById(self::getActivePlayerId()) - 1;
        $count = self::getRescueCount($x, $y, $side, -1);

        if (count($pieces) === 0 || count($pieces) > $count) {
            throw new BgaUserException('Invalid rescue count');
        }

        self::DbQuery(
            "DELETE FROM piece WHERE x = $x AND y = $y");

        $values = [];
        $rescues = [];
        $icons = [];

        foreach ($pieces as [$suit, $value, $baseIndex]) {
            if (!in_array($value, PIECE_VALUES, true)) {
                throw new BgaUserException("Invalid piece value");
            }
            if (($suit & Suit::OWNER_MASK) !== ($side << 1)) {
                throw new BgaUserException("Invalid piece suit");
            }
            [$baseX, $baseY] = PLAYER_BASES[$side][$baseIndex];
            $values[] = "($value, $suit, $baseX, $baseY)";
            $rescues[] = [
                'value' => $value,
                'suit' => $suit,
                'x' => $baseX,
                'y' => $baseY
            ];
            $icons[] = "$suit,$value";
        }

        $args = implode(',', $values);
        self::DbQuery("INSERT INTO piece(value, suit, x, y) VALUES $args");

        self::notifyAllPlayers('rescue', clienttranslate('${player_name} exchanges ${pieceIcon} for ${pieceIcons}'), [
            'player_name' => self::getActivePlayerName(),
            'capturedPiece' => [
                'suit' => $rescuerSuit,
                'value' => $rescuerValue
            ],
            'rescuedPieces' => $rescues,
            'pieceIcon' => "$rescuerSuit,$rescuerValue",
            'pieceIcons' => implode(',', $icons),
            'preserve' => ['pieceIcon', 'pieceIcons']
        ]);

        $this->gamestate->nextState('');
    }

    function pass(): void
    {
        self::checkAction('pass');

        if ((int)$this->gamestate->state_id() === State::MOVE) {
            self::setGameStateValue(Globals::MOVED_SUITS, 0b11);
        }
        $this->gamestate->nextState('next');
    }

    function undo(): void
    {

    }

    static function checkGameEnd(): bool
    {
        $ownerMask = Suit::OWNER_MASK;
        $queen = 12;
        $king = 13;
        $ace = 0;
        $lastSpace = BOARD_SIZE[1] - 1;

        $playerId = self::getUniqueValueFromDb(<<<EOF
            SELECT player_id 
            FROM player LEFT JOIN piece 
                ON player_no = 1 + ((suit & $ownerMask) >> 1)
                    AND value in ($king, $ace)        
            GROUP BY player_id
            HAVING COUNT(value) = 0
            ORDER BY player_no ASC
            LIMIT 1
            EOF);

        if ($playerId !== null) {
            self::DbQuery(<<<EOF
                UPDATE player 
                SET player_score = 1
                WHERE player_id <> $playerId;
                EOF);
            return true;
        }

        $playerId = self::getUniqueValueFromDb(<<<EOF
            SELECT player_id 
            FROM piece INNER JOIN player  
                ON player_no = 1 + ((suit & $ownerMask) >> 1)
            WHERE value IN ($queen, $king, $ace)
                AND y = $lastSpace * (2 - player_no) 
            GROUP BY player_id
            HAVING COUNT(*) > 0
            ORDER BY player_no DESC
            LIMIT 1
            EOF);

        if ($playerId !== null) {
            self::DbQuery(<<<EOF
                UPDATE player 
                SET player_score = 1
                WHERE player_id = $playerId;
                EOF);
            return true;
        }

        return false;
    }

    function stReveal(): void
    {
        $pieces = self::getObjectListFromDb('SELECT * FROM piece');
        self::notifyAllPlayers('deploy',
            clienttranslate('Piece positions are revealed'),
            ['pieces' => $pieces]);
        $this->gamestate->nextState('');
    }

    function stNextTurn(): void
    {
        if (self::checkGameEnd()) {
            $this->gamestate->nextState('end');
            return;
        }

        $isFirstMove = (int)self::getGameStateValue(Globals::FIRST_MOVE);
        $movedSuits = (int)self::getGameStateValue(Globals::MOVED_SUITS);

        if ($isFirstMove) {
            if ($movedSuits) {
                self::setGameStateValue(Globals::FIRST_MOVE, 0);
            }
            self::activeNextPlayer();
        } else if ($movedSuits === 0b11) {
            self::setGameStateValue(Globals::MOVED_SUITS, 0);
            self::activeNextPlayer();
        }
        $this->gamestate->nextState('move');
    }

    function argMove(): array {
        return [
            'movedSuits' => self::getGameStateValue(Globals::MOVED_SUITS)
        ];
    }

    function argRescue(): array {
        $rescuer = (int)self::getGameStateValue(Globals::RESCUER);
        $x = $rescuer & 0xFF;
        $y = ($rescuer >> 8) & 0xFF;
        $side = self::getPlayerNoById(self::getActivePlayerId()) - 1;

        return [
            'x' => $x,
            'y' => $y,
            'side' => $side,
            'rescueCount' => self::getRescueCount($x, $y, $side, -1)
        ];
    }


    function zombieTurn($state, $activePlayer)
    {
        $stateName = $state['name'];

        if ($state['type'] === FsmType::SINGLE_PLAYER) {
            self::setGameStateValue(Globals::MOVED_SUITS, 0b11);
            $this->gamestate->jumpToState(State::NEXT_TURN);
        } else if ($state['type'] === FsmType::MULTIPLE_PLAYERS) {
            $this->gamestate->setPlayerNonMultiactive($activePlayer, '');
        } else {
            throw new feException("Zombie mode not supported at this game state: $stateName");
        }
    }

    function upgradeTableDb($from_version)
    {

    }
}
