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
    };
    this.main.fieldValue = function(s, i, d) { 
        return this.data[i];
    };
    this.main.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.ags.settingsHistory', 'args':{'tnid':M.curTenantID, 'field':i}};
    };
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
            alert('App Error');
            return false;
        } 

        this.main.open(cb);
    }
}
