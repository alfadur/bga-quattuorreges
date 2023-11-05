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
            Globals::FIRST_MOVE, Globals::FIRST_MOVE_ID,
            Globals::MOVED_SUITS, Globals::MOVED_SUITS_ID,
            Globals::RESCUER, Globals::RESCUER_ID,
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
            self::setInitialGameStateValue($name, $value);
        }
    }

    protected function getAllDatas()
    {
        $result = [
            'players' => self::getObjectListFromDb(
                'SELECT player_id FROM player',
                true)
        ];

        if ($this->gamestate->state_id() !== State::SETUP) {
            $result['players'] = self::getObjectListFromDb(
                'SELECT player_id FROM player',
                true);
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

    static function canCapture(object $piece, object $target): bool
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

    static function getRescueCount(int $x, int $y, int $side): bool
    {
        $lastSpace = BOARD_SIZE[1] - 1;
        if ($y === $lastSpace * $side) {
            return 2;
        }
        foreach (PLAYER_BASES[$side] as [$sx, $sy]) {
            if ($sx === $x && $sy === $y) {
                return 1;
            }
        }
        return 0;
    }

    function deploy(array $deployments) {
        self::checkAction('deploy');
        $playerId = (int)self::getCurrentPlayerId();

        if (count($deployments) !== 2 * count(PIECE_VALUES)) {
            throw new BgaUserException('Invalid piece count');
        }

        $values = [];
        $suitOwner = (self::getPlayerNoById($playerId) - 1) << 1;

        foreach ($deployments as $i => [$x, $y]) {
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
        $this->gamestate->nextState('move');
        $playerId = self::getActivePlayerId();
        $tx = $x;
        $ty = $y;
        $checks = [];

        foreach ($steps as $i => $step) {
            if ($i > 0) {
                $checks[] = "x = $tx AND y = $ty";
            }
            [$dx, $dy] = HexDirection::ALL[$step];
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
        [$movedPiece, $capturedPiece] = self::getObjectListFromDb(<<<EOF
            SELECT * FROM piece 
            WHERE x = $x AND y = $y AND suit & $suitOwner <> 0 
               AND (SELECT COUNT(*) FROM piece AS kings
                    WHERE kings.suit = suit) <> 0
               OR x = $tx AND y = $ty
            ORDER BY ABS(x - $x) + ABS(y - $y) ASC
            LIMIT 2
            EOF);

        if ($movedPiece <> null && (int)$movedPiece['x'] <> $x || (int)$movedPiece['y'] <>
        $y) {
            throw new BgaUserException('Invalid piece');
        }

        $movedSuitBit = 1 << ((int)$movedPiece['suit'] & (~Suit::OWNER_MASK));

        if (self::getGameStateValue(Globals::MOVED_SUITS) & $movedSuitBit) {
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

        $rescue = self::getRescueCount($tx, $ty, $side) > 0;
        if ($rescue) {
            self::setGameStateValue(Globals::RESCUER, $tx + ($ty << 8));
        }

        $this->gamestate->nextState($rescue ? 'rescue' : 'next');
    }

    function rescue(array $pieces): void
    {
        self::checkAction('rescue');

        $rescuer = self::getGameStateValue(Globals::RESCUER);
        $x = $rescuer && 0xFF;
        $y = ($rescuer >> 8) && 0xFF;

        $side = self::getPlayerNoById(self::getActivePlayerId()) - 1;
        $count = self::getRescueCount($x, $y, $side);

        if (count($pieces) === 0 || count($pieces) > $count) {
            throw new BgaUserException('Invalid rescue count');
        }

        self::DbQuery(
            'DELETE FROM piece WHERE x = $x AND $y = $y');

        $values = [];
        foreach ($pieces as [$value, $suit, $baseIndex]) {
            if (!in_array($value, PIECE_VALUES)) {
                throw new BgaException("Invalid piece value");
            }
            if (($suit & Suit::OWNER_MASK) !== ($side << 1)) {
                throw new BgaException("Invalid piece suit");
            }
            [$x, $y] = PLAYER_BASES[$side][$baseIndex];
            $values[] = "($value, $suit, $x, $y)";
        }

        $args = implode(',', $values);
        self::DbQuery("INSERT INTO piece(value, suit, x, y) VALUES $args");

        $this->gamestate->nextState('');
    }

    function pass(): void
    {
        self::checkAction('pass');
        if ($this->gamestate->state_id() === State::MOVE) {
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
            WHERE value IN (NULL, $king, $ace)
            GROUP BY player_id
            HAVING COUNT(value) = 0
            SORT BY player_no ASC
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
                AND y = $lastSpace * (player_no - 1) 
            GROUP BY player_id
            HAVING COUNT(value) > 0
            SORT BY player_no DESC
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

    function stNextTurn(): void
    {
        if (self::checkGameEnd()) {
            $this->gamestate->nextState('end');
            return;
        }

        $isFirstMove = self::getGameStateValue(Globals::FIRST_MOVE);
        $movedSuits = self::getGameStateValue(Globals::MOVED_SUITS);

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
