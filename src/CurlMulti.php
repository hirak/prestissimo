<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

class CurlMulti
{
    /** @var resource curl_multi */
    private $mh;

    /** @var resource[] curl */
    private $unused = array();

    /** @var resource[] curl */
    private $using = array();

    /** @var array {src: Aspects\HttpGetRequest, dest: OutputFile}*/
    private $targets;

    /** @var array {src: Aspects\HttpGetRequest, dest: OutputFile}*/
    private $runningTargets;

    /**
     * @param int $maxConnections
     */
    public function __construct($maxConnections)
    {
        $this->mh = curl_multi_init();

        for ($i = 0; $i < $maxConnections; ++$i) {
            $this->unused[] = curl_init();
        }
    }

    public function __destruct()
    {
        foreach ($this->using as $ch) {
            curl_multi_remove_handle($this->mh, $ch);
            curl_close($ch);
        }

        foreach ($this->unused as $ch) {
            curl_close($ch);
        }

        curl_multi_close($this->mh);
    }

    /**
     * @param bool $pipeline
     * @codeCoverageIgnore
     */
    public function setupShareHandler($pipeline)
    {
        if (function_exists('curl_share_init')) {
            $sh = curl_share_init();
            curl_share_setopt($sh, CURLSHOPT_SHARE, CURL_LOCK_DATA_SSL_SESSION);

            foreach ($this->unused as $ch) {
                curl_setopt($ch, CURLOPT_SHARE, $sh);
            }
        }

        if ($pipeline && function_exists('curl_multi_setopt')) {
            curl_multi_setopt($this->mh, CURLMOPT_PIPELINING, true);
        }
    }

    /**
     * @param array $targets {src: Aspects\HttpGetRequest, dest: OutputFile}
     */
    public function setTargets(array $targets)
    {
        $this->targets = $targets;
    }

    public function setupEventLoop()
    {
        while (count($this->unused) > 0 && count($this->targets) > 0) {
            $target = array_pop($this->targets);
            $ch = array_pop($this->unused);
            $index = (int)$ch;

            $this->using[$index] = $ch;
            $this->runningTargets[$index] = $target;
            Factory::getPreEvent($target['src'])->notify();

            $opts = $target['src']->getCurlOpts();
            unset($opts[CURLOPT_ENCODING], $opts[CURLOPT_USERPWD]);
            curl_setopt_array($ch, $opts);
            curl_setopt($ch, CURLOPT_FILE, $target['dest']->getPointer());
            curl_multi_add_handle($this->mh, $ch);
        }
    }

    public function wait()
    {
        $expectRunning = count($this->using);
        $running = 0;
        $retryCnt = 0;

        do {
            do {
                $stat = curl_multi_exec($this->mh, $running);
            } while ($stat === CURLM_CALL_MULTI_PERFORM);
            if (-1 === curl_multi_select($this->mh)) {
                // @codeCoverageIgnoreStart
                if ($retryCnt++ > 10) {
                    throw new \RuntimeException('curl_multi_select failure');
                }
                // @codeCoverageIgnoreEnd
                usleep(100000);
            }
        } while ($running > 0 && $running >= $expectRunning);
    }

    public function getFinishedResults()
    {
        $results = array();
        $successCnt = $failureCnt = 0;
        do {
            if ($raised = curl_multi_info_read($this->mh, $remains)) {
                $ch = $raised['handle'];
                $errno = curl_errno($ch);
                $info = curl_getinfo($ch);
                curl_setopt($ch, CURLOPT_FILE, STDOUT);
                $index = (int)$ch;
                $target = $this->runningTargets[$index];
                if (CURLE_OK === $errno && 200 === $info['http_code']) {
                    ++$successCnt;
                    $target['dest']->setSuccess();
                    $results[] = $info['url'];
                } else {
                    ++$failureCnt;
                }
                unset($this->using[$index], $this->runningTargets[$index], $target);
                curl_multi_remove_handle($this->mh, $ch);
                $this->unused[] = $ch;
            }
        } while ($remains > 0);

        return compact('successCnt', 'failureCnt', 'results');
    }

    public function remain()
    {
        return count($this->runningTargets) > 0;
    }
}
