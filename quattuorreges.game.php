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
            Globals::REMAINING_MOVES, Globals::REMAINING_MOVES_ID,
            Globals::REPEATED_ACE, Globals::REPEATED_ACE_ID
        ]);
    }

    protected function getGameName( )
    {
        // Used for translations and stuff. Please do not modify.
        return "quattuorreges";
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

        self::setInitialGameStateValue(Globals::FIRST_MOVE, 1);
        self::setInitialGameStateValue(Globals::REMAINING_MOVES, 0);
        self::setInitialGameStateValue(Globals::REPEATED_ACE, 0);
    }

    protected function getAllDatas()
    {
        $result = [];

        $sql = 'SELECT player_id id, player_score score FROM player ';
        $result['players'] = self::getCollectionFromDb($sql);
        $result['pieces'] = self::getObjectListFromDb('SELECT x, y FROM piece');

        return $result;
    }

    function getGameProgression()
    {
        return 0;
    }

    static function checkPosition(int $x, int $y) {
        if (false) {
            throw new BgaUserException('Invalid path');
        }
    }

    static function getRange(object $piece): int
    {
        $value = (int)$piece['value'];
        switch ($value) {
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
        $targetValue = (int)$piece['value'];
        return $pieceValue === 0 && 11 <= $targetValue && $targetValue <= 13
            || 11 <= $pieceValue && $pieceValue <= 13 && 11 <= $targetValue && $targetValue <= 13
                && ($pieceValue - $targetValue + 3) % 3 === 1
            || 11 <= $pieceValue && $pieceValue <= 13 && 7 <= $targetValue && $targetValue <= 10
            || 7 <= $pieceValue && $pieceValue <= 10 && $targetValue === 0
            || 7 <= $pieceValue && $pieceValue <= 10 && 7 <= $targetValue && $targetValue < $pieceValue
            || $pieceValue === 7 && $targetValue === 11;
    }

    function deploy(array $deployments) {
        self::checkAction('deploy');
        $playerId = (int)self::getCurrentPlayerId();

        if (count($deployments) !== 2 * count(DEPLOYMENT_VALUES)) {
            throw new BgaUserException('Invalid piece count');
        }

        $values = [];
        $suitOwner = (self::getPlayerNoById($playerId) - 1) << 1;

        foreach ($deployments as $i => [$x, $y]) {
            $suit = $suitOwner + intdiv($i, count(DEPLOYMENT_VALUES));
            $value = DEPLOYMENT_VALUES[$i % count(DEPLOYMENT_VALUES)];
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

    function moveImpl(int $pieceId, int $x, int $y, array $steps): void
    {
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
            self::checkPosition($tx, $ty);
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

        $suitOwner = (self::getPlayerNoById($playerId) - 1) << 1;
        [$movedPiece, $capturedPiece] = self::getObjectListFromDb(<<<EOF
            SELECT * FROM piece 
            WHERE piece_id = $pieceId AND x = $x AND y = $y AND suit & $suitOwner <> 0 
               OR piece_id <> $pieceId AND x = $tx AND y = $ty
            ORDER BY ABS(piece_id - $pieceId) ASC
            LIMIT 2
            EOF);

        if ((int)$movedPiece['piece_id'] <> $pieceId) {
            throw new BgaUserException('Invalid piece');
        }

        if (self::getRange($movedPiece['value']) < count($steps)) {
            throw new BgaUserException('Path out of range');
        }

        if ($capturedPiece !== null && !self::canCapture($movedPiece, $capturedPiece)) {
            throw new BgaUserException('Capture not allowed');
        }

        $repeatedAce = 0;
        if ($capturedPiece !== null) {
            self::DbQuery(<<<EOF
                    DELETE FROM piece
                    WHERE piece_id = $capturedPiece[piece_id]
                    EOF
            );
            if ((int)$movedPiece['value'] === 0) {
                $repeatedAce = (int)$movedPiece['piece_id'];
            }
        }

        self::setGameStateValue(Globals::REPEATED_ACE, $repeatedAce);

        if ($repeatedAce !== 0) {
            self::incGameStateValue(Globals::REMAINING_MOVES, -1);
        }

        self::DbQuery(<<<EOF
            UPDATE piece
            SET x = $x, y = $y
            WHERE piece_id = $pieceId                
            EOF);
    }

    function move(int $pieceId, int $x, int $y, array $steps): void
    {
        self::checkAction('move');
        self::moveImpl($pieceId, $x, $y, $steps);
        $this->gamestate->nextState('move');
    }

    function moveAce(int $x, int $y, array $steps): void
    {
        self::checkAction('moveAce');
        $aceId = self::getGameStateValue(Globals::REPEATED_ACE);
        self::moveImpl($aceId, $x, $y, $steps);
        $this->gamestate->nextState('moveAce');
    }

    static function checkGameEnd(): bool
    {
        $ownerMask = Suit::OWNER_MASK;
        $king = 13;
        $playerId = self::getCollectionFromDb(<<<EOF
            SELECT player_id 
            FROM player LEFT JOIN piece 
                ON player_no = 1 + ((suit & $ownerMask) >> 1)
            WHERE value IN ($king, NULL)
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

        return false;
    }

    function stNextTurn(): void
    {
        if (self::checkGameEnd()) {
            $this->gamestate->nextState('end');
            return;
        }

        $isFirstMove = self::getGameStateValue(Globals::FIRST_MOVE);

        if ($isFirstMove) {
            self::setGameStateValue(Globals::FIRST_MOVE, 0);
            self::setGameStateValue(Globals::REMAINING_MOVES, 1);
            self::activeNextPlayer();
        } else {
            $remainingMoves = self::getGameStateValue(Globals::REMAINING_MOVES);
            if ($remainingMoves === 0) {
                self::setGameStateValue(Globals::REMAINING_MOVES, 2);
                self::activeNextPlayer();
            }
        }
        $transition = self::getGameStateValue(Globals::REPEATED_ACE) === 0 ? 'move' : 'moveAce';
        $this->gamestate->nextState($transition);
    }


    function zombieTurn($state, $activePlayer)
    {
        $stateName = $state['name'];

        if ($state['type'] === FsmType::SINGLE_PLAYER) {
            self::setGameStateValue(Globals::REMAINING_MOVES, 0);
            $this->gamestate->nextState( '' );
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
