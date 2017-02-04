<?php
class DataModelException extends Exception {
    /** @var array $errors */
    protected $errors = [];

    /**
     * @param array $errors
     * @return DataModelException
     */
    public static function create(array $errors) {
        $Obj = new self('Возникли ошибки в данных');
        $Obj->setErrors($errors);
        return $Obj;
    }

    /**
     * @param array $errors
     * @return $this
     */
    public function setErrors(array $errors) {
        $this->errors = $errors;
        return $this;
    }

    /**
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }
}