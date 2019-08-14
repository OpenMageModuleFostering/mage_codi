Validation.add('validate-codi-password', 'Please enter valid password.', function (v,elm) {
    var expr = /^[A-Za-z0-9`~@#%\^&\*\(\)–_=\+\[\]\{\}\\|;:'",\.\<\>/\?]+$/;
    if(expr.test(elm.value)){
        return true;
    }
    return false
});