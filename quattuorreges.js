/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * QuattuorReges implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

const deploymentValues = Object.freeze([7, 8, 9, 10, 11, 12, 13, 0]);
const deploymentSuits = Object.freeze([0, 1, 2, 3]);
const deploymentRows = Object.freeze([
    [0, 13],
    [7, 8, 11],
    [9, 10, 12]
]);

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

const spaceCorners = Object.freeze([
    {x: 20, y: 20},
    {x: 20, y: -20},
    {x: 0, y: -20},
    {x: -20, y: -20},
    {x: -20, y: 20},
    {x: 0, y: 20},
]);

function createElement(parent, html) {
    const element = parent.appendChild(
        document.createElement("div"));
    element.outerHTML = html;
    return parent.lastElementChild;
}

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
    return pieceValue === 7 || pieceValue === 9 ? 3 :
        pieceValue === 8 ? 4 : 2;
}

function getPiece(suit, value) {
    return document.getElementById(`qtr-piece-${suit}-${value}`);
}

function getSpace(x, y) {
    return document.getElementById(`qtr-board-space-${x}-${y}`);
}

function swapRemove(array, index) {
    if (index >= 0 && array.length > index) {
        const result = array[index];
        array[index] = array[array.length - 1];
        array.pop();
        return result;
    }
}

function pointAdd(p1, p2) {
    return {
        x: parseInt(p1.x) + parseInt(p2.x),
        y: parseInt(p1.y) + parseInt(p2.y)
    }
}

function *spacesAround(space) {
    let index = 0;
    for (const direction of moveDirections) {
        yield [index++, pointAdd(space, direction)];
    }
}

function getPlayerColor(suit) {
    return (parseInt(suit) & 0x2) === 0 ?  "red" : "black";
}

function createPiece(suit, value) {
    return `<div id="qtr-piece-${suit}-${value}"
        class="qtr-piece" 
        data-color="${getPlayerColor(suit)}"
        data-suit="${suit}"
        data-value="${value}"></div>`;
}

function canCapture(pieceValue, targetValue) {
    pieceValue = parseInt(pieceValue);
    targetValue = parseInt(targetValue);
    return pieceValue === 0 && (11 <= targetValue && targetValue <= 13 || targetValue === 0)
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

class SpaceOutliner {
    spaces = [];
    lookup = new Map;

    __key(space) {
        return space.y * 1024 + space.x;
    }

    __findNeighbor(space, directionIndex) {
        const neighbor = pointAdd(space, moveDirections[directionIndex]);
        return this.lookup.get(this.__key(neighbor));
    }

    __isOpen(space, directionIndex) {
        const neighbor = pointAdd(space, moveDirections[directionIndex]);
        return !this.lookup.has(this.__key(neighbor));
    }

    constructor(spaces) {
        for (const space of spaces) {
            const x = parseInt(space instanceof HTMLElement ?
                space.dataset.x : space.x);
            const y = parseInt(space instanceof HTMLElement ?
                space.dataset.y : space.y);
            const item = {x, y};
            this.lookup.set(this.__key(item), item);
        }

        const sides = moveDirections.length;

        for (const [_, space] of this.lookup) {
            space.openSides = [];
            const startRange = {
                start: 0,
                length: 1,
                isOpen: this.__isOpen(space, 0)
            };

            let range = startRange;
            if (range.isOpen) {
                space.openSides.push(range);
            }

            for (let i = 1; i < sides; ++i) {
                const isOpen = this.__isOpen(space, i);
                if (isOpen === range.isOpen) {
                    ++range.length;
                } else {
                    range = {
                        start: i,
                        length: 1,
                        isOpen
                    };
                    if (range.isOpen) {
                        space.openSides.push(range);
                    }
                }
            }

            if (range !== startRange && range.isOpen && startRange.isOpen) {
                startRange.start = range.start;
                startRange.length += range.length;
                space.openSides.pop();
            }

            if (space.openSides.length > 0) {
                this.spaces.push(space);
            }
        }
    }

    popOutline() {
        const sides = moveDirections.length;
        const result = [];
        const index = this.spaces.findIndex(h => h.openSides.length > 0);

        if (index >= 0) {
            let space = this.spaces[index];
            let side = swapRemove(space.openSides, 0);

            while (space && side) {
                result.push({
                    x: space.x,
                    y: space.y,
                    start: side.start,
                    length: side.length
                });

                const connection = (side.start + side.length) % sides;
                space = this.__findNeighbor(space, connection);

                if (space) {
                    const sideIndex = space.openSides.findIndex(s => s.start === (connection + 4) % sides);

                    side = sideIndex >= 0 ? swapRemove(space.openSides, sideIndex) : null;
                }
            }
        }
        return result;
    }
}

function buildPath(spaces) {
    const outliner = new SpaceOutliner(spaces);
    const paths = [];

    let outline = outliner.popOutline();
    while (outline.length > 0) {
        const points = [];

        for (const side of outline) {
            for (let i = 0; i < side.length; ++i) {
                const direction = (side.start + i) % moveDirections.length;
                const center = {
                    x: 42 * (side.x - ((side.y + 1) >> 1))
                        + 21 * (1 + (side.y & 0b1)),
                    y: 630 - 42 * side.y - 21
                }
                const point = pointAdd(center, spaceCorners[direction]);
                points.push(point);
            }
        }

        const path = points.map(p => `${p.x} ${p.y}`).join("L");
        paths.push(`M${path}Z`);
        outline = outliner.popOutline();
    }
    return paths;
}


function buildSelection(spaces) {
    const paths = buildPath(spaces);
    document.getElementById("qtr-selection-svg").classList.remove("hidden");
    document.getElementById("qtr-selection-svg-path").setAttribute("d", paths.join(" "));
}

function buildWarning(spaces) {
    const paths = buildPath(spaces);
    document.getElementById("qtr-warning-svg").classList.remove("hidden");
    document.getElementById("qtr-warning-svg-path").setAttribute("d", paths.join(" "));
}

function isCapturable(x, y, oldX, oldY, color, pieceValue, range) {
    const paths = collectPaths(x, y, range)
    let item = paths.next();

    while (!item.done) {
        const path = item.value;
        const space = document.getElementById(
            `qtr-board-space-${path.space.x}-${path.space.y}`);

        if (space && space.children.length > 0) {
            const piece = space.firstElementChild;
            if (!piece.classList.contains("qtr-inactive")
                && path.steps.length <= getPieceRange(piece.dataset.value)
                && piece.dataset.color !== color
                && canCapture(piece.dataset.value, pieceValue))
            {
                return true;
            }
        }

        const isPassable = path.space.x === parseInt(oldX) && path.space.y === parseInt(oldY)
            || space && space.children.length === 0
        item = paths.next(isPassable);
    }
    return false;
}

function prepareMove(x, y, color, pieceValue) {
    const result = [];
    const selection = [];
    const warning = [];

    const paths = collectPaths(x, y, getPieceRange(pieceValue));
    let item = paths.next();

    while (!item.done) {
        const path = item.value;
        const space = document.getElementById(
            `qtr-board-space-${path.space.x}-${path.space.y}`);
        if (space) {
            const target = space.firstElementChild;
            if (!target
                || (color !== target.dataset.color &&
                    canCapture(pieceValue, target.dataset.value)))
            {
                space.classList.add("qtr-selectable");
                result.push(path);

                if (isCapturable(path.space.x, path.space.y, x, y, color, pieceValue, 4)) {
                    warning.push(path.space);
                } else {
                    selection.push(path.space);
                }
            }
        }

        item = paths.next(space && space.children.length === 0);
    }

    buildSelection(selection);
    buildWarning(warning);
    return result;
}

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter"
], (dojo, declare) => declare("bgagame.quattuorreges", ebg.core.gamegui, {
    constructor() {
        try {
            this.useOffsetAnimation = CSS.supports("offset-path", "path('M 0 0')");
        } catch (e) {
            this.useOffsetAnimation = false;
        }
        this.phantomRescue = null;
    },

    setup(data) {
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
                if (!getPiece(suit, value)) {
                    const element = document.createElement("div");
                    document.querySelector(`.qtr-captures[data-color=${getPlayerColor(suit)}]`)
                        .appendChild(element);
                    element.outerHTML = createPiece(suit, value);
                }
            }

            const isSetup = document.querySelector(".qtr-board-space .qtr-piece") === null;

            if (!isSetup && data.gamestate.name !== "setup") {
                const king = getPiece(suit, 13);
                if (!king.parentElement.classList.contains("qtr-board-space")) {
                    this.updateSuitActivity(suit, false);
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

        const helpContainer = document.getElementById("qtr-help-button-place");
        const helpTitle =  _("Reference");
        const helpButton = createElement(helpContainer, `<div id="qtr-help-button">
            <div class="fa6-solid fa6-circle-question"></div>
            <div>${helpTitle}</div>
        </div>`);

        helpButton.addEventListener("mousedown", () => {
            this.hidePinnedDiagram()

            const pinButtonHtml = `<div class="qtr-pin-button fa6-solid fa6-thumbtack"></div>`
            const dialog = new ebg.popindialog();
            dialog.create("qtr-capture-dialog");
            dialog.setTitle(_("Rules Reference") + pinButtonHtml);
            dialog.setContent(`<div class="qtr-capture-diagram"></div>`);
            dialog.show();

            document.querySelector(".qtr-pin-button").addEventListener("mousedown", () => {
                dialog.destroy();
                document.getElementById("qtr-pinned-diagram").classList.remove("hidden", "qtr-fading");
            });
        });

        this.addTooltip(helpButton.getAttribute("id"), "", _("Show rules reference"));

        document.getElementById("qtr-pinned-diagram").addEventListener("mousedown", () => {
            this.hidePinnedDiagram();
        });

        this.setupNotifications();
    },

    onEnteringState(stateName, state) {
        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
                case "move": {
                    const selection = [];
                    const warning = [];
                    const movedSuits = parseInt(state.args.movedSuits);
                    const rescuedPieces = parseInt(state.args.rescuedPieces);
                    const lockedBases = playerBases[this.playerColor]
                        .filter((base, index) =>
                            [0, 1, 2, 3].some(i =>
                                ((rescuedPieces >> 8 * i) & 0xFF) === index))
                        .map(id => document.getElementById(id));

                    const pieces = document.querySelectorAll(
                        `.qtr-board-space .qtr-piece[data-color="${this.playerColor}"]`);
                    for (const piece of pieces) {
                        const suitBit = 0x1 << (parseInt(piece.dataset.suit) & 0x1);

                        if ((movedSuits & suitBit) === 0) {
                            const locked = lockedBases.some(base => piece.parentElement === base);
                            const king = getPiece(piece.dataset.suit, 13);

                            if (!locked
                                && (king.parentElement.classList.contains("qtr-board-space")
                                    || piece.dataset.value === "0"))
                            {
                                const space = piece.parentElement;
                                space.classList.add("qtr-selectable");

                                if (isCapturable(space.dataset.x, space.dataset.y, -1, -1, piece.dataset.color, piece.dataset.value, 4)) {
                                    warning.push(space);
                                } else {
                                    selection.push(space);
                                }
                            }
                        }
                    }
                    buildSelection(selection);
                    buildWarning(warning);
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
                case "retreat": {
                    const selection = [];
                    const warning = [];
                    const value = state.args.piece
                    const piece = getPiece(value.suit, value.value);
                    const retreat = getSpace(value.x, value.y);
                    for (const space of [retreat, piece.parentElement]) {
                        space.classList.add("qtr-selectable");
                        if (isCapturable(space.dataset.x, space.dataset.y, -1, -1, piece.dataset.color, piece.dataset.value, 4)) {
                            warning.push(space);
                        } else {
                            selection.push(space);
                        }
                    }
                    buildSelection(selection);
                    buildWarning(warning);
                    break;
                }
                case "rescue": {
                    this.rescueCount = Math.min(
                        parseInt(state.args.rescueCount),
                        getFreeBases(this.playerColor).length,
                        getCapturedPieces(this.playerColor).length);
                    this.rescuedPieces = [];
                }
                //fallthrough
                case "clientRescuePiece": {
                    for (const piece of getCapturedPieces(this.playerColor)) {
                        piece.classList.add("qtr-selectable");
                    }
                    break;
                }
                case "clientRescueBase": {
                    const selection = [];
                    const warning = [];
                    for (const base of getFreeBases(this.playerColor)) {
                        base.classList.add("qtr-selectable");
                        if (isCapturable(base.dataset.x, base.dataset.y, -1, -1, this.selectedPiece.dataset.color, this.selectedPiece.dataset.value, 4)) {
                            warning.push(base);
                        } else {
                            selection.push(base);
                        }
                    }
                    buildSelection(selection);
                    buildWarning(warning);
                    break;
                }
            }
        }

        if (stateName === "setup") {
            const help = document.getElementById("qtr-help-button");
            const helpContainer = document.getElementById("qtr-board-help-button-place");
            helpContainer.appendChild(help);
        }
    },

    onLeavingState(stateName) {
        for (const svg of document.querySelectorAll(".qtr-board-svg")) {
            svg.classList.add("hidden");
        }
        switch (stateName) {
            case "setup":
                const help = document.getElementById("qtr-help-button");
                const helpContainer = document.getElementById("qtr-help-button-place");
                this.animateTranslation(help, helpContainer);
            //fallthrough
            case "clientMove":
            case "clientRescueBase": {
                if (this.unselectPiece()) {
                    clearTag("qtr-selectable");
                }
                break;
            }
            case "move":
            case "retreat":
            case "rescue":
            case "clientRescuePiece": {
                clearTag("qtr-selectable");
                break;
            }
        }
    },

    onUpdateActionButtons(stateName, args) {
        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
                case "setup": {
                    const spaces = document.querySelectorAll(
                        `.qtr-board-space[data-color="${this.playerColor}"]`);
                    for (const space of spaces) {
                        space.classList.add("qtr-selectable");
                    }
                    if (typeof g_ReplayFrom === "undefined" && !g_archive_mode) {
                        this.addActionButton("qtr-randomize", `🎲 ${_("Randomize")}`, event => {
                            event.stopPropagation();
                            this.randomizePieces();
                        }, null, null, "gray");
                    }
                    this.addActionButton("qtr-deploy", _("Deploy pieces"), event => {
                        event.stopPropagation();
                        this.deployPieces();
                    });
                    this.updateDeployment();
                    buildSelection(spaces);
                    break;
                }
                case "move":
                case "retreat":
                case "rescue":
                case "clientRescuePiece":
                case "clientRescueBase": {
                    const name = stateName === "move" ?
                        _("Pass") : _("Skip")
                    this.addActionButton("qtr-pass", name, event => {
                        event.stopPropagation();
                        if (stateName === "retreat") {
                            this.retreat(false);
                        } else if (stateName !== "move") {
                            this.rescuePieces(this.rescuedPieces);
                        } else {
                            this.pass();
                        }
                    }, null, null, "red");
                    break;
                }
                case "confirmTurn": {
                    this.addActionButton("qtr-confirm", _("Confirm"), event => {
                        event.stopPropagation();
                        this.confirm();
                    }, null, null, "red");
                    break;
                }
            }

            const cancellableStates =
                ["clientMove", "clientRescuePiece", "clientRescueBase"];
            if (cancellableStates.indexOf(stateName) >= 0) {
                this.addActionButton("qtr-cancel", _("Cancel"), event => {
                    event.stopPropagation();
                    if (this.phantomRescue !== null) {
                        const color = this.phantomRescue.dataset.color;
                        this.animateTranslation(this.phantomRescue,
                            document.querySelector(`.qtr-captures[data-color="${color}"]`));
                        this.phantomRescue = null;
                    }
                    this.restoreServerGameState();
                }, null, null, "gray");
            }

            if (stateName === "retreat"
                || stateName === "rescue"
                || stateName === "confirmTurn"
                || stateName === "move" && args.canUndo)
            {
                this.addActionButton("qtr-undo", _("Undo"), event => {
                    event.stopPropagation();
                    this.undo();
                }, null, null, "gray");
            }
        } else if (!this.isSpectator) {
            switch (stateName) {
                case "setup": {
                    this.unselectPiece();
                    clearTag("qtr-selectable");
                    document.getElementById("qtr-selection-svg").classList.add("hidden");
                    this.addActionButton("qtr-cancel", _("Cancel"), event => {
                        dojo.stopEvent(event);
                        this.cancelDeploy();
                    }, null, null, "gray");
                }
            }
        }
    },

    hidePinnedDiagram() {
        const pinnedDiagram = document.getElementById("qtr-pinned-diagram");
        pinnedDiagram.classList.add("qtr-fading");
        setTimeout(() => {
            if (pinnedDiagram.classList.contains("qtr-fading")) {
                pinnedDiagram.classList.add("hidden");
            }
        }, 350);
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
            this.selectPiece(captures[captures.length - 1]);
        } else if (!this.selectedPiece) {
            this.selectPiece(document.querySelector(
                `.qtr-piece[data-color="${this.playerColor}"]`));
        }

        document.getElementById("qtr-deploy").classList.toggle(
            "disabled", hasPieces);
    },

    updateSuitActivity(suit, enable) {
        const suitPieces = document.querySelectorAll(`.qtr-piece[data-suit="${suit}"]`);
        if (enable) {
            for (const otherPiece of suitPieces) {
                otherPiece.classList.remove("qtr-inactive");
            }
        } else {
            for (const otherPiece of suitPieces) {
                const value = pieceValues[otherPiece.dataset.value];
                if (value !== "A" && value !== "K") {
                    otherPiece.classList.add("qtr-inactive");
                }
            }
        }
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
                const path = this.paths.filter(p =>
                    p.space.x === x && p.space.y === y)[0];
                const sourceSpace = this.selectedPiece.parentElement;
                this.ajaxcall("/quattuorreges/quattuorreges/move.html", {
                    x: sourceSpace.dataset.x,
                    y: sourceSpace.dataset.y,
                    steps: path.steps.join(","),
                    retreat: false,
                    lock: true
                }, () => {});
            }
        } else if (this.checkAction("retreat", true)) {
            this.retreat(space.children.length === 0);
        } else if (this.checkAction("clientRescuePiece", true)
            || this.checkAction("rescue", true)) {
            this.addRescuedPiece(space.children[0]);
        } else if (this.checkAction("clientRescueBase", true)) {
            this.rescuedPieces.push({
                suit: this.selectedPiece.dataset.suit,
                value: this.selectedPiece.dataset.value,
                base: space.dataset.baseIndex
            });
            this.animateTranslation(this.selectedPiece, space);
            if (--this.rescueCount > 0) {
                this.phantomRescue = this.selectedPiece;
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

        if (this.checkAction("clientRescuePiece", true)
            || this.checkAction("rescue", true))
        {
            this.addRescuedPiece(piece);
        }
    },

    randomizePieces() {
        function range(n) {
            const result = [];
            for (let i = 0; i < n; ++i) {
                result.push(i);
            }
            return result;
        }

        const side = this.playerColor === "red" ? 0 : 1;
        const suits = [side * 2, side * 2 + 1];

        const rows = [0, 1, 2].map(n => {
            const y = 14 * side + (n + 3) * (1 - 2 * side);
            return range(Math.ceil((17 - y % 2) / 2) - 2)
                .map(x => ({x: x + 1, y}))
                .filter(({x}) => n > 0 || x !== 2 && x !== 5);
        });

        const animationNodes = [];
        const animationDestinations = [];

        rows.forEach((row, rowIndex) => {
            const values = deploymentRows[rowIndex];

            for (const value of values) {
                const [{x, y}] = row.splice(
                    Math.floor(Math.random() * row.length), 1);
                const swap = Math.floor(Math.random() * 2);

                suits.forEach((suit, suitIndex) => {
                    suitIndex = swap ? (1 - suitIndex) : suitIndex;
                    const rowLength = 17 - y % 2;
                    const suitX = ((y + 1) >> 1) +
                        (rowLength - 1) * suitIndex + x * (1 - 2 * suitIndex);

                    animationNodes.push(getPiece(suit, value));
                    animationDestinations.push(getSpace(suitX, y));
                });
            }
        });

        this.animateTranslation(animationNodes, animationDestinations);
        this.updateDeployment();
    },

    deployPieces() {
        const spaces = [];
        for (const suit of deploymentSuits) {
            for (const value of deploymentValues) {
                const space = getPiece(suit, value).parentElement;
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

    retreat(retreat) {
        this.ajaxcall("/quattuorreges/quattuorreges/retreat.html", {
            retreat,
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
        }, () => {
            this.phantomRescue = null;
        });
    },

    pass() {
        this.ajaxcall("/quattuorreges/quattuorreges/pass.html", {
            lock: true,
        }, () => {});
    },

    confirm() {
        this.ajaxcall("/quattuorreges/quattuorreges/confirm.html", {
            lock: true,
        }, () => {});
    },

    undo() {
        this.ajaxcall("/quattuorreges/quattuorreges/undo.html", {
            lock: true,
        }, () => {});
    },

    animateTranslation(nodes, destinations, delay) {
        if (!Array.isArray(nodes)) {
            if (window.getComputedStyle(nodes.parentElement).display === "flex") {
                const siblings = Array.from(nodes.parentElement.children).filter(n => n !== nodes);
                nodes = [nodes, ...siblings];
                destinations = [destinations, ...siblings.map(n => n.parentElement)];
            } else if (window.getComputedStyle(destinations).display === "flex") {
                const siblings = Array.from(destinations.children).filter(n => n !== nodes);
                nodes = [nodes, ...siblings];
                destinations = [destinations, ...siblings.map(n => n.parentElement)];
            } else {
                nodes = [nodes];
                destinations = [destinations];
            }
        }

        const useOffsetAnimation = this.useOffsetAnimation;
        function stopAnimation(node) {
            if (useOffsetAnimation) {
                if (node.classList.contains("qtr-moving")) {
                    node.classList.remove("qtr-moving");
                    node.style.offsetPath = "unset";
                }
            } else if (node.classList.contains("qtr-moving-simple")) {
                node.classList.remove("qtr-moving-simple");
                node.style.transform = "none";
            }
            node.style.animationDelay = "unset";
        }

        nodes.forEach(stopAnimation);
        const startRects = nodes.map(node =>
            node.getBoundingClientRect());
        nodes.forEach((node, index) => {
            if (node.parentElement !== destinations[index]) {
                if (this.useOffsetAnimation) {
                    node.classList.add("qtr-moving");
                } else {
                    node.classList.add("qtr-moving-simple");
                }
                destinations[index].appendChild(node)
            }
        });

        nodes.forEach((node, index) => {
            const startRect = startRects[index];
            const endRect = node.getBoundingClientRect();
            const [dX, dY] = [
                endRect.left - startRect.left,
                endRect.top - startRect.top
            ];

            if (typeof delay === "number") {
                node.style.animationDelay = `${delay * index}ms`;
            }

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

            node.addEventListener(
                "animationend",
                () => stopAnimation(node),
                {once: true});
        })
    },

    setupNotifications() {
        dojo.subscribe('update', this, (data) => {
            const moves = {};
            for (const move of data.args.moves) {
                moves[`${move.suit},${move.value}`] = move;
            }
            for (const key of Object.keys(moves)) {
                const move = moves[key];
                const piece = getPiece(move.suit, move.value);
                const space = getSpace(move.x, move.y);
                if (!space && piece.parentElement.classList.contains("qtr-board-space")) {
                    const captures = document.querySelector(
                        `.qtr-captures[data-color="${piece.dataset.color}"]`);
                    this.animateTranslation(piece, captures);
                } else if (space && space !== piece.parentElement) {
                    this.animateTranslation(piece, space);
                }

                if (pieceValues[move.value] === "K") {
                    this.updateSuitActivity(move.suit, space);
                }
            }
        });

        dojo.subscribe("deploy", this, (data) => {
            const moves = [];

            for (const item of data.args.pieces) {
                const piece = getPiece(item.suit, item.value);
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

            const timeStep = 50;
            const pieces = moves.map(m => m.piece);
            const spaces = moves.map(m => m.space);
            this.animateTranslation(pieces, spaces, timeStep);

            this.notifqueue.setSynchronousDuration(600 + (moves.length - 1) * timeStep);
        })

        this.notifqueue.setSynchronous('deploy');
        this.notifqueue.setSynchronous('update', 600);
    },

    formatPieceIcon(...values) {
        const pieces = [];
        while (values.length >= 2) {
            pieces.push(values.slice(0, 2));
            values.splice(0, 2);
        }
        return pieces.map(([suit, value]) => {
            return `<div class="qtr-piece qtr-piece-icon" 
                data-color="${getPlayerColor(suit)}"
                data-suit="${suit}"
                data-value="${value}"></div>`;
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
