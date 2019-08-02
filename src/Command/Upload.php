<?php

namespace UploaderService\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UploaderService\Config\Config;
use UploaderService\Config\Exceptions\InvalidConfigValueException;
use UploaderService\Config\Exceptions\MissingConfigValueException;
use UploaderService\Service\Uploader;

/**
 * Class Upload
 *
 * @package UploaderService\Command
 */
class Upload extends Command {

    /**
     * @var string
     */
    protected static $defaultName = 'upload';

    /**
     * @var Config|null
     */
    protected $config;

    protected function configure() {

        $this
            ->setDescription( 'Uploads large files to an S3 bucket.' )
            ->setHelp( "This command scans a folder for large files and uploads them to an S3 bucket.\n\n" .

                       "Use <comment>--size</comment> to specify a size threshold above which to process files.\n" )

            ->addOption( 'path', '', InputOption::VALUE_OPTIONAL,
                         'Specifies a source directory path to perform the scans on.', '' )

            ->addOption( 'size-threshold', '', InputOption::VALUE_OPTIONAL,
                         'Specifies a size threshold above which to process files.', '' )

            ->addOption( 's3-region', '', InputOption::VALUE_OPTIONAL,
                         'Specifies an AWS S3 region to use for uploads.', '' )

            ->addOption( 's3-bucket', '', InputOption::VALUE_OPTIONAL,
                         'Specifies an AWS S3 bucket name to use for uploads.', '' )

            ->addOption( 's3-key', '', InputOption::VALUE_OPTIONAL,
                         'Specifies an AWS S3 key to use for uploads.', '' )

            ->addOption( 's3-secret', '', InputOption::VALUE_OPTIONAL,
                         'Specifies an AWS S3 secret to use for uploads.', '' );

    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     * @throws InvalidConfigValueException
     * @throws MissingConfigValueException
     */
    protected function execute( InputInterface $input, OutputInterface $output ) {

        $config = isset( $this->config ) ? $this->config : new Config();
        $config->mergeFromInput( $input );

        $uploader = new Uploader( $config, $output );
        $uploader->scan();
        $uploader->upload();

    }

    /**
     * Upload constructor.
     *
     * @param Config|null $config
     */
    public function __construct( Config $config = null ) {

        parent::__construct();

        $this->config = $config;

    }

}
