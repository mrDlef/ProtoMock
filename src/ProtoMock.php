<?php

namespace Hal\ProtoMock;

/**
 * Class ProtoMock
 * @package Hal\ProtoMock
 */
class ProtoMock
{
    /**
     * @var string[]
     */
    protected static $protocols = [];

    /**
     * @var resource
     */
    protected $handler;

    /**
     * @var Mocks
     */
    protected static $mocks;

    /**
     * @var string
     */
    protected $tempFile;

    /**
     * @var resource|null
     */
    public $context;

    /**
     * @param string $protocol
     * @return $this
     */
    public function enable($protocol)
    {
        static::$protocols[] = $protocol;
        static::$mocks = new Mocks;
        stream_wrapper_unregister($protocol);
        stream_wrapper_register($protocol, static::class);
        return $this;
    }

    /**
     * @param string $protocol
     * @return $this
     */
    public function disable($protocol)
    {
        $k = array_search($protocol, static::$protocols);
        if (false === $k) {
            return $this;
        }

        stream_wrapper_unregister($protocol);
        stream_wrapper_restore($protocol);
        unset(static::$protocols[$k]);
        return $this;
    }

    /**
     * @return $this
     */
    protected function restoreDefaults()
    {
        foreach (static::$protocols as $protocol) {
            stream_wrapper_unregister($protocol);
            stream_wrapper_restore($protocol);
        }
        return $this;
    }

    /**
     * @return $this
     */
    protected function restoreCustoms()
    {
        foreach (static::$protocols as $protocol) {
            stream_wrapper_unregister($protocol);
            stream_wrapper_register($protocol, static::class);
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function reset()
    {
        static::$mocks = new Mocks;
        $this->restoreDefaults();
        return $this;
    }

    /**
     * @param $url
     * @param int $mode
     * @return Mock
     */
    public function with($url, $mode = Mock::MATCHING_EXACT)
    {
        $mock = new Mock($url, $mode);
        static::$mocks->attach($mock);
        return $mock;
    }

    /**
     * @param $regex
     * @return Mock
     */
    public function matching($regex)
    {
        return $this->with($regex, Mock::MATCHING_REGEX);
    }

    /**
     * @param Mock $mock
     * @return $this
     */
    public function without(Mock $mock)
    {
        static::$mocks->detach($mock);
        return $this;
    }

    /**
     * @param $path
     * @param string $mode
     * @return bool
     */
    public function stream_open($path, $mode = 'r')
    {
        $this->restoreDefaults();

        if (static::$mocks->supports($path)) {
            $content = static::$mocks->get($path)->getContent($this->context);
            if (false === $content) {
                $this->restoreCustoms();
                return false;
            }
            $this->tempFile = tempnam(sys_get_temp_dir(), 'mock');
            file_put_contents($this->tempFile, $content);
            $path = $this->tempFile;
        }

        $this->handler = fopen($path, $mode);
        $this->restoreCustoms();
        return true;
    }

    /**
     * @return array
     */
    public function stream_stat()
    {
        return fstat($this->handler);
    }

    /**
     * @return bool
     */
    public function stream_eof()
    {
        return feof($this->handler);
    }

    /**
     * @param int $length
     * @return string
     */
    public function stream_read($length)
    {
        return fread($this->handler, $length);
    }

    /**
     * @return bool
     */
    public function stream_close()
    {
        $result = fclose($this->handler);
        if ($this->tempFile) {
            $this->restoreDefaults();
            unlink($this->tempFile);
            $this->restoreCustoms();
        }
        return $result;
    }

    /**
     * @return array
     */
    public function url_stat()
    {
        return stat($this->handler);
    }
}
