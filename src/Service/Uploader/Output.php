<?php

namespace UploaderService\Service\Uploader;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Output
 *
 * @package UploaderService\Service\Uploader
 */
class Output {

    /**
     * @var OutputInterface|null
     */
    protected $consoleOutput;

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * @var bool
     */
    protected $muteConsoleOutputForExceptions;

    /**
     * @param string $message
     *
     * @return string
     */
    protected function formatCount( string $message ): string {

        return preg_replace_callback( '/{count:(?P<int>-?\d+)}/i', function ( array $matches ) {

            return number_format( (int) $matches['int'], 0, '', ',' );

        }, $message );

    }

    /**
     * @param string $message
     *
     * @return string
     */
    protected function formatPluralize( string $message ): string {

        return preg_replace_callback( '/{pluralize:(?P<int>\d+):(?P<word>.+?)}/i', function ( array $matches ) {

            return 1 === (int) $matches['int'] ? $matches['word'] : $matches['word'] . 's';

        }, $message );

    }

    /**
     * @param bool $stripTags
     * @param string $message
     * @param mixed ...$arguments
     *
     * @return string
     */
    protected function format( bool $stripTags, string $message, ...$arguments ): string {

        if ( $stripTags ) {

            $message = strip_tags( $message );

        }

        $message = sprintf( $message, ...$arguments );
        $message = $this->formatCount( $message );
        $message = $this->formatPluralize( $message );

        return $message;

    }

    /**
     * Output constructor.
     *
     * @param OutputInterface|null $consoleOutput
     * @param LoggerInterface|null $logger
     * @param bool $muteConsoleOutputForExceptions
     */
    public function __construct(

        OutputInterface $consoleOutput = null,
        LoggerInterface $logger = null,
        bool $muteConsoleOutputForExceptions = false

    ) {

        $this->consoleOutput = $consoleOutput;
        $this->logger = $logger;
        $this->muteConsoleOutputForExceptions = $muteConsoleOutputForExceptions;

    }

    /**
     * @param string $message
     * @param mixed ...$arguments
     *
     * @return Output
     */
    public function debug( string $message, ...$arguments ): Output {

        if ( isset( $this->consoleOutput ) && $this->consoleOutput->isVerbose() ) {

            $this->consoleOutput->writeln( $this->format( false, $message, ...$arguments ) );

        }

        if ( isset( $this->logger ) ) {

            $this->logger->debug( $this->format( true, $message, ...$arguments ) );

        }

        return $this;

    }

    /**
     * @param string $message
     * @param mixed ...$arguments
     *
     * @return Output
     */
    public function success( string $message, ...$arguments ): Output {

        if ( isset( $this->consoleOutput ) ) {

            $this->consoleOutput->writeln(

                sprintf(

                    '<fg=green>%s</>',
                    $this->format( false, $message, ...$arguments )

                )

            );

        }

        if ( isset( $this->logger ) ) {

            $this->logger->info( $this->format( true, $message, ...$arguments ) );

        }

        return $this;

    }

    /**
     * @param Exception $e
     *
     * @return Output
     */
    public function exception( Exception $e ): Output {

        if ( isset( $this->consoleOutput ) && ! $this->muteConsoleOutputForExceptions ) {

            $this->consoleOutput->writeln( sprintf( '<fg=red>%s</>', $e ) );

        }

        if ( isset( $this->logger ) ) {

            $this->logger->error( $e );

        }

        return $this;

    }

    /**
     * @param iterable $iterable
     *
     * @return iterable
     */
    public function trackProgress( iterable $iterable ): iterable {

        if ( ! $this->consoleOutput ) {

            return $iterable;

        }

        $progress = new ProgressBar( $this->consoleOutput );

        $progress->start( is_countable( $iterable ) ? count( $iterable ) : 0 );
        $this->consoleOutput->write( "\n" );

        foreach ( $iterable as $key => $value ) {

            yield $key => $value;

            $progress->advance();
            $this->consoleOutput->write( "\n" );

        }

        $progress->finish();
        $this->consoleOutput->write( "\n" );

    }

}
