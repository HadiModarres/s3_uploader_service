<?php

namespace UploaderService\Config\Exceptions;

use Exception;

/**
 * Class NotInSpecException
 *
 * @package UploaderService\Config\Exceptions
 */
class NotInSpecException extends Exception {

    /**
     * NotInSpecException constructor.
     *
     * @param string $key
     */
    public function __construct( string $key ) {

        parent::__construct( sprintf( 'Invalid configuration key - not found in spec: "%s".', $key ) );

    }

}
