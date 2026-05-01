import Validator from "../Validator.js";


class IsNumber extends Validator {

    errorMessage = 'Value must be numeric.';

    validate(value) {
        return typeof value === 'number';
    }

}

export default IsNumber;