<?php

namespace UploaderService\Command;

use Exception;
use Monolog\Handler\FilterHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UploaderService\Command\Exceptions\InvalidLogNotationException;
use UploaderService\Config\Config;
use UploaderService\Config\Exceptions\NotInSpecException;
use UploaderService\Service\Uploader;
use UploaderService\Service\Uploader\Output;

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

    /**
     * @param string[] $notations
     *
     * @return Logger|null
     * @throws InvalidLogNotationException
     * @throws Exception
     */
    protected function parseLogger( array $notations ): ?Logger {

        if ( empty( $notations ) ) {

            return null;

        }

        $logger = new Logger( static::$defaultName );

        foreach ( $notations as $notation ) {

            if ( ! preg_match( '/^(?P<strict>\!?)(?P<level>debug|info|error):(?P<path>.+)$/', $notation, $matches ) ) {

                throw new InvalidLogNotationException( $notation );

            }

            $handler = new StreamHandler( $matches['path'], $matches['level'] );

            if ( '!' === $matches['strict'] ) {

                $handler = new FilterHandler( $handler, $matches['level'], $matches['level'] );

            }

            $logger->pushHandler( $handler );

        }

        return $logger;

    }

    protected function configure() {

        $this
            ->setDescription( 'Uploads large files to an S3 bucket.' )
            ->setHelp( "This command scans a folder for large files and uploads them to an S3 bucket." )

            ->addOption( 'path', '', InputOption::VALUE_OPTIONAL,
                         'Specifies a source directory path to perform the scans on.' )

            ->addOption( 'size-threshold', '', InputOption::VALUE_OPTIONAL,
                         'Specifies a size threshold above which to process files.' )

            ->addOption( 's3-region', '', InputOption::VALUE_OPTIONAL,
                         'Specifies an AWS S3 region to use for uploads.' )

            ->addOption( 's3-bucket', '', InputOption::VALUE_OPTIONAL,
                         'Specifies an AWS S3 bucket name to use for uploads.' )

            ->addOption( 's3-key', '', InputOption::VALUE_OPTIONAL,
                         'Specifies an AWS S3 key to use for uploads.' )

            ->addOption( 's3-secret', '', InputOption::VALUE_OPTIONAL,
                         'Specifies an AWS S3 secret to use for uploads.' )

            ->addOption( 'delete', '', InputOption::VALUE_NONE,
                         'Specifies whether files should be deleted locally after uploaded.' )

            ->addOption( 'limit', '', InputOption::VALUE_OPTIONAL,
                         'Specifies a maximum number of files to be processed.' )

            ->addOption( 'skip-up-to-date', '', InputOption::VALUE_NONE,
                         'Specifies whether up-to-date files should be skipped rather than rewritten (default).' )

            ->addOption( 'log', '', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                         "Specifies a log entry in the format <comment>log_level</comment>:" .
                         "<comment>path_to_file</comment>, where\n" .
                         "<comment>log_level</comment> is one of <comment>debug</comment>, <comment>info</comment> " .
                         "and <comment>error</comment>, and <comment>path_to_file</comment> is\n" .
                         "where logs should be written.\n" .
                         "By default logs will include entries from the specified <comment>log_level</comment>\n" .
                         "and above. To only write entries for the specific level prefix\n" .
                         "with an exclamation mark. Example: <comment>!info:info.log</comment>." )

            ->addOption( 'config', '', InputOption::VALUE_OPTIONAL,
                         "Specifies a path to a JSON config file to read from and write configuration values to.\n" .
                         "You can use this file to avoid configuring trivial stuff via the command line.\n" .
                         "The file will not read or write values for the following entries: <comment>path</comment>,\n" .
                         "<comment>s3-key</comment>, <comment>s3-secret</comment> and <comment>log</comment>.\n" .
                         "Values specified via the command line will override those loaded from the config file." )

            ->addOption( 'dump-config', '', InputOption::VALUE_NONE,
                         "Use this to force save a configuration file for later use to the path specified\n" .
                         "by <comment>--config</comment>.\n" .
                         "If this option is set no other operation will be performed." )

        ;

    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     * @throws InvalidLogNotationException
     * @throws NotInSpecException
     * @throws Exception
     */
    protected function execute( InputInterface $input, OutputInterface $output ) {

        $logger = $this->parseLogger( $input->getOption( 'log' ) );
        $uploaderOutput = new Output( $output, $logger, true );

        $configPath = $input->getOption( 'config' );
        if ( $configPath && is_file( $configPath ) ) {

            $this->config->mergeJSONFile( $configPath );

        }

        $this->config->mergeConsoleInput( $input );

        if ( $configPath && $input->getOption( 'dump-config' ) ) {

            $uploaderOutput->debug( 'Dumping configuration to <comment>%s</comment>.', $configPath );
            $this->config->writeJSON( $configPath );

            $uploaderOutput->success( 'Successfully dumped configuration to <comment>%s</comment>.', $configPath );
            return;

        }

        ( new Uploader( $this->config, $uploaderOutput ) )
            ->scan()
            ->upload();

    }

    /**
     * Upload constructor.
     *
     * @param Config|null $config
     */
    public function __construct( Config $config = null ) {

        parent::__construct();

        $this->config = $config ?? new Config();

    }

}
