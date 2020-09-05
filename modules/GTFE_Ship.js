class GTFE_Ship {
    constructor(game) {
        this.game = game;
    }

    cargoTiles () {
        return dojo.query('.tile', 'my_ship').filter( 
            dojo.hitch(this, node => {
                let id = node.id.split('_')[1];
                let type = this.game.tiles[id]['type']; 
                if (type != 'cargo' && type != 'hazard')
                    return false;
                dojo.addClass(node, type);
                return true;
        }));
    }

}