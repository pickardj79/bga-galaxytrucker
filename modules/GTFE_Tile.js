class GTFE_Tile {
    constructor(game, id) {
        // id is back-end id, like "1"
        this.game = game;
        this.id = id;
        this.nodeId = "tile_" + id;
        this.type = game.tiles[id]['type'];
        this.hold = game.tiles[id]['hold'];
    }

    static getType(game, id) { return game.tiles[id]['type']; }
    static getHold(game, id) { return game.tiles[id]['hold']; }

    queryGoods() {
        this.contentNodes = dojo.query('.goods', this.nodeId);
        return this.contentNodes;
    }

    getEmptyContentClass() {
        // returns class of an empty "place" on the tile 
        //  or undefined if the tile is full
        for (let i = 1; i <= 3; i++) {
            if (i > this.hold)
                return;
            let tgt_class = 'p' + i + 'on' + this.hold;
            for (let node of this.contentNodes) {
                if (node.classList.contains(tgt_class) )
                    continue;
                return tgt_class;
            }
        }
        return;
    }
}
