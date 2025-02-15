<?php
class RouterosAPI {
    private $socket;
    private $connected = false;
    private $timeout = 3;
    private $attempts = 3;
    public $debug = false;

    public function connect($host, $user, $pass, $port = 8728) {
        for ($i = 0; $i < $this->attempts; $i++) {
            $this->socket = @fsockopen($host, $port, $errno, $errstr, $this->timeout);
            if ($this->socket) {
                stream_set_timeout($this->socket, $this->timeout);
                if ($this->login($user, $pass)) {
                    $this->connected = true;
                    return true;
                }
                fclose($this->socket);
            }
            usleep(500000); // Sleep for 0.5s before retrying
        }
        return false;
    }

    private function login($user, $pass) {
        $this->write('/login');
        $response = $this->read();

        if (isset($response[0]) && strpos($response[0], 'ret=') !== false) {
            $challenge = substr($response[0], 4);
            $md5 = md5(chr(0) . $pass . pack('H*', $challenge));
            $this->write(['/login', '=name=' . $user, '=response=00' . $md5]);
            $response = $this->read();
            return in_array('!done', $response);
        } elseif (isset($response[0]) && $response[0] == '!done') {
            return true; // Plaintext authentication (RouterOS v7)
        }
        return false;
    }

    public function write($commands) {
        if (!is_array($commands)) {
            $commands = [$commands];
        }
        foreach ($commands as $command) {
            fwrite($this->socket, $command . "\0");
        }
        fwrite($this->socket, "\0");
    }

    public function read() {
        $response = [];
        $buffer = '';
        while (!feof($this->socket)) {
            $buffer .= fread($this->socket, 4096);
            if (substr($buffer, -1) === "\0") {
                break;
            }
        }
        $lines = explode("\0", $buffer);
        foreach ($lines as $line) {
            if ($line !== '') {
                $response[] = trim($line);
            }
        }
        return $response;
    }

    public function disconnect() {
        if ($this->socket) {
            fclose($this->socket);
            $this->connected = false;
        }
    }
}
?>
