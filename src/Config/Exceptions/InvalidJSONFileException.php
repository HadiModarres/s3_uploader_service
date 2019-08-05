<?php

namespace UploaderService\Config\Exceptions;

use Exception;

/**
 * Class InvalidJSONFileException
 *
 * @package UploaderService\Config\Exceptions
 */
class InvalidJSONFileException extends Exception {

    /**
     * InvalidJSONFileException constructor.
     *
     * @param string $path
     */
    public function __construct( string $path ) {

        parent::__construct( sprintf( 'Invalid or unreadable file, or content is not valid JSON: %s.', $path ) );

    }

}
