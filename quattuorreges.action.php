<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * QuattuorReges implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 * 
 * quattuorreges.action.php
 *
 * QuattuorReges main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/quattuorreges/quattuorreges/myAction.html", ...)
 *
 */
  
  
class action_quattuorreges extends APP_GameAction
{
// Constructor: please do not modify
    public function __default()
    {
        if (self::isArg('notifwindow')) {
            $this->view = 'common_notifwindow';
            $this->viewArgs['table'] = self::getArg('table', AT_posint, true);
        } else {
            $this->view = 'hamletthevillagebuildinggame_hamletthevillagebuildinggame';
            self::trace( 'Complete reinitialization of board game' );
        }
    }

    public function cancel()
    {
        self::setAjaxMode();
        $this->game->cancel();
        self::ajaxResponse();
    }

    public function deploy()
    {
        self::setAjaxMode();
        $args = self::getArg('positions', AT_numberllist, true);
        $deployments = array_map(
            fn($piece) => array_map(
                fn($value) => (int)$value,
                explode(',', $piece)),
            explode(';', $args));
        $this->game->deploy($deployments);
        self::ajaxResponse();
    }

    public function move()
    {
        self::setAjaxMode();
        $pieceId = self::getArg('pieceId', AT_posint, true);
        $x = self::getArg('x', AT_posint, true);
        $y = self::getArg('y', AT_posint, true);
        $stepsList = self::getArg('steps', AT_numberllist, true);
        $steps = array_map(fn($d) => (int)$d, explode(',', $stepsList));
        $this->game->move($pieceId, $x, $y, $steps);
        self::ajaxResponse();
    }

    public function moveAce()
    {
        self::setAjaxMode();
        $x = self::getArg('x', AT_posint, true);
        $y = self::getArg('y', AT_posint, true);
        $stepsList = self::getArg('steps', AT_numberllist, true);
        $steps = array_map(fn($d) => (int)$d, explode(',', $stepsList));
        $this->game->moveAce($x, $y, $steps);
        self::ajaxResponse();
    }
}
  

