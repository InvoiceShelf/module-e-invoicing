import pluginVue from 'eslint-plugin-vue'
import pluginVueA11y from 'eslint-plugin-vuejs-accessibility'
import tseslint from 'typescript-eslint'

// The host's form controls, so a <label> wrapping one counts as labelling it
const CONTROL_COMPONENTS = [
  'BaseInput',
  'BaseTextarea',
  'BaseMoney',
  'BaseMultiselect',
  'BaseDatePicker',
  'BaseSelectInput',
  'BaseSwitch',
  'BaseCheckbox',
]

export default [
  ...tseslint.configs.recommended,
  ...pluginVue.configs['flat/recommended'],
  {
    files: ['**/*.vue'],
    languageOptions: { parserOptions: { parser: tseslint.parser } },
  },
  {
    rules: {
      'vue/multi-word-component-names': 'off',
      'vue/require-default-prop': 'off',
      'vue/one-component-per-file': 'off',
      'vue/require-prop-types': 'off',
      'vue/max-attributes-per-line': 'off',
      'vue/singleline-html-element-content-newline': 'off',
      'vue/multiline-html-element-content-newline': 'off',
    },
  },
  // Accessibility, as in the host: what a keyboard or screen reader user
  // cannot reach or hear. Only native elements are checked.
  {
    files: ['**/*.vue'],
    plugins: { 'vuejs-accessibility': pluginVueA11y },
    rules: {
      'vuejs-accessibility/alt-text': 'error',
      'vuejs-accessibility/anchor-has-content': 'error',
      'vuejs-accessibility/aria-props': 'error',
      'vuejs-accessibility/aria-role': 'error',
      'vuejs-accessibility/click-events-have-key-events': 'error',
      'vuejs-accessibility/form-control-has-label': 'error',
      'vuejs-accessibility/heading-has-content': 'error',
      'vuejs-accessibility/iframe-has-title': 'error',
      'vuejs-accessibility/interactive-supports-focus': 'error',
      'vuejs-accessibility/label-has-for': [
        'error',
        { required: { some: ['nesting', 'id'] }, controlComponents: CONTROL_COMPONENTS },
      ],
      'vuejs-accessibility/mouse-events-have-key-events': 'error',
      'vuejs-accessibility/no-autofocus': 'warn',
      'vuejs-accessibility/no-static-element-interactions': 'error',
      'vuejs-accessibility/role-has-required-aria-props': 'error',
      'vuejs-accessibility/tabindex-no-positive': 'error',
    },
  },
]
