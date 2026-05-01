class Validator {

    errorMessage = '';

    /**
     * @param {Number|String|Boolean} value
     *
     * @return boolean
     */
    validate(value) {
        throw new Error('Must be implemented by children.');
    }

    getErrorMessage() {

        return this.errorMessage;

    }

}

export default Validator;