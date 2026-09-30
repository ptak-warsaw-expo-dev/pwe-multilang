(function ($, window) {
    'use strict';

    const config = window.pweConditionalGroups || {};
    const property = config.property || 'pweConditionalGroups';
    const text = config.strings || {};
    let fieldEditor = null;

    const operatorLabels = {
        is: text.is || 'is',
        isnot: text.isnot || 'is not',
        '>': text.greater || 'greater than',
        '<': text.less || 'less than',
        contains: text.contains || 'contains',
        not_contains: text.notContains || 'does NOT contain',
        starts_with: text.starts || 'starts with',
        ends_with: text.ends || 'ends with'
    };

    function blankDefinition() {
        return {logicType: 'any', groups: []};
    }

    function parseDefinition(value) {
        if (typeof value === 'string' && value) {
            try { value = JSON.parse(value); } catch (error) { value = null; }
        }

        if (!value || typeof value !== 'object') return blankDefinition();
        return {
            logicType: value.logicType === 'all' ? 'all' : 'any',
            groups: Array.isArray(value.groups) ? value.groups : []
        };
    }

    function conditionalFields(currentFieldId) {
        const source = window.form && Array.isArray(window.form.fields)
            ? window.form.fields
            : (Array.isArray(config.fields) ? config.fields : []);
        return source.filter(function (item) {
            if (!item || String(item.id) === String(currentFieldId)) return false;
            if (typeof window.IsConditionalLogicField !== 'function') return true;
            try { return window.IsConditionalLogicField(item); } catch (error) { return true; }
        });
    }

    function fieldLabel(item) {
        return item.adminLabel || item.label || ('Field ' + item.id);
    }

    function getField(fieldId) {
        if (typeof window.GetFieldById === 'function') {
            try {
                const selected = window.GetFieldById(fieldId);
                if (selected) return selected;
            } catch (error) { /* Use the localized form data below. */ }
        }
        const fields = conditionalFields(null);
        return fields.find(function (item) { return String(item.id) === String(fieldId); });
    }

    function getOperators(objectType, fieldId) {
        let operators = Object.assign({}, operatorLabels);
        if (window.gform && typeof window.gform.applyFilters === 'function') {
            operators = window.gform.applyFilters('gform_conditional_logic_operators', operators, objectType, fieldId);
        }
        return operators || operatorLabels;
    }

    function normaliseRule(rule, fields) {
        const firstId = fields.length ? String(fields[0].id) : '';
        return {
            fieldId: rule && rule.fieldId !== undefined ? String(rule.fieldId) : firstId,
            operator: rule && rule.operator ? rule.operator : 'is',
            value: rule && rule.value !== undefined ? String(rule.value) : ''
        };
    }

    function Editor($mount, options) {
        this.$mount = $mount;
        this.objectType = options.objectType || 'field';
        this.currentFieldId = options.currentFieldId || null;
        this.definition = parseDefinition(options.value);
        this.onChange = options.onChange;
        this.render();
    }

    Editor.prototype.fields = function () {
        return conditionalFields(this.currentFieldId);
    };

    Editor.prototype.commit = function () {
        this.onChange(this.definition);
    };

    Editor.prototype.addGroup = function () {
        const fields = this.fields();
        this.definition.groups.push({
            logicType: 'all',
            rules: [normaliseRule(null, fields)]
        });
        this.commit();
        this.render();
    };

    Editor.prototype.addRule = function (groupIndex) {
        this.definition.groups[groupIndex].rules.push(normaliseRule(null, this.fields()));
        this.commit();
        this.render();
    };

    Editor.prototype.removeRule = function (groupIndex, ruleIndex) {
        const group = this.definition.groups[groupIndex];
        if (group.rules.length === 1) {
            this.definition.groups.splice(groupIndex, 1);
        } else {
            group.rules.splice(ruleIndex, 1);
        }
        this.commit();
        this.render();
    };

    Editor.prototype.renderValue = function ($cell, rule, groupIndex, ruleIndex) {
        const source = getField(rule.fieldId);
        const choices = source && Array.isArray(source.choices) ? source.choices : [];
        let $input;

        if (choices.length) {
            $input = $('<select class="pwe-cg-control pwe-cg-value"></select>');
            choices.forEach(function (choice) {
                const value = choice.value !== undefined ? String(choice.value) : String(choice.text || '');
                $('<option></option>').val(value).text(choice.text || value).appendTo($input);
            });
            if (!choices.some(function (choice) { return String(choice.value) === rule.value; })) {
                $('<option></option>').val(rule.value).text(rule.value).appendTo($input);
            }
            $input.val(rule.value);
        } else {
            $input = $('<input type="text" class="pwe-cg-control pwe-cg-value">')
                .attr('placeholder', text.value || 'Enter a value')
                .val(rule.value);
        }

        $input.attr({'data-group': groupIndex, 'data-rule': ruleIndex}).appendTo($cell);
    };

    Editor.prototype.render = function () {
        const self = this;
        const fields = this.fields();
        this.$mount.empty().addClass('pwe-conditional-groups');

        if (this.definition.groups.length) {
            const $intro = $('<div class="pwe-cg-intro"></div>');
            $intro.append(document.createTextNode('Match '));
            $('<select class="pwe-cg-outer-logic"><option value="any">any</option><option value="all">all</option></select>')
                .val(this.definition.logicType)
                .appendTo($intro);
            $intro.append(document.createTextNode(' of the rule groups match (the native rules above are the first group):'));
            this.$mount.append($intro);
        }

        this.definition.groups.forEach(function (group, groupIndex) {
            if (groupIndex > 0) {
                self.$mount.append($('<div class="pwe-cg-separator"><span></span></div>').find('span').text(self.definition.logicType.toUpperCase()).end());
            }

            group.logicType = group.logicType === 'any' ? 'any' : 'all';
            group.rules = Array.isArray(group.rules) && group.rules.length ? group.rules : [normaliseRule(null, fields)];

            const $group = $('<section class="pwe-cg-group"></section>').attr('data-group', groupIndex);
            const $header = $('<div class="pwe-cg-group-header"></div>');
            $header.append(document.createTextNode((text.match || 'Match') + ' '));
            $('<select class="pwe-cg-group-logic"><option value="all">all</option><option value="any">any</option></select>')
                .val(group.logicType)
                .attr('data-group', groupIndex)
                .appendTo($header);
            $header.append(document.createTextNode(' ' + (text.rules || 'of the following rules:')));
            $('<button type="button" class="pwe-cg-delete-group dashicons dashicons-trash"></button>')
                .attr({'data-group': groupIndex, 'aria-label': text.deleteGroup || 'Delete rule group', 'title': text.deleteGroup || 'Delete rule group'})
                .appendTo($header);
            $group.append($header);

            group.rules.forEach(function (rawRule, ruleIndex) {
                const rule = normaliseRule(rawRule, fields);
                group.rules[ruleIndex] = rule;
                const $row = $('<div class="pwe-cg-rule"></div>');
                const $field = $('<select class="pwe-cg-control pwe-cg-field"></select>')
                    .attr({'data-group': groupIndex, 'data-rule': ruleIndex});
                fields.forEach(function (item) {
                    $('<option></option>').val(String(item.id)).text(fieldLabel(item)).appendTo($field);
                });
                $field.val(rule.fieldId).appendTo($row);

                const $operator = $('<select class="pwe-cg-control pwe-cg-operator"></select>')
                    .attr({'data-group': groupIndex, 'data-rule': ruleIndex});
                const operators = getOperators(self.objectType, rule.fieldId);
                Object.keys(operators).forEach(function (key) {
                    const labelKey = operators[key];
                    const label = operatorLabels[key] || (window.gf_vars && window.gf_vars[labelKey]) || labelKey || key;
                    $('<option></option>').val(key).text(label).appendTo($operator);
                });
                $operator.val(rule.operator).appendTo($row);

                self.renderValue($row, rule, groupIndex, ruleIndex);
                $('<button type="button" class="pwe-cg-icon pwe-cg-add-rule" aria-label="Add rule">+</button>')
                    .attr({'data-group': groupIndex, 'data-rule': ruleIndex}).appendTo($row);
                $('<button type="button" class="pwe-cg-icon pwe-cg-remove-rule" aria-label="Remove rule">−</button>')
                    .attr({'data-group': groupIndex, 'data-rule': ruleIndex}).appendTo($row);
                $group.append($row);
            });

            $('<button type="button" class="pwe-cg-link pwe-cg-add-rule-link"></button>')
                .text(text.addRule || '+ Add Rule').attr('data-group', groupIndex).appendTo($group);
            self.$mount.append($group);
        });

        $('<button type="button" class="pwe-cg-link pwe-cg-add-group"></button>')
            .text(text.addGroup || '+ Add Rule Group').appendTo(this.$mount);

        this.bind();
    };

    Editor.prototype.bind = function () {
        const self = this;
        this.$mount.off('.pweGroups');
        this.$mount.on('change.pweGroups', '.pwe-cg-outer-logic', function () {
            self.definition.logicType = this.value === 'all' ? 'all' : 'any';
            self.commit(); self.render();
        });
        this.$mount.on('change.pweGroups', '.pwe-cg-group-logic', function () {
            self.definition.groups[Number($(this).data('group'))].logicType = this.value === 'any' ? 'any' : 'all';
            self.commit();
        });
        this.$mount.on('change.pweGroups', '.pwe-cg-field', function () {
            const group = Number($(this).data('group'));
            const rule = Number($(this).data('rule'));
            self.definition.groups[group].rules[rule].fieldId = this.value;
            self.definition.groups[group].rules[rule].value = '';
            self.commit(); self.render();
        });
        this.$mount.on('change.pweGroups', '.pwe-cg-operator', function () {
            self.definition.groups[Number($(this).data('group'))].rules[Number($(this).data('rule'))].operator = this.value;
            self.commit();
        });
        this.$mount.on('change.pweGroups input.pweGroups', '.pwe-cg-value', function () {
            self.definition.groups[Number($(this).data('group'))].rules[Number($(this).data('rule'))].value = this.value;
            self.commit();
        });
        this.$mount.on('click.pweGroups', '.pwe-cg-add-group', function () { self.addGroup(); });
        this.$mount.on('click.pweGroups', '.pwe-cg-add-rule, .pwe-cg-add-rule-link', function () { self.addRule(Number($(this).data('group'))); });
        this.$mount.on('click.pweGroups', '.pwe-cg-remove-rule', function () { self.removeRule(Number($(this).data('group')), Number($(this).data('rule'))); });
        this.$mount.on('click.pweGroups', '.pwe-cg-delete-group', function () {
            self.definition.groups.splice(Number($(this).data('group')), 1);
            self.commit(); self.render();
        });
    };

    function mountFieldEditor(selectedField) {
        $('.pwe-cg-field-mount').remove();
        const $native = $('.conditional_logic_container:visible').first();
        if (!$native.length) return;
        let $mount = $native.siblings('.pwe-cg-field-mount');
        if (!$mount.length) $mount = $('<div class="pwe-cg-field-mount"></div>').insertAfter($native);

        fieldEditor = new Editor($mount, {
            objectType: 'field',
            currentFieldId: selectedField.id,
            value: selectedField[property],
            onChange: function (definition) {
                selectedField[property] = definition;
                if (typeof window.SetFieldProperty === 'function') window.SetFieldProperty(property, definition);
            }
        });
    }

    function mountSettingsEditor() {
        const $input = $('.pwe-conditional-groups-value, [name="' + property + '"], [name$="' + property + '"]').first();
        if (!$input.length) return false;
        if ($input.data('pwe-mounted')) return true;
        $input.data('pwe-mounted', true);

        const $wrapper = $input.closest('.gform-settings-field');
        const $mount = $('<div class="pwe-cg-settings-mount"></div>');
        if ($wrapper.length) $mount.insertBefore($wrapper);
        else $mount.insertBefore($input);

        let objectType = 'notification';
        const page = new URLSearchParams(window.location.search);
        if (page.get('subview') === 'confirmation') objectType = 'confirmation';
        else if (page.get('subview') && page.get('subview') !== 'notification') objectType = 'feed_condition';

        new Editor($mount, {
            objectType: objectType,
            value: $input.val(),
            onChange: function (definition) { $input.val(JSON.stringify(definition)); }
        });

        return true;
    }

    $(document).on('gform_load_field_settings', function (event, selectedField) {
        window.setTimeout(function () { mountFieldEditor(selectedField); }, 0);
    });

    $(document).on('change', 'input[id*="conditional_logic"]', function () {
        if (window.field) window.setTimeout(function () { mountFieldEditor(window.field); }, 50);
    });

    $(function () {
        if (mountSettingsEditor()) return;

        const root = document.querySelector('#gform-settings')
            || document.querySelector('.gform-settings-panel')
            || document.body;
        const observer = new MutationObserver(function () {
            if (mountSettingsEditor()) observer.disconnect();
        });
        observer.observe(root, {childList: true, subtree: true});
    });
}(jQuery, window));
