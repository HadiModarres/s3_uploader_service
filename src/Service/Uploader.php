<?php

namespace UploaderService\Service;

use Aws\S3\S3Client;
use Exception;
use RuntimeException;
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
     * @param SplFileInfo $file
     * @param Output $output
     * @param int $deleted
     * @param bool $delete
     *
     * @return Uploader
     * @throws CouldNotDeleteFileException
     */
    protected function maybeDelete( SplFileInfo $file, Output $output, int &$deleted, bool $delete ): Uploader {

        if ( ! $delete ) {

            return $this;

        }

        if ( ! @unlink( $file->getPathname() ) ) {

            throw new CouldNotDeleteFileException( $file );

        }

        $deleted++;

        $output->success( 'Successfully deleted <comment>%1$s</comment>.', $file->getPathname() );
        return $this;

    }

    /**
     * @param S3Client $client
     * @param string $s3Bucket
     * @param SplFileInfo $file
     * @param Output $output
     * @param int $skipped
     * @param int $deleted
     * @param bool $skipUpToDate
     * @param bool $delete
     *
     * @return bool
     * @throws CouldNotDeleteFileException
     */
    protected function maybeSkip(

        S3Client $client,
        string $s3Bucket,
        SplFileInfo $file,
        Output $output,
        int &$skipped,
        int &$deleted,
        bool $skipUpToDate,
        bool $delete

    ): bool {

        if ( ! $skipUpToDate ) {

            return false;

        }

        $output->debug(

            'Skip up-to-date is enabled, checking if file <comment>%1$s</comment> exists in bucket.',
            $file->getRelativePathname()

        );

        if ( $client->doesObjectExist( $s3Bucket, $file->getRelativePathname() ) ) {

            $output->debug(

                'File <comment>%1$s</comment> exists in bucket, comparing file sizes.',
                $file->getRelativePathname()

            );

            $localSize = $file->getSize();
            $remoteSize = $client->headObject(

                [

                    'Bucket' => $s3Bucket,
                    'Key' => $file->getRelativePathname(),

                ]

            )->get( 'ContentLength');

            if ( ! is_int( $remoteSize ) || 0 > $remoteSize ) {

                throw new RuntimeException(

                    sprintf(

                        'Failed to retrieve file size from bucket for: %s.',
                        $file->getRelativePathname()

                    )

                );

            }

            if ( $localSize === $remoteSize ) {

                $output->debug(

                    'File <comment>%1$s</comment> exists in bucket with the same file size, skipping.',
                    $file->getRelativePathname()

                );

                $skipped++;

                $this->maybeDelete( $file, $output, $deleted, $delete );
                return true;

            }

        }

        $output->debug(

            'File <comment>%1$s</comment> does not exist in bucket or is a different size, will process.',
            $file->getRelativePathname()

        );

        return false;

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
            $limit = $this->config->getLimit();

            $output = $this->getOutput();
            $output->debug(

                'Scanning directory <comment>%1$s</comment> for %2$sfiles larger than <comment>%3$s</comment>.',
                $path,
                0 < $limit ? sprintf( 'up to <comment>{count:%d}</comment> ', $limit ) : '',
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

                if ( 0 < $limit && $limit <= count( $this->queue ) ) {

                    break;

                }

            }

            $output->debug(

                'Found <comment>{count:%1$d}</comment> {pluralize:%1$d:file} larger than <comment>%2$s</comment>.',
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
            $skipUpToDate = $this->config->getSkipUpToDate();
            $delete = $this->config->getDelete();

            $queue = $this->queue;
            $queued = count( $queue );

            $uploaded = 0;
            $skipped = 0;
            $deleted = 0;

            $output = $this->getOutput();
            $output->debug(

                'Preparing to upload <comment>{count:%1$d}</comment> queued files.',
                $queued

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

                if (

                    $this->maybeSkip( $client, $s3Bucket, $file, $output, $skipped, $deleted, $skipUpToDate, $delete )

                ) {

                    continue;

                }

                $output->debug(

                    'Uploading <comment>%1$s</comment> to ' .
                    'https://<comment>%2$s</comment>.s3-<comment>%3$s</comment>.amazonaws.com/<comment>%4$s</comment>.',
                    $file->getRelativePathname(),
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

                $uploaded++;

                $output->success(

                    'Successfully uploaded <comment>%1$s</comment> to ' .
                    'https://<comment>%2$s</comment>.s3-<comment>%3$s</comment>.amazonaws.com/<comment>%4$s</comment>.',
                    $file->getPathname(),
                    $s3Bucket,
                    $s3Region,
                    $file->getRelativePathname()

                );

                $this->maybeDelete( $file, $output, $deleted, $delete );

            }

            $output->debug(

                'Uploaded <comment>{count:%1$d}</comment> {pluralize:%1$d:file} ' .
                '(skipped <comment>{count:%2$d}</comment> {pluralize:%2$d:file}) and ' .
                'deleted <comment>{count:%3$d}</comment> {pluralize:%3$d:file} out of ' .
                '<comment>{count:%4$d}</comment> processed.',
                $uploaded,
                $skipped,
                $deleted,
                $queued

            );

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
