<?php

namespace Hirak\Prestissimo;

class CurlStream
{
    /** @type resource $context */
    public $context;

    /** @type resource<url>[] */
    private static $cache = array();

    /** @type resource<curl> $ch */
    private $ch;

    /** @type int $p */
    private $p = 0;

    /** @type string[] $header */
    private static $header = array();

    /** @type string $body */
    private $body;

    /** @type int $length */
    private $length;

    /**
     *
     */
    static public function getLastHeaders()
    {
        return self::$header;
    }

//__construct ( void )
//__destruct ( void )
//public bool dir_closedir ( void )
//public bool dir_opendir ( string $path , int $options )
//public string dir_readdir ( void )
//public bool dir_rewinddir ( void )
//public bool mkdir ( string $path , int $mode , int $options )
//public bool rename ( string $path_from , string $path_to )
//public bool rmdir ( string $path , int $options )
//public resource stream_cast ( int $cast_as )

    public function stream_close()
    {
        //curl_close($this->ch);
    }

    /**
     * @return bool
     */
    public function stream_eof()
    {
        return $this->p > $this->length;
    }

//public bool stream_flush ( void )
//public bool stream_lock ( int $operation )
//public bool stream_metadata ( string $path , int $option , mixed $value )

    /**
     * @param string $path
     * @param string $mode
     * @param int $options
     * @param string &$opend_path
     * @return bool
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $parsed = parse_url($path);
        $origin = "$parsed[scheme]://";
        if (isset($parsed['user'])) {
            $origin .= $parsed['user'];
            if (isset($parsed['pass'])) {
                $origin .= ":$parsed[pass]";
            }
            $origin .= '@';
        }
        $origin .= $parsed['host'];
        if (isset($parsed['port'])) {
            $origin .= ":$parsed[port]";
        }

        if (isset(self::$cache[$origin])) {
            $ch = self::$cache[$origin];
        } else {
            $ch = self::$cache[$origin] = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => 'gzip',
                CURLOPT_HEADER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 20,
            ));
        }
        curl_setopt($ch, CURLOPT_URL, $path);

        $params = stream_context_get_params($this->context);
        $context = $params['options'];
        if (isset($context['http']['method']) && $context['http']['method'] === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }
        if (isset($context['http']['header'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $context['http']['header']);
        }
        if (isset($context['http']['content'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $context['http']['content']);
        }

        if (isset($params['notification'])) {
            $callbackGet = $params['notification'];
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION,
                function($ch, $downbytesMax, $downbytes, $upbytesMax, $upbytes)
                use($callbackGet)
                {
                    static $bytesMaxSended = false;
                    static $prevDownbytes = 0;

                    if (
                        $downbytes &&
                        $downbytesMax &&
                        ($prevDownbytes < $downbytes)
                    ) {
                        if ($bytesMaxSended) {
                            $code = \STREAM_NOTIFY_PROGRESS;
                        } else {
                            $code = \STREAM_NOTIFY_FILE_SIZE_IS;
                            $bytesMaxSended = true;
                        }
                        call_user_func(
                            $callbackGet,
                            $code, //notificationCode
                            \STREAM_NOTIFY_SEVERITY_INFO, //severity
                            '', //message
                            0, //messageCode
                            $downbytes, //bytesTransferred
                            $downbytesMax //bytesMax
                        );
                        $prevDownbytes = $downbytes;
                    }
                    return 0;
                }
            );
        } else {
            curl_setopt($ch, CURLOPT_NOPROGRESS, true);
        }

        $result = curl_exec($ch);
        $errno = curl_errno($ch);
        if (CURLE_OK !== $errno) {
            throw new \RuntimeException(curl_error($ch), $errno);
        }

        $info = curl_getinfo($ch);
        $header = substr($result, 0, $info['header_size']);
        self::$header = explode("\r\n", rtrim($header));
        $this->body = substr($result, $info['header_size']);
        $this->length = strlen($this->body);

        if (isset($params['notification'])) {
            $callbackGet = $params['notification'];
            if (401 === $info['http_code']) {
                call_user_func(
                    $callbackGet,
                    STREAM_NOTIFY_AUTH_REQUIRED, //notificationCode
                    STREAM_NOTIFY_SEVERITY_ERR, //severity
                    '', //message
                    0, //messageCode
                    $info['download_content_length'], //bytesTransferred
                    $info['download_content_length'] //bytesMax
                );
            } elseif (403 === $info['http_code']) {
                call_user_func(
                    $callbackGet,
                    STREAM_NOTIFY_AUTH_RESULT, //notificationCode
                    STREAM_NOTIFY_SEVERITY_ERR, //severity
                    '', //message
                    0, //messageCode
                    $info['download_content_length'], //bytesTransferred
                    $info['download_content_length'] //bytesMax
                );
            }
        }

        $this->ch = $ch;
        return true;
    }

    /**
     * @param int $count
     * @return string
     */
    public function stream_read($count)
    {
        $p = $this->p;
        $this->p += $count;
        return substr($this->body, $p, $count);
    }

//public bool stream_seek ( int $offset , int $whence = SEEK_SET )
//public bool stream_set_option ( int $option , int $arg1 , int $arg2 )

    /**
     * @return array
     */
    function stream_stat()
    {
        return array();
    }

//public int stream_tell ( void )
//public bool stream_truncate ( int $new_size )
//public int stream_write ( string $data )
//public bool unlink ( string $path )
//public array url_stat ( string $path , int $flags )
}
