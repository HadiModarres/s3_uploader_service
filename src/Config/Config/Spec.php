<?php

namespace UploaderService\Config\Config;

use UploaderService\Config\Exceptions\InvalidConfigValueException;

/**
 * Class Spec
 *
 * @package UploaderService\Config\Config
 */
class Spec {

    /**
     * @var string
     */
    protected $key;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var mixed|null
     */
    protected $default;

    /**
     * @var bool
     */
    protected $serializable;

    /**
     * @var callable|null
     */
    protected $filter;

    /**
     * Spec constructor.
     *
     * @param string $key
     * @param string $type
     * @param mixed|null $default
     * @param bool $serializable
     * @param callable|null $filter
     */
    public function __construct(

        string $key,
        string $type,
        $default = null,
        bool $serializable = true,
        callable $filter = null

    ) {

        $this
            ->setKey( $key )
            ->setType( $type )
            ->setDefault( $default )
            ->setSerializable( $serializable )
            ->setFilter( $filter );

    }

    /**
     * @return string
     */
    public function getKey(): string {

        return $this->key;

    }

    /**
     * @param string $key
     *
     * @return Spec
     */
    public function setKey( string $key ): Spec {

        $this->key = $key;
        return $this;

    }

    /**
     * @return string
     */
    public function getType(): string {

        return $this->type;

    }

    /**
     * @param string $type
     *
     * @return Spec
     */
    public function setType( string $type ): Spec {

        $this->type = $type;
        return $this;

    }

    /**
     * @return mixed|null
     */
    public function getDefault() {

        return $this->default;

    }

    /**
     * @param null $default
     *
     * @return Spec
     */
    public function setDefault( $default = null ): Spec {

        $this->default = $default;
        return $this;

    }

    /**
     * @return bool
     */
    public function hasDefault(): bool {

        return isset( $this->default );

    }

    /**
     * @return bool
     */
    public function isSerializable(): bool {

        return $this->serializable;

    }

    /**
     * @param bool $serializable
     *
     * @return Spec
     */
    public function setSerializable( bool $serializable ): Spec {

        $this->serializable = $serializable;
        return $this;

    }

    /**
     * @return callable|null
     */
    public function getFilter(): ?callable {

        return $this->filter;

    }

    /**
     * @param callable|null $filter
     *
     * @return Spec
     */
    public function setFilter( callable $filter = null ): Spec {

        $this->filter = $filter;
        return $this;

    }

    /**
     * @return bool
     */
    public function hasFilter(): bool {

        return isset( $this->filter );

    }

    /**
     * @param $value
     *
     * @return mixed
     * @throws InvalidConfigValueException
     */
    public function filter( $value ) {

        $targetType = $this->getType();
        $actualType = gettype( $value );

        // special, cast-able
        if ( 'string' === $targetType && 'integer' === $actualType ) {

            $actualType = 'string';
            $value = (string) $value;

        } else if ( 'integer' === $targetType && 'string' === $actualType ) {

            $actualType = 'integer';
            $value = (int) $value;

        }

        if ( $actualType !== $targetType ) {

            throw new InvalidConfigValueException( $this->getKey(), $value );

        }

        if ( ! $this->hasFilter() ) {

            return $value;

        }

        return call_user_func( $this->getFilter(), $value );

    }

}
