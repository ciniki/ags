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
        'image':{'label':'Name Cards', 'aside':'yes', 'fields':{
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
    };
    this.main.fieldValue = function(s, i, d) { 
        return this.data[i];
    };
    this.main.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.ags.settingsHistory', 'args':{'tnid':M.curTenantID, 'field':i}};
    };
    this.main.addDropImage = function(iid) {
        M.ciniki_ags_settings.main.setFieldValue('namecards-image', iid);
        return true;
    }
    this.main.deleteImage = function(fid) {
        this.setFieldValue(fid, 0);
        return true;
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
