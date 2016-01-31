<?php
/**
 * test http server
 *
 * php http.php
 *
 * exit:  http://localhost:1337/?exit=1
 * delay:  http://localhost:1337/?wait=1 (seconds)
 */
set_time_limit(0);
ob_implicit_flush();

$response =<<<_HTTP_
HTTP/1.1 200 OK\r
Content-Type: text/plain\r
Content-Length: 1\r
\r

_HTTP_;

$context['socket']['backlog'] = 128;
$server = stream_socket_server(
    'tcp://0.0.0.0:1337',
    $errno,
    $errmsg,
    STREAM_SERVER_LISTEN | STREAM_SERVER_BIND,
    stream_context_create($context)
);
stream_set_blocking($server, 0);
stream_set_timeout($server, 0);

$readOrigin = array();
$readOrigin[(int)$server] = $server;
$writeOrigin = array();
$except = null;

$waiting = array();

LOOP: {
    $read = $readOrigin;
    $write = $writeOrigin;

    stream_select($read, $write, $except, 1); //block

    if (PHP_VERSION_ID < 50400) {
        $readfix = array();
        foreach ($read as $stream) {
            $readfix[(int)$stream] = $stream;
        }
        $read = $readfix;
    }

    if (isset($read[(int)$server])) {
        $connection = stream_socket_accept($server, 30, $peername);
        stream_set_blocking($connection, 0);
        $readOrigin[(int)$connection] = $connection;
        unset($read[(int)$server]);
    }

    $now = microtime(true);
    foreach ($waiting as $waitingStream) {
        list($stream, $until, $messageBody) = $waitingStream;
        if ($now > $until) {
            $index = (int)$stream;
            fwrite($stream, $response . $messageBody);
            fclose($stream);
            unset($waiting[$index]);
            unset($readOrigin[$index]);
        }
    }

    foreach ($read as $stream) {
        $requestLine = stream_get_line($stream, 4096, "\r\n");

        list(, $path,) = explode(' ', $requestLine);
        parse_str(parse_url($path, PHP_URL_QUERY), $query);
        if (isset($query['exit']) && $query['exit'] === '1') exit;

        if (isset($query['status'])) {
            switch ($query['status']) {
                case '401':
                    $errorResponse = <<<_HTTP_
HTTP/1.1 401 Unauthorized\r
Connection: close\r
\r

_HTTP_;
                    break;
                case '403':
                    $errorResponse = <<<_HTTP_
HTTP/1.1 403 Forbidden\r
Connection: close\r
\r

_HTTP_;
                    break;
                case '404':
                default:
                    $errorResponse = <<<_HTTP_
HTTP/1.1 404 Not Found\r
Connection: close\r
\r

_HTTP_;
                    break;
            }
            fwrite($stream, $errorResponse);
            fclose($stream);
            unset($readOrigin[(int)$stream]);
            continue;
        }

        if (isset($query['wait']) && $query['wait'] > 0) {
            $waiting[(int)$stream] = array(
                $stream,
                microtime(true) + (int)$query['wait'],
                (int)$query['wait'],
            );
        } else {
            fwrite($stream, $response . '0');
            fclose($stream);
        }
        unset($readOrigin[(int)$stream]);
    }

    goto LOOP;
}
