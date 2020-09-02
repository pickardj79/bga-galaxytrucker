class GTFE_Tile {
    ALL_TILE_CONTENT_CLASSES = "p1on1 p1on2 p2on2 p1on3 p2on3 p3on3";

    constructor(game, id) {
        // id is back-end id, like "1"
        this.game = game;
        this.id = id;
        this.nodeId = "tile_" + id;
        this.type = game.tiles[id]['type'];
        this.hold = game.tiles[id]['hold'];
    }

    // static functions but not declared as such since I don't know how to get this
    // module loaded into other modules so the only way to access this class is game. 
    // E.g. to get type of tile 23: game.newGTFE_Tile(1).getType(game, 23);
    getType(game, id) { return game.tiles[id]['type']; }
    getHold(game, id) { return game.tiles[id]['hold']; }
    // ALL_TILE_CONTENT_CLASSES() { return "p1on1 p1on2 p2on2 p1on3 p2on3 p3on3"; }

    queryGoods() {
        this.contentNodes = dojo.query('.goods', this.nodeId);
        return this.contentNodes;
    }

    getEmptyContentClass() {
        // returns class of an empty "place" on the tile 
        //  or undefined if the tile is full
        let curNodes = this.queryGoods();
        for (let i = 1; i <= 3; i++) {
            if (i > this.hold)
                break;
            let tgt_class = 'p' + i + 'on' + this.hold;
            if (curNodes.filter( n => n.classList.contains(tgt_class)).length == 0 )
                return tgt_class;
            // for (let node of this.queryGoods()) {
                // if (!node.classList.contains(tgt_class) )
                    // return tgt_class;
            // }
        }
        this.game.throw_bug_report("Could not find empty content class");
        return;
    }
}
