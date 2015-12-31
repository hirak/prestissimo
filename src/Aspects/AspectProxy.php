<?php
/*
 * hirak/prestissimo
 * @author Hiraku Nakano
 * @license https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo\Aspects;

use SplObserver;
use SplSubject;

/**
 * setting for proxy server
 */
class AspectProxy implements SplObserver
{
    public function update(SplSubject $ev)
    {
        if ('pre-download' !== (string)$ev) {
            return;
        }

        $request = $ev->getRequest();
    }
}
