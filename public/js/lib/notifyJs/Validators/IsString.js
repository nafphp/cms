import Validator from "../Validator.js";


class IsString extends Validator {

    errorMessage = 'Value may only consist of chars.';

    validate(value) {
        return typeof value === 'string' && (/[a-zA-Z]+/).test(value);
    }

}

export default IsString;