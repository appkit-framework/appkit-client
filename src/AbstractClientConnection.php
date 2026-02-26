<?php

namespace AppKit\Client;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;

abstract class AbstractClientConnection implements EventEmitterInterface {
    use EventEmitterTrait;

    protected const CLOSED        = 0;
    protected const CONNECTED     = 1;
    protected const DISCONNECTING = 2;

    private $status = self::CLOSED;

    abstract protected function doConnect();
    abstract protected function doDisconnect();

    public function connect() {
        $this -> doConnect();
        $this -> status = self::CONNECTED;
    }

    public function disconnect() {
        $this -> status = self::DISCONNECTING;
        $this -> doDisconnect();
    }

    public function isConnected() {
        return $this -> status == self::CONNECTED;
    }

    protected function getStatus() {
        return $this -> status;
    }

    protected function setClosed() {
        if($this -> status == self::CLOSED)
            return;

        $this -> status = self::CLOSED;
        $this -> emit('close');
    }

    protected function ensureConnected() {
        if($this -> status != self::CONNECTED)
            throw new ClientConnectionException('Connection is closed');
    }
}
