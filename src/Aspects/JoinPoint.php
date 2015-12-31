<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use SplSubject;
use SplObserver;
use SplObjectStorage;

/**
 * Simple EventDispatcher for extending downloader
 */
class JoinPoint implements SplSubject
{
    /** @var SplObjectStorage */
    protected $storage;

    /** @var HttpGetRequest */
    protected $request;

    /** @var string */
    protected $name;

    /**
     * @param string $pointName
     */
    public function __construct($pointName)
    {
        $this->name = $pointName;
        $this->storage = new SplObjectStorage;
        $this->request = new HttpGetRequest;
    }

    public function attach(SplObserver $observer)
    {
        $this->storage->attach($observer);
    }

    public function detach(SplObserver $observer)
    {
        $this->storage->detach($observer);
    }

    public function notify()
    {
        foreach ($this->storage as $observer) {
            $observer->update($this);
        }
    }

    final public function refRequest()
    {
        return $this->request;
    }

    public function __toString()
    {
        return $this->name;
    }
}
