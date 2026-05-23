<template>
<div :style="format.style">
    <template v-if="format.type == 'debug'">
        <pre>{{format}}</pre>
    </template>
    <template v-else-if="format.type == 'simpletext'">
        {{renderText}}
    </template>
    <template v-else-if="format.type == 'text'">
        <v-md-preview v-if="renderText != undefined && renderText.length > 0"
                      :text="renderText" />
    </template>
    <template v-else-if="format.type == 'image'">
        Image goes here
    </template>
    <template v-else>
        Unknown field type: {{format.type}}
    </template>
</div>
</template>

<script>

export default {
    props: ['format', 'value'],
    data: () => ({
        // userResponse: ""
    }),
    methods: {},
    computed: {
        renderText() {
            if (!this.value) {
                //console.log('rendering bare template because badge', this.value)
                return this.format.text;
            }
            //console.log('rendering template', this.format.text)
            //var r = expandTpl(this.format.text, this.value);
            //console.log('render result', r)
            return this.$compileTemplate(this.format.text, this.value);
        }
    },

};
</script>
