<?php

namespace UploaderService\Config\Exceptions;

use Exception;

/**
 * Class MissingConfigValueException
 *
 * @package UploaderService\Config\Exceptions
 */
class MissingConfigValueException extends Exception {

    /**
     * MissingConfigValueException constructor.
     *
     * @param string $key
     */
    public function __construct( string $key ) {

        parent::__construct( sprintf( 'Missing configuration entry "%s".', $key ) );

    }

}
