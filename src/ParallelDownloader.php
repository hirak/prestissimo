<?php

namespace Hiraku\Prestissimo;

use Composer\Package;
use Composer\IO;

/**
 * 先行して並列ダウンロードを行い、キャッシュファイルを作成する。
 * ダウンロード先は直接書き込み限定であり、RETURNTRANSFER機能は使うことができない。
 *
 */
class ParallelDownloader
{
    /** @var IO/IOInterface */
    protected $io;

    /** @var int */
    protected $totalCnt = 0;
    protected $successCnt = 0;
    protected $failureCnt = 0;

    public function __construct(IO\IOInterface $io)
    {
        $this->io = $io;
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
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_HTTPGET => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING => 'gzip',
                CURLOPT_TIMEOUT => 10,
            ));
            $unused[] = $ch;
            curl_multi_add_handle($mh, $ch);
        }

        $chFpMap = array();
        $running = 0; //ref type
        $remains = 0; //ref type

        $this->totalCnt = count($packages);
        $this->successCnt = 0;
        $this->failureCnt = 0;
        $this->io->writeError($this->makeDownloadingText(), false);
        do {
            do {
                // $packagesから一個取ってきて、$unusedから一個取ってきて、chをつくる。
                $ch = array_pop($unused);
                $package = array_pop($packages);

                // make file resource
                $filepath = static::getCacheKey($package);
                $dir = dirname($filepath);
                if (! file_exists($dir)) {
                    mkdir($dir, 0766, true);
                }
                $fp = fopen($filepath, 'wb');
                $chFpMap[(int)$ch] = $fp;

                // make url
                $url = $package->getDistUrl();
                if (preg_match($url, '%^https://api\.github\.com/repos/[^/]+/[^/]+/zipball/%')) {
                    $url = str_replace('api.github.com/repos', 'codeload.github.com', $url);
                    $url = str_replace('zipball', 'legacy.zip', $url);
                }

                curl_setopt_array($ch, array(
                    CURLOPT_URL => $url,
                    CURLOPT_FILE => $fp,
                ));
            } while ($unused || $packages);

            // start multi download
            do $stat = curl_multi_exec($mh, $running);
            while ($stat === CURLM_CALL_MULTI_PERFORM);

            // wait for any event
            do switch (curl_multi_select($mh, 5)) {
                case -1:
                    usleep(10);
                    do $stat = curl_multi_exec($mh, $running);
                    while ($stat === CURLM_CALL_MULTI_PERFORM);
                    continue 2;
                case 0:
                    continue 2;
                default:
                    // イベントが発生して、そのイベントがダウンロード完了であれば、chをunusedに戻す。
                    // それ以外のイベントであれば、再度待つ。
                    // イベントの走査が終わっていたら、ループをやり直す。(そのままdo whileを抜ければOK)
                    do if ($raised = curl_multi_info_read($mh, $remains)) {
                        $ch = $raised['handle'];
                        $info = curl_getinfo($ch);
                        $errno = curl_errno($ch);
                        if (CURLE_OK === $errno && 200 === $info['http_code']) {
                            ++$this->success;
                            $this->io->overwriteError($this->makeDownloadingText(), false);
                        } else {
                            ++$this->failure;
                            $this->io->overwriteError($this->makeDownloadingText(), false);
                        }
                        $index = (int)$ch;
                        $fp = $chFpMap[$index];
                        unset($chFpMap[$index]);
                        fclose($fp);
                        array_push($unused, $ch);
                    } while ($remains);
            } while ($running);

            // もし、$packagesが空っぽになったならば、ループを抜ける。
        } while ($packages);

        // 後片付
        foreach ($unused as $ch) {
            curl_multi_remove_handle($mh, $ch);
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

    public static function getCacheKey(PackageInterface $p)
    {
        $distRef = $p->getDistReference();
        if (preg_match('{^[a-f0-9]{40}$}', $distRef)) {
            return "{$p->getName()}/$distRef.{$p->getDistType()}";
        }

        return "{$p->getName()}/{$p->getVersion()}-$distRef.{$p->getDistType()}";
    }
}
