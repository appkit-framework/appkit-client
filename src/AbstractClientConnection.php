<?php

namespace AppKit\Client;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;

abstract class AbstractClientConnection implements EventEmitterInterface {
    use EventEmitterTrait;

    private $connected = false;

    abstract protected function doConnect();
    abstract protected function doDisconnect();

    public function connect() {
        $this -> doConnect();
        $this -> connected = true;
    }

    public function disconnect() {
        $this -> doDisconnect();
        $this -> setClosed();
    }

    public function isConnected() {
        return $this -> connected;
    }

    protected function setClosed() {
        if(! $this -> connected)
            return;

        $this -> connected = false;
        $this -> emit('close');
    }

    protected function ensureConnected() {
        if(! $this -> connected)
            throw new ClientConnectionException('Connection is closed');
    }
}
