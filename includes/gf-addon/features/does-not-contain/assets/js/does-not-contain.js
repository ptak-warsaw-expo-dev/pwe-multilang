(function (window) {
    if (window.gform) {
        window.gf_vars = window.gf_vars || {};
        window.gf_vars.pweDoesNotContain = 'does NOT contain';
        window.gform.addFilter('gform_conditional_logic_operators', function (operators) {
            operators.not_contains = 'pweDoesNotContain';
            return operators;
        });
    }

    if (typeof window.gf_matches_operation !== 'function' || window.gf_matches_operation.pweDoesNotContain) return;

    const originalMatchesOperation = window.gf_matches_operation;
    window.gf_matches_operation = function (sourceValue, targetValue, operation) {
        if (operation === 'not_contains') {
            return !originalMatchesOperation(sourceValue, targetValue, 'contains');
        }
        return originalMatchesOperation.apply(this, arguments);
    };
    window.gf_matches_operation.pweDoesNotContain = true;
}(window));
