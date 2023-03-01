//
function ciniki_ags_settings() {
    this.toggleOptions = {'no':' No ', 'yes':' Yes '};

    //
    // The main panel, which lists the options for production
    //
    this.main = new M.panel('Blog Settings', 'ciniki_ags_settings', 'main', 'mc', 'medium', 'sectioned', 'ciniki.ags.settings.main');
    this.main.sections = {
        'options':{'label':'Defaults', 'fields':{
            'defaults-item-fee-percent':{'label':'Fee Percent', 'type':'text', 'size':'small'},
            }},
        'options2':{'label':'Sales Reporting', 'fields':{
            'sales-customer-name':{'label':'Show Sales Customer Name', 'type':'toggle', 'toggles':{'no':'No', 'yes':'Yes'}},
            'sales-pdf-customer-name':{'label':'PDF Include Customer Name', 'type':'toggle', 'toggles':{'no':'No', 'yes':'Yes'}},
            }},
        'barcodes':{'label':'Barcode Printing', 'fields':{
            'barcodes-barcode-format':{'label':'Barcode Format', 'type':'select', 'default':'exhibitorcode-message', 'options':{
                'exhibitorcode-message':'Exhibitor Code - Participant Message', 
                'price-message':'Price - Participant Message',
                }},
            'barcodes-label-format':{'label':'Label Format', 'type':'select', 'default':'taginfo-price', 'options':{
                'taginfo':'Tag Info',
                'taginfo-price':'Tag Info - Price',
                }},
            }},
        'image':{'label':'Name Cards', 'aside':'yes', 
//            'visible':function() { return M.modFlagOn('ciniki.ags', 0x0400) ? 'no' : 'yes'; },
            'fields':{
                'namecards-image':{'label':'Logo', 'type':'image_id', 'controls':'all', 'history':'no', 'size':'small'},
                'namecards-template':{'label':'Format', 'type':'select', 'default':'businesscards', 'options':{
                    'businesscards':'Business Cards',
                    'fourbythree':'4 x 3 Cards',
                    }},
                'namecards-artist-prefix':{'label':'Artist Prefix', 'type':'toggle', 'default':'none', 'toggles':{
                    'none':'None',
                    'by':'By',
                    }},
                'namecards-include-size':{'label':'Include Size', 'type':'toggle', 'default':'yes', 'toggles':{
                    'no':'No',
                    'yes':'Yes',
                    }},
                'namecards-last-line':{'label':'Last Line', 'type':'text'},
            }},
        'typecards':{'label':'Name Cards', 'type':'simplegrid', 'aside':'no', 'num_cols':3,
            'visible':function() { return M.modFlagOn('ciniki.ags', 0x0400) ? 'yes' : 'no'; },
            'headerValues':['Image', 'Exhibit Type', 'Format'],
            'cellClasses':['thumbnail', ''],
            },
    };
    this.main.fieldValue = function(s, i, d) { 
        return this.data[i];
    };
    this.main.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.ags.settingsHistory', 'args':{'tnid':M.curTenantID, 'field':i}};
    };
    this.main.cellValue = function(s, i, j, d) {
        switch(j) {
            case 0: if( d.image > 0 ) {
                return '<img width="75px" height="75px" src=\'' + M.api.getBinaryURL('ciniki.images.get',{'tnid':M.curTenantID, 'image_id':d.image, 'version':'thumbnail', 'maxwidth':'75'}) + '\'/>';
                }
                return '<img width="75px" height="75px" src=\'/ciniki-mods/core/ui/themes/default/img/noimage_75.jpg\' />';
            case 1: return d.name;
            case 2: if( d.template == 'fourbythree' ) {
                return '4x3 Cards';
                } 
                return 'Business Cards';
        }
    }
    this.main.rowFn = function(s, i, d) {
        return 'M.ciniki_ags_settings.main.editTypeNameCard(\'' + i + '\');';
    }
    this.main.addDropImage = function(iid) {
        M.ciniki_ags_settings.main.setFieldValue('namecards-image', iid);
        return true;
    }
    this.main.deleteImage = function(fid) {
        this.setFieldValue(fid, 0);
        return true;
    }
    this.main.editTypeNameCard = function(i) {
        M.ciniki_ags_settings.typenamecard.open('M.ciniki_ags_settings.main.open();', this.data.typecards[i]);
    }
    this.main.open = function(cb) {
        M.api.getJSONCb('ciniki.ags.settingsGet', {'tnid':M.curTenantID}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_ags_settings.main;
            p.data = rsp.settings;
            p.refresh();
            p.show(cb);
        });
    }
    this.main.save = function() {
        var c = this.serializeForm('no');
        if( c != '' ) {
            M.api.postJSONCb('ciniki.ags.settingsUpdate', {'tnid':M.curTenantID}, c, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                } 
                M.ciniki_ags_settings.main.close();
            });
        } else {
            M.ciniki_ags_settings.main.close();
        }
    }
    this.main.addButton('save', 'Save', 'M.ciniki_ags_settings.main.save();');
    this.main.addClose('Cancel');

    //
    // The panel to edit a type name card
    //
    this.typenamecard = new M.panel('Type Name Card', 'ciniki_ags_settings', 'typenamecard', 'mc', 'medium', 'sectioned', 'ciniki.ags.settings.typenamecard');
    this.typenamecard.sections = {
        'namecard':{'label':'', 'fields':{
            'image':{'label':'Logo', 'type':'image_id', 'controls':'all', 'history':'no', 'size':'small'},
            'template':{'label':'Format', 'type':'select', 'default':'businesscards', 'options':{
                'businesscards':'Business Cards',
                'fourbythree':'4 x 3 Cards',
                }},
            'artist-prefix':{'label':'Artist Prefix', 'type':'toggle', 'default':'none', 'toggles':{
                'none':'None',
                'by':'By',
                }},
            'include-size':{'label':'Include Size', 'type':'toggle', 'default':'yes', 'toggles':{
                'no':'No',
                'yes':'Yes',
                }},
            'last-line':{'label':'Last Line', 'type':'text'},
        }},
        '_description':{'label':'Website Description', 
            'visible':function() { return M.modOn('ciniki.wng') ? 'yes' : 'no'; },
            'fields':{
                'title':{'label':'Title', 'type':'text'},
                'description':{'label':'Description', 'type':'textarea', 'size':'medium'},
                }},
    };
    this.typenamecard.fieldValue = function(s, i, d) { 
        return this.data[i];
    };
    this.typenamecard.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.ags.settingsHistory', 'args':{'tnid':M.curTenantID, 'field':i}};
    };
    this.typenamecard.addDropImage = function(iid) {
        M.ciniki_ags_settings.typenamecard.setFieldValue('image', iid);
        return true;
    }
    this.typenamecard.deleteImage = function(fid) {
        this.setFieldValue(fid, 0);
        return true;
    }
    this.typenamecard.open = function(cb, card) {
        this.data = card;
        this.refresh();
        this.show(cb);
    }
    this.typenamecard.save = function() {
        var c = '';
        for(var i in this.sections.namecard.fields) {
            var n = this.formFieldValue(this.sections.namecard.fields[i], i);
            if( n != this.data[i] ) {
                c += encodeURIComponent('namecards-' + this.data.permalink + '-' + i) + '=' + encodeURIComponent(n) + '&';
            }
        }
        if( M.modOn('ciniki.wng') ) {
            var n = this.formFieldValue(this.sections._description.fields.description, 'title');
            if( n != this.data['title'] ) {
                c += encodeURIComponent('namecards-' + this.data.permalink + '-title') + '=' + encodeURIComponent(n) + '&';
            }
            var n = this.formFieldValue(this.sections._description.fields.description, 'description');
            if( n != this.data['description'] ) {
                c += encodeURIComponent('namecards-' + this.data.permalink + '-description') + '=' + encodeURIComponent(n) + '&';
            }
        }
        if( c != '' ) {
            M.api.postJSONCb('ciniki.ags.settingsUpdate', {'tnid':M.curTenantID}, c, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                } 
                M.ciniki_ags_settings.typenamecard.close();
            });
        } else {
            M.ciniki_ags_settings.typenamecard.close();
        } 
    }
    this.typenamecard.addButton('save', 'Save', 'M.ciniki_ags_settings.typenamecard.save();');
    this.typenamecard.addClose('Cancel');

    //
    // Arguments:
    // aG - The arguments to be parsed into args
    //
    this.start = function(cb, appPrefix, aG) {
        args = {};
        if( aG != null ) { args = eval(aG); }

        //
        // Create the app container if it doesn't exist, and clear it out
        // if it does exist.
        //
        var appContainer = M.createContainer(appPrefix, 'ciniki_ags_settings', 'yes');
        if( appContainer == null ) {
            M.alert('App Error');
            return false;
        } 

        this.main.open(cb);
    }
}
