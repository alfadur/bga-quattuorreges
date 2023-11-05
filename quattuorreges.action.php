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
            $this->view = 'quattuorreges_quattuorreges';
            self::trace( 'Complete reinitialization of board game' );
        }
    }

    public function cancel()
    {
        self::setAjaxMode();
        $this->game->cancel();
        self::ajaxResponse();
    }

    function parseList(string $args, int $size): array
    {
        $values = array_map(
            fn($value) => (int)$value,
            explode(',', $args));
        return $size > 1 ? array_chunk($values, $size) : $values;
    }

    public function deploy()
    {
        self::setAjaxMode();
        $args = self::getArg('positions', AT_numberlist, true);
        $this->game->deploy($this->parseList($args, 2));
        self::ajaxResponse();
    }

    public function move()
    {
        self::setAjaxMode();
        $x = self::getArg('x', AT_posint, true);
        $y = self::getArg('y', AT_posint, true);
        $retreat = self::getArg('retreat', AT_bool, true);
        $steps = self::getArg('steps', AT_numberlist, true);
        $this->game->move($x, $y, $this->parseList($steps, 1), $retreat);
        self::ajaxResponse();
    }

    public function rescue()
    {
        self::setAjaxMode();
        $args = self::getArg('pieces', AT_numberlist, true);
        $this->game->rescue($this->parseList($args, 3));
        self::ajaxResponse();
    }

    public function pass()
    {
        self::setAjaxMode();
        $this->game->pass();
        self::ajaxResponse();
    }

    public function undo()
    {
        self::setAjaxMode();
        $this->game->undo();
        self::ajaxResponse();
    }
}
  

