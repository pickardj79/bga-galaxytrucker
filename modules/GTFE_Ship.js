class GTFE_Ship {

    INFOHTML = {
        'cannon' : undefined,
        'engine' : "<p>"+_('Engine strength:')+" <span id='curr_sel'>${curr}</span></p>"+
            "<p>"+_('Max engine strength:')+" <span id='max_str'>${max}</span></p>",
        'crew' : "<p>"+_('Crew selected:')+"<span id='curr_sel'>${curr}</span></p>"+
            "<p>"+_('Crew required:')+"<span id='needed_sel'>${max}</span></p>",
        'goods': undefined
    };

    constructor(game) {
        this.game = game;
        this.connects = {};

        // var to keep track of selections
        this._maxAllowed = undefined;
        this._typeToSelect = undefined;
        this._nbSelected = undefined;
        this._maxRequired = undefined;
        this._hasAlien = undefined;
    }

    connect(node, func) {
        // assume all onclicks, for simplicity
        let handle = dojo.connect(node, 'onclick', func);
        this.connects[node.id] = handle;
        return handle;
    }

    disconnectAll() {
        for ( let [nodeId, handle] of Object.entries(this.connects) )
            dojo.disconnect(handle);
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

    ////////////////////////////////////////////////////////////
    ////////////////// CONTENT CHOOSING ////////////////////////

    prepareContentChoice(type, maxSel, maxRequired, baseStr, maxStr, hasAlien) {
        // type: type of thing, see QA check below for options
        // baseStr: base strength with nothing selected
        // max: max available to select
        // maxRequired: the player must choose the max (e.g. when selected crew to lose)
        
        if (type != 'engine' && type != 'cannon' && type != 'crew' && type != 'goods')
            this.game.throw_bug_report("Unexpected content type: " + type);

        this._nbSelected = 0;
        this._maxAllowed = parseInt(maxSel);
        this._typeToSelect = type;
        this._maxRequired = maxRequired;
        this._baseStrength = parseInt(baseStr);
        this._hasAlien = hasAlien;

        let typeClass = type == 'engine' || type == 'cannon' ? 'cell' : type;

        dojo.query('.'+typeClass, 'my_ship').forEach( node => {
            this.connect( node, dojo.partial(this.onSelectContent, this));
            dojo.addClass( node, 'available');
        } );

        dojo.place( this.game.format_string( this.INFOHTML[type], {
                                curr: baseStr,
                                max: maxStr
                            } ), "info_box", "only" );
        dojo.style( 'info_box', 'display', 'block' );
    }

    // Partially-applied function to get this object into a callback
    onSelectContent(this_ship, evt ) {
        dojo.stopEvent( evt );

        var contId = evt.currentTarget.id;
        // Deselect this content if it's already selected
        if (dojo.hasClass(contId, 'selected')) {
            dojo.removeClass( contId, 'selected');
            dojo.addClass( contId, 'available' );
            this_ship._nbSelected--;
            if ( this_ship._nbSelected === (this_ship._maxAllowed-1) ) 
                // all content divs were previously marked as unselectable because max
                // were selected; we add available class back to them
                dojo.query('.'+this_ship._typeToSelect+':not(.selected)').addClass('available');
        }
        // Else this element is available, select it
        else {
            if ( this_ship._nbSelected === this_ship._maxAllowed )
                return;

            dojo.removeClass( contId, 'available');
            dojo.addClass( contId, 'selected');
            this_ship._nbSelected++;
            if ( this_ship._nbSelected === this_ship._maxAllowed ) 
                dojo.query('.available.content').removeClass('available');
        }

        this_ship.updateInfoBox();
    }

    selectedStr() {
        // strength of selected items including aliens and base strength
        switch ( this._typeToSelect ) {
            case 'engine':
                var currentStrength = this._baseStrength + this._nbSelected*2;
                console.log('base strength: ', this._baseStrength);
                console.log('current strength: ', currentStrength);
                // dojo.query is temporary, I'll use something more reliable when
                // the changes in content table are done.
                if ( this._baseStrength==0 && this._nbSelected>0 && this._hasAlien ) {
                    // baseStrength == 0 means that the alien bonus (if present) is not counted
                    // in baseStrength (because no simple engine/cannon), so we must add it if
                    // at least one cell is selected
                    currentStrength += 2;
                }
                $("curr_sel").innerHTML = currentStrength;
                return currentStrength;
            case 'crew':
                return this._nbSelected; 
        }
    }

    updateInfoBox() {
        $("curr_sel").innerHTML = this.selectedStr();
    }

    onValidateContentChoice() {
        
        if (this._maxRequired && this._nbSelected != this._maxAllowed) {
            let type = this._typeToSelect;
            this.game.showMessage( _("Wrong number of " + this._typeToSelect + " selected"), 'error' );
            return;
        }

        let ids = dojo.query('.selected', 'my_ship')
                      .map( i => this.game.getPart(i.id, 1) );

        if (ids.length != this._nbSelected)
            this.game.throw_bug_report("onValidateContentChoice _nbSelected not length of ids: " +
                this._nbSelected + "; " + ids.length
            );

        return {
            ids: ids,
            contentType: this._typeToSelect,
            str: this.selectedStr()
        }
    }

    onLeavingContentChoice() {
        this._nbSelected = this._maxAllowed = this._typeToSelect 
            = this._maxRequired = this._hasAlien = undefined;

        dojo.style( 'info_box', 'display', 'none' );
        dojo.empty( 'info_box' );

        dojo.query('.content', 'my_ship').removeClass('available selected');
        this.disconnectAll();
    }
}