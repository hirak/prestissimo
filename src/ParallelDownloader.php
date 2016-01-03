<?php

namespace Hirak\Prestissimo;

use Composer\Package;
use Composer\IO;
use Composer\Config;

/**
 * 先行して並列ダウンロードを行い、キャッシュファイルを作成する。
 * ダウンロード先は直接書き込み限定であり、RETURNTRANSFER機能は使うことができない。
 *
 */
class ParallelDownloader
{
    /** @var IO/IOInterface */
    protected $io;

    /** @var Config */
    protected $config;

    /** @var int */
    protected $totalCnt = 0;
    protected $successCnt = 0;
    protected $failureCnt = 0;

    public function __construct(IO\IOInterface $io, Config $config)
    {
        $this->io = $io;
        $this->config = $config;
    }

    /**
     * 並列数$connsで$packagesを並列にダウンロードしてゆき、先にキャッシュを作成してゆく。
     * $connsよりも少ない数しかダウンロードしないのであれば、このメソッドを使ってはならない。
     * @param Package\PackageInterface[] $packages
     * @param int $conns
     * @param bool $progress
     * @return void
     */
    public function download(array $packages, $conns, $progress) 
    {
        if (count($packages) < $conns) {
            throw new \InvalidArgumentException;
        }
        $mh = curl_multi_init();
        $unused = array();
        for ($i = 0; $i < $conns; ++$i) {
            $unused[] = curl_init();
        }

        $cachedir = rtrim($this->config->get('cache-files-dir'), '\/');

        $chFpMap = array();
        $running = 0; //ref type
        $remains = 0; //ref type

        $this->totalCnt = count($packages);
        $this->successCnt = 0;
        $this->failureCnt = 0;
        $this->io->writeError($this->makeDownloadingText(), false);
        do {
            while ($unused && $packages) {
                // $packagesから一個取ってきて、$unusedから一個取ってきて、chをつくる。
                $package = array_pop($packages);
                $filepath = $cachedir . DIRECTORY_SEPARATOR . static::getCacheKey($package);
                if (file_exists($filepath)) {
                    continue;
                }
                $ch = array_pop($unused);

                // make file resource
                $fp = CurlRemoteFilesystem::createFile($filepath);
                $chFpMap[(int)$ch] = $fp;

                // make url
                $url = $package->getDistUrl();
                $request = new Aspects\HttpGetRequest(parse_url($url, PHP_URL_HOST), $url, $this->io);
                $onPreDownload = Factory::getPreEvent($request);
                $onPreDownload->notify();

                curl_setopt_array($ch, $request->getCurlOpts());
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_multi_add_handle($mh, $ch);
            }

            // start multi download
            do $stat = curl_multi_exec($mh, $running);
            while ($stat === CURLM_CALL_MULTI_PERFORM);

            // wait for any event
            do switch (curl_multi_select($mh, 10)) {
                case -1:
                    usleep(10);
                    do $stat = curl_multi_exec($mh, $running);
                    while ($stat === CURLM_CALL_MULTI_PERFORM);
                    continue 2;
                case 0:
                    continue 2;
                default:
                    do $stat = curl_multi_exec($mh, $running);
                    while ($stat === CURLM_CALL_MULTI_PERFORM);
                    // イベントが発生して、そのイベントがダウンロード完了であれば、chをunusedに戻す。
                    // それ以外のイベントであれば、再度待つ。
                    // イベントの走査が終わっていたら、ループをやり直す。(そのままdo whileを抜ければOK)
                    do if ($raised = curl_multi_info_read($mh, $remains)) {
                        $ch = $raised['handle'];
                        $response = new Aspects\HttpGetResponse(
                            $errno = curl_errno($ch),
                            $error = curl_error($ch),
                            $info = curl_getinfo($ch)
                        );
                        if (CURLE_OK === $errno && 200 === $info['http_code']) {
                            ++$this->successCnt;
                            //$this->io->overwriteError($this->makeDownloadingText(), false);
                        } else {
                            ++$this->failureCnt;
                            //$this->io->overwriteError($this->makeDownloadingText(), false);
                        }
                        curl_setopt($ch, CURLOPT_FILE, STDOUT);
                        $index = (int)$ch;
                        $fp = $chFpMap[$index];
                        fclose($fp);
                        unset($chFpMap[$index]);
                        curl_multi_remove_handle($mh, $ch);
                        $unused[] = $ch;
                    } while ($remains);
                    continue 3;
            } while ($running);

            // もし、$packagesが空っぽになったならば、ループを抜ける。
        } while ($packages);

        // 後片付
        foreach ($unused as $ch) {
            curl_close($ch);
        }
        curl_multi_close($mh);
    }

    /**
     * @param int $success
     * @param int $failure
     * @return string
     */
    private function makeDownloadingText()
    {
        return "    Pre Downloading: <comment>success: $this->successCnt, failure: $this->failureCnt, total: $this->totalCnt</comment>";
    }

    public static function getCacheKey(Package\PackageInterface $p)
    {
        $distRef = $p->getDistReference();
        if (preg_match('{^[a-f0-9]{40}$}', $distRef)) {
            return "{$p->getName()}/$distRef.{$p->getDistType()}";
        }

        return "{$p->getName()}/{$p->getVersion()}-$distRef.{$p->getDistType()}";
    }
}
