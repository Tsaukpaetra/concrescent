<template>
    <v-tooltip right>
        <template v-slot:activator="{ on, attrs }">
            <div v-bind="attrs"
            v-on="on">
            <div :style="styleRealName">{{ badge.real_name }}</div>
            <div :style="styleFandomName">{{ badge.fandom_name }} </div>
        </div>
        </template>
        <span>{{badge.name_on_badge}}</span>
    </v-tooltip>
</template>

<script>
export default {
    components: {},
    props: ['badge'],
    data: () => ({}),
    computed: {
        styleRealName() {

            switch (this.badge.name_on_badge) {
                case 'Fandom Name Large, Real Name Small':
                    return "color: black;";
                case 'Fandom Name Only':
                    return "color: grey";
                case 'Real Name Large, Fandom Name Small':
                    return "color: black; font-weight: bold;";
                case 'Real Name Only':
                    return "color: black; font-weight: bold;";

            }
            return "color: teal;";
        },

        styleFandomName() {

            switch (this.badge.name_on_badge) {
                case 'Fandom Name Large, Real Name Small':
                    return "color: black; font-weight: bold;";
                case 'Fandom Name Only':
                    return "color: black; font-weight: bold;";
                case 'Real Name Large, Fandom Name Small':
                    return "color: black;";
                case 'Real Name Only':
                    return "color: gray;";
            }
            return "color: teal;";
        }
    },
    methods: {
        badgeDisplayName(value, secondary) {
            if (typeof value === 'undefined' || value == null) { return null; }
            // Fixup raw DB values if the corrected values don't exist
            if (typeof value.name_on_badge === 'undefined' && typeof value['name-on-badge'] === 'string') { value.name_on_badge = value['name-on-badge']; }
            if (typeof value.real_name === 'undefined' && typeof value['real-name'] === 'string') { value.real_name = value['real-name']; }
            if (typeof value.fandom_name === 'undefined' && typeof value['fandom-name'] === 'string') { value.fandom_name = value['fandom-name']; }

            if (!value.fandom_name) {
                // We don't really care, just put the first and last name
                return secondary ? null : value.real_name;
            }
            let { name_on_badge } = value;
            // default it if not set
            if (name_on_badge == null) {
                name_on_badge = 'Fandom Name Large, Real Name Small';
            }

            if (!secondary) {
                switch (name_on_badge) {
                    case 'Fandom Name Large, Real Name Small':
                    case 'Fandom Name Only':
                        return value.fandom_name;
                    case 'Real Name Large, Fandom Name Small':
                    case 'Real Name Only':
                        return value.real_name;
                }
            } else {
                console.log('/?r', name_on_badge)
                switch (name_on_badge) {
                    case 'Fandom Name Large, Real Name Small':
                        return value.real_name;
                    case 'Real Name Large, Fandom Name Small':
                        return value.fandom_name;
                    case 'Real Name Only':
                    case 'Fandom Name Only':
                        return null;
                }
            }
        }
    }
};
</script>
