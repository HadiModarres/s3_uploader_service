<?php

namespace UploaderService\Config\Exceptions;

use Exception;

/**
 * Class ConfigNotMergedException
 *
 * @package UploaderService\Config\Exceptions
 */
class ConfigNotMergedException extends Exception {

    /**
     * ConfigNotMergedException constructor.
     */
    public function __construct() {

        parent::__construct( 'Configuration has not been merged yet.' );

    }

}
