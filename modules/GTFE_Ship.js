class GTFE_Ship {
    constructor(game) {
        this.game = game;
    }

    placeContent ( tileContent ) {
        let game = this.game;

        var divId = tileContent.content_id;
        var tileId = tileContent.tile_id;
        var ctType = tileContent.content_type;
        var ctSubtype = tileContent.content_subtype;
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
                game.showMessage( "Error: unrecognized content type: "+
                            ctSubtype+" "+game.plReportBug, "error" );
                return;
        }

        var destDivId = ( rotWithTile ) ? 'tile_'+tileId : 'overlaytile_'+tileId;
        // position (given by CSS, eg. class p2on3) will depend on the
        // tile (eg. 2 cells batteries vs 3 cells batteries)
        //var posClass = ( alien ) ? 'p1on1' : 'p'+tileContent.place+'on'+tileContent.capacity;
        classes += ' p'+tileContent.place+'on'+tileContent.capacity;
        dojo.place( game.format_block( 'jstpl_content', {
                        content_id: divId,
                        classes: classes,
                    } ), destDivId );
    }

}