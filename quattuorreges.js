/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * QuattuorReges implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

const pieceValues = Object.freeze({
    "0": "A",
    "7": "7",
    "8": "8",
    "9": "9",
    "10": "10",
    "11": "J",
    "12": "Q",
    "13": "K"
});

const suitValues = Object.freeze({
    "0": "♥",
    "1": "♦",
    "2": "♠",
    "3": "♣",
})

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

        const playerId = this.getCurrentPlayerId().toString();
        const order = parseInt(data.players[playerId].no) - 1;
        const board = document.getElementById("qtr-board");
        board.dataset.order = order.toString();
        board.classList.add(
            order > 0 ? "qtr-black-player" : "qtr-red-player"
        );

        for (const space of document.querySelectorAll(".qtr-board-space")) {
            space.addEventListener("click", (event) => {
                event.stopPropagation();
                this.onSpaceClick(
                    space,
                    parseInt(space.dataset.x),
                    parseInt(space.dataset.y)
                )
            });
        }

        for (const {suit, value, x, y} of data.pieces) {
            const space = document.getElementById(`qtr-board-space-${x}-${y}`);
            const playerClass = (parseInt(suit) & 0x2) === 0 ?
                "qtr-red-player" : "qtr-black-player";
            const content = `${pieceValues[value]}<br>${suitValues[suit]}`;
            const piece = `<div id="qtr-piece-${suit}-${value}" class="qtr-piece ${playerClass}">${content}</div>`;
            dojo.place(piece, space);
        }

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
                case 'setup':
                    this.addActionButton('qtr-deploy', _("Deploy pieces"), event => {
                        dojo.stopEvent(event);
                        this.deployPieces();
                    });
            }
        } else {
            switch (stateName) {
                case 'setup':
                    this.addActionButton('qtr-cancel', _("Cancel"), event => {
                        dojo.stopEvent(event);
                        this.cancelDeploy();
                    }, null, null, "grey");
            }
        }
    },

    onSpaceClick(space, x, y) {
        console.log(`Space click (${x}, ${y})`);
        if (this.checkAction("deploy", true)) {
            space.classList.toggle("qtr-selected");
        }
    },

    deployPieces() {
        const spaces = Array.from(document.querySelectorAll(".qtr-board-space.qtr-selected"));
        const positions = spaces
            .map(s => `${s.dataset.x},${s.dataset.y}`)
            .join(",");
        this.ajaxcall("/quattuorreges/quattuorreges/deploy.html", {
            positions,
            lock: true,
        }, () => {});
    },

    cancelDeploy() {
        this.ajaxcall("/quattuorreges/quattuorreges/cancel.html", {
            lock: true,
        }, () => {});
    },

    setupNotifications() {
        console.log("notifications subscriptions setup");
    }
}));
