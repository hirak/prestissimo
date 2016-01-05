<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo\Aspects;

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

    /** @var HttpGetResponse */
    protected $response;

    /** @var string */
    protected $name;

    /**
     * @param string $pointName
     */
    public function __construct($pointName, HttpGetRequest $request)
    {
        $this->name = $pointName;
        $this->detachAll();
        $this->request = $request;
    }

    public function attach(SplObserver $observer)
    {
        $this->storage->attach($observer);
    }

    public function attachArray(array $observers)
    {
        $storage = $this->storage;
        foreach ($observers as $observer) {
            $storage->attach($observer);
        }
    }

    public function detach(SplObserver $observer)
    {
        $this->storage->detach($observer);
    }

    public function detachAll()
    {
        $this->storage = new SplObjectStorage;
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

    public function setRequest(HttpGetRequest $req)
    {
        $this->request = $req;
    }

    final public function refResponse()
    {
        return $this->response;
    }

    public function setResponse(HttpGetResponse $res)
    {
        $this->response = $res;
    }

    public function __toString()
    {
        return $this->name;
    }
}
