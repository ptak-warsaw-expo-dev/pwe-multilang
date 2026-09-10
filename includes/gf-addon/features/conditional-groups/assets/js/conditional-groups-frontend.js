(function (window) {
    'use strict';

    if (!window.gform || window.pweConditionalGroupsFrontend) return;
    window.pweConditionalGroupsFrontend = true;

    window.gform.addFilter('gform_is_value_match', function (isMatch, formId, rule) {
        if (!rule || rule.operator === 'pwe_group_dependency') return rule ? true : isMatch;
        if (rule.operator !== 'pwe_grouped_rules') return isMatch;

        const definition = rule.pweGroups || {};
        const groups = Array.isArray(definition.groups) ? definition.groups : [];
        const matchAll = definition.logicType === 'all';

        if (!groups.length || typeof window.gf_get_field_action !== 'function') return false;

        for (let index = 0; index < groups.length; index++) {
            const matches = window.gf_get_field_action(formId, groups[index]) === 'show';
            if (!matchAll && matches) return true;
            if (matchAll && !matches) return false;
        }

        return matchAll;
    });
}(window));
