function updateQueryId(newId,oldId) {

    console.log('subTabEditorByQueryId watcher: $route.query.id', newId, oldId)
    if (newId === oldId) return; //Shouldn't happen unless for some reason we're changing tabs but keeping the same id?
    const id = this.$route.query.id;
    const tab = this.subTabIx;
    let ed = this.subTabs.find((t) => t.key == tab)?.editor;
    console.log('select tab', tab, ed, this.subTabs)

    if (ed == undefined) return; //an ID was specified but no editor that would handle it?
    if (id) {
        if (id != this[ed.editObject][ed.editKey ?? 'id']) {
            //A different ID is loaded or not loaded, do so
            console.log('loading editor', tab, id)
            let dummy = {};
            dummy[ed.editKey ?? 'id'] = id
            this[ed.editFunction](dummy);
        }
    } else {
        console.log('id is nothing so closing the associated editor', id, ed.dialogToggle)
        //Ensure editor is closed and the data removed
        this[ed.dialogToggle] = false;
        this[ed.editObject] = {};
    }
}

export const subTabEditorByQueryIdMixin = {
    methods: {
        setQueryId: function (id) {
            // 1. Shallow clone the current query object
            const query = { ...this.$route.query };

            // 2. Safely apply your ID change
            if (id) {
                query.id = id;
            } else {
                delete query.id;
            }

            console.log('Pushing named route with query:', query);

            // 3. Navigate using the NAME and explicitly supply active params
            this.$router.push({
                name: this.$route.name,
                params: this.$route.params,
                query: query
            }).catch((err) => {
                if (err.name !== 'NavigationDuplicated') {
                    console.error('fail setQueryId', err);
                }
            });
        },
    },
    watch: {
        '$route.query.id': {
            handler: updateQueryId,
        },
    },
  mounted() {    
    for (let subTabIx in this.subTabs) {
        let ed = this.subTabs[subTabIx].editor;
        if (ed != undefined) {
            //Create watcher
            this.$watch(ed.dialogToggle, function(editing){
                this.setQueryId(editing ? this[ed.editObject][ed.editKey||'id'] : null );
            })
        }
    }
    this.$nextTick(() => {
        console.log('calling init updateQueryId')
        updateQueryId.call(this,this.$route.query.id,undefined);
    });
  }
};