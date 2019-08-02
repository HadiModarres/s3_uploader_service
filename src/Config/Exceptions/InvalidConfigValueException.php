<?php

namespace UploaderService\Config\Exceptions;

use Exception;

/**
 * Class InvalidConfigValueException
 *
 * @package UploaderService\Config\Exceptions
 */
class InvalidConfigValueException extends Exception {

    /**
     * InvalidConfigValueException constructor.
     *
     * @param string $key
     * @param string $value
     */
    public function __construct( string $key, string $value ) {

        parent::__construct( sprintf( 'Empty or invalid value supplied for configuration key "%s": "%s".',
                                      $key, $value ) );

    }

}
