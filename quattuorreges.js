/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * QuattuorReges implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

const deploymentValues = [7, 8, 9, 10, 11, 12, 13, 0];
const deploymentSuits = [0, 1, 2, 3];

const pieceValues = Object.freeze({
    "7": "7",
    "8": "8",
    "9": "9",
    "10": "10",
    "11": "J",
    "12": "Q",
    "13": "K",
    "0": "A",
});

const pieceSuits = Object.freeze({
    "0": "♥",
    "1": "♦",
    "2": "♠",
    "3": "♣",
});

const playerBases = {
    "red": ["qtr-board-space-7-3", "qtr-board-space-15-3"],
    "black": ["qtr-board-space-8-11", "qtr-board-space-16-11"]
}

const moveDirections = Object.freeze([[1, 0], [1, 1], [0, 1], [-1, 0], [-1, -1], [0, -1]]
    .map(([x, y]) => Object.freeze({x, y})));

function clearTag(tag) {
    for (const element of document.querySelectorAll(`.${tag}`)) {
        element.classList.remove(tag);
    }
}

function getFreeBases(playerColor) {
    return playerBases[playerColor]
        .map(id => document.getElementById(id))
        .filter(base => base.children.length === 0);
}

function getCapturedPieces(playerColor) {
    return document.querySelectorAll(
        `.qtr-captures[data-color="${playerColor}"] .qtr-piece`)
}

function getPieceRange(pieceValue) {
    pieceValue = parseInt(pieceValue);
    return pieceValue === 0 || pieceValue === 9 ? 3 :
        pieceValue === 8 ? 4 : 2;
}

function *spacesAround(space) {
    let index = 0;
    for (const {x, y} of moveDirections) {
        yield [index++,{
            x: parseInt(space.x) + x,
            y: parseInt(space.y) + y
        }];
    }
}

function getPlayerColor(suit) {
    return (parseInt(suit) & 0x2) === 0 ?  "red" : "black";
}

function createPiece(suit, value) {
    const content = `${pieceValues[value]}<br>${pieceSuits[suit]}`;
    return `<div id="qtr-piece-${suit}-${value}"
        class="qtr-piece" 
        data-color="${getPlayerColor(suit)}"
        data-suit="${suit}"
        data-value="${value}">${content}</div>`;
}

function canCapture(pieceValue, targetValue) {
    pieceValue = parseInt(pieceValue);
    targetValue = parseInt(targetValue);
    return pieceValue === 0
        || 11 <= pieceValue && pieceValue <= 13 && 11 <= targetValue && targetValue <= 13
        && (pieceValue - targetValue + 3) % 3 === 1
        || 11 <= pieceValue && pieceValue <= 13 && 7 <= targetValue && targetValue <= 10
        && !(pieceValue === 11 && targetValue === 7)
        || 7 <= pieceValue && pieceValue <= 10 && targetValue === 0
        || 7 <= pieceValue && pieceValue <= 10 && 7 <= targetValue && targetValue < pieceValue
        || pieceValue === 7 && targetValue === 11;
}

class Queue {
    values = [];
    offset = 0;

    isEmpty() { return this.offset === this.values.length; }
    enqueue(value) { this.values.push(value); }
    dequeue() { return this.isEmpty() ? undefined : this.values[this.offset++]; }
}

function* collectPaths(x, y, range) {
    const queue = new Queue;
    const visited = new Set;

    const startPath = {
        space: {x: parseInt(x), y: parseInt(y)},
        steps: []
    };

    queue.enqueue(startPath);
    visited.add(JSON.stringify(startPath.space));

    while (!queue.isEmpty()) {
        const {space, steps} = queue.dequeue();
        if (steps.length < range) {
            for (const [directionIndex, newSpace] of spacesAround(space)) {
                const value = JSON.stringify(newSpace);

                if (!visited.has(value)) {
                    visited.add(value);
                    const newPath = {
                        space: newSpace,
                        steps: [...steps, directionIndex]
                    };
                    const isPassable = yield newPath;
                    if (isPassable === undefined || isPassable) {
                        queue.enqueue(newPath);
                    }
                }
            }
        }
    }
}

function prepareMove(x, y, color, pieceValue) {
    const result = [];

    const paths = collectPaths(x, y, getPieceRange(pieceValue));
    let item = paths.next();

    while (!item.done) {
        const path = item.value;
        const space = document.getElementById(
            `qtr-board-space-${path.space.x}-${path.space.y}`);
        if (space) {
            const target = space.firstChild;
            if (!target
                || (color !== target.dataset.color &&
                    canCapture(pieceValue, target.dataset.value)))
            {
                space.classList.add("qtr-selectable");
                result.push(path);
            }
        }

        item = paths.next(space && space.children.length === 0);
    }

    return result;
}

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter"
], (dojo, declare) => declare("bgagame.quattuorreges", ebg.core.gamegui, {
    constructor() {
        console.log("quattuorreges constructor");
        try {
            this.useOffsetAnimation = CSS.supports("offset-path", "path('M 0 0')");
        } catch (e) {
            this.useOffsetAnimation = false;
        }
    },

    setup(data) {
        console.log("Starting game setup");

        const playerId = this.getCurrentPlayerId().toString();
        const order = playerId in data.players ?
            parseInt(data.players[playerId].no) - 1 :
            0;
        const container = document.getElementById("qtr-board-container");
        this.playerColor = order > 0 ? "black" : "red";
        container.dataset.color = this.playerColor;

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
            const space = document.getElementById(
                `qtr-board-space-${x}-${y}`);
            space.innerHTML = createPiece(suit, value);
        }

        for (const suit of Object.keys(pieceSuits)) {
            for (const value of Object.keys(pieceValues)) {
                const id = `qtr-piece-${suit}-${value}`
                if (!document.getElementById(id)) {
                    const element = document.createElement("div");
                    document.querySelector(`.qtr-captures[data-color=${getPlayerColor(suit)}]`)
                        .appendChild(element);
                    element.outerHTML = createPiece(suit, value);
                }
            }
        }

        for (const piece of document.querySelectorAll(".qtr-piece")) {
            piece.addEventListener("click", (event) => {
                event.stopPropagation();
                this.onPieceClick(piece);
            });
        }

        for (const color of Object.keys(playerBases)) {
            playerBases[color].forEach((id, index) =>
                document.getElementById(id).dataset.baseIndex = index.toString());
        }

        this.setupNotifications();

        console.log("Ending game setup");
    },

    onEnteringState(stateName, state) {
        console.log(`Entering state: ${stateName}`);

        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
                case "move": {
                    const movedSuits = parseInt(state.args.movedSuits);
                    console.log(movedSuits);
                    const pieces = document.querySelectorAll(
                        `.qtr-board-space .qtr-piece[data-color="${this.playerColor}"]`);
                    for (const piece of pieces) {
                        const suitBit = 0x1 << (parseInt(piece.dataset.suit) & 0x1);

                        if ((movedSuits & suitBit) === 0) {
                            const king = document.querySelector(
                                `.qtr-piece[data-suit="${piece.dataset.suit}"][data-value="13"]`);

                            if (king.parentElement.classList.contains("qtr-board-space")
                                || piece.dataset.value === "0")
                            {
                                piece.parentElement.classList.add("qtr-selectable");
                            }
                        }
                    }
                    break;
                }
                case "clientMove": {
                    const space = this.selectedPiece.parentElement;
                    this.paths = prepareMove(
                        space.dataset.x,
                        space.dataset.y,
                        this.selectedPiece.dataset.color,
                        this.selectedPiece.dataset.value);
                    break;
                }
                case "rescue": {
                    console.log(state.args);
                    this.rescueCount = Math.min(
                        parseInt(state.args.rescueCount),
                        getFreeBases(this.playerColor).length,
                        getCapturedPieces(this.playerColor).length);
                    this.rescuedPieces = [];
                    this.setClientState("clientRescuePiece", {
                        descriptionmyturn: _("${you} must select a piece to rescue"),
                        possibleactions: ["clientRescuePiece"]
                    });
                    break;
                }
                case "clientRescuePiece": {
                    for (const piece of getCapturedPieces(this.playerColor)) {
                        piece.classList.add("qtr-selectable");
                    }
                    break;
                }
                case "clientRescueBase": {
                    for (const base of getFreeBases(this.playerColor)) {
                        base.classList.add("qtr-selectable");
                    }
                    break;
                }
            }
        }
    },

    onLeavingState(stateName) {
        console.log(`Leaving state: ${stateName}`);

        switch (stateName) {
            case "setup":
            case "clientMove":
            case "clientRescueBase": {
                if (this.unselectPiece()) {
                    clearTag("qtr-selectable");
                }
                break;
            }
            case "move":
            case "clientRescuePiece": {
                clearTag("qtr-selectable");
                break;
            }
        }
    },

    onUpdateActionButtons(stateName, args) {
        console.log(`onUpdateActionButtons: ${stateName}`);

        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
                case "setup": {
                    const spaces = document.querySelectorAll(
                        `.qtr-board-space[data-color="${this.playerColor}"]`);
                    for (const space of spaces) {
                        space.classList.add("qtr-selectable");
                    }
                    this.addActionButton("qtr-deploy", _("Deploy pieces"), event => {
                        event.stopPropagation();
                        this.deployPieces();
                    });
                    this.updateDeployment();
                    break;
                }

                case "move":
                case "clientRescuePiece":
                case "clientRescueBase": {
                    this.addActionButton("qtr-pass", _("Pass"), event => {
                        event.stopPropagation();
                        if (stateName !== "move" && this.rescuedPieces.length > 0) {
                            this.rescuePieces(this.rescuedPieces)
                        } else {
                            this.pass();
                        }
                    }, null, null, "gray");
                    break;
                }
                case "clientMove": {
                    this.addActionButton("qtr-cancel", _("Cancel"), event => {
                        event.stopPropagation();
                        this.restoreServerGameState();
                    }, null, null, "gray");
                }
            }
        } else if (!this.isSpectator) {
            switch (stateName) {
                case "setup": {
                    this.unselectPiece();
                    clearTag("qtr-selectable");
                    this.addActionButton("qtr-cancel", _("Cancel"), event => {
                        dojo.stopEvent(event);
                        this.cancelDeploy();
                    }, null, null, "gray");
                }
            }
        }
    },

    selectPiece(piece) {
        if (piece) {
            this.unselectPiece();
            this.selectedPiece = piece;
            this.selectedPiece.classList.add("qtr-selected");
            return true;
        }
        return false;
    },

    unselectPiece() {
        if (this.selectedPiece) {
            this.selectedPiece.classList.remove("qtr-selected");
            this.selectedPiece = undefined;
            return true;
        }
        return false;
    },

    updateDeployment() {
        const captures = getCapturedPieces(this.playerColor);
        const hasPieces = captures.length > 0;

        if (hasPieces) {
            this.selectPiece(captures[0]);
        } else if (!this.selectedPiece) {
            this.selectPiece(document.querySelector(
                `.qtr-piece[data-color="${this.playerColor}"]`));
        }

        document.getElementById("qtr-deploy").classList.toggle(
            "disabled", hasPieces);
    },

    addRescuedPiece(piece) {
        const freeBases = getFreeBases(this.playerColor);
        if (freeBases.length > 1) {
            this.selectPiece(piece);
            this.setClientState("clientRescueBase", {
                descriptionmyturn: _("${you} must select a base for the piece"),
                possibleactions: ["clientRescueBase"]
            });
        } else {
            this.rescuedPieces.push({
                suit: piece.dataset.suit,
                value: piece.dataset.value,
                base: freeBases[0].dataset.baseIndex
            });
            this.animateTranslation(piece, freeBases[0]);
            this.rescuePieces(this.rescuedPieces);
        }
    },

    onSpaceClick(space, x, y) {
        console.log(`Space click (${x}, ${y})`);
        if (!space.classList.contains("qtr-selectable")) {
            return;
        }

        if (this.checkAction("deploy", true)) {
            if (!this.selectPiece(space.children[0])
                && this.selectedPiece)
            {
                this.animateTranslation(this.selectedPiece, space);
                this.updateDeployment();
            }
        } else if (this.checkAction("move", true)) {
            const piece = space.children[0];
            if (this.selectPiece(piece)) {
                this.setClientState("clientMove", {
                    descriptionmyturn: _("${you} must select the destination space"),
                    possibleactions: ["clientMove"]
                });
            }
        } else if (this.checkAction("clientMove", true)) {
            if (space.classList.contains("qtr-selectable")) {
                const piece = document.querySelector(".qtr-piece.qtr-selected");
                const sourceSpace = piece.parentElement;
                const path = this.paths.filter(p =>
                    p.space.x === x && p.space.y === y)[0];

                this.ajaxcall("/quattuorreges/quattuorreges/move.html", {
                    x: sourceSpace.dataset.x,
                    y: sourceSpace.dataset.y,
                    steps: path.steps.join(","),
                    retreat: false,
                    lock: true
                }, () => {});
            }
        } else if (this.checkAction("clientRescuePiece", true)) {
            this.addRescuedPiece(space.children[0]);
        } else if (this.checkAction("clientRescueBase", true)) {
            this.rescuedPieces.push({
                suit: this.selectedPiece.dataset.suit,
                value: this.selectedPiece.dataset.value,
                base: space.dataset.baseIndex
            });
            this.animateTranslation(this.selectedPiece, space);
            if (--this.rescueCount > 0) {
                this.setClientState("clientRescuePiece", {
                    descriptionmyturn: _("${you} must select a piece to rescue"),
                    possibleactions: ["clientRescuePiece"]
                });
            } else {
                this.rescuePieces(this.rescuedPieces);
            }
        }
    },

    onPieceClick(piece) {
        if (!piece.classList.contains("qtr-selectable")) {
            return;
        }

        if (this.checkAction("clientRescuePiece", true)) {
            this.addRescuedPiece(piece);
        }
    },

    deployPieces() {
        const spaces = [];
        for (const suit of deploymentSuits) {
            for (const value of deploymentValues) {
                const space = document
                    .getElementById(`qtr-piece-${suit}-${value}`)
                    .parentElement;
                if (space.classList.contains("qtr-board-space")) {
                    spaces.push(space);
                }
            }
        }
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

    rescuePieces(pieces) {
        const args = pieces
            .map(p => `${p.suit},${p.value},${p.base}`)
            .join(",");
        this.ajaxcall("/quattuorreges/quattuorreges/rescue.html", {
            pieces: args,
            lock: true,
        }, () => {});
    },

    pass() {
        this.ajaxcall("/quattuorreges/quattuorreges/pass.html", {
            lock: true,
        }, () => {});
    },

    animateTranslation(node, destination) {
        const useOffsetAnimation = this.useOffsetAnimation;
        function stopAnimation() {
            if (useOffsetAnimation) {
                if (node.classList.contains("qtr-moving")) {
                    node.classList.remove("qtr-moving");
                    node.style.offsetPath = "unset";
                }
            } else if (node.classList.contains("qtr-moving-simple")) {
                node.classList.remove("qtr-moving-simple");
                node.style.transform = "none";
            }
        }
        stopAnimation();

        const startRect = node.getBoundingClientRect();
        destination.appendChild(node);
        const endRect = node.getBoundingClientRect();
        const [dX, dY] = [
            endRect.left - startRect.left,
            endRect.top - startRect.top
        ];

        if (this.useOffsetAnimation) {
            node.style.offsetPath =
                `path("M 0 0 Q ${-dX / 2 - dY / 8} ${-dY / 2 + dX / 8} ${-dX} ${-dY}")`;
            node.classList.add("qtr-moving");

            node.addEventListener("animationend", () => {
            }, {once: true});
        } else {
            node.style.transform = `translate(${-dX}px, ${-dY}px)`;
            node.classList.add("qtr-moving-simple");
        }

        node.addEventListener("animationend", stopAnimation, {once: true});
    },

    setupNotifications() {
        console.log("notifications subscriptions setup");

        function getPath(data, ...path) {
            let source = data.args;
            for (const item of path) {
                if (item in source) {
                    source = source[item];
                } else {
                    return;
                }
            }
            return source;
        }
        function argsToPiece(data, ...path) {
            const source = getPath(data, ...path);
            if (source) {
                const {suit, value} = source;
                return document.getElementById(`qtr-piece-${suit}-${value}`);
            }
        }

        function argsToSpace(data, ...path) {
            const source = getPath(data, ...path);
            if (source) {
                const {x, y} = source;
                return document.getElementById(`qtr-board-space-${x}-${y}`);
            }
        }

        dojo.subscribe("deploy", this, (data) => {
            console.log(data.args);
            const moves = [];

            for (const item of data.args.pieces) {
                const piece = document.getElementById(`qtr-piece-${item.suit}-${item.value}`);
                if (!piece.parentElement.classList.contains("qtr-board-space")) {
                    const x = parseInt(item.x);
                    const y = parseInt(item.y);
                    const space = document.getElementById(`qtr-board-space-${x}-${y}`);
                    const position = x - (y + 1) / 2;
                    moves.push({piece, space, x, y, position})
                }
            }

            moves.sort((m1, m2) => {
                const colors = [m1, m2].map(m => m.piece.dataset.color);
                if (colors[0] !== colors[1]) {
                    return 0;
                }
                return this.playerColor === "black" ?
                    m1.position - m2.position :
                    m2.position - m1.position;
            });

            const timeStep = 300;
            moves.forEach((m, i) =>
                setTimeout(
                    () => this.animateTranslation(m.piece, m.space),
                    i * timeStep)
            );

            this.notifqueue.setSynchronousDuration(900 + (moves.length - 1) * timeStep);
        })

        dojo.subscribe("move", this, (data) => {
            console.log(data.args);
            const movedPiece = argsToPiece(data, "movedPiece");
            const capturedPiece = argsToPiece(data, "capturedPiece");
            if (capturedPiece) {
                const space = document.querySelector(
                    `.qtr-captures[data-color="${capturedPiece.dataset.color}"]`)
                this.animateTranslation(capturedPiece, space);
            }
            const space = document.getElementById(
                `qtr-board-space-${data.args.x}-${data.args.y}`)
            this.animateTranslation(movedPiece, space);

            this.notifqueue.setSynchronousDuration(500);
        });

        dojo.subscribe("rescue", this, (data) => {
            console.log(data.args);
            const capturedPiece = argsToPiece(data, "capturedPiece");
            const captures = document.querySelector(`.qtr-captures[data-color="${capturedPiece.dataset.color}"]`);
            this.animateTranslation(capturedPiece, captures);

            for (let i = 0; i < data.args.rescuedPieces.length; ++i) {
                const piece = argsToPiece(data, "rescuedPieces", i);
                const space = argsToSpace(data, "rescuedPieces", i);
                if (piece.parentElement !== space) {
                    this.animateTranslation(piece, space);
                }
            }
        });
    },

    formatPieceIcon(...values) {
        const pieces = [];
        while (values.length >= 2) {
            pieces.push(values.slice(0, 2));
            values.splice(0, 2);
        }
        return pieces.map(([suit, value]) => {
            const content = `${pieceValues[value]}<br>${pieceSuits[suit]}`;
            return `<div class="qtr-piece qtr-piece-icon" 
                data-color="${getPlayerColor(suit)}"
                data-value="${value}">${content}</div>`;
        }).join("");
    },

    format_string_recursive(log, args) {
        if (args && !("substitutionComplete" in args)) {
            args.substitutionComplete = true;
            const formatters = {
                piece: this.formatPieceIcon,
            };
            for (const iconType of Object.keys(formatters)) {
                const icons = Object.keys(args).filter(name => name.startsWith(`${iconType}Icon`));

                for (const icon of icons) {
                    const values = args[icon].toString().split(",");
                    args[icon] = formatters[iconType].call(this, ...values);
                }
            }
        }
        return this.inherited({callee: this.format_string_recursive}, arguments);
    }
}));
