<template>
  <v-form ref="form" lazy-validation>
    <!-- Name field -->
   <v-combobox v-model="nameData" :items="formattedSuggestions" :multiple="false" :readonly="!allowSetName"
      :rules="fileNameRules" :return-object="true" item-value="value" item-text="text" :messages="selectedNameTip"
      label="Template Name" placeholder="Enter or select a template name" prepend-icon="mdi-file-edit-outline" required
      @input="handleName">
      <template v-slot:item="{ item }">
        <v-list-item-content>
          <v-list-item-title>{{ item.text }}</v-list-item-title>
          <v-list-item-subtitle class="text--secondary">
            {{ item.tip }}
          </v-list-item-subtitle>
        </v-list-item-content>
      </template></v-combobox>

    <!-- Active toggle -->
    <v-switch v-model="emailData.active" :true-value="1" :false-value="0" @change="emitChanges" label="Enable Template"
      hide-details class="mb-3"></v-switch>

    <!-- FROM FIELD -->
    <v-text-field v-model="emailData.from" label="From" prepend-icon="mdi-account-arrow-right" :rules="[rules.email]"
      placeholder="(Default)" persistent-placeholder @input="emitChanges"></v-text-field>
    <!-- TO FIELD -->
    <v-combobox v-if="showTo" v-model="emailData.to" :search-input.sync="searchTO" label="To"
      hint="Press Enter or comma to add multiple recipients" multiple chips small-chips deletable-chips disable-lookup
      append-icon="" append-outer-icon="mdi-close" @click:append-outer="clearAndHide('to')"
      prepend-icon="mdi-account-arrow-left" :rules="[rules.required, rules.email]"
      @change="processAddressInput('to')" @paste.native="processAddressPastedText('to', $event)">
      <template #selection="{ item, selected, disabled, parent }">
        <v-chip small :input-value="selected" :disabled="disabled"
          :color="isValidEmailFormat(item) ? 'light-grey' : 'error'"
          :text-color="isValidEmailFormat(item) ? 'black' : 'white'" close @click:close="parent.selectItem(item)">
          <span>{{ item }}</span>
        </v-chip>
      </template></v-combobox>

    <!-- REPLY-TO FIELD -->
    <v-combobox v-if="showREPLY_TO" v-model="emailData.reply_to" :search-input.sync="searchREPLY_TO" label="Reply To"
      hint="Press Enter or comma to add multiple recipients" multiple chips small-chips deletable-chips disable-lookup
      append-icon="" append-outer-icon="mdi-close" @click:append-outer="clearAndHide('reply_to')"
      prepend-icon="mdi-comment-account" :rules="[rules.emailArray]"
      @change="processAddressInput('reply_to')" @paste.native="processAddressPastedText('reply_to', $event)">
      <template #selection="{ item, selected, disabled, parent }">
        <v-chip small :input-value="selected" :disabled="disabled"
          :color="isValidEmailFormat(item) ? 'light-grey' : 'error'"
          :text-color="isValidEmailFormat(item) ? 'black' : 'white'" close @click:close="parent.selectItem(item)">
          <span>{{ item }}</span>
        </v-chip>
      </template></v-combobox>

    <!-- CC FIELD -->
    <v-expand-transition>
      <v-combobox v-if="showCC || emailData.cc.length > 0" v-model="emailData.cc" :search-input.sync="searchCC"
        label="Cc" multiple chips small-chips deletable-chips disable-lookup append-icon=""
        append-outer-icon="mdi-close" @click:append-outer="clearAndHide('cc')" prepend-icon="mdi-account-multiple"
        :rules="[rules.emailArray]" @change="processAddressInput('cc')"
        @paste.native="processAddressPastedText('cc', $event)">
        <template #selection="{ item, selected, disabled, parent }">
          <v-chip small :input-value="selected" :disabled="disabled"
            :color="isValidEmailFormat(item) ? 'light-grey' : 'error'"
            :text-color="isValidEmailFormat(item) ? 'black' : 'white'" close @click:close="parent.selectItem(item)">
            <span>{{ item }}</span>
          </v-chip>
        </template></v-combobox>
    </v-expand-transition>

    <!-- BCC FIELD -->
    <v-expand-transition>
      <v-combobox v-if="showBCC || emailData.bcc.length > 0" v-model="emailData.bcc" :search-input.sync="searchBCC"
        label="Bcc" multiple chips small-chips deletable-chips disable-lookup append-icon=""
        append-outer-icon="mdi-close" @click:append-outer="clearAndHide('bcc')"
        prepend-icon="mdi-account-multiple-outline" :rules="[rules.emailArray]" @change="processAddressInput('bcc')"
        @paste.native="processAddressPastedText('bcc', $event)"><template
          #selection="{ item, selected, disabled, parent }">
          <v-chip small :input-value="selected" :disabled="disabled"
            :color="isValidEmailFormat(item) ? 'light-grey' : 'error'"
            :text-color="isValidEmailFormat(item) ? 'black' : 'white'" close @click:close="parent.selectItem(item)">
            <span>{{ item }}</span>
          </v-chip>
        </template></v-combobox>
    </v-expand-transition>

    <!-- CC & BCC TOGGLE BUTTONS -->
    <v-row no-gutters class="justify-end mb-2">
      <v-btn v-show="!showREPLY_TO" text small color="secondary" class="mr-2" @click="showREPLY_TO = !showREPLY_TO">
        Show Reply To
      </v-btn>
      <v-btn v-show="!showCC" text small color="secondary" class="mr-2" @click="showCC = !showCC">
        Show CC
      </v-btn>
      <v-btn v-show="!showBCC" text small color="secondary" @click="showBCC = !showBCC">
        Show BCC
      </v-btn>
    </v-row>

    <!-- SUBJECT FIELD -->
    <v-text-field v-model="emailData.subject" label="Subject" prepend-icon="mdi-format-title" :rules="[rules.required]"
      @input="emitChanges" :messages="filteredSubject"></v-text-field>

    <!-- BODY COMPOSITION AREA -->
    <div class="mt-6">
      <!-- MODE SELECTOR HEADER -->
      <v-row align="center" class="mb-3 px-3">
        <div class="subtitle-2 grey--text text--darken-1 d-flex align-center">
          <v-icon small left>mdi-text-long</v-icon> Message Body
        </div>
        <v-spacer></v-spacer>

        <v-btn-toggle v-model="emailData.format" mandatory dense color="primary" @change="emitChanges">
          <v-btn value="Text Only" small>
            <v-icon left small>mdi-text</v-icon> Text Only
          </v-btn>
          <v-btn value="Markdown" small>
            <v-icon left small>mdi-language-markdown</v-icon> Markdown
          </v-btn>
          <v-btn value="Full HTML" small>
            <v-icon left small>mdi-xml</v-icon> Raw HTML
          </v-btn>
        </v-btn-toggle>
      </v-row>

      <!-- DYNAMIC INPUT & PREVIEW PANELS -->
      <v-window v-model="emailData.format" touchless>

        <!-- TEXT ONLY MODE (Split View) -->
        <v-window-item value="Text Only">
          <v-row>
            <v-col cols="12" md="6">
              <div class="preview-label font-weight-medium mb-1">Edit:</div>
              <v-textarea v-model="emailData.body" outlined rows="14" no-resize
                placeholder="Type plain text message here..." @input="emitChanges"></v-textarea>
            </v-col>
            <v-col cols="12" md="6">
              <div class="preview-label font-weight-medium mb-1">Live Text Preview:</div>
              <div class="preview-pane text-preview-pane">
                {{ filteredBody }}
              </div>
            </v-col>
          </v-row>
        </v-window-item>

        <!-- MARKDOWN MODE (v-md-editor has built-in preview) -->
        <v-window-item value="Markdown">
          <v-row>
            <v-col cols="12" md="6">
              <div class="preview-label font-weight-medium mb-1">Edit:</div>
              <v-md-editor v-model="emailData.body" height="400px" @change="emitChanges" mode="edit"></v-md-editor>
            </v-col>
            <v-col cols="12" md="6">
              <div class="preview-label font-weight-medium mb-1">Live Preview:</div>
              <div class="preview-pane">

                <v-md-preview :text="filteredBody" />
              </div>
            </v-col>
          </v-row>
        </v-window-item>

        <!-- RAW HTML MODE (Split View) -->
        <v-window-item value="Full HTML">
          <v-row>
            <v-col cols="12" md="6">
              <div class="preview-label font-weight-medium mb-1">Edit:</div>
              <v-textarea v-model="emailData.body" outlined height="400" class="html-code-textarea"
                @input="emitChanges"></v-textarea>
            </v-col>
            <v-col cols="12" md="6">
              <div class="preview-label font-weight-medium mb-1">Live HTML Render:</div>
              <!-- Use v-html carefully to render computed string -->
              <div class="preview-pane html-preview-pane" v-html="filteredBody"></div>
            </v-col>
          </v-row>
        </v-window-item>

      </v-window>
    </div>
  </v-form>
</template>

<script>


export default {
  name: 'EmailComposer',
  props: {
    value: {
      type: Object,
      default: () => ({
        name: '',
        active: 1,
        from: '',
        to: '',
        cc: '',
        bcc: '',
        subject: '',
        body: '',
        format: 'Markdown'
      })
    },
    templateData: {
      type: Object,
      default: () => ({})
    },
    suggestedNames: {
      type: Object,
      default: () => ({})
    },    
    allowSetName: {
      type: Boolean,
      default: () => false
    },
    existingNames: {
      type: Array,
      default: () => []
    },
    showTo: {
      type: Boolean,
      default: () => false
    }
  },
  data() {
    return {
      nameData:{},
      showREPLY_TO: false,
      showCC: false,
      showBCC: false,
      emailData: {
        name: '',
        active: 1,
        from: '',
        to: [],
        reply_to: [],
        cc: [],
        bcc: [],
        subject: '',
        body: '',
        format: 'Markdown'
      },
      searchTO: '',
      searchREPLY_TO: '',
      searchCC: '',
      searchBCC: '',
      // Reusable regex checking for valid "Name" <email@domain.com> OR naked email@domain.com
      emailValidationRegex: /^(?:"[^"]*"|[^<,\r\n\t]+)\s*<[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}>$|^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/,

      rules: {
        required: v => (v && v.length > 0) || 'This field is required.',
        emailArray: v => {
          if (!v || v.length === 0) return true;
          return v.every(email => this.isValidEmailFormat(email)) || 'One or more email addresses are invalid.';
        },
        email: v => {
          if (!v || v.length === 0) return true;
          return this.isValidEmailFormat(v) || 'Valid email required'
        }
      },
      fileNameRules: [
        v => {
          return !!v || 'Template name is required.';
        },
        v => {
          const val = typeof v === 'object' && v !== null ? v.value : v;
          return (val && val.length <= 255) || 'Template name must be 255 characters or less.';
        },
        v => {
          //Don't validate if we can't set the name anyways
          if(!this.allowSetName) {console.log('no eval exist',this.allowSetName); return true; }
          const val = typeof v === 'object' && v !== null ? v.value : v;
          return (val && !this.existingNames.includes(val)) || 'Template already exists.';
        }
      ]
    };
  },
  computed: {
    // Computed property handles executing filter logic smoothly on text/HTML changes
    filteredBody() {
      if (!this.emailData.body) return '';

      return this.$compileTemplate(this.emailData.body, this.templateData);
    },
    filteredSubject(){
      if (!this.emailData.subject) return '';
      return this.$compileTemplate(this.emailData.subject, this.templateData);
    },
    formattedSuggestions() {
      return Object.keys(this.suggestedNames).map(key => ({
        value: key,
        text: this.suggestedNames[key].text,
        tip: this.suggestedNames[key].tip,
        disabled: this.existingNames.includes(key)
      }));
    },
    selectedNameTip(){
      if (typeof this.nameData == 'String') return [];
      return this.nameData?.tip;
    },
  },
  watch: {
    value: {
      handler(newVal) {
        this.syncIncomingData(newVal);
      },
      immediate: true,
      deep: true
    }
  },
  methods: {
    syncIncomingData(source) {
      this.emailData = {
        name: source.name || '',
        active: source.active ? 1 : 0,
        from: source.from || '',
        to: Array.isArray(source.to) ? [...source.to] : this.parseEmailString(source.to, true),
        reply_to: Array.isArray(source.reply_to) ? [...source.reply_to] : this.parseEmailString(source.reply_to, true),
        cc: Array.isArray(source.cc) ? [...source.cc] : this.parseEmailString(source.cc, true),
        bcc: Array.isArray(source.bcc) ? [...source.bcc] : this.parseEmailString(source.bcc, true),
        subject: source.subject || '',
        body: source.body || '',
        format: source.format || 'Markdown'
      };
      this.nameData = this.formattedSuggestions.find((item) => item.value == source.name) || source.name 
      this.showREPLY_TO = this.emailData.reply_to.length > 0;
      this.showCC = this.emailData.cc.length > 0;
      this.showBCC = this.emailData.bcc.length > 0;
    },
    emitChanges() {
      const payload = JSON.parse(JSON.stringify({
        ...this.emailData,
        //name: this.nameData?.value || this.nameData,
        to: this.emailData.to.join(", "),
        reply_to: this.emailData.reply_to.join(", "),
        cc: this.emailData.cc.join(", "),
        bcc: this.emailData.bcc.join(", "),
    }));
    console.log('emit payload', payload)
      this.$emit('input', payload);
      this.$emit('change', payload);
    },
    handleName(newvalue){

      if (!newvalue) {
        this.emailData.name = '';
      } else if (typeof newvalue === 'string') {
        //If the name actally matches an item despite being manually typed, use that
        let inList = this.formattedSuggestions.find((item) => item.value == newvalue);
        if(inList){
          this.nameData = inList 
          console.log('in list', inList)
        }
        // User typed a brand new custom string
        this.emailData.name = newvalue;
      } else if (typeof newvalue === 'object') {
        // User picked an existing suggestion item
        this.emailData.name = newvalue.value; 
      }
      this.emitChanges();
    },
    clearAndHide(fieldName) {
      console.log(fieldName)
      this.$set(this.emailData, fieldName, []);
      fieldName = fieldName.toUpperCase();
      if (this['show' + fieldName])
        this['show' + fieldName] = false
      this['search' + fieldName] = null

      this.emitChanges();
    },
    // Helper method used by both the validation rules and the template slots
    isValidEmailFormat(item) {
      return this.emailValidationRegex.test(item.trim());
    },
    parseEmailString(textString, ensureArray){
      //Check if textString is a string
      if(typeof textString != 'string'){
        return ensureArray? [] : textString;
      }

      // 1. Regex to match EITHER a full RFC822 structure OR a standard standalone email.
      // Match 1: "Display Name" <email@domain.com> (handles quotes, names, and brackets)
      // Match 2: Standalone email@domain.com
      const fullEmailRegex = /(?:(?:"[^"]*"|[^<,;\r\n\t]+)\s*<[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}>|[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/g;

      // Find all matches in the raw string directly, ignoring delimiter splitting completely.
      // This stops commas inside quotes (e.g. "Smith, John") from breaking the string.
      const matches = textString.match(fullEmailRegex);

      if (matches) {
        var result = [];
        matches.forEach(match => {
          // 1. Trim outer spaces
          // 2. Remove trailing semicolons/commas at the very end of the match
          // 3. Condense internal multi-spaces into a single space
          const cleanItem = match.trim().replace(/^[;,] +/, '').replace(/\s+/g, ' ');

          // Add to selections if not already there
          if (cleanItem && !result.includes(cleanItem)) {
            result.push(cleanItem);
          }

        });
        return result;
      } else {
        //Fallback, just return the input
        if(ensureArray)
        return Array.isArray(textString) ? textString : textString.length ? [textString] : [];
        return ensureArray;
      }
    },

    processAddressInput(fieldName) {
      // If there's text typed and the user presses enter or tab
      let fieldtext = this['search' + fieldName.toUpperCase()];
      console.log('processAddinput', fieldName, fieldtext, this.searchTO)
      if (fieldtext && fieldtext.trim() !== '') {
        this.extractAndAddEmails(fieldName, fieldtext);
      } else {        
        // Trigger emit because some other change happened
        console.log('aske edmit anetae')
        this.emitChanges();
      }
    },
    processAddressPastedText(fieldName, event) {
      // Prevent default to manually handle splitting of pasted emails
      event.preventDefault();
      const pastedText = (event.clipboardData || window.clipboardData).getData('text');
      this.extractAndAddEmails(fieldName, pastedText);
    },
    extractAndAddEmails(fieldName, textString) {
      console.log('processing', textString)
      let field = this.emailData[fieldName];
      let processed = this.parseEmailString(textString);
      if(Array.isArray(processed)) {        
        processed.forEach(item => {
          // Add to selections if not already there
          if (item && !field.includes(item)) {
            field.push(item);
          }
        });
      } else {
        //Didn't match, let natural processing occur
        this['search' + fieldName.toUpperCase()] = textString;
      }
      
        // Trigger emit
        this.emitChanges();
    },
  }
};
</script>

<style scoped>
.v-md-editor {
  border: 1px solid rgba(0, 0, 0, 0.42) !important;
  border-radius: 4px;
}

.v-md-editor:focus-within {
  border-color: #1976d2 !important;
}

/* Hides the external link SVG icon injected by the VuePress theme */
::v-deep .v-md-editor-preview a svg.v-md-svg-outbound {
  display: none !important;
}

/* Fixes any unnecessary right padding left over by the removed icon */
.v-md-editor-markdown-body a.external {
  padding-right: 0 !important;
}

.html-code-textarea>>>textarea {
  font-family: 'Fira Code', Consolas, Monaco, 'Courier New', Courier, monospace !important;
  font-size: 0.9rem;
  line-height: 1.5;
}

/* Common Preview Layout Styling */
.preview-label {
  font-size: 0.75rem;
  color: rgba(0, 0, 0, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.preview-pane {
  border: 1px dashed rgba(0, 0, 0, 0.23);
  border-radius: 4px;
  height: 400px;
  /* Roughly matches the height of the 14-row textarea */
  overflow-y: auto;
  padding: 12px;
}

/* Mode-Specific Preview tweaks */
.text-preview-pane {
  white-space: pre-wrap;
  /* Keeps user line breaks intact */
  font-family: inherit;
}
</style>