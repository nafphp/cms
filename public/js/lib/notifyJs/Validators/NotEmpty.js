import Validator from "../Validator.js";


class NotEmpty extends Validator {

    errorMessage = 'The field must not be empty.';

    validate(value) {
        return (!!value.length);
    }

}

export default NotEmpty;