<?php

namespace UploaderService\Service;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use UploaderService\Config\Config;
use UploaderService\Config\Exceptions\MissingConfigValueException;

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
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var SplFileInfo[]
     */
    protected $queue = [];

    /**
     * Uploader constructor.
     *
     * @param Config $config
     * @param OutputInterface|null $output
     */
    public function __construct( Config $config, OutputInterface $output = null ) {

        $this->config = $config;
        $this->output = $output;

    }

    /**
     * @return Uploader
     */
    public function clear(): Uploader {

        $this->queue = [];
        return $this;

    }

    /**
     * @return Uploader
     * @throws MissingConfigValueException
     */
    public function scan(): Uploader {

        $path = $this->config->getPath();

        $files = ( new Finder() )
            ->files()
            ->size( '>= ' . $this->config->getSizeThreshold() )
            ->in( $path );

        if ( isset( $this->output ) ) {

            $this->output->writeln( sprintf( 'Scanning <comment>%s</comment>', $path ) );

            $progress = new ProgressBar( $this->output );
            $files = $progress->iterate( $files );

        }

        /** @var SplFileInfo $file */
        foreach ( $files as $file ) {

            $this->queue[] = $file;

        }

        if ( isset( $this->output ) ) {

            $this->output->writeln( '' );

        }

        return $this;

    }

    /**
     * @return Uploader
     * @throws MissingConfigValueException
     * @throws S3Exception
     */
    public function upload(): Uploader {

        $s3Region = $this->config->getS3Region();
        $s3Bucket = $this->config->getS3Bucket();
        $s3Key = $this->config->getS3Key();
        $s3Secret = $this->config->getS3Secret();

        $queue = $this->queue;
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

        if ( isset( $this->output ) ) {

            $this->output->writeln(

                sprintf(

                    'Uploading <comment>%s</comment> files',
                    number_format( count( $this->queue ), 0, '', ',' )

                )

            );

            $progress = new ProgressBar( $this->output );
            $queue = $progress->iterate( $queue );

        }

        /** @var SplFileInfo $file */
        foreach ( $queue as $file ) {

            $client->putObject(

                [

                    'Bucket' => $s3Bucket,
                    'Key' => $file->getRelativePathname(),
                    'Body' => $file->getContents(),
                    'ACL' => 'public-read',

                ]

            );

        }

        if ( isset( $this->output ) ) {

            $this->output->writeln( '' );

        }

        return $this;

    }

}
