<template>
    <!-- v-input handles the label, error states, and messages layout -->
    <v-input :value="expiresInValue" :rules="rules" :error-messages="errorMessages" :disabled="disabled"
        :readonly="readonly" :hint="hint" :persistent-hint="persistentHint" class="duration-picker-wrapper">
        <template v-slot:default>
            <v-card outlined :class="['pa-1 d-inline-block', { 'error--text border-error': hasError }]"
                style="max-width: 520px;">
                <v-row no-gutters align="center">
                    <v-col v-for="(unit) in durationUnits" :key="unit.key" :style="{
                        width: '80px',
                        borderRight: 'none'
                    }" class="flex-grow-0">
                        <div class="duration-input-box">
                            <input v-model.number="components[unit.key]" type="number" :disabled="disabled"
                                :readonly="readonly" class="duration-native-input" @keyup="handleKeyUp"
                                @blur="normalizeInputs" />
                            <span
                                class="duration-suffix grey--text text--darken-1 font-weight-medium text-caption select-none">
                                {{ unit.suffix }}
                            </span>
                        </div>
                    </v-col>
                </v-row>
            </v-card>
        </template>
    </v-input>
</template>

<script>
const MULTIPLIERS = {
    y: 31536000,
    mo: 2592000,
    d: 86400,
    h: 3600,
    m: 60,
    s: 1
};

export default {
    name: 'ValidatedDurationPicker',
    props: {
        value: { type: Number, default: 0 },
        rules: { type: Array, default: () => [] },
        errorMessages: { type: [String, Array], default: () => [] },
        disabled: { type: Boolean, default: false },
        readonly: { type: Boolean, default: false },
        hint: { type: String, default: '' },
        persistentHint: { type: Boolean, default: false }
    },
    data() {
        return {
            expiresInValue: 0,
            durationUnits: [
                { key: 'y', suffix: 'y' },
                { key: 'mo', suffix: 'mo' },
                { key: 'd', suffix: 'd' },
                { key: 'h', suffix: 'h' },
                { key: 'm', suffix: 'm' },
                { key: 's', suffix: 's' }
            ],
            components: { y: 0, mo: 0, d: 0, h: 0, m: 0, s: 0 }
        };
    },
    computed: {
        hasError() {
            return this.errorMessages.length > 0;
        }
    },
    watch: {
        value: {
            immediate: true,
            handler(newVal) {
                if (newVal !== this.expiresInValue) {
                    this.expiresInValue = newVal;
                    this.parseSecondsToComponents(newVal);
                }
            }
        }
    },
    methods: {
        handleKeyUp(event) {
            // Check if the pressed key is an arrow key
            const arrowKeys = ['ArrowUp', 'ArrowDown'];
            if (arrowKeys.includes(event.key)) {
                // We use a small timeout to allow the v-model to update the 
                // component value before we recalculate the total
                this.$nextTick(() => {
                    this.updateExpiresIn();
                    this.parseSecondsToComponents(this.expiresInValue)
                });
            }
        },
        updateExpiresIn() {
            let totalSeconds = 0;
            for (const key in this.components) {
                const val = parseInt(this.components[key], 10) || 0;
                totalSeconds += val * MULTIPLIERS[key];
            }
            this.expiresInValue = totalSeconds;
            this.$emit('input', totalSeconds);
        },
        normalizeInputs() {
            this.parseSecondsToComponents(this.expiresInValue);
        },
        parseSecondsToComponents(totalSeconds) {
            let remainder = totalSeconds;
            this.components.y = Math.floor(remainder / MULTIPLIERS.y); remainder %= MULTIPLIERS.y;
            this.components.mo = Math.floor(remainder / MULTIPLIERS.mo); remainder %= MULTIPLIERS.mo;
            this.components.d = Math.floor(remainder / MULTIPLIERS.d); remainder %= MULTIPLIERS.d;
            this.components.h = Math.floor(remainder / MULTIPLIERS.h); remainder %= MULTIPLIERS.h;
            this.components.m = Math.floor(remainder / MULTIPLIERS.m); remainder %= MULTIPLIERS.m;
            this.components.s = remainder;
        }
    }
};
</script>

<style scoped>
.duration-input-box {
    display: flex;
    align-items: center;
    justify-content: flex-end;
}

.duration-native-input {
    width: 100%;
    min-width: 24px;
    text-align: right;
    border: none;
    outline: none;
    background: transparent;
}

/* Hide native spinners for Chrome, Safari, Edge, Opera */
.duration-native-input::-webkit-outer-spin-button,
.duration-native-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Hide native spinners for Firefox */
.duration-native-input[type=number] {
    -moz-appearance: textfield;
}

.duration-suffix {
    margin-left: 4px;
    flex-shrink: 0;
}

.select-none {
    user-select: none;
}
</style>