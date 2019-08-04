<?php

namespace UploaderService\Service;

use Aws\S3\S3Client;
use Exception;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use UploaderService\Config\Config;
use UploaderService\Service\Exceptions\CouldNotDeleteFileException;
use UploaderService\Service\Uploader\Output;

/**
 * Class Uploader
 *
 * @package UploaderService\Service
 */
class Uploader {

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Output
     */
    protected $output;

    /**
     * @var SplFileInfo[]
     */
    protected $queue = [];

    /**
     * @param callable $callback
     *
     * @return mixed
     * @throws Exception
     */
    protected function tryCatch( callable $callback ) {

        try {

            return call_user_func( $callback );

        } catch ( Exception $e ) {

            $this->output->exception( $e );
            throw $e;

        }

    }

    /**
     * Uploader constructor.
     *
     * @param Config $config
     * @param Output|null $output
     */
    public function __construct( Config $config, Output $output = null ) {

        $this
            ->setConfig( $config )
            ->setOutput( $output ?? new Output() );

    }

    /**
     * @return Uploader
     */
    public function clear(): Uploader {

        $output = $this->getOutput();

        $output->debug( 'Clearing queue.' );

        $cleared = count( $this->queue );
        $this->queue = [];

        $output->success( 'Successfully cleared <comment>{count:%1$d}</comment> {pluralize:%1$d:item}.', $cleared );

        return $this;

    }

    /**
     * @return Uploader
     * @throws Exception
     */
    public function scan(): Uploader {

        $this->tryCatch( function () {

            $path = $this->config->getPath();
            $sizeThreshold = $this->config->getSizeThreshold();

            $output = $this->getOutput();
            $output->debug(

                'Scanning directory <comment>%1$s</comment> for files larger than <comment>%2$s</comment>.',
                $path,
                $sizeThreshold

            );

            $files = ( new Finder() )
                ->files()
                ->size( '>= ' . $sizeThreshold )
                ->in( $path );

            $files = $output->trackProgress( $files );

            /** @var SplFileInfo $file */
            foreach ( $files as $file ) {

                $this->queue[] = $file;

            }

            $output->debug(

                'Found <comment>{count:%1$d}</comment> files larger than <comment>%2$s</comment>.',
                count( $this->queue ),
                $sizeThreshold

            );

        } );

        return $this;

    }

    /**
     * @return Uploader
     * @throws Exception
     */
    public function upload(): Uploader {

        $this->tryCatch( function () {

            $s3Region = $this->config->getS3Region();
            $s3Bucket = $this->config->getS3Bucket();
            $s3Key = $this->config->getS3Key();
            $s3Secret = $this->config->getS3Secret();
            $delete = $this->config->getDelete();

            $queue = $this->queue;

            $output = $this->getOutput();
            $output->debug(

                'Preparing to upload <comment>{count:%1$d}</comment> queued files.',
                count( $queue )

            );

            $client = new S3Client(

                [

                    'version' => 'latest',
                    'region' => $s3Region,
                    'credentials' => [

                        'key' => $s3Key,
                        'secret' => $s3Secret,

                    ],

                ]

            );

            $queue = $output->trackProgress( $queue );

            /** @var SplFileInfo $file */
            foreach ( $queue as $file ) {

                $output->debug(

                    'Uploading <comment>%1$s</comment> to ' .
                    'https://<comment>%2$s</comment>.s3-<comment>%3$s</comment>.amazonaws.com/<comment>%4$s</comment>.',
                    $file->getPathname(),
                    $s3Bucket,
                    $s3Region,
                    $file->getRelativePathname()

                );

                $client->putObject(

                    [

                        'Bucket' => $s3Bucket,
                        'Key' => $file->getRelativePathname(),
                        'Body' => $file->getContents(),
                        'ACL' => 'public-read',

                    ]

                );

                $output->success(

                    'Successfully uploaded <comment>%1$s</comment> to ' .
                    'https://<comment>%2$s</comment>.s3-<comment>%3$s</comment>.amazonaws.com/<comment>%4$s</comment>.',
                    $file->getPathname(),
                    $s3Bucket,
                    $s3Region,
                    $file->getRelativePathname()

                );

                if ( ! $delete ) {

                    continue;

                }

                if ( ! @unlink( $file->getPathname() ) ) {

                    throw new CouldNotDeleteFileException( $file );

                }

                $output->success( 'Successfully deleted <comment>%1$s</comment>.', $file->getPathname() );

            }


        } );

        return $this;

    }

    /**
     * @return Config
     */
    public function getConfig(): Config {

        return $this->config;

    }

    /**
     * @param Config $config
     *
     * @return Uploader
     */
    public function setConfig( Config $config ): Uploader {

        $this->config = $config;
        return $this;

    }

    /**
     * @return Output
     */
    public function getOutput(): Output {

        return $this->output;

    }

    /**
     * @param Output $output
     *
     * @return Uploader
     */
    public function setOutput( Output $output ): Uploader {

        $this->output = $output;
        return $this;

    }

}
