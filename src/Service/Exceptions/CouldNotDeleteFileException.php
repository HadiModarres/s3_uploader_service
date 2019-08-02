<?php

namespace UploaderService\Service\Exceptions;

use Exception;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class CouldNotDeleteFileException
 *
 * @package UploaderService\Service\Exceptions
 */
class CouldNotDeleteFileException extends Exception {

    /**
     * CouldNotDeleteFileException constructor.
     *
     * @param SplFileInfo $file
     */
    public function __construct( SplFileInfo $file ) {

        parent::__construct( sprintf( 'Could not delete file: %s.', $file->getRelativePathname() ) );

    }

}
