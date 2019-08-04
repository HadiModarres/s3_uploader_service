<?php

namespace UploaderService\Command\Exceptions;

use Exception;

/**
 * Class InvalidLogNotationException
 *
 * @package UploaderService\Command\Exceptions
 */
class InvalidLogNotationException extends Exception {

    /**
     * InvalidLoggerNotationException constructor.
     *
     * @param string $notation
     */
    public function __construct( string $notation ) {

        parent::__construct( sprintf( 'Invalid log notation: %s.', $notation ) );

    }

}
