import exportFromJSON from 'export-from-json';
import admin from '../api/admin';

export const exportTableMixin = {
    // Define common state for the export dialog
    data() {
        return {
            optionExportFormat: 'csv',
            optionExportRawHeaders: false,
            optionExportIncludeHidden: false,
        };
    },
    methods: {
        doExport: function (fileName) {
            if (typeof this.loading != 'undefined') this.loading = true;
            console.log('doing export', typeof this.loading)
            admin.genericGetList(this.apiPath, this?.pageOptionsForGet || {}, (results, total) => {
                if (typeof this.loading != 'undefined') this.loading = false;

                //Add virtual columns
                results = this.exportMixin_addDisplayedVirtualColumns(results);
                //Make values pretty if asked
                if (!this.optionExportRawHeaders) {
                    results = this.exportMixin_makeValuesPretty(results);
                }
                //Remove hidden columns if they're not asked for
                if (!this.optionExportIncludeHidden) {
                    results = this.exportMixin_hideColumnsHidden(results);
                }
                //Finally make headers pretty
                if (!this.optionExportRawHeaders) {
                    results = this.exportMixin_makeHeadersPretty(results);
                }
                // Iterate through the results and replace explicit null values with an empty string
                results = results.map(obj => {
                    return Object.fromEntries(
                        Object.entries(obj).map(([key, value]) => [key, value === null ? '' : value])
                    );
                });
                results = this.exportMixin_makeItemsOrdered(results);

                //TODO: auto-generate name from context info?
                console.log('exporting', fileName)
                if (!fileName || typeof fileName != 'string') fileName = 'Export';
                const exportType = this.optionExportFormat;

                exportFromJSON({
                    data: results,
                    fileName,
                    exportType,
                    // fields: headerOrder,
                    withBOM: true
                });
            })
        },
        exportMixin_makeHeadersPretty(input) {
            if (!this.exportMixin_CheckPropExists('availableHeaders')) return input;
            if (typeof input !== 'object') return input;
            if (Array.isArray(input)) return input.map(this.exportMixin_makeHeadersPretty, this);
            var that = this;
            return Object.keys(input).reduce(function (newObj, key) {
                let val = input[key];
                let newVal = (typeof val === 'object') && val !== null ? that.exportMixin_makeHeadersPretty.call(that, val) : val;
                //find new key
                var newKeyObj = that.availableHeaders.find((header) => {
                    if (header == key) return true;
                    if (header.value == key) return true;
                })
                if (typeof newKeyObj == 'string') newKeyObj = { value: newKeyObj, text: newKeyObj };
                if (typeof newKeyObj == 'undefined') newKeyObj = { value: key, text: key };
                newObj[newKeyObj.text] = newVal;
                return newObj;
            }, {});
        },
        exportMixin_makeValuesPretty(input) {
            if (!this.exportMixin_CheckPropExists('exportPrettifiers')) return input;
            if (typeof input !== 'object') return input;
            if (Array.isArray(input)) return input.map(this.exportMixin_makeValuesPretty, this);

            return Object.keys(input).reduce((newObj, key) => {
                let value = input[key];

                // Apply transformations if a prettifier exists for this specific key
                if (this.exportPrettifiers[key]) {
                    try {
                        value = this.exportPrettifiers[key](value, input);
                    } catch (error) {
                        console('exportMixin_makeValuesPretty: Error calling prettifier function for ' + key, error, input)
                    }
                }
                newObj[key] = value;
                return newObj;
            }, {});
        },
        exportMixin_addDisplayedVirtualColumns(input) {
            if (!(this.exportMixin_CheckPropExists('displayedHeaders') && this.exportMixin_CheckPropExists('exportBuiltinPrettifiers'))) return input;
            if (typeof input !== 'object') return input;
            if (Array.isArray(input)) return input.map(this.exportMixin_addDisplayedVirtualColumns, this);
            let virtualHeaders = this.displayedHeaders.filter(k => !(k in input));
            return virtualHeaders.reduce((newObj, key) => {
                let value = '';
                //Use available built-in prettier for initial data, if it exists
                if (this.exportBuiltinPrettifiers[key]) {
                    try {
                        value = this.exportBuiltinPrettifiers[key](input);
                    } catch (error) {
                        console('exportMixin_addDisplayedVirtualColumns: Error calling built-in prettifier function for ' + key, error, input)
                    }
                }

                if (this.exportPrettifiers[key]) {
                    try {
                        value = this.exportPrettifiers[key](value, input);
                    } catch (error) {
                        console('exportMixin_addDisplayedVirtualColumns: Error calling custom prettifier function for ' + key, error, input)
                    }
                }
                newObj[key] = value;
                return newObj;
            }, input);
        },
        exportMixin_hideColumnsHidden(input) {
            if (!(this.exportMixin_CheckPropExists('displayedHeaders') && this.exportMixin_CheckPropExists('availableHeaders'))) return input;
            if (typeof input !== 'object') return input;
            if (Array.isArray(input)) return input.map(this.exportMixin_hideColumnsHidden, this);
            let displayedHeaders = this.displayedHeaders.map(h => this.availableHeaders.find(a => a.value == h));
            return Object.keys(input).reduce((newObj, key) => {
                let value = input[key];
                //Only add to the object if it's not hidden
                if (displayedHeaders.find((header) => header.value == key || header.text == key)) {
                    newObj[key] = value;
                }
                return newObj;
            }, {});

        },
        exportMixin_makeItemsOrdered(input) {

            if (typeof input !== 'object') return input;
            if (Array.isArray(input)) return input.map(this.exportMixin_makeItemsOrdered, this);
            let displayedHeaders = this.displayedHeaders.map(h => this.availableHeaders.find(a => a.value == h));

            let firstItemKeys = Object.keys(input);

            let headerOrder = [
                ...displayedHeaders.filter(k => firstItemKeys.includes(k.value) || firstItemKeys.includes(k.text)).map(key => this.optionExportRawHeaders ? key.value : key.text),
                ...firstItemKeys.filter(k => !(displayedHeaders.find(j => j.value == k || j.text == k)))
            ];
            // Reconstruct the object with keys in the exact right sequence
            return headerOrder.reduce((newObj, key) => {
                newObj[key] = input[key];
                return newObj;
            }, {});
        },
        exportMixin_CheckPropExists(propname, logit) {
            if (!(Object.prototype.hasOwnProperty.call(this, propname) || this[propname] !== undefined)) {
                if (logit && process.env.NODE_ENV !== 'production') {
                    console.warn(
                        "[exportMixin]: The component using this mixin should define the '" + propname + "' prop. "
                    );
                }
                return false;
            }
            return true;
        },
        //Shamelessly stolen from the runtime for Vue2
        exportMixin_getComponentName(vm) {
            if (vm.$root === vm) {
                return '<Root>';
            }
            var options = typeof vm == 'function' && vm.cid != null
                ? vm.options
                : vm._isVue
                    ? vm.$options || vm.constructor.options
                    : vm;
            var name = options.name || options.__name || options._componentTag;
            var file = options.__file;
            if (!name && file) {
                var match = file.match(/([^/\\]+)\.vue$/);
                name = match && match[1];
            }

            var classifyRE_1 = /(?:^|[-_])(\w)/g;
            var classify_1 = function (str) {
                return str.replace(classifyRE_1, function (c) { return c.toUpperCase(); }).replace(/[-_]/g, '');
            };
            return ((name ? "<".concat(classify_1(name), ">") : "<Anonymous>") +
                (file ? " at ".concat(file) : ''));
        },
        exportMixin_generateComponentTrace() {
            let vm = this;
            if (vm._isVue && vm.$parent) {
                var tree = [];
                var currentRecursiveSequence = 0;
                while (vm) {
                    if (tree.length > 0) {
                        var last = tree[tree.length - 1];
                        if (last.constructor === vm.constructor) {
                            currentRecursiveSequence++;
                            vm = vm.$parent;
                            continue;
                        }
                        else if (currentRecursiveSequence > 0) {
                            tree[tree.length - 1] = [last, currentRecursiveSequence];
                            currentRecursiveSequence = 0;
                        }
                    }
                    tree.push(vm);
                    vm = vm.$parent;
                }

                var repeat_1 = function (str, n) {
                    var res = '';
                    while (n) {
                        if (n % 2 === 1)
                            res += str;
                        if (n > 1)
                            str += str;
                        n >>= 1;
                    }
                    return res;
                };
                return ('\n\nfound in\n\n' +
                    tree
                        .map(function (vm, i) {
                            return "".concat(i === 0 ? '---> ' : repeat_1(' ', 5 + i * 2)).concat(typeof vm == 'array'
                                ? "".concat(this.exportMixin_getComponentName(vm[0]), "... (").concat(vm[1], " recursive calls)")
                                : this.exportMixin_getComponentName(vm));
                        }, this)
                        .join('\n'));
            }
            else {
                return "\n\n(found in ".concat(this.exportMixin_getComponentName(vm), ")");
            }
        }
        //end runtime code theft
    },
    mounted() {
        
        if (!this.apiPath) {
            console.warn("[ExportMixin]: 'apiPath' is missing. Export functionality may fail.");
        }
        // Validation check: Verify the component has implemented the required contract
        if (process.env.NODE_ENV !== 'production') {
            //Check Properties we expect to have
            if (![
                'exportPrettifiers',
                'exportBuiltinPrettifiers',
                'pageOptionsForGet',
                'isExporting',
                'availableHeaders',
                'displayedHeaders',
            ].reduce((missing, prop) => this.exportMixin_CheckPropExists(prop, true) && missing, true)) {
                console.warn('[exportMixin]: The associated functionality depending on these props will be disabled.' + this.exportMixin_generateComponentTrace())
            }
        }
    }
};