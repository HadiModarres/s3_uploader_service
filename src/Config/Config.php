<?php

namespace UploaderService\Config;

use JsonSerializable;
use Symfony\Component\Console\Input\InputInterface;
use UploaderService\Config\Config\Spec;
use UploaderService\Config\Exceptions\CannotWriteJSONFileException;
use UploaderService\Config\Exceptions\InvalidConfigValueException;
use UploaderService\Config\Exceptions\InvalidJSONFileException;
use UploaderService\Config\Exceptions\MissingConfigValueException;
use UploaderService\Config\Exceptions\NotInSpecException;

/**
 * Class Config
 *
 * @method Config setPath( string $value )
 * @method Config setSizeThreshold( string $value )
 * @method Config setDelete( bool $value )
 * @method Config setLimit( int $value )
 * @method Config setSkipUpToDate( bool $value )
 * @method Config setS3Region( string $value )
 * @method Config setS3Bucket( string $value )
 * @method Config setS3Key( string $value )
 * @method Config setS3Secret( string $value )
 *
 * @method string getPath()
 * @method string getSizeThreshold()
 * @method bool getDelete()
 * @method int getLimit()
 * @method bool getSkipUpToDate()
 * @method string getS3Region()
 * @method string getS3Bucket()
 * @method string getS3Key()
 * @method string getS3Secret()
 *
 * @package UploaderService\Config
 */
class Config implements JsonSerializable {

    const PATH              = 'path';
    const SIZE_THRESHOLD    = 'size-threshold';
    const DELETE            = 'delete';
    const LIMIT             = 'limit';
    const SKIP_UP_TO_DATE   = 'skip-up-to-date';
    const S3_REGION         = 's3-region';
    const S3_BUCKET         = 's3-bucket';
    const S3_KEY            = 's3-key';
    const S3_SECRET         = 's3-secret';

    /**
     * @var Spec[]
     */
    protected $spec = [];

    /**
     * @var array
     */
    protected $config = [];

    /**
     * Config constructor.
     */
    public function __construct() {

        $this->spec = [

            static::PATH => new Spec( static::PATH, 'string', null, false, function ( string $value ) {

                if ( ! is_dir( $value ) ) {

                    throw new InvalidConfigValueException( static::PATH, $value );

                }

                return realpath( $value );

            } ),

            static::SIZE_THRESHOLD => new Spec( static::SIZE_THRESHOLD, 'string', null, true, function ( string $value ) {

                if ( ! preg_match( '/^\s*([0-9\.]+)\s*([kmg]i?)?\s*$/i', $value, $matches ) ) {

                    throw new InvalidConfigValueException( static::SIZE_THRESHOLD, $value );

                }

                return $value;

            } ),

            static::DELETE => new Spec( static::DELETE, 'boolean', false ),

            static::LIMIT => new Spec( static::LIMIT, 'integer', 0 ),

            static::SKIP_UP_TO_DATE => new Spec( static::SKIP_UP_TO_DATE, 'boolean', false ),

            static::S3_REGION => new Spec( static::S3_REGION, 'string', null, true, function ( string $value ) {

                if ( empty( $value ) ) {

                    throw new InvalidConfigValueException( static::S3_REGION, $value );

                }

                return $value;

            } ),

            static::S3_BUCKET => new Spec( static::S3_BUCKET, 'string', null, true, function ( string $value ) {

                if ( empty( $value ) ) {

                    throw new InvalidConfigValueException( static::S3_BUCKET, $value );

                }

                return $value;

            } ),

            static::S3_KEY => new Spec( static::S3_KEY, 'string', null, false, function ( string $value ) {

                if ( empty( $value ) ) {

                    throw new InvalidConfigValueException( static::S3_KEY, $value );

                }

                return $value;

            } ),

            static::S3_SECRET => new Spec( static::S3_SECRET, 'string', null, false, function ( string $value ) {

                if ( empty( $value ) ) {

                    throw new InvalidConfigValueException( static::S3_SECRET, $value );

                }

                return $value;

            } ),

        ];

    }

    /**
     * @param $name
     * @param $arguments
     *
     * @return mixed|Config|null
     * @throws InvalidConfigValueException
     * @throws MissingConfigValueException
     * @throws NotInSpecException
     */
    public function __call( $name, $arguments ) {

        if ( 'set' === substr( $name, 0, 3 ) ) {

            return $this->set(

                strtolower( preg_replace( '/([A-Z])/', '-$1', lcfirst( substr( $name, 3 ) ) ) ),
                ...$arguments

            );

        }

        if ( 'get' === substr( $name, 0, 3 ) ) {

            return $this->get( strtolower( preg_replace( '/([A-Z])/', '-$1', lcfirst( substr( $name, 3 ) ) ) ) );

        }

        trigger_error( sprintf( 'Call to undefined method %1$s::%2$s()', __CLASS__, $name ), E_USER_ERROR );
        return null;

    }

    /**
     * @param string $key
     * @param $value
     *
     * @return Config
     * @throws NotInSpecException
     */
    public function set( string $key, $value ): Config {

        if ( ! array_key_exists( $key, $this->spec ) ) {

            throw new NotInSpecException( $key );

        }

        $this->config[ $key ] = $value;
        return $this;

    }

    /**
     * @param string $key
     *
     * @return mixed|null
     * @throws InvalidConfigValueException
     * @throws MissingConfigValueException
     * @throws NotInSpecException
     */
    public function get( string $key ) {

        if ( ! array_key_exists( $key, $this->spec ) ) {

            throw new NotInSpecException( $key );

        }

        $spec = $this->spec[ $key ];

        if ( ! $this->has( $key ) ) {

            if ( $spec->hasDefault() ) {

                return $spec->getDefault();

            }

            throw new MissingConfigValueException( $key );

        }

        return $spec->filter( $this->config[ $key ] );

    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has( string $key ): bool {

        return array_key_exists( $key, $this->config );

    }

    /**
     * @param InputInterface $input
     *
     * @return Config
     * @throws NotInSpecException
     */
    public function mergeConsoleInput( InputInterface $input ): Config {

        foreach ( $this->spec as $key => $spec ) {

            $value = $input->getOption( $key );
            if ( ! isset( $value ) ) {

                continue;

            }

            $this->set( $key, $value );

        }

        return $this;

    }

    /**
     * @param array $data
     *
     * @return Config
     * @throws NotInSpecException
     */
    public function mergeArray( array $data ): Config {

        foreach ( $this->spec as $key => $spec ) {

            if ( ! array_key_exists( $key, $data ) ) {

                continue;

            }

            $this->set( $key, $data[ $key ] );

        }

        return $this;

    }

    /**
     * @param string $path
     *
     * @return Config
     * @throws InvalidJSONFileException
     * @throws NotInSpecException
     */
    public function mergeJSONFile( string $path ): Config {

        $content = @file_get_contents( $path );
        if ( ! $content ) {

            throw new InvalidJSONFileException( $path );

        }

        $data = @json_decode( $content, true );
        if ( ! is_array( $data ) ) {

            throw new InvalidJSONFileException( $path );

        }

        return $this->mergeArray( $data );

    }

    /**
     * @return array
     */
    public function jsonSerialize(): array {

        return array_map( function ( Spec $spec ) {

            return $this->get( $spec->getKey() );

        }, array_filter( $this->spec, function ( Spec $spec ) {

            return $spec->isSerializable() &&
                   $this->has( $spec->getKey() ) &&
                   ( ! $spec->hasDefault() || $spec->getDefault() !== $this->get( $spec->getKey() ) );

        } ) );

    }

    /**
     * @param string $path
     *
     * @return Config
     * @throws CannotWriteJSONFileException
     */
    public function writeJSON( string $path ): Config {

        if ( ! @file_put_contents( $path, json_encode( $this ) ) ) {

            throw new CannotWriteJSONFileException( $path );

        }

        return $this;

    }

}
