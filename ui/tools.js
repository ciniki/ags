//
function ciniki_ags_tools() {
    //
    // Panels
    //
    this.toggleOptions = {'no':'No', 'yes':'Yes'};

    //
    // The tools menu 
    //
    this.menu = new M.panel('Gallery Tools',
        'ciniki_ags_tools', 'menu',
        'mc', 'narrow', 'sectioned', 'ciniki.ags.tools.menu');
    this.menu.data = {};
    this.menu.sections = {
        'tools':{'label':'Tools', 
            'visible':function() {return M.modFlagAny('ciniki.ags', 0x6002); },
            'list':{
                'categories':{'label':'Update Item Categories', 
                    'visible':function() {return M.modFlagAny('ciniki.ags', 0x6002); },
                    'fn':'M.ciniki_ags_tools.tags.open(\'M.ciniki_ags_tools.menu.open();\',\'20\',\'Item Categories\');',
                    },
            }},
        };
    this.menu.open = function(cb) {
        this.refresh();
        this.show(cb);
    };
    this.menu.addClose('Back');

    //
    // The item tags update
    //
    this.tags = new M.panel('Tags',
        'ciniki_ags_tools', 'tags',
        'mc', 'medium', 'sectioned', 'ciniki.ags.tools.tags');
    this.tags.data = {};
    this.tags.fieldname = '';
    this.tags.sections = {
        'items':{'label':'Fields', 'fields':{}},
        'buttons':{'label':'', 'buttons':{
            'save':{'label':'Save', 'fn':'M.ciniki_ags_tools.tags.save();'},
            }},
        };
    this.tags.fieldValue = function(s, i, d) {
        return this.data[i].name;
    }
    this.tags.open = function(cb, tag, tagname) {
        if( tag != null ) {
            this.tag_type = tag;
        }
        if( tagname != null ) {
            this.tagname = tagname;
            this.title = tagname;
            this.sections.items.label = tagname;
        }
        M.api.getJSONCb('ciniki.ags.itemTags', {'tnid':M.curTenantID, 'types':this.tag_type}, function(rsp) {
            if( rsp['stat'] != 'ok' ) {
                M.api.err(rsp);
                return false;
            } 
            var p = M.ciniki_ags_tools.tags;
            p.data = {};
            p.sections.items.fields = {};
            if( rsp.tag_types != null && rsp.tag_types[0]['type']['tags'] != null ) {
                for(i in rsp.tag_types[0]['type']['tags']) {
                    var tag = rsp.tag_types[0]['type']['tags'][i]['tag'];
                    p.sections.items.fields[tag.permalink] = {
                        'label':tag['name'], 'type':'text',
                        };
                    p.data[tag.permalink] = tag;
                }
            }
            p.refresh();
            p.show(cb);
            });
    }
    this.tags.save = function() {
        var c = this.serializeForm('yes');
        M.api.postJSONCb('ciniki.ags.itemTagsUpdate', {'tnid':M.curTenantID, 'tag_type':this.tag_type}, c,
            function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                } 
                M.ciniki_ags_tools.tags.close();
            });
    };
    this.tags.addButton('save', 'Save', 'M.ciniki_ags_tools.tags.save();');
    this.tags.addClose('Cancel');

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
        var appContainer = M.createContainer(appPrefix, 'ciniki_ags_tools', 'yes');
        if( appContainer == null ) {
            M.alert('App Error');
            return false;
        }

        this.menu.open(cb);
    }
}
