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
     * @param $value
     */
    public function __construct( string $key, $value ) {

        parent::__construct(

            sprintf(

                'Empty or invalid value supplied for configuration key "%s": %s.',
                $key,
                is_object( $value ) ?
                    'object ' . get_class( $value ) :
                    gettype( $value ) . ' ' . json_encode( $value )

            )

        );

    }

}
