class GTFE_Tile {
    ALL_TILE_CONTENT_CLASSES = "p1on1 p1on2 p2on2 p1on3 p2on3 p3on3";

    constructor(game, id) {
        // id is back-end id, like "1"
        if (typeof(id) == 'string' && id.startsWith('tile_'))
            id = id.split("_")[1];
        this.game = game;
        this.id = id;
        this.nodeId = "tile_" + id;
        this.type = game.tiles[id]['type'];
        this.hold = game.tiles[id]['hold'];
    }

    // static functions but not declared as such since I don't know how to get this
    // module loaded into other modules so the only way to access this class is through $game. 
    // E.g. to get type of tile 23: game.newGTFE_Tile(1).getType(game, 23);
    getType(game, id) { return game.tiles[id]['type']; }
    getHold(game, id) { return game.tiles[id]['hold']; }

    queryGoods() {
        this.contentNodes = dojo.query('.goods', this.nodeId);
        return this.contentNodes;
    }

    getPlaceClass(place) {
        // returns place class pXonY for place, or an empty place class if not place
        if (place) {
            if (place > this.hold)
                this.game.throw_bug_report("Place cannot be greater than hold: " + place + ", " + this.id);
            return 'p' + place + 'on' + this.hold;
        }
        
        // returns class of an empty "place" on the tile 
        //  or undefined if the tile is full
        let curNodes = this.queryGoods();
        for (let i = 1; i <= 3; i++) {
            if (i > this.hold)
                break;
            let tgt_class = 'p' + i + 'on' + this.hold;
            if (curNodes.filter( n => n.classList.contains(tgt_class)).length == 0 )
                return tgt_class;
        }

        this.game.throw_bug_report("Could not find empty content class");
        return;
    }

    placeContent ( cont, slideFromId ) {
        let game = this.game;

        if (cont.tile_id != this.id)
            game.throw_bug_report("Wrong content for tile (" + cont.tile_id + "," + this.id + ")");

        var tileId = cont.tile_id;
        var ctSubtype = cont.content_subtype;
        //var error = false;
        //var alien = false;
        var rotWithTile = false;

        switch( ctSubtype ) {
            case 'cell':
                rotWithTile = true;
                var classes = 'cell';
                break;
            case 'human':
                var classes = 'crew human';
                break;
            case 'purple':
            case 'brown':
                //alien = true;
                var classes = 'crew alien '+ctSubtype;
                break;
            case 'ask_purple':
            case 'ask_brown':
                // This tile is special: we must place humans and alien(s) icons,
                // to show that the owner of this tile will have to make a choice
                var classes = 'crew alien_choice '+game.getPart( ctSubtype, 1); // color
                break;
            case 'ask_human':
                var classes = 'crew human_choice';
                break;
            case 'red':
            case 'yellow':
            case 'green':
            case 'blue':
                rotWithTile = true;
                var classes = 'goods '+ctSubtype;
                break;

            default:
                game.throw_bug_report("Unrecognized content type " + ctSubtype + " for id " + cont.content_id);
                return;
        }

        var destDivId = ( rotWithTile ) ? 'tile_'+tileId : 'overlaytile_'+tileId;
        // position (given by CSS, eg. class p2on3) will depend on the
        // tile (eg. 2 cells batteries vs 3 cells batteries)
        //var posClass = ( alien ) ? 'p1on1' : 'p'+cont.place+'on'+cont.capacity;
        let contentDiv = 'content_' + cont.content_id;
        if (slideFromId) {
            dojo.place( game.format_block( 'jstpl_content', {
                        content_id: cont.content_id,
                        classes: classes,
                    } ), slideFromId );
            this.slideContent(contentDiv, cont.place, 500);

        }
        else {
            dojo.place( game.format_block( 'jstpl_content', {
                        content_id: cont.content_id,
                        classes: classes + ' ' + this.getPlaceClass(cont.place)
                    } ), destDivId );
        }
        
        return contentDiv;
    }

    slideContent(contDiv, place, duration, delay) {
        // Slides content to this node at given place
        // will assign an empty place if place is null

        this._attachToSquare(contDiv);

        let anim = this.game.slideToObject(contDiv, this.nodeId, duration, delay);
        dojo.connect(anim, "onEnd", dojo.hitch( this, function(contDiv) {
            dojo.place(contDiv, this.nodeId, "last");
            dojo.style(contDiv,'top',null);
            dojo.style(contDiv,'left',null);
            dojo.style(contDiv, 'z-index', 1);
            dojo.removeClass(contDiv, this.ALL_TILE_CONTENT_CLASSES);
            dojo.addClass(contDiv, this.getPlaceClass(place));
        }));
        anim.play();
    }

    loseContent(cont, toCard) {
        // This has nothing to do with this Tile

        let divId = 'content_' + cont.id;
        dojo.style( divId, 'z-index', '50' ); // Not working, certainly due to stacking context. TODO
        this._attachToSquare(divId);
        if ( toCard || cont.toCard ) {
            this.game.slideToObjectAndDestroy( divId, "current_card", 500, i*200 );
        }
        else {
            var anim = dojo.fx.combine([
                dojo.fx.slideTo({ node:divId, left:100, top:400,
                                  units:"px", duration: 1700, delay:i*200 }),
                dojo.fadeOut({ node:divId, duration: 1700, delay:i*200 })
            ]);
            dojo.connect( anim, "onEnd", function(){  dojo.destroy( divId ); } );
            anim.play();
        }
    }

    _attachToSquare(contDiv) {
        // sliding to/from rotated tiles with slideToObject is tricky,
        //  first attach to content's grand-parent node (square)

        if (typeof(contDiv) == 'string')
            contDiv = $(contDiv);

        let gParent = contDiv.parentNode.parentNode;
        if (gParent.classList.contains('square'))
            dojo.place(contDiv, gParent, "last");
    }

}
