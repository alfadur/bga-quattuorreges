/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * QuattuorReges implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter"
], (dojo, declare) => declare("bgagame.quattuorreges", ebg.core.gamegui, {
    constructor() {
        console.log("quattuorreges constructor");
    },

    setup(data) {
        console.log("Starting game setup");

        const isBlackPlayer = parseInt(data.players[this.getCurrentPlayerId().toString()].no);
        document.getElementById("qtr-board").classList.add(
            isBlackPlayer ? "qtr-black-player" : "qtr-red-player"
        );

        this.setupNotifications();

        console.log("Ending game setup");
    },

    onEnteringState(stateName, args) {
        console.log(`Entering state: ${stateName}`);

        switch (stateName) {

        }
    },

    onLeavingState(stateName) {
        console.log(`Leaving state: ${stateName}`);

        switch (stateName) {

        }
    },

    onUpdateActionButtons(stateName, args) {
        console.log(`onUpdateActionButtons: ${stateName}`);

        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
            }
        }
    },

    setupNotifications() {
        console.log("notifications subscriptions setup");
    }
}));
