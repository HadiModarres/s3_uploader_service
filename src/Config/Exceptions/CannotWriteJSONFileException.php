<?php

namespace UploaderService\Config\Exceptions;

use Exception;

/**
 * Class CannotWriteJSONFileException
 *
 * @package UploaderService\Config\Exceptions
 */
class CannotWriteJSONFileException extends Exception {

    /**
     * CannotWriteJSONFileException constructor.
     *
     * @param string $path
     */
    public function __construct( string $path ) {

        parent::__construct( sprintf( 'Could not write configuration as JSON to: %s.', $path ) );

    }

}
