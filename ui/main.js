//
// This is the main app for the ags module
//
function ciniki_ags_main() {

    this.menutabs = {'label':'', 'type':'menutabs', 'selected':'exhibits', 'tabs':{
        'exhibits':{'label':'Exhibits', 'fn':'M.ciniki_ags_main.switchTab("exhibits");'},
        'locations':{'label':'Locations', 'fn':'M.ciniki_ags_main.switchTab("locations");'},
        'exhibitors':{'label':'Exhibitors', 'fn':'M.ciniki_ags_main.switchTab("exhibitors");'},
//        'reports':{'label':'Reports', 'fn':'M.ciniki_ags_main.switchTab("reports");'},
        }};
    this.switchTab = function(t) {
        this.menutabs.selected = t;
        this[t].open();
    }

    //
    // The panel to list the item
    //
    this.exhibits = new M.panel('Exhibits', 'ciniki_ags_main', 'exhibits', 'mc', 'xlarge', 'sectioned', 'ciniki.ags.main.exhibits');
    this.exhibits.data = {};
    this.exhibits.nplist = [];
    this.exhibits.sections = {
        '_tabs':this.menutabs,
        'search':{'label':'', 'type':'livesearchgrid', 'livesearchcols':4,
            'headerValues':['Name', 'Location', 'Start', 'End'],
            'cellClasses':[''],
            'hint':'Search exhibits',
            'noData':'No exhibits found',
            },
        '_years':{'label':'', 'type':'paneltabs', 'selected':'', 'tabs':{}, 'visible':'no'},
        'exhibits':{'label':'Exhibits', 'type':'simplegrid', 'num_cols':4,
            'headerValues':['Name', 'Location', 'Start', 'End'],
            'sortable':'yes',
            'sortTypes':['date', 'date', 'text', 'text'],
            'noData':'No exhibit',
            'addTxt':'Add Exhibit',
            'addFn':'M.ciniki_ags_main.exhibitedit.open(\'M.ciniki_ags_main.exhibits.open();\',0);'
            },
    }
    this.exhibits.liveSearchCb = function(s, i, v) {
        if( s == 'search' && v != '' ) {
            M.api.getJSONBgCb('ciniki.ags.exhibitSearch', {'tnid':M.curTenantID, 'start_needle':v, 'limit':'25'}, function(rsp) {
                M.ciniki_ags_main.exhibits.liveSearchShow('search',null,M.gE(M.ciniki_ags_main.exhibits.panelUID + '_' + s), rsp.exhibits);
                });
        }
    }
    this.exhibits.liveSearchResultValue = function(s, f, i, j, d) {
        switch(j) {
            case 0: return d.name;
            case 1: return d.location_name;
            case 2: return d.start_date_display;
            case 3: return d.end_date_display;
        }
    }
    this.exhibits.liveSearchResultRowFn = function(s, f, i, j, d) {
        return 'M.ciniki_ags_main.exhibit.open(\'M.ciniki_ags_main.exhibits.open();\',\'' + d.id + '\');';
    }
    this.exhibits.sectionData = function(s) {
        if( s == 'exhibits' ) {
            if( this.sections._years.selected != '' ) {
                for(var i in this.data.years) {
                    if( this.data.years[i].year == this.sections._years.selected ) {
                        return this.data.years[i].exhibits;
                    }
                }
            }
        }
        return this.data[s];
    }
    this.exhibits.cellValue = function(s, i, j, d) {
        if( s == 'exhibits' ) {
            switch(j) {
                case 0: return d.name;
                case 1: return d.location_name;
                case 2: return d.start_date_display;
                case 3: return d.end_date_display;
            }
        }
    }
    this.exhibits.rowFn = function(s, i, d) {
        if( s == 'exhibits' ) {
            return 'M.ciniki_ags_main.exhibit.open(\'M.ciniki_ags_main.exhibits.open();\',\'' + d.id + '\',M.ciniki_ags_main.exhibits.nplist);';
        }
    }
    this.exhibits.switchYear = function(y) {
        this.sections._years.selected = this.data.years[y].year;
        this.refreshSection('_years');
        this.refreshSection('exhibits');
    }
    this.exhibits.open = function(cb) {
        M.api.getJSONCb('ciniki.ags.exhibitList', {'tnid':M.curTenantID}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_ags_main.exhibits;
            p.data = rsp;
            p.sections._years.visible = 'no';
            p.sections._years.tabs = {};
            if( rsp.years != null && rsp.years != '' ) {
                var year_found = 'no';
                var i = 0;
                for(i in rsp.years) {
                    p.sections._years.tabs[rsp.years[i].year] = {'label':rsp.years[i].year, 'fn':'M.ciniki_ags_main.exhibits.switchYear(' + i + ');'};
                    if( rsp.years[i].year == p.sections._years.selected ) {
                        year_found = 'yes';
                    }
                }
                if( year_found == 'no' ) {
                    p.sections._years.selected = rsp.years[i].year;
                }
                if( rsp.years.length > 1 ) {
                    p.sections._years.visible = 'yes';
                }
            }
            p.nplist = (rsp.nplist != null ? rsp.nplist : null);
            p.refresh();
            p.show(cb);
        });
    }
    this.exhibits.addClose('Back');

    //
    // The panel to display Exhibit
    //
    this.exhibit = new M.panel('Exhibit', 'ciniki_ags_main', 'exhibit', 'mc', 'xlarge mediumaside', 'sectioned', 'ciniki.ags.main.exhibit');
    this.exhibit.data = null;
    this.exhibit.exhibit_id = 0;
    this.exhibit.sections = {
        'exhibit_details':{'label':'', 'type':'simplegrid', 'num_cols':2, 'aside':'yes', 
            'cellClasses':['label', ''],
            'changeTxt':'Edit',
            'changeFn':'M.ciniki_ags_main.exhibitedit.open(\'M.ciniki_ags_main.exhibit.open();\',M.ciniki_ags_main.exhibit.exhibit_id,null);',
            },
        '_buttons':{'label':'', 'aside':'yes', 'buttons':{
            'excelinventory':{'label':'Inventory (Excel)', 'fn':'M.ciniki_ags_main.exhibit.exhibitInventory();'},
            'pricespdf':{'label':'Price List (PDF)', 'fn':'M.ciniki_ags_main.exhibit.exhibitPriceList();'},
            'pricebookpdf':{'label':'Untagged Price Book (PDF)', 'fn':'M.ciniki_ags_main.exhibit.exhibitPriceBook();'},
            'inventorypdf':{'label':'Current Inventory (PDF)', 'fn':'M.ciniki_ags_main.exhibit.currentInventoryPDF();'},
            'salespdf':{'label':'Unpaid Sales (PDF)', 'fn':'M.ciniki_ags_main.exhibit.unpaidSalesPDF();'},
            }},
        '_tabs':{'label':'', 'type':'paneltabs', 'selected':'participants', 
            'tabs':{
                'participants':{'label':'Participants', 'fn':'M.ciniki_ags_main.exhibit.switchTab("participants");'},
                'inventory':{'label':'Inventory', 'fn':'M.ciniki_ags_main.exhibit.switchTab("inventory");'},
                'sales':{'label':'Sales', 'fn':'M.ciniki_ags_main.exhibit.switchTab("sales");'},
            }},
//        'participant_search':{'label':'', 'type':'livesearchgrid', 'livesearchcols':6,
//            'visible':function() { return M.ciniki_ags_main.exhibit.sections._tabs.selected == 'participants' ? 'yes' : 'hidden'},
//            'cellClasses':[''],
//            'headerValues':['Name', 'Status', '# Items', 'Sold', 'Fees', 'Net'],
//            'hint':'Search participants',
//            'noData':'No participants found',
//            },
        'participants':{'label':'Exhibit Participants', 'type':'simplegrid', 'num_cols':6,
            'visible':function() { return M.ciniki_ags_main.exhibit.sections._tabs.selected == 'participants' ? 'yes' : 'hidden'},
            'sortable':'yes',
            'sortTypes':['text', 'text', 'number', 'number', 'number', 'number'],
            'headerValues':['Name', 'Status', '# Items', 'Fees', 'Payout', 'Total'],
            'headerClasses':['','','alignright','alignright','alignright','alignright'],
            'cellClasses':['','','alignright','alignright','alignright','alignright'],
            'addTxt':'Add Participant',
            'addTopFn':'M.ciniki_ags_main.participant.addCustomer(\'M.ciniki_ags_main.exhibit.open();\',M.ciniki_ags_main.exhibit.exhibit_id);'
            },
        'inventory_search':{'label':'', 'type':'livesearchgrid', 'livesearchcols':5,
            'visible':function() { return M.ciniki_ags_main.exhibit.sections._tabs.selected == 'inventory' ? 'yes' : 'hidden'},
            'headerValues':['Exhibitor', 'Code', 'Item', 'Price', 'Quantity'],
            'headerClasses':['','','','','alignright'],
            'cellClasses':['','','','','alignright'],
            'hint':'Search inventory',
            'noData':'No items found',
            },
        'inventory':{'type':'simplegrid', 'num_cols':5,
            'visible':function() { return M.ciniki_ags_main.exhibit.sections._tabs.selected == 'inventory' ? 'yes' : 'hidden'},
            'sortable':'yes',
            'sortTypes':['text', 'text', 'number', 'number', 'number', 'number'],
            'headerValues':['Exhibitor', 'Code', 'Item', 'Price', 'Quantity'],
            'headerClasses':['','','','','alignright'],
            'cellClasses':['','','','','alignright'],
            'noData':'No inventory',
            },
        'sales_search':{'label':'', 'type':'livesearchgrid', 'livesearchcols':5,
            'visible':function() { return M.ciniki_ags_main.exhibit.sections._tabs.selected == 'sales' ? 'yes' : 'hidden'},
            'cellClasses':[''],
            'headerValues':['Name', 'Status', '# Items', 'Fees', 'Payout', 'Total'],
            'hint':'Search sales',
            'noData':'No items found',
            },
        'pending_payouts':{'label':'Pending Payouts', 'type':'simplegrid', 'num_cols':6,
            'visible':function() { return M.ciniki_ags_main.exhibit.sections._tabs.selected == 'sales' ? 'yes' : 'hidden'},
            'sortable':'yes',
            'sortTypes':['text', 'text', 'number', 'number', 'number', 'number'],
            'headerValues':['Exhibitor', 'Code', 'Item', 'Fees', 'Payout', 'Total'],
            },
        'paid_sales':{'label':'Paid Sales', 'type':'simplegrid', 'num_cols':6,
            'visible':function() { return M.ciniki_ags_main.exhibit.sections._tabs.selected == 'sales' ? 'yes' : 'hidden'},
            'sortable':'yes',
            'sortTypes':['text', 'text', 'number', 'number', 'number', 'number'],
            'headerValues':['Exhibitor', 'Code', 'Item', 'Fees', 'Payout', 'Total'],
            },
    }
    this.exhibit.liveSearchCb = function(s, i, v) {
        if( s == 'participant_search' && v != '' ) {
            M.api.getJSONBgCb('ciniki.ags.exhibitSearchParticipant', {'tnid':M.curTenantID, 'exhibit_id':this.exhibit_id, 'start_needle':v, 'limit':'25'}, function(rsp) {
                M.ciniki_ags_main.exhibit.liveSearchShow('participant_search',null,M.gE(M.ciniki_ags_main.exhibit.panelUID + '_' + s), rsp.items);
                });
        }
        if( s == 'inventory_search' && v != '' ) {
            M.api.getJSONBgCb('ciniki.ags.exhibitSearchInventory', {'tnid':M.curTenantID, 'exhibit_id':this.exhibit_id, 'start_needle':v, 'limit':'25'}, function(rsp) {
                M.ciniki_ags_main.exhibit.liveSearchShow('inventory_search',null,M.gE(M.ciniki_ags_main.exhibit.panelUID + '_' + s), rsp.items);
                });
        }
    }
    this.exhibit.liveSearchResultValue = function(s, f, i, j, d) {
            return this.cellValue(s, i, j, d);
        if( s == 'participant_search' ) {
            return this.cellValue(s, i, j, d);
        }
        if( s == 'inventory_search' ) {
            switch(j) {
                case 0: return d.display_name;
                case 1: return d.code;
                case 2: return d.name;
                case 3: return d.unit_amount_display;
                case 4: return d.inventory;
            }
        }
    }
    this.exhibit.liveSearchResultCellFn = function(s, f, i, j, d) {
        return this.cellFn(s, i, j, d);
    }
    this.exhibit.liveSearchResultRowFn = function(s, f, i, j, d) {
        return this.rowFn(s, i, d);
    }
    this.exhibit.cellValue = function(s, i, j, d) {
        if( s == 'exhibit_details' ) {
            switch(j) {
                case 0: return d.label;
                case 1: return d.value;
            }
        }
        if( s == 'participants' || s == 'participant_search' ) {
            switch(j) { 
                case 0: return d.display_name;
                case 1: return d.status_text;
                case 2: return d.num_items;
                case 3: return d.tenant_amount_display;
                case 4: return d.exhibitor_amount_display;
                case 5: return d.total_amount_display;
            }
        }
        if( s == 'inventory' || s == 'inventory_search' ) {
            switch(j) {
                case 0: return d.display_name;
                case 1: return d.code;
                case 2: return d.name;
                case 3: return d.unit_amount_display;
                case 4: return d.inventory + '<span class="faicon edit">&#xf040;</span>';
            }
        }
        if( s == 'paid_sales' || s == 'pending_payouts' ) {
            switch(j) {
                case 0: return d.display_name;
                case 1: return d.code;
                case 2: return d.name;
                case 3: return d.tenant_amount_display;
                case 4: return d.exhibitor_amount_display;
                case 5: return d.total_amount_display;
            }
        }
    }
    this.exhibit.footerValue = function(s, i, d) {
        if( s == 'participants' ) {
            switch(i) {
                case 0: return '';
                case 1: return '';
                case 2: return this.data.totals.participants.num_items;
                case 3: return this.data.totals.participants.tenant_amount_display;
                case 4: return this.data.totals.participants.exhibitor_amount_display;
                case 5: return this.data.totals.participants.total_amount_display;
            }
            return '';
        }
        if( s == 'paid_sales' ) {
            switch(i) {
                case 0: return '';
                case 1: return '';
                case 2: return '';
                case 3: return this.data.totals.paid_sales.tenant_amount_display;
                case 4: return this.data.totals.paid_sales.exhibitor_amount_display;
                case 5: return this.data.totals.paid_sales.total_amount_display;
            }
            return '';
        }
        if( s == 'pending_payouts' ) {
            switch(i) {
                case 0: return '';
                case 1: return '';
                case 2: return '';
                case 3: return this.data.totals.pending_payouts.tenant_amount_display;
                case 4: return this.data.totals.pending_payouts.exhibitor_amount_display;
                case 5: return this.data.totals.pending_payouts.total_amount_display;
            }
            return '';
        }
        return null;
    }
    this.exhibit.cellFn = function(s, i, j, d) {
        if( (s == 'inventory' || s == 'inventory_search') && j == 4 ) {
            return 'event.stopPropagation(); return M.ciniki_ags_main.exhibit.inventoryUpdate(event,\'' + d.id + '\');';
        }
        return '';
    }
    this.exhibit.rowFn = function(s, i, d) {
        if( s == 'participants' || s == 'participant_search' ) {
            return 'M.ciniki_ags_main.participant.open(\'M.ciniki_ags_main.exhibit.open();\',\'' + d.id + '\');';
        }
        if( s == 'inventory' || s == 'inventory_search' ) {
            return 'M.ciniki_ags_main.item.open(\'M.ciniki_ags_main.exhibit.open();\',\'' + d.item_id + '\',0,0);';
        }
        return '';
    }
    this.exhibit.switchTab = function(t) {
        this.sections._tabs.selected = t;
        this.refreshSection('_tabs');
        this.showHideSection('participant_search');
        this.showHideSection('participants');
        this.showHideSection('inventory_search');
        this.showHideSection('inventory');
        this.showHideSection('sales_search');
        this.showHideSection('pending_payouts');
        this.showHideSection('paid_sales');
    }
    this.exhibit.inventoryUpdate = function(event,ei_id) {
        var i = prompt('Enter new inventory quantity: ');
        if( i != null && i != '' ) {
            i = parseFloat(i);
            M.api.getJSONCb('ciniki.ags.exhibitItemUpdate', {'tnid':M.curTenantID, 'exhibit_item_id':ei_id, 'inventory':i}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                if( event.target.tagName == 'SPAN' ) {
                    event.target.parentNode.innerHTML = i + '<span class="faicon edit">&#xf040;</span>';
                } else {
                    event.target.innerHTML = i + '<span class="faicon edit">&#xf040;</span>';
                }
            });
        }
    }
    this.exhibit.open = function(cb, eid, list) {
        if( eid != null ) { this.exhibit_id = eid; }
        if( list != null ) { this.nplist = list; }
        M.api.getJSONCb('ciniki.ags.exhibitGet', {'tnid':M.curTenantID, 'exhibit_id':this.exhibit_id, 'details':'yes'}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_ags_main.exhibit;
            p.data = rsp;
            p.refresh();
            p.show(cb);
        });
    }
    this.exhibit.exhibitInventory = function() {
        M.api.openFile('ciniki.ags.exhibitInventory', {'tnid':M.curTenantID, 'exhibit_id':this.exhibit_id});
    }
    this.exhibit.exhibitPriceList = function() {
        M.api.openPDF('ciniki.ags.exhibitPriceList', {'tnid':M.curTenantID, 'exhibit_id':this.exhibit_id});
    }
    this.exhibit.exhibitPriceBook = function() {
        M.api.openPDF('ciniki.ags.exhibitPriceBook', {'tnid':M.curTenantID, 'exhibit_id':this.exhibit_id});
    }
    this.exhibit.currentInventoryPDF = function() {
        M.api.openPDF('ciniki.ags.exhibitInventoryPDF', {'tnid':M.curTenantID, 'exhibit_id':this.exhibit_id});
    }
    this.exhibit.unpaidSalesPDF = function() {
        M.api.openPDF('ciniki.ags.unpaidSalesPDF', {'tnid':M.curTenantID, 'exhibit_id':this.exhibit_id});
    }
    this.exhibit.addClose('Back');

    //
    // The panel to edit Exhibit
    //
    this.exhibitedit = new M.panel('Exhibit', 'ciniki_ags_main', 'exhibitedit', 'mc', 'medium mediumaside', 'sectioned', 'ciniki.ags.main.exhibitedit');
    this.exhibitedit.data = null;
    this.exhibitedit.exhibit_id = 0;
    this.exhibitedit.nplist = [];
    this.exhibitedit.sections = {
        '_primary_image_id':{'label':'', 'type':'imageform', 'aside':'yes', 'fields':{
            'primary_image_id':{'label':'', 'type':'image_id', 'hidelabel':'yes', 'controls':'all', 'history':'no',
                'addDropImage':function(iid) {
                    M.ciniki_ags_main.exhibitedit.setFieldValue('primary_image_id', iid);
                    return true;
                    },
                'addDropImageRefresh':'',
             },
        }},
        'general':{'label':'', 'aside':'yes', 'fields':{
            'name':{'label':'Name', 'required':'yes', 'type':'text'},
            'location_id':{'label':'Location', 'type':'select', 'options':{}, 'complex_options':{'value':'id', 'name':'name'}},
            'status':{'label':'Status', 'type':'toggle', 'toggles':{'50':'Active', '90':'Archived'}},
            'flags':{'label':'Options', 'type':'flags', 'flags':{'1':{'name':'Visible'}}},
            'start_date':{'label':'Start', 'type':'date'},
            'end_date':{'label':'End', 'type':'date'},
            }},
        '_types':{'label':'Type', 'aside':'yes', 'fields':{
            'types':{'label':'', 'hidelabel':'yes', 'type':'tags', 'tags':[], 'hint':'Enter a new exhibit type: '},
            }},
        '_webcollections':{'label':'Web Collections', 'aside':'yes', 
            'active':function() {return M.modFlagSet('ciniki.web', 0x08); },
            'fields':{
                'webcollections':{'label':'', 'hidelabel':'yes', 'type':'collection'},
            }},
        '_synopsis':{'label':'Synopsis', 'fields':{
            'synopsis':{'label':'', 'hidelabel':'yes', 'type':'textarea', 'size':'small'},
            }},
        '_description':{'label':'Description', 'fields':{
            'description':{'label':'', 'hidelabel':'yes', 'type':'textarea', 'size':'large'},
            }},
        '_buttons':{'label':'', 'buttons':{
            'save':{'label':'Save', 'fn':'M.ciniki_ags_main.exhibitedit.save();'},
            'delete':{'label':'Delete', 
                'visible':function() {return M.ciniki_ags_main.exhibitedit.exhibit_id > 0 ? 'yes' : 'no'; },
                'fn':'M.ciniki_ags_main.exhibitedit.remove();'},
            }},
        };
    this.exhibitedit.fieldValue = function(s, i, d) { return this.data[i]; }
    this.exhibitedit.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.ags.exhibitHistory', 'args':{'tnid':M.curTenantID, 'exhibit_id':this.exhibit_id, 'field':i}};
    }
    this.exhibitedit.open = function(cb, eid, list) {
        if( eid != null ) { this.exhibit_id = eid; }
        if( list != null ) { this.nplist = list; }
        M.api.getJSONCb('ciniki.ags.exhibitGet', {'tnid':M.curTenantID, 'exhibit_id':this.exhibit_id, 'locations':'yes', 'types':'yes', 'webcollections':'yes'}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_ags_main.exhibitedit;
            p.data = rsp.exhibit;
            p.sections.general.fields.location_id.options = rsp.locations;
            p.sections.general.fields.location_id.options.unshift({'id':0, 'name':'None'});
            p.sections._types.fields.types.tags = [];
            if( rsp.types != null ) {
                for(i in rsp.types) {
                    p.sections._types.fields.types.tags.push(rsp.types[i].tag.name);
                }
            }
            p.refresh();
            p.show(cb);
        });
    }
    this.exhibitedit.save = function(cb) {
        if( cb == null ) { cb = 'M.ciniki_ags_main.exhibitedit.close();'; }
        if( !this.checkForm() ) { return false; }
        if( this.exhibit_id > 0 ) {
            var c = this.serializeForm('no');
            if( c != '' ) {
                M.api.postJSONCb('ciniki.ags.exhibitUpdate', {'tnid':M.curTenantID, 'exhibit_id':this.exhibit_id}, c, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    eval(cb);
                });
            } else {
                eval(cb);
            }
        } else {
            var c = this.serializeForm('yes');
            M.api.postJSONCb('ciniki.ags.exhibitAdd', {'tnid':M.curTenantID}, c, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_ags_main.exhibitedit.exhibit_id = rsp.id;
                eval(cb);
            });
        }
    }
    this.exhibitedit.remove = function() {
        if( confirm('Are you sure you want to remove exhibit?') ) {
            M.api.getJSONCb('ciniki.ags.exhibitDelete', {'tnid':M.curTenantID, 'exhibit_id':this.exhibit_id}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_ags_main.exhibitedit.close();
            });
        }
    }
    this.exhibitedit.addButton('save', 'Save', 'M.ciniki_ags_main.exhibitedit.save();');
    this.exhibitedit.addClose('Cancel');

    //
    // The participant panel
    //
    this.participant = new M.panel('Participant', 'ciniki_ags_main', 'participant', 'mc', 'large mediumaside columns', 'sectioned', 'ciniki.ags.main.participant');
    this.participant.data = null;
    this.participant.participant_id = 0;
    this.participant.exhibit_id = 0;
    this.participant.exhibitor_id = 0;
    this.participant.nplist = [];
    this.participant.sections = {
        'exhibit_details':{'label':'Exhibit', 'type':'simplegrid', 'num_cols':2, 'aside':'yes', 
            'cellClasses':['flexlabel', ''],
            // Exhibit
            },
        'participant_details':{'label':'Participant', 'type':'simplegrid', 'num_cols':2, 'aside':'yes', 
            'cellClasses':['flexlabel', ''],
            // Name
            // Exhibit
            // Status
            // Option **future**
            // # Items
            },
        'contact_details':{'label':'', 'type':'simplegrid', 'num_cols':2, 'aside':'yes', 
            'cellClasses':['label', ''],
            'changeTxt':'Edit',
            'changeFn':'M.startApp(\'ciniki.customers.edit\',null,\'M.ciniki_ags_main.participant.open();\',\'mc\',{\'customer_id\':M.ciniki_ags_main.participant.data.participant.customer_id});',
            },
        'barcodes':{'label':'Print Barcodes', 'aside':'yes',
            'visible':function() {return M.ciniki_ags_main.participant.data.participant.status == 50 ? 'yes' :'no';},
            'fields':{
                'start_row':{'label':'Row', 'type':'select', 'options':{'1':'1', '2':'2', '3':'3', '4':'4', '5':'5', '6':'6', '7':'7', '8':'8', '9':'9', '10':'10', '11':'11', '12':'12', '13':'13', '14':'14', '15':'15', '16':'16', '17':'17', '18':'18', '19':'19', '20':'20'}},
                'start_col':{'label':'Column', 'type':'select', 'options':{'1':'1', '2':'2', '3':'3', '4':'4'}},
                'tag_info_price':{'label':'Name/Prices', 'type':'toggle', 'default':'no', 'toggles':{'no':'No', 'yes':'Yes'}},
            }},
        '_buttons':{'label':'', 'aside':'yes', 'buttons':{
            'barcodes':{'label':'Exhibit Item Barcodes', 
                'visible':function() {return M.ciniki_ags_main.participant.data.participant.status == 50 ? 'yes' :'no';},
                'fn':'M.ciniki_ags_main.participant.printBarcodes();',
                },
            'accept':{'label':'Accept', 
                'visible':function() {return M.ciniki_ags_main.participant.data.participant.status == 30 ? 'yes' :'no';},
                'fn':'M.ciniki_ags_main.participant.acceptParticipant();',
                },
            'reject':{'label':'Reject', 
                'visible':function() {return M.ciniki_ags_main.participant.data.participant.status == 30 ? 'yes' :'no';},
                'fn':'M.ciniki_ags_main.participant.rejectParticipant();',
                },
            'additem':{'label':'New Exhibitor Item', 
                'visible':function() {return M.ciniki_ags_main.participant.data.participant.status == 50 ? 'yes' :'no';},
                'fn':'M.ciniki_ags_main.item.open(\'M.ciniki_ags_main.participant.open();\',0,M.ciniki_ags_main.participant.exhibitor_id,M.ciniki_ags_main.participant.exhibit_id,null);',
                },
            'inventorypdf':{'label':'Current Inventory (PDF)', 
                'visible':function() {return M.ciniki_ags_main.participant.data.participant.status == 50 ? 'yes' :'no';},
                'fn':'M.ciniki_ags_main.participant.exhibitInventoryPDF();',
                },
            'summarypdf':{'label':'Unpaid Sales (PDF)', 
                'visible':function() {return M.ciniki_ags_main.participant.data.participant.status == 50 ? 'yes' :'no';},
                'fn':'M.ciniki_ags_main.participant.unpaidSalesPDF();',
                },
            }},
        '_tabs':{'label':'', 'type':'paneltabs', 'selected':'inventory', 
            'tabs':{
                'inventory':{'label':'Inventory', 'fn':'M.ciniki_ags_main.participant.switchTab("inventory");'},
                'sales':{'label':'Sales', 'fn':'M.ciniki_ags_main.participant.switchTab("sales");'},
            }},
/*        'inventory_search':{'label':'', 'type':'livesearchgrid', 'livesearchcols':5,
            'visible':function() { return M.ciniki_ags_main.participant.sections._tabs.selected == 'inventory' ? 'yes' : 'hidden'},
            'cellClasses':[''],
            'headerValues':['Exhibitor', 'Code', 'Item', 'Price', 'Quantity'],
            'hint':'Search inventory',
            'noData':'No items found',
            }, */
        'inventory':{'label':'Exhibit Items', 'type':'simplegrid', 'panelcolumn':1, 'num_cols':5,
            'visible':function() { return M.ciniki_ags_main.participant.sections._tabs.selected == 'inventory' ? 'yes' : 'hidden'},
            'sortable':'yes',
            'sortTypes':['text', 'text', 'number', 'number', 'number'],
            'headerValues':['Code', 'Item', 'Price', 'Quantity', ''],
            'cellClasses':['multiline', 'multiline', 'multiline alignright', 'alignright'],
            'noData':'No items in this exhibit',
            },
        'available':{'label':'Catalog Items', 'type':'simplegrid', 'panelcolumn':2, 'num_cols':4,
            'visible':function() { return M.ciniki_ags_main.participant.sections._tabs.selected == 'inventory' ? 'yes' : 'hidden'},
            'sortable':'yes',
            'sortTypes':['text', 'text', 'number', 'number', ''],
            'headerValues':['Code', 'Item', 'Price', ''],
            'cellClasses':['multiline', 'multiline', 'multiline alignright', 'alignright'],
            'noData':'No items in their catalog',
            },
/*        'sales_search':{'label':'', 'type':'livesearchgrid', 'livesearchcols':5,
            'visible':function() { return M.ciniki_ags_main.participant.sections._tabs.selected == 'sales' ? 'yes' : 'hidden'},
            'cellClasses':[''],
            'headerValues':['Name', 'Status', '# Items', 'Amount', 'Fees', 'Net'],
            'hint':'Search sales',
            'noData':'No items found',
            }, */
        'pending_payouts':{'label':'Pending Payouts', 'type':'simplegrid', 'num_cols':7,
            'visible':function() { return M.ciniki_ags_main.participant.sections._tabs.selected == 'sales' ? 'yes' : 'hidden'},
            'sortable':'yes',
            'sortTypes':['text', 'text', 'number', 'date', 'number', 'number', 'number'],
            'headerValues':['Code', 'Item', 'Date', 'Fees', 'Payout', 'Totals'],
            },
        'paid_sales':{'label':'Paid Sales', 'type':'simplegrid', 'num_cols':6,
            'visible':function() { return M.ciniki_ags_main.participant.sections._tabs.selected == 'sales' ? 'yes' : 'hidden'},
            'sortable':'yes',
            'sortTypes':['text', 'text', 'number', 'date', 'number', 'number', 'number'],
            'headerValues':['Code', 'Item', 'Date', 'Fees', 'Payout', 'Totals'],
            },
    }
    this.participant.fieldValue = function(s, i, d) { return this.data[i]; }
//    this.participant.fieldHistoryArgs = function(s, i) {
//        return {'method':'ciniki.ags.participantHistory', 'args':{'tnid':M.curTenantID, 'participant_id':this.participant_id, 'field':i}};
//    }
    this.participant.cellValue = function(s, i, j, d) {
        if( s == 'exhibit_details' ) {
            switch(j) {
                case 0: return d.label;
                case 1: return d.value;
            }
        } 
        if( s == 'participant_details' ) {
            switch(j) {
                case 0: return d.label;
                case 1: return d.value;
            }
        } 
        if( s == 'contact_details' ) {
            switch(j) {
                case 0: return d.label;
                case 1: return d.value;
            }
        }
        if( s == 'inventory' ) {
            switch(j) {
                case 0: 
                    if( d.types != null && d.types != '' ) {
                        return '<span class="maintext">' + d.code + '</span><span class="subtext">' + d.types + '</span>';
                    } 
                    return d.code;
                case 1: 
                    if( d.tag_info != null && d.tag_info != '' ) {
                        return '<span class="maintext">' + d.name + '</span><span class="subtext">' + d.tag_info + '</span>';
                    }
                    return d.name;
                case 2: 
                    if( d.flags_text != null && d.flags_text != '' ) {
                        return '<span class="maintext">' + d.unit_amount_display + '</span><span class="subtext">' + d.flags_text + '</span>';
                    }
                    return d.unit_amount_display;
                case 3: return d.inventory + '<span class="faicon edit">&#xf040;</span>';
                case 4: return '<button onclick="event.stopPropagation();M.ciniki_ags_main.participant.itemRemove(event,' + d.item_id + ');">Remove</button>';
            }
            return '';
        }
        if( s == 'available' ) {
            switch(j) {
                case 0: 
                    if( d.types != null && d.types != '' ) {
                        return '<span class="maintext">' + d.code + '</span><span class="subtext">' + d.types + '</span>';
                    } 
                    return d.code;
                case 1: 
                    if( d.tag_info != null && d.tag_info != '' ) {
                        return '<span class="maintext">' + d.name + '</span><span class="subtext">' + d.tag_info + '</span>';
                    }
                    return d.name;
                case 2: 
                    if( d.flags_text != null && d.flags_text != '' ) {
                        return '<span class="maintext">' + d.unit_amount_display + '</span><span class="subtext">' + d.flags_text + '</span>';
                    }
                    return d.unit_amount_display;
                case 3: return '<button onclick="event.stopPropagation();M.ciniki_ags_main.participant.itemAdd(event,' + d.item_id + ');">Add</button>';
            }
            return '';
        }
        if( s == 'paid_sales' || s == 'pending_payouts' ) {
            switch(j) {
                case 0: return d.code;
                case 1: return d.name;
                case 2: return d.sell_date;
                case 3: return d.tenant_amount_display;
                case 4: return d.exhibitor_amount_display;
                case 5: return d.total_amount_display;
                case 6: return '<button onclick="M.ciniki_ags_main.participant.itemPaid(event,' + d.id + ');">Paid</button>';
            }
        }
    }
    this.participant.footerValue = function(s, i, d) {
        if( s == 'paid_sales' ) {
            switch(i) {
                case 0: return '';
                case 1: return '';
                case 2: return ;
                case 3: return this.data.totals.paid_sales.tenant_amount_display;
                case 4: return this.data.totals.paid_sales.exhibitor_amount_display;
                case 5: return this.data.totals.paid_sales.total_amount_display;
            }
            return '';
        }
        if( s == 'pending_payouts' ) {
            switch(i) {
                case 0: return '';
                case 1: return '';
                case 2: return '';
                case 3: return this.data.totals.pending_payouts.tenant_amount_display;
                case 4: return this.data.totals.pending_payouts.exhibitor_amount_display;
                case 5: return this.data.totals.pending_payouts.total_amount_display;
            }
            return '';
        }
        return null;
    }
    this.participant.cellFn = function(s, i, j, d) {
        if( s == 'inventory' && j == 3 ) {
            return 'event.stopPropagation(); return M.ciniki_ags_main.participant.inventoryUpdate(event,\'' + d.exhibit_item_id + '\');';
        }
        return '';
    }
    this.participant.rowFn = function(s, i, d) {
        if( s == 'inventory' || s == 'available' ) {
            return 'M.ciniki_ags_main.item.open(\'M.ciniki_ags_main.participant.open();\',\'' + d.item_id + '\',0,0);';
        }
        return '';
    }
    this.participant.switchTab = function(t) {
        this.sections._tabs.selected = t;
        this.refreshSection('_tabs');
        this.showHideSection('inventory');
        this.showHideSection('available');
        this.showHideSection('pending_payouts');
        this.showHideSection('paid_sales');
    }
    this.participant.inventoryUpdate = function(event,ei_id) {
        var i = prompt('Enter new inventory quantity: ');
        if( i != null && i != '' ) {
            i = parseFloat(i);
            M.api.getJSONCb('ciniki.ags.exhibitItemUpdate', {'tnid':M.curTenantID, 'exhibit_item_id':ei_id, 'inventory':i}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                if( event.target.tagName == 'SPAN' ) {
                    event.target.parentNode.innerHTML = i + '<span class="faicon edit">&#xf040;</span>';
                } else {
                    event.target.innerHTML = i + '<span class="faicon edit">&#xf040;</span>';
                }
            });
        }
    }
    this.participant.itemAdd = function(event, item_id) {
        M.api.getJSONCb('ciniki.ags.exhibitItemAdd', {'tnid':M.curTenantID, 'exhibit_id':this.data.participant.exhibit_id, 'item_id':item_id, 'quantity':1}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_ags_main.participant;
            // Add new row to inventory table
            var tr = p.createSectionGridRow('inventory', p.data.inventory.length, p.sections.inventory, p.sections.inventory.num_cols, rsp.item);
            var e = M.gE(p.panelUID + '_inventory_grid');
            e = e.getElementsByTagName('tbody')[0];
            if( e.children.length == 1 && e.children[0].children[0].innerHTML == 'No items in this exhibit' ) {
                e.removeChild(e.firstChild);
            }
            e.appendChild(tr);
            // Re-sort table
            M.resortGrid(p.panelUID + '_inventory_grid', p.sections.inventory.sortTypes, null, e);
            // Delete the row that was clicked on
            var r = event.target.parentNode.parentNode;
            var t = r.parentNode;
            t.deleteRow(r.rowIndex-1);
        });
        
    }
    this.participant.itemRemove = function(event, item_id) {
        M.api.getJSONCb('ciniki.ags.exhibitItemDelete', {'tnid':M.curTenantID, 'exhibit_id':this.data.participant.exhibit_id, 'item_id':item_id}, function(rsp) {
            var p = M.ciniki_ags_main.participant;
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            // Add new row to inventory table
            var tr = p.createSectionGridRow('available', p.data.available.length, p.sections.available, p.sections.available.num_cols, rsp.item);
            var e = M.gE(p.panelUID + '_available_grid');
            e = e.getElementsByTagName('tbody')[0];
            if( e.children.length == 1 && e.children[0].children[0].innerHTML == 'No items in their catalog' ) {
                e.removeChild(e.firstChild);
            }
            e.appendChild(tr);
            // Re-sort table
            M.resortGrid(p.panelUID + '_available_grid', p.sections.inventory.sortTypes, null, e);
            // Delete the row that was clicked on
            var r = event.target.parentNode.parentNode;
            var t = r.parentNode;
            t.deleteRow(r.rowIndex-1);
        });
    }
    this.participant.itemPaid = function(e, i) {
        e.stopPropagation();
        this.savePos();
        M.api.getJSONCb('ciniki.ags.participantGet', {'tnid':M.curTenantID, 'participant_id':this.participant_id, 'action':'itempaid', 'sale_id':i}, this.openFinish);
    }
    this.participant.printBarcodes = function() {
        var row = this.formValue('start_row');
        var col = this.formValue('start_col');
        var tip = this.formValue('tag_info_price');
        M.api.openFile('ciniki.ags.exhibitorBarcodes', {'tnid':M.curTenantID, 'exhibit_id':this.data.participant.exhibit_id, 'exhibitor_id':this.data.participant.exhibitor_id, 'start_row':row, 'start_col':col, 'tag_info_price':tip});
    }
    this.participant.exhibitInventoryPDF = function() {
        M.api.openFile('ciniki.ags.exhibitInventoryPDF', {'tnid':M.curTenantID, 'exhibit_id':this.exhibit_id, 'exhibitor_id':this.exhibitor_id});
    }
    this.participant.unpaidSalesPDF = function() {
        M.api.openFile('ciniki.ags.unpaidSalesPDF', {'tnid':M.curTenantID, 'exhibit_id':this.exhibit_id, 'exhibitor_id':this.exhibitor_id});
    }
    this.participant.addCustomer = function(cb, eid) {
        if( cb != null ) { this.cb = cb; }
        this.exhibit_id = eid;
        M.startApp('ciniki.customers.edit',null,cb,'mc',{'next':'M.ciniki_ags_main.participant.addParticipant','customer_id':0});
    }
    this.participant.addParticipant = function(customer_id) {
        M.ciniki_ags_main.editparticipant.addCustomer(this.cb, customer_id, this.exhibit_id);
    }
    this.participant.open = function(cb, pid, list) {
        if( pid != null ) { this.participant_id = pid; }
        if( list != null ) { this.nplist = list; }
        if( cb != null ) { this.cb = cb; }
        M.api.getJSONCb('ciniki.ags.participantGet', {'tnid':M.curTenantID, 'participant_id':this.participant_id}, this.openFinish);
    }
    this.participant.openFinish = function(rsp) {
        if( rsp.stat != 'ok' ) {
            M.api.err(rsp);
            return false;
        }
        var p = M.ciniki_ags_main.participant;
        p.data = rsp;
        p.exhibit_id = rsp.participant.exhibit_id;
        p.exhibitor_id = rsp.participant.exhibitor_id;
        p.refresh();
        p.show();
    }
    this.participant.save = function(cb) {
        if( cb == null ) { cb = 'M.ciniki_ags_main.participant.close();'; }
        if( !this.checkForm() ) { return false; }
        if( this.participant_id > 0 ) {
            var c = this.serializeForm('no');
            if( c != '' ) {
                M.api.postJSONCb('ciniki.ags.participantUpdate', {'tnid':M.curTenantID, 'participant_id':this.participant_id}, c, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    eval(cb);
                });
            } else {
                eval(cb);
            }
        } else {
            var c = this.serializeForm('yes');
            M.api.postJSONCb('ciniki.ags.participantAdd', {'tnid':M.curTenantID}, c, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_ags_main.participant.participant_id = rsp.id;
                eval(cb);
            });
        }
    }
    this.participant.remove = function() {
        if( confirm('Are you sure you want to remove participant?') ) {
            M.api.getJSONCb('ciniki.ags.participantDelete', {'tnid':M.curTenantID, 'participant_id':this.participant_id}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_ags_main.participant.close();
            });
        }
    }
    this.participant.nextButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.participant_id) < (this.nplist.length - 1) ) {
            return 'M.ciniki_ags_main.participant.save(\'M.ciniki_ags_main.participant.open(null,' + this.nplist[this.nplist.indexOf('' + this.participant_id) + 1] + ');\');';
        }
        return null;
    }
    this.participant.prevButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.participant_id) > 0 ) {
            return 'M.ciniki_ags_main.participant.save(\'M.ciniki_ags_main.participant_id.open(null,' + this.nplist[this.nplist.indexOf('' + this.participant_id) - 1] + ');\');';
        }
        return null;
    }
    this.participant.addClose('Back');
    this.participant.addButton('next', 'Next');
    this.participant.addLeftButton('prev', 'Prev');

    //
    // The panel to edit Exhibitor
    //
    this.editparticipant = new M.panel('Participant', 'ciniki_ags_main', 'editparticipant', 'mc', 'medium', 'sectioned', 'ciniki.ags.main.editparticipant');
    this.nextFn = null;
    this.editparticipant.data = null;
    this.editparticipant.participant_id = 0;
    this.editparticipant.exhibitor_id = 0;
    this.editparticipant.customer_id = 0;
    this.editparticipant.nplist = [];
    this.editparticipant.sections = {
        'general':{'label':'', 'fields':{
            'display_name_override':{'label':'Exhibitor Name', 'type':'text'},
            'code':{'label':'Code', 'required':'yes', 'type':'text'},
            'status':{'label':'Status', 'type':'toggle', 'toggles':{'30':'Applied', '50':'Accepted', '90':'Rejected'}},
            }},
        '_buttons':{'label':'', 'buttons':{
            'next':{'label':'Next', 
                'visible':function() { return M.ciniki_ags_main.editparticipant.participant_id == 0 ? 'yes' : 'no'},
                'fn':'M.ciniki_ags_main.editparticipant.save();',
                },
            'save':{'label':'Save', 
                'visible':function() { return M.ciniki_ags_main.editparticipant.participant_id > 0 ? 'yes' : 'no'},
                'fn':'M.ciniki_ags_main.editparticipant.save();',
                },
            }},
        };
    this.editparticipant.fieldValue = function(s, i, d) { return this.data[i]; }
    this.editparticipant.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.ags.exhibitorHistory', 'args':{'tnid':M.curTenantID, 'exhibitor_id':this.exhibitor_id, 'field':i}};
    }
    this.editparticipant.addCustomer = function(cb, cid, eid) {
        this.participant_id = 0;
        this.exhibitor_id = 0;
        this.customer_id = cid;
        this.exhibit_id = eid;
        M.api.getJSONCb('ciniki.ags.participantGet', {'tnid':M.curTenantID, 'participant_id':0, 'exhibitor_id':0, 'customer_id':this.customer_id, 'exhibit_id':this.exhibit_id}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_ags_main.editparticipant;
            p.data = rsp.participant;
            p.customer_id = rsp.participant.customer_id;
            p.exhibitor_id = rsp.participant.exhibitor_id;
            p.refresh();
            p.show(cb);
        });
    }
    this.editparticipant.open = function(cb, pid) {
        this.exhibit_id = 0;
        if( pid != null ) { this.participant_id = pid; }
        M.api.getJSONCb('ciniki.ags.exhibitorGet', {'tnid':M.curTenantID, 'participant_id':this.participant_id}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_ags_main.editparticipant;
            p.data = rsp.participant;
            p.customer_id = rsp.participant.customer_id;
            p.refresh();
            p.show(cb);
        });
    }
    this.editparticipant.save = function(cb) {
        if( cb == null ) { cb = 'M.ciniki_ags_main.exhibitor.close();'; }
        if( !this.checkForm() ) { return false; }
        if( this.participant_id > 0 ) {
            var c = this.serializeForm('no');
            if( c != '' ) { 
                M.api.postJSONCb('ciniki.ags.participantUpdate', {'tnid':M.curTenantID, 'participant_id':this.participant_id}, c, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    eval(cb);
                });
            } else {
                eval(cb);
            }
        } else {
            var c = this.serializeForm('yes');
            M.api.postJSONCb('ciniki.ags.participantAdd', {'tnid':M.curTenantID, 'exhibit_id':this.exhibit_id, 'exhibitor_id':this.exhibitor_id, 'customer_id':this.customer_id}, c, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_ags_main.participant.participant_id = rsp.id;
                M.ciniki_ags_main.participant.open();
            });
        }
    }
    this.editparticipant.addButton('save', 'Save', 'M.ciniki_ags_main.editparticipant.save();');
    this.editparticipant.addClose('Cancel');


    //
    // The panel to list the location
    //
    this.locations = new M.panel('location', 'ciniki_ags_main', 'locations', 'mc', 'medium', 'sectioned', 'ciniki.ags.main.locations');
    this.locations.data = {};
    this.locations.nplist = [];
    this.locations.location_id = 0;
    this.locations.sections = {
        '_tabs':this.menutabs,
//        'search':{'label':'', 'type':'livesearchgrid', 'aside':'yes', 'livesearchcols':1,
//            'cellClasses':[''],
//            'hint':'Search location',
//            'noData':'No location found',
//            },
        'locations':{'label':'Locations', 'type':'simplegrid', 'aside':'yes', 'num_cols':1,
            'noData':'No locations',
            'addTxt':'Add Location',
            'addFn':'M.ciniki_ags_main.location.open(\'M.ciniki_ags_main.locations.open();\',0,null);',
            'editFn':function(s, i, d) {
                return 'M.ciniki_ags_main.location.open(\'M.ciniki_ags_main.locations.open();\',\'' + d.id + '\',M.ciniki_ags_main.location.nplist);';
                },
            },
        'notes':{'label':'Notes', 'type':'html', 'num_cols':1, 
            'visible':function() { 
                return M.ciniki_ags_main.locations.data.notes != null && M.ciniki_ags_main.locations.data.notes != '' ? 'yes' :'no'; 
                },
            },
        '_years':{'label':'', 'type':'paneltabs', 'selected':'', 'tabs':{}, 'visible':'no'},
        'exhibits':{'label':'Exhibits', 'type':'simplegrid', 'num_cols':2, 
            'visible':function() { return M.ciniki_ags_main.locations.location_id > 0 ? 'yes' : 'no'; },
            'cellClasses':['multiline', 'multiline'],
            'noData':'No exhibits',
            'addTxt':'Add Exhibit',
            'addFn':'M.ciniki_ags_main.exhibit.open(\'M.ciniki_ags_main.locations.open();\',0,{\'location_id\':M.ciniki_ags_main.locations.location_id});',
            },
    }
    this.locations.liveSearchCb = function(s, i, v) {
        if( s == 'search' && v != '' ) {
            M.api.getJSONBgCb('ciniki.ags.locationSearch', {'tnid':M.curTenantID, 'start_needle':v, 'limit':'25'}, function(rsp) {
                M.ciniki_ags_main.locations.liveSearchShow('search',null,M.gE(M.ciniki_ags_main.locations.panelUID + '_' + s), rsp.locations);
                });
        }
    }
    this.locations.liveSearchResultValue = function(s, f, i, j, d) {
        return d.name;
    }
    this.locations.liveSearchResultRowFn = function(s, f, i, j, d) {
        return 'M.ciniki_ags_main.location.open(\'M.ciniki_ags_main.locations.open();\',\'' + d.id + '\');';
    }
    this.locations.sectionData = function(s) {
        if( s == 'exhibits' ) {
            if( this.sections._years.selected != '' ) {
                for(var i in this.data.years) {
                    if( this.data.years[i].year == this.sections._years.selected ) {
                        return this.data.years[i].exhibits;
                    }
                }
            }
        }
        if( s == 'notes' ) {
            return this.data.notes.replace(/\n/g, '<br/>');
        }
        return this.data[s];
    }
    this.locations.cellValue = function(s, i, j, d) {
        if( s == 'locations' && M.modFlagOn('ciniki.ags', 0x20) ) {
            switch(j) {
                case 0: return d.category;
                case 1: return d.name;
            }
        } else if( s == 'locations' ) {
            switch(j) {
                case 0: return d.name;
            }
//        } else if( s == 'notes' ) {
//            return M.ciniki_ags_main.locations.data.notes;
        } else if( s == 'exhibits' ) {
            switch(j) {
                case 0: return '<span class="maintext">' + d.start_date_display + '</span><span class="subtext">' + d.end_date_display + '</span>';
                case 1: return '<span class="maintext">' + d.name + '</span><span class="subtext">' + d.location_name + '</span>';
            }
        }
        
    }
    this.locations.rowClass = function(s, i, d) {
        if( s == 'locations' && this.location_id == d.id ) {
            return 'highlight';
        }
        return '';
    }
//    this.locations.editFn = function(s, i, d) {
//        if( s == 'locations' ) {
//            return 'M.ciniki_ags_main.location.open(\'M.ciniki_ags_main.locations.open();\',' + d.id + ',M.ciniki_ags_main.location.nplist);';
//        }
//        return '';
//    }
    this.locations.rowFn = function(s, i, d) {
        if( s == 'locations' ) {
            return 'M.ciniki_ags_main.locations.open(\'M.ciniki_ags_main.locations.open();\',' + d.id + ');';
        } else if( s == 'exhibits' ) {
            return 'M.ciniki_ags_main.exhibit.open(\'M.ciniki_ags_main.locations.open();\',\'' + d.id + '\',null);';
        }
        return '';
    }
    this.locations.switchYear = function(y) {
        this.sections._years.selected = this.data.years[y].year;
        this.refreshSection('_years');
        this.refreshSection('exhibits');
    }
    this.locations.open = function(cb, lid) {
        if( lid != null ) { this.location_id = lid; }
        M.api.getJSONCb('ciniki.ags.locationList', {'tnid':M.curTenantID, 'location_id':this.location_id}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_ags_main.locations;
            p.data = rsp;
            p.size = p.location_id > 0 ? 'medium mediumaside' : 'medium';
            p.sections._years.visible = 'no';
            p.sections._years.tabs = {};
            if( rsp.years != null && rsp.years != '' ) {
                var year_found = 'no';
                var i = 0;
                for(i in rsp.years) {
                    p.sections._years.tabs[rsp.years[i].year] = {'label':rsp.years[i].year, 'fn':'M.ciniki_ags_main.locations.switchYear(' + i + ');'};
                    if( rsp.years[i].year == p.sections._years.selected ) {
                        year_found = 'yes';
                    }
                }
                if( year_found == 'no' ) {
                    p.sections._years.selected = rsp.years[i].year;
                }
                if( rsp.years.length > 1 ) {
                    p.sections._years.visible = 'yes';
                }
            }
            p.sections.locations.num_cols = M.modFlagOn('ciniki.ags', 0x20) ? 2 : 1;
            p.nplist = (rsp.nplist != null ? rsp.nplist : null);
            p.refresh();
            p.show(cb);
        });
    }
    this.locations.addClose('Back');

    //
    // The panel to edit Location
    //
    this.location = new M.panel('Location', 'ciniki_ags_main', 'location', 'mc', 'medium mediumaside', 'sectioned', 'ciniki.ags.main.location');
    this.location.data = null;
    this.location.location_id = 0;
    this.location.nplist = [];
    this.location.sections = {
        'general':{'label':'', 'aside':'yes', 'fields':{
            'name':{'label':'Name', 'required':'yes', 'type':'text'},
            'category':{'label':'Category', 'type':'text',
                'visible':function() {return M.modFlagSet('ciniki.ags', 0x20); },
                },
//            'flags':{'label':'Options', 'type':'flags', 'flags':{'1':{'name':'Visible'}}},
            }},
        '_address':{'label':'Address', 'aside':'yes', 'fields':{
            'address1':{'label':'Address Line 1', 'type':'text'},
            'address2':{'label':'Address Line 2', 'type':'text'},
            'city':{'label':'City', 'type':'text'},
            'province':{'label':'Province', 'type':'text', 'size':'small'},
            'postal':{'label':'Postal', 'type':'text', 'size':'small'},
            'country':{'label':'Country', 'type':'text'},
            }},
        '_map':{'label':'', 'aside':'yes', 'fields':{
            'latitude':{'label':'Latitude', 'type':'text'},
            'longitude':{'label':'Longitude', 'type':'text'},
            }},
        '_map_buttons':{'label':'', 'aside':'yes', 'buttons':{
            '_latlong':{'label':'Lookup Lat/Long', 'fn':'M.ciniki_ags_main.location.lookupLatLong();'},
            }},
        '_notes':{'label':'Notes', 'fields':{
            'notes':{'label':'', 'hidelabel':'yes', 'type':'textarea', 'size':'large'},
            }},
//            'primary_image_id':{'label':'Primary Image', 'type':'text'},
//            'synopsis':{'label':'Synopsis', 'type':'text'},
//        '_description':{'label':'Description', 'fields':{
//            'description':{'label':'', 'hidelabel':'yes', 'type':'textarea', 'size':'large'},
//            }},
        '_buttons':{'label':'', 'buttons':{
            'save':{'label':'Save', 'fn':'M.ciniki_ags_main.location.save();'},
            'delete':{'label':'Delete', 
                'visible':function() {return M.ciniki_ags_main.location.location_id > 0 ? 'yes' : 'no'; },
                'fn':'M.ciniki_ags_main.location.remove();'},
            }},
        };
    this.location.fieldValue = function(s, i, d) { return this.data[i]; }
    this.location.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.ags.locationHistory', 'args':{'tnid':M.curTenantID, 'location_id':this.location_id, 'field':i}};
    }
    this.location.lookupLatLong = function() {
        M.startLoad();
        if( document.getElementById('googlemaps_js') == null) {
            var script = document.createElement("script");
            script.id = 'googlemaps_js';
            script.type = "text/javascript";
            script.src = "https://maps.googleapis.com/maps/api/js?key=" + M.curTenant.settings['googlemapsapikey'] + "&sensor=false&callback=M.ciniki_ags_main.location.lookupGoogleLatLong";
            document.body.appendChild(script);
        } else {
            this.lookupGoogleLatLong();
        }
    }
    this.location.lookupGoogleLatLong = function() {
        var address = this.formValue('address1') + ', ' + this.formValue('address2') + ', ' + this.formValue('city') + ', ' + this.formValue('province');
        var geocoder = new google.maps.Geocoder();
        geocoder.geocode( { 'address': address}, function(results, status) {
            if (status == google.maps.GeocoderStatus.OK) {
                M.ciniki_ags_main.location.setFieldValue('latitude', results[0].geometry.location.lat());
                M.ciniki_ags_main.location.setFieldValue('longitude', results[0].geometry.location.lng());
                M.stopLoad();
            } else {
                alert('We were unable to lookup your latitude/longitude, please check your address in Settings: ' + status);
                M.stopLoad();
            }
        }); 
    }
    this.location.open = function(cb, lid, list) {
        if( lid != null ) { this.location_id = lid; }
        if( list != null ) { this.nplist = list; }
        M.api.getJSONCb('ciniki.ags.locationGet', {'tnid':M.curTenantID, 'location_id':this.location_id}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_ags_main.location;
            p.data = rsp.location;
            p.refresh();
            p.show(cb);
        });
    }
    this.location.save = function(cb) {
        if( cb == null ) { cb = 'M.ciniki_ags_main.location.close();'; }
        if( !this.checkForm() ) { return false; }
        if( this.location_id > 0 ) {
            var c = this.serializeForm('no');
            if( c != '' ) {
                M.api.postJSONCb('ciniki.ags.locationUpdate', {'tnid':M.curTenantID, 'location_id':this.location_id}, c, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    eval(cb);
                });
            } else {
                eval(cb);
            }
        } else {
            var c = this.serializeForm('yes');
            M.api.postJSONCb('ciniki.ags.locationAdd', {'tnid':M.curTenantID}, c, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_ags_main.location.location_id = rsp.id;
                eval(cb);
            });
        }
    }
    this.location.remove = function() {
        if( confirm('Are you sure you want to remove location?') ) {
            M.api.getJSONCb('ciniki.ags.locationDelete', {'tnid':M.curTenantID, 'location_id':this.location_id}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_ags_main.location.close();
            });
        }
    }
    this.location.nextButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.location_id) < (this.nplist.length - 1) ) {
            return 'M.ciniki_ags_main.location.save(\'M.ciniki_ags_main.location.open(null,' + this.nplist[this.nplist.indexOf('' + this.location_id) + 1] + ');\');';
        }
        return null;
    }
    this.location.prevButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.location_id) > 0 ) {
            return 'M.ciniki_ags_main.location.save(\'M.ciniki_ags_main.location_id.open(null,' + this.nplist[this.nplist.indexOf('' + this.location_id) - 1] + ');\');';
        }
        return null;
    }
    this.location.addButton('save', 'Save', 'M.ciniki_ags_main.location.save();');
    this.location.addClose('Cancel');
    this.location.addButton('next', 'Next');
    this.location.addLeftButton('prev', 'Prev');

    //
    // The panel to list the exhibitor
    //
    this.exhibitors = new M.panel('exhibitor', 'ciniki_ags_main', 'exhibitors', 'mc', 'large', 'sectioned', 'ciniki.ags.main.exhibitors');
    this.exhibitors.data = {};
    this.exhibitors.nplist = [];
    this.exhibitors.sections = {
        '_tabs':this.menutabs,
        'search':{'label':'', 'type':'livesearchgrid', 'livesearchcols':1,
            'cellClasses':[''],
            'hint':'Search exhibitors',
            'noData':'No exhibitor found',
            },
        'exhibitors':{'label':'Exhibitors', 'type':'simplegrid', 'num_cols':5,
            'headerValues':['Code', 'Name', 'Items', 'Exhibits', 'Sales'],
            'sortable':'yes',
            'sortTypes':['text', 'text','number', 'number', 'number'],
            'headerClasses':['', '', 'alignright', 'alignright', 'alignright'],
            'cellClasses':['', '', 'alignright', 'alignright', 'alignright'],
            'noData':'No exhibitors',
            'addTxt':'Add Exhibitor',
            'addFn':'M.ciniki_ags_main.exhibitor.addCustomer(\'M.ciniki_ags_main.exhibitors.open();\');',
            },
    }
    this.exhibitors.liveSearchCb = function(s, i, v) {
        if( s == 'search' && v != '' ) {
            M.api.getJSONBgCb('ciniki.ags.exhibitorSearch', {'tnid':M.curTenantID, 'start_needle':v, 'limit':'25'}, function(rsp) {
                M.ciniki_ags_main.exhibitors.liveSearchShow('search',null,M.gE(M.ciniki_ags_main.exhibitors.panelUID + '_' + s), rsp.exhibitors);
                });
        }
    }
    this.exhibitors.liveSearchResultValue = function(s, f, i, j, d) {
        return d.display_name;
    }
    this.exhibitors.liveSearchResultRowFn = function(s, f, i, j, d) {
        return 'M.ciniki_ags_main.exhibitor.open(\'M.ciniki_ags_main.exhibitors.open();\',\'' + d.id + '\');';
    }
    this.exhibitors.cellValue = function(s, i, j, d) {
        if( s == 'exhibitors' ) {
            switch(j) {
                case 0: return d.code;
                case 1: return d.display_name;
                case 2: return d.num_items;
                case 3: return d.num_exhibits;
                case 4: return d.num_sales;
            }
        }
    }
    this.exhibitors.rowFn = function(s, i, d) {
        if( s == 'exhibitors' ) {
            return 'M.ciniki_ags_main.exhibitor.open(\'M.ciniki_ags_main.exhibitors.open();\',\'' + d.id + '\',M.ciniki_ags_main.exhibitor.nplist);';
        }
    }
    this.exhibitors.open = function(cb) {
        M.api.getJSONCb('ciniki.ags.exhibitorList', {'tnid':M.curTenantID}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_ags_main.exhibitors;
            p.data = rsp;
            p.nplist = (rsp.nplist != null ? rsp.nplist : null);
            p.refresh();
            p.show(cb);
        });
    }
    this.exhibitors.addClose('Back');

    //
    // The panel to edit Exhibitor
    //
    this.exhibitor = new M.panel('Exhibitor', 'ciniki_ags_main', 'exhibitor', 'mc', 'xlarge mediumaside', 'sectioned', 'ciniki.ags.main.exhibitor');
    this.exhibitor.data = null;
    this.exhibitor.exhibitor_id = 0;
    this.exhibitor.exhibit_id = 0;
    this.exhibitor.nplist = [];
    this.exhibitor.sections = {
        'exhibitor_details':{'label':'Exhibitor', 'type':'simplegrid', 'num_cols':2, 'aside':'yes',
            'cellClasses':['label', ''],
            'changeTxt':'Edit',
            'changeFn':'M.ciniki_ags_main.editexhibitor.open(\'M.ciniki_ags_main.exhibitor.open();\',M.ciniki_ags_main.exhibitor.exhibitor_id);',
            },
        'customer_details':{'label':'Contact', 'type':'simplegrid', 'num_cols':1, 'aside':'yes',
            'cellClasses':['', ''],
            'changeTxt':'Edit',
            'changeFn':'M.startApp(\'ciniki.customers.edit\',null,\'M.ciniki_ags_main.exhibitor.open();\',\'mc\',{\'customer_id\':M.ciniki_ags_main.exhibitor.data.exhibitor.customer_id});',
            },
        'barcodes':{'label':'Print Barcodes', 'aside':'yes',
            'visible':function() {return M.ciniki_ags_main.exhibitor.exhibitor_id > 0 ? 'yes' :'no';},
            'fields':{
                'start_row':{'label':'Row', 'type':'select', 'options':{'1':'1', '2':'2', '3':'3', '4':'4', '5':'5', '6':'6', '7':'7', '8':'8', '9':'9', '10':'10', '11':'11', '12':'12', '13':'13', '14':'14', '15':'15', '16':'16', '17':'17', '18':'18', '19':'19', '20':'20'}},
                'start_col':{'label':'Column', 'type':'select', 'options':{'1':'1', '2':'2', '3':'3', '4':'4'}},
                'tag_info_price':{'label':'Name/Prices', 'type':'toggle', 'default':'no', 'toggles':{'no':'No', 'yes':'Yes'}},
            }},
        '_buttons':{'label':'', 'aside':'yes', 'buttons':{
            'barcodes':{'label':'Print Barcodes', 
                'visible':function() {return M.ciniki_ags_main.exhibitor.exhibitor_id > 0 ? 'yes' : 'no'; },
                'fn':'M.ciniki_ags_main.exhibitor.printBarcodes();',
                },
            'salespdf':{'label':'Unpaid Sales PDF', 
                'visible':function() {return M.ciniki_ags_main.exhibitor.exhibitor_id > 0 ? 'yes' : 'no'; },
                'fn':'M.ciniki_ags_main.exhibitor.unpaidSalesPDF();',
                },
            'delete':{'label':'Delete Exhibitor', 
                'visible':function() {return M.ciniki_ags_main.exhibitor.exhibitor_id > 0 ? 'yes' : 'no'; },
                'fn':'M.ciniki_ags_main.exhibitor.remove();',
                },
            }},

        '_tabs':{'label':'', 'type':'paneltabs', 'selected':'items', 
            'tabs':{
                'items':{'label':'Catalog', 'fn':'M.ciniki_ags_main.exhibitor.switchTab("items");'},
// Exhibits is mostly ready, not sure if needed???
//                'exhibits':{'label':'Exhibits', 'fn':'M.ciniki_ags_main.exhibitor.switchTab("exhibits");'},
                'sales':{'label':'Sales', 'fn':'M.ciniki_ags_main.exhibitor.switchTab("sales");'},
            }},
        'exhibits':{'label':'Exhibits', 'type':'simplegrid', 'num_cols':5,
            'visible':function() { return M.ciniki_ags_main.exhibitor.sections._tabs.selected == 'exhibits' ? 'yes' : 'hidden'},
            'headerValues':['Name', '# Items', '# Sales', 'Fees', 'Payouts', 'Total'],
            'noData':'No exhibits',
//            'addTxt':'Add exhibit',
//            'addFn':'M.ciniki_ags_main.item.open(\'M.ciniki_ags_main.exhibitor.open();\',0,M.ciniki_ags_main.exhibitor.exhibitor_id,M.ciniki_ags_main.exhibitor.exhibit_id);',
            },
        'item_search':{'label':'', 'type':'livesearchgrid', 'livesearchcols':5,
            'visible':function() { return M.ciniki_ags_main.exhibitor.sections._tabs.selected == 'items' ? 'yes' : 'hidden'},
            'headerValues':['Code', 'Name', 'Price', 'Fee', 'Status'],
            'cellClasses':[''],
            'hint':'Search catalog',
            'noData':'No items found',
            },
        'items':{'label':'Catalog', 'type':'simplegrid', 'num_cols':5,
            'visible':function() { return M.ciniki_ags_main.exhibitor.sections._tabs.selected == 'items' ? 'yes' : 'hidden'},
            'headerValues':['Code', 'Name', 'Price', 'Fee', 'Status'],
            'noData':'No Items',
            'addTxt':'Add Item',
            'addFn':'M.ciniki_ags_main.item.open(\'M.ciniki_ags_main.exhibitor.open();\',0,M.ciniki_ags_main.exhibitor.exhibitor_id,0);',
            },
//        'sales_search':{'label':'', 'type':'livesearchgrid', 'livesearchcols':1,
//            'visible':function() { return M.ciniki_ags_main.exhibitor.sections._tabs.selected == 'sales' ? 'yes' : 'hidden'},
//            'cellClasses':[''],
//            'hint':'Search sales',
//            'noData':'No items found',
//            },
        'pending_payouts':{'label':'Pending Payouts', 'type':'simplegrid', 'num_cols':7,
            'visible':function() { return M.ciniki_ags_main.exhibitor.sections._tabs.selected == 'sales' ? 'yes' : 'hidden'},
            'headerValues':['Item', 'Exhibit', 'Date/Inv #', 'Fees', 'Payout', 'Total'],
            'noData':'No pending payouts',
            },
        'paid_sales':{'label':'Paid Sales', 'type':'simplegrid', 'num_cols':6,
            'visible':function() { return M.ciniki_ags_main.exhibitor.sections._tabs.selected == 'sales' ? 'yes' : 'hidden'},
            'headerValues':['Item', 'Exhibit', 'Date/Inv #', 'Fees', 'Payout', 'Total'],
            'noData':'No Paid Sales',
            },
        };
    this.exhibitor.fieldValue = function(s, i, d) { return this.data[i]; }
    this.exhibitor.liveSearchCb = function(s, i, v) {
        if( s == 'item_search' && v != '' ) {
            M.api.getJSONBgCb('ciniki.ags.exhibitorItemSearch', {'tnid':M.curTenantID, 'start_needle':v, 'exhibitor_id':this.exhibitor_id, 'limit':'25'}, function(rsp) {
                M.ciniki_ags_main.exhibitor.liveSearchShow('item_search',null,M.gE(M.ciniki_ags_main.exhibitor.panelUID + '_' + s), rsp.items);
                });
        }
        if( s == 'sales_search' && v != '' ) {
            M.api.getJSONBgCb('ciniki.ags.exhibitorSalesSearch', {'tnid':M.curTenantID, 'start_needle':v, 'exhibitor_id':this.exhibitor_id, 'limit':'25'}, function(rsp) {
                M.ciniki_ags_main.exhibitor.liveSearchShow('sales_search',null,M.gE(M.ciniki_ags_main.exhibitor.panelUID + '_' + s), rsp.items);
                });
        }
    }
    this.exhibitor.liveSearchResultValue = function(s, f, i, j, d) {
        if( s == 'item_search' ) {
            return this.cellValue(s, i, j, d);
        }
        if( s == 'sales_search' ) {
            return d.name;
        }
    }
    this.exhibitor.liveSearchResultRowFn = function(s, f, i, j, d) {
        return 'M.ciniki_ags_main.item.open(\'M.ciniki_ags_main.exhibitor.open();\',\'' + d.id + '\',0,0);';
    }
    this.exhibitor.cellValue = function(s, i, j, d) {
        if( s == 'exhibitor_details' ) {
            switch(j) {
                case 0: return d.label;
                case 1: return d.value;
            }
        }
        if( s == 'customer_details' ) {
            switch(d.detail.label) {
                case 'Email': return M.linkEmail(d.detail.value);
            }
            return d.detail.value;
        }
        if( s == 'exhibits' ) {
            switch(j) {
                case 0: return d.name;
                case 1: return d.num_items;
                case 2: return d.num_sales;
                case 3: return d.fee_amount_display;
                case 4: return d.payout_amount_display;
                case 5: return d.total_amount_display;
            }
        }
        if( s == 'items' || s == 'item_search' ) {
            switch(j) {
                case 0: return d.code;
                case 1: return d.name;
                case 2: return d.unit_amount_display;
                case 3: return d.fee_percent_display;
                case 4: return d.status_text;
            }
        }
        if( s == 'pending_payouts' || s == 'paid_sales' ) {
            switch(j) {
                case 0: return d.code + ': ' + d.name;
                case 1: return d.exhibit_name;
                case 2: return d.sell_date;
                case 3: return d.tenant_amount_display;
                case 4: return d.exhibitor_amount_display;
                case 5: return d.total_amount_display;
                case 6: return '<button onclick="M.ciniki_ags_main.exhibitor.itemPaid(event,' + d.id + ');">Paid</button>';
            }
        }
    }
    this.exhibitor.footerValue = function(s, i, d) {
        if( s == 'pending_payouts' ) {
            switch(i) {
                case 3: return this.data.pending_payouts_totals.tenant_amount_display;
                case 4: return this.data.pending_payouts_totals.exhibitor_amount_display;
                case 5: return this.data.pending_payouts_totals.total_amount_display;
            }
            return '';
        }
        if( s == 'paid_sales' ) {
            switch(i) {
                case 3: return this.data.paid_sales_totals.tenant_amount_display;
                case 4: return this.data.paid_sales_totals.exhibitor_amount_display;
                case 5: return this.data.paid_sales_totals.total_amount_display;
            }
            return '';
        }
        return null;
    }
    this.exhibitor.rowFn = function(s, i, d) {
        if( s == 'items' ) {
            return 'M.ciniki_ags_main.item.open(\'M.ciniki_ags_main.exhibitor.open();\',\'' + d.id + '\',0,0);';
        }
        return '';
    }
    this.exhibitor.addCustomer = function(cb) {
        if( cb != null ) { this.cb = cb; }
        M.startApp('ciniki.customers.edit',null,cb,'mc',{'next':'M.ciniki_ags_main.exhibitor.addExhibitor','customer_id':0});
    }
    this.exhibitor.addExhibitor = function(customer_id) {
        M.ciniki_ags_main.editexhibitor.addCustomer(this.cb, customer_id);
    }
    this.exhibitor.printBarcodes = function() {
        var row = this.formValue('start_row');
        var col = this.formValue('start_col');
        var tip = this.formValue('tag_info_price');
        M.api.openFile('ciniki.ags.exhibitorBarcodes', {'tnid':M.curTenantID, 'exhibitor_id':this.exhibitor_id, 'start_row':row, 'start_col':col, 'tag_info_price':tip});
    }
    this.exhibitor.unpaidSalesPDF = function() {
        M.api.openFile('ciniki.ags.unpaidSalesPDF', {'tnid':M.curTenantID, 'exhibitor_id':this.exhibitor_id});
    }
    this.exhibitor.itemPaid = function(e, i) {
        e.stopPropagation();
        this.savePos();
        M.api.getJSONCb('ciniki.ags.exhibitorGet', {'tnid':M.curTenantID, 'exhibitor_id':this.exhibitor_id, 'customer_id':this.customer_id, 'action':'itempaid', 'sale_id':i}, this.openFinish);
    }
    this.exhibitor.switchTab = function(t) {
        this.sections._tabs.selected = t;
        this.refreshSection('_tabs');
        this.showHideSection('exhibits');
        this.showHideSection('item_search');
        this.showHideSection('items');
        this.showHideSection('sales_search');
        this.showHideSection('pending_payouts');
        this.showHideSection('paid_sales');
    }
    this.exhibitor.open = function(cb, eid, cid, list) {
        if( eid != null ) { this.exhibitor_id = eid; }
        if( cid != null ) { this.customer_id = cid; }
        if( list != null ) { this.nplist = list; }
        if( cb != null ) { this.cb = cb; }
        M.api.getJSONCb('ciniki.ags.exhibitorGet', {'tnid':M.curTenantID, 'exhibitor_id':this.exhibitor_id, 'customer_id':this.customer_id}, M.ciniki_ags_main.exhibitor.openFinish);
    }
    this.exhibitor.openFinish = function(rsp) {
        if( rsp.stat != 'ok' ) {
            M.api.err(rsp);
            return false;
        }
        var p = M.ciniki_ags_main.exhibitor;
        p.data = rsp;
        p.customer_id = rsp.exhibitor.customer_id;
        p.refresh();
        p.show();
    }
    this.exhibitor.remove = function() {
        if( confirm('Are you sure you want to remove exhibitor?') ) {
            M.api.getJSONCb('ciniki.ags.exhibitorDelete', {'tnid':M.curTenantID, 'exhibitor_id':this.exhibitor_id}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_ags_main.exhibitor.close();
            });
        }
    }
/*    this.exhibitor.nextButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.exhibitor_id) < (this.nplist.length - 1) ) {
            return 'M.ciniki_ags_main.exhibitor.save(\'M.ciniki_ags_main.exhibitor.open(null,' + this.nplist[this.nplist.indexOf('' + this.exhibitor_id) + 1] + ');\');';
        }
        return null;
    }
    this.exhibitor.prevButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.exhibitor_id) > 0 ) {
            return 'M.ciniki_ags_main.exhibitor.save(\'M.ciniki_ags_main.exhibitor_id.open(null,' + this.nplist[this.nplist.indexOf('' + this.exhibitor_id) - 1] + ');\');';
        }
        return null;
    } */
    this.exhibitor.addClose('Back');
//    this.exhibitor.addButton('next', 'Next');
//    this.exhibitor.addLeftButton('prev', 'Prev');

    //
    // The panel to edit Exhibitor
    //
    this.editexhibitor = new M.panel('Exhibitor', 'ciniki_ags_main', 'editexhibitor', 'mc', 'medium', 'sectioned', 'ciniki.ags.main.editexhibitor');
    this.nextFn = null;
    this.editexhibitor.data = null;
    this.editexhibitor.exhibitor_id = 0;
    this.editexhibitor.nplist = [];
    this.editexhibitor.sections = {
        'general':{'label':'', 'fields':{
            'display_name_override':{'label':'Exhibitor Name', 'type':'text'},
            'code':{'label':'Code', 'required':'yes', 'type':'text'},
            }},
        '_buttons':{'label':'', 'buttons':{
            'next':{'label':'Next', 
                'visible':function() { return (this.nextFn != null || M.ciniki_ags_main.editexhibitor.exhibitor_id == 0) ? 'yes' : 'no'},
                'fn':'M.ciniki_ags_main.editexhibitor.save();',
                },
            'save':{'label':'Save', 
                'visible':function() { return (this.nextFn == null || M.ciniki_ags_main.editexhibitor.exhibitor_id > 0) ? 'yes' : 'no'},
                'fn':'M.ciniki_ags_main.editexhibitor.save();',
                },
            }},
        };
    this.editexhibitor.fieldValue = function(s, i, d) { return this.data[i]; }
    this.editexhibitor.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.ags.exhibitorHistory', 'args':{'tnid':M.curTenantID, 'exhibitor_id':this.exhibitor_id, 'field':i}};
    }
    this.editexhibitor.addCustomer = function(cb, cid, nextFn) {
        this.exhibitor_id = 0;
        this.customer_id = cid;
        this.nextFn = nextfn;
        M.api.getJSONCb('ciniki.ags.exhibitorGet', {'tnid':M.curTenantID, 'exhibitor_id':0, 'customer_id':this.customer_id}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_ags_main.editexhibitor;
            p.data = rsp.exhibitor;
            p.customer_id = rsp.exhibitor.customer_id;
            p.refresh();
            p.show(cb);
        });
    }
    this.editexhibitor.open = function(cb, eid) {
        if( eid != null ) { this.exhibitor_id = eid; }
        M.api.getJSONCb('ciniki.ags.exhibitorGet', {'tnid':M.curTenantID, 'exhibitor_id':this.exhibitor_id}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_ags_main.editexhibitor;
            p.data = rsp.exhibitor;
            p.customer_id = rsp.exhibitor.customer_id;
            p.refresh();
            p.show(cb);
        });
    }
    this.editexhibitor.save = function(cb) {
        if( cb == null ) { cb = 'M.ciniki_ags_main.exhibitor.close();'; }
        if( !this.checkForm() ) { return false; }
        if( this.exhibitor_id > 0 ) {
            var c = this.serializeForm('no');
            if( c != '' ) { 
                M.api.postJSONCb('ciniki.ags.exhibitorUpdate', {'tnid':M.curTenantID, 'exhibitor_id':this.exhibitor_id}, c, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    if( this.nextFn != null ) {
                        this.nextFn(this.exhibitor_id);
                    } else {
                        eval(cb);
                    }
                });
            } else {
                if( this.nextFn != null ) {
                    this.nextFn(this.exhibitor_id);
                } else {
                    eval(cb);
                }
            }
        } else {
            var c = this.serializeForm('yes');
            M.api.postJSONCb('ciniki.ags.exhibitorAdd', {'tnid':M.curTenantID, 'customer_id':this.customer_id}, c, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                if( this.nextFn != null ) {
                    this.nextFn(this.exhibitor_id);
                } else {
                    M.ciniki_ags_main.exhibitor.exhibitor_id = rsp.id;
                    M.ciniki_ags_main.exhibitor.open();
                }
            });
        }
    }
    this.editexhibitor.addButton('save', 'Save', 'M.ciniki_ags_main.editexhibitor.save();');
    this.editexhibitor.addClose('Cancel');

    //
    // The panel to edit Item
    //
    this.item = new M.panel('Item', 'ciniki_ags_main', 'item', 'mc', 'large mediumaside columns', 'sectioned', 'ciniki.ags.main.item');
    this.item.data = null;
    this.item.item_id = 0;
    this.item.exhibitor_id = 0;
    this.item.nplist = [];
    this.item.sections = {
        'general':{'label':'', 'aside':'yes', 'fields':{
            'status':{'label':'Status', 'type':'toggle', 'toggles':{'50':'Active', '70':'Sold', '90':'Archived'}},
            'code':{'label':'Code', 'required':'yes', 'type':'text', 'size':'small'},
            'name':{'label':'Name', 'required':'yes', 'type':'text'},
            'tag_info':{'label':'Tag Info', 'required':'no', 'type':'text'},
            'medium':{'label':'Medium', 'required':'no', 'type':'text'},
            'exhibitor_code':{'label':'Exhibitor Code', 'type':'text'},
            }},
        '_types':{'label':'Type', 'aside':'yes', 'fields':{
            'types':{'label':'', 'hidelabel':'yes', 'type':'tags', 'tags':[], 'hint':'Enter a new item type: '},
            }},
        'price':{'label':'Pricing', 'aside':'yes', 'fields':{
            'flags':{'label':'Options', 'type':'flags', 'flags':{'1':{'name':'For Sale'}, '2':{'name':'Sell Online'}, '5':{'name':'Tagged'}}},
            'unit_amount':{'label':'Price', 'type':'text', 'size':'small'},
//            'unit_discount_amount':{'label':'Discount Amount', 'type':'text', 'size':'small'},
//            'unit_discount_percentage':{'label':'Discount Percent', 'type':'text', 'size':'small'},
            'fee_percent':{'label':'Fee %', 'type':'text', 'size':'small'},
            }},
        'exhibit':{'label':'Inventory', 'aside':'yes', 
            'visible':function() { return M.ciniki_ags_main.item.item_id == 0 && M.ciniki_ags_main.item.exhibit_id > 0 ? 'yes' : 'no'; },
            'fields':{
                'quantity':{'label':'Inventory', 'type':'text', 'size':'small'},
            }},
        'inventory':{'label':'Inventory', 'type':'simplegrid', 'num_cols':2, 'aside':'yes',
            'visible':function() { return M.ciniki_ags_main.item.item_id > 0 ? 'yes' : 'no'; },
            'headerValues':['Exhibit', 'Inventory'],
            'headerClasses':['', 'alignright'],
            'cellClasses':['', 'alignright'],
            },
        '_primary_image_id':{'label':'Image', 'type':'imageform', 'panelcolumn':1, 'fields':{
            'primary_image_id':{'label':'', 'type':'image_id', 'hidelabel':'yes', 'controls':'all', 'history':'no',
                'addDropImage':function(iid) {
                    M.ciniki_ags_main.item.setFieldValue('primary_image_id', iid);
                    return true;
                    },
                'addDropImageRefresh':'',
             },
        }},
        '_synopsis':{'label':'Synopsis', 'panelcolumn':1, 'fields':{
            'synopsis':{'label':'', 'hidelabel':'yes', 'type':'textarea', 'size':'small'},
            }},
        '_description':{'label':'Description', 'panelcolumn':1, 'fields':{
            'description':{'label':'', 'hidelabel':'yes', 'type':'textarea', 'size':'large'},
            }},
        'images':{'label':'Additional Images', 'panelcolumn':2, 'type':'simplethumbs'},
        '_images':{'label':'', 'type':'simplegrid', 'panelcolumn':2, 'num_cols':1,
            'addTxt':'Add Image',
            'addFn':'M.ciniki_ags_main.item.save("M.ciniki_ags_main.itemimage.open(\'M.ciniki_ags_main.item.open();\',0,M.ciniki_ags_main.item.item_id);");',
            },
        '_notes':{'label':'Notes', 'panelcolumn':2, 'fields':{
            'notes':{'label':'', 'hidelabel':'yes', 'type':'textarea', 'size':'medium'},
            }},
        '_buttons':{'label':'', 'panelcolumn':2, 'buttons':{
            'save':{'label':'Save', 'fn':'M.ciniki_ags_main.item.save();'},
            'delete':{'label':'Delete', 
                'visible':function() {return M.ciniki_ags_main.item.item_id > 0 ? 'yes' : 'no'; },
                'fn':'M.ciniki_ags_main.item.remove();'},
            }},
        };
    this.item.fieldValue = function(s, i, d) { return this.data[i]; }
    this.item.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.ags.itemHistory', 'args':{'tnid':M.curTenantID, 'item_id':this.item_id, 'field':i}};
    }
    this.item.thumbFn = function(s, i, d) {
        return 'M.ciniki_ags_main.item.save("M.ciniki_ags_main.itemimage.open(\'M.ciniki_ags_main.item.open();\',' + d.id + ',M.ciniki_ags_main.item.item_id);");';
    }
    this.item.cellValue = function(s, i, j, d) {
        switch(j) {
            case 0: return d.exhibit_name;
            case 1: return d.inventory + '<span class="faicon edit">&#xf040;</span>';
        }
    }
    this.item.cellFn = function(s, i, j, d) {
        if( s == 'inventory' && j == 1 ) {
            return 'event.stopPropagation(); return M.ciniki_ags_main.item.inventoryUpdate(event,\'' + d.id + '\');';
        }
        return '';
    }
    this.item.inventoryUpdate = function(event,ei_id) {
        var i = prompt('Enter new inventory quantity: ');
        if( i != null && i != '' ) {
            i = parseFloat(i);
            M.api.getJSONCb('ciniki.ags.exhibitItemUpdate', {'tnid':M.curTenantID, 'exhibit_item_id':ei_id, 'inventory':i}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                if( event.target.tagName == 'SPAN' ) {
                    event.target.parentNode.innerHTML = i + '<span class="faicon edit">&#xf040;</span>';
                } else {
                    event.target.innerHTML = i + '<span class="faicon edit">&#xf040;</span>';
                }
            });
        }
    }
    this.item.open = function(cb, iid, eid, exid,list) {
        if( iid != null ) { this.item_id = iid; }
        if( eid != null ) { this.exhibitor_id = eid; }
        if( exid != null ) { this.exhibit_id = exid; }
        if( list != null ) { this.nplist = list; }
        M.api.getJSONCb('ciniki.ags.itemGet', {'tnid':M.curTenantID, 'item_id':this.item_id, 'exhibitor_id':this.exhibitor_id, 'exhibit_id':this.exhibit_id, 'images':'yes'}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_ags_main.item;
            p.data = rsp.item;
            p.sections._types.fields.types.tags = rsp.types;
            p.refresh();
            p.show(cb);
        });
    }
    this.item.save = function(cb) {
        if( cb == null ) { cb = 'M.ciniki_ags_main.item.close();'; }
        if( !this.checkForm() ) { return false; }
        if( this.item_id > 0 ) {
            var c = this.serializeForm('no');
            if( c != '' ) {
                M.api.postJSONCb('ciniki.ags.itemUpdate', {'tnid':M.curTenantID, 'item_id':this.item_id}, c, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    eval(cb);
                });
            } else {
                eval(cb);
            }
        } else {
            var c = this.serializeForm('yes');
            M.api.postJSONCb('ciniki.ags.itemAdd', {'tnid':M.curTenantID, 'exhibitor_id':this.exhibitor_id, 'exhibit_id':this.exhibit_id}, c, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_ags_main.item.item_id = rsp.id;
                eval(cb);
            });
        }
    }
    this.item.remove = function() {
        if( confirm('Are you sure you want to remove item?') ) {
            M.api.getJSONCb('ciniki.ags.itemDelete', {'tnid':M.curTenantID, 'item_id':this.item_id}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_ags_main.item.close();
            });
        }
    }
    this.item.nextButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.item_id) < (this.nplist.length - 1) ) {
            return 'M.ciniki_ags_main.item.save(\'M.ciniki_ags_main.item.open(null,' + this.nplist[this.nplist.indexOf('' + this.item_id) + 1] + ');\');';
        }
        return null;
    }
    this.item.prevButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.item_id) > 0 ) {
            return 'M.ciniki_ags_main.item.save(\'M.ciniki_ags_main.item_id.open(null,' + this.nplist[this.nplist.indexOf('' + this.item_id) - 1] + ');\');';
        }
        return null;
    }
    this.item.addButton('save', 'Save', 'M.ciniki_ags_main.item.save();');
    this.item.addClose('Cancel');
//    this.item.addButton('next', 'Next');
//    this.item.addLeftButton('prev', 'Prev');

    //
    // The panel to edit Item Image
    //
    this.itemimage = new M.panel('Item Image', 'ciniki_ags_main', 'itemimage', 'mc', 'medium', 'sectioned', 'ciniki.ags.main.itemimage');
    this.itemimage.data = null;
    this.itemimage.item_id = 0;
    this.itemimage.itemimage_id = 0;
    this.itemimage.nplist = [];
    this.itemimage.sections = {
        '_image_id':{'label':'Image', 'type':'imageform', 'aside':'yes', 'fields':{
            'image_id':{'label':'', 'type':'image_id', 'hidelabel':'yes', 'controls':'all', 'history':'no',
                'addDropImage':function(iid) {
                    M.ciniki_ags_main.itemimage.setFieldValue('image_id', iid);
                    return true;
                    },
                'addDropImageRefresh':'',
             },
        }},
        'general':{'label':'', 'fields':{
            'name':{'label':'Name', 'type':'text'},
            'flags':{'label':'Options', 'type':'flags', 'flags':{'1':{'name':'Visible'}, '2':{'name':'Sold'}}},
            }},
        '_description':{'label':'Description', 'fields':{
            'description':{'label':'', 'hidelabel':'yes', 'type':'textarea'},
            }},
        '_buttons':{'label':'', 'buttons':{
            'save':{'label':'Save', 'fn':'M.ciniki_ags_main.itemimage.save();'},
            'delete':{'label':'Delete', 
                'visible':function() {return M.ciniki_ags_main.itemimage.itemimage_id > 0 ? 'yes' : 'no'; },
                'fn':'M.ciniki_ags_main.itemimage.remove();'},
            }},
        };
    this.itemimage.fieldValue = function(s, i, d) { return this.data[i]; }
    this.itemimage.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.ags.itemImageHistory', 'args':{'tnid':M.curTenantID, 'itemimage_id':this.itemimage_id, 'field':i}};
    }
    this.itemimage.open = function(cb, iid, item_id, list) {
        if( iid != null ) { this.itemimage_id = iid; }
        if( item_id != null ) { this.item_id = item_id; }
        if( list != null ) { this.nplist = list; }
        M.api.getJSONCb('ciniki.ags.itemImageGet', {'tnid':M.curTenantID, 'itemimage_id':this.itemimage_id}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_ags_main.itemimage;
            p.data = rsp.itemimage;
            p.refresh();
            p.show(cb);
        });
    }
    this.itemimage.save = function(cb) {
        if( cb == null ) { cb = 'M.ciniki_ags_main.itemimage.close();'; }
        if( !this.checkForm() ) { return false; }
        if( this.itemimage_id > 0 ) {
            var c = this.serializeForm('no');
            if( c != '' ) {
                M.api.postJSONCb('ciniki.ags.itemImageUpdate', {'tnid':M.curTenantID, 'itemimage_id':this.itemimage_id}, c, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    eval(cb);
                });
            } else {
                eval(cb);
            }
        } else {
            var c = this.serializeForm('yes');
            M.api.postJSONCb('ciniki.ags.itemImageAdd', {'tnid':M.curTenantID, 'item_id':this.item_id}, c, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_ags_main.itemimage.itemimage_id = rsp.id;
                eval(cb);
            });
        }
    }
    this.itemimage.remove = function() {
        if( confirm('Are you sure you want to remove itemImage?') ) {
            M.api.getJSONCb('ciniki.ags.itemImageDelete', {'tnid':M.curTenantID, 'itemimage_id':this.itemimage_id}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_ags_main.itemimage.close();
            });
        }
    }
    this.itemimage.nextButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.itemimage_id) < (this.nplist.length - 1) ) {
            return 'M.ciniki_ags_main.itemimage.save(\'M.ciniki_ags_main.itemimage.open(null,' + this.nplist[this.nplist.indexOf('' + this.itemimage_id) + 1] + ');\');';
        }
        return null;
    }
    this.itemimage.prevButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.itemimage_id) > 0 ) {
            return 'M.ciniki_ags_main.itemimage.save(\'M.ciniki_ags_main.itemimage_id.open(null,' + this.nplist[this.nplist.indexOf('' + this.itemimage_id) - 1] + ');\');';
        }
        return null;
    }
    this.itemimage.addButton('save', 'Save', 'M.ciniki_ags_main.itemimage.save();');
    this.itemimage.addClose('Cancel');
    this.itemimage.addButton('next', 'Next');
    this.itemimage.addLeftButton('prev', 'Prev');



    //
    // Start the app
    // cb - The callback to run when the user leaves the main panel in the app.
    // ap - The application prefix.
    // ag - The app arguments.
    //
    this.start = function(cb, ap, ag) {
        args = {};
        if( ag != null ) {
            args = eval(ag);
        }
        
        //
        // Create the app container
        //
        var ac = M.createContainer(ap, 'ciniki_ags_main', 'yes');
        if( ac == null ) {
            alert('App Error');
            return false;
        }
       
        this[this.menutabs.selected].open(cb);
    }
}
