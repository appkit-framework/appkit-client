<?php

namespace AppKit\Client;

use AppKit\StartStop\StartStopInterface;
use AppKit\Health\HealthIndicatorInterface;
use AppKit\Health\HealthCheckResult;
use AppKit\Async\Task;
use function AppKit\Async\await;
use function AppKit\Async\delay;

use Throwable;
use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use React\Promise\Deferred;

abstract class AbstractClient
implements StartStopInterface, HealthIndicatorInterface, EventEmitterInterface {
    use EventEmitterTrait;

    protected $log;

    private $connection;
    private $connectTask;
    private $disconnectDeferred;

    abstract protected function createConnection();
    
    function __construct($log) {
        $this -> log = $log;
    }
    
    public function start() {
        $this -> startConnectTask();
        $this -> connectTask -> await();
    }
    
    public function stop() {
        if($this -> connectTask -> getStatus() == Task::RUNNING) {
            $this -> log -> debug('Connect task running during stop, canceling');
            $this -> connectTask -> cancel() -> join();
        }

        if($this -> isConnected()) {
            $this -> log -> debug('Trying to disconnect');
            $this -> disconnectDeferred = new Deferred();

            try {
                $this -> connection -> disconnect();
            } catch(Throwable $e) {
                $error = 'Failed to disconnect';
                $this -> log -> error($error, $e);
                throw new ClientException(
                    $error,
                    previous: $e
                );
            }

            await($this -> disconnectDeferred -> promise());
            $this -> log -> info('Disconnected');
        }
    }

    public function checkHealth() {
        $data = [
            'Connected' => $this -> isConnected()
        ];

        if($this -> connection instanceof HealthIndicatorInterface)
            $data['Connection ' . get_class($this -> connection)] = $this -> connection;

        return new HealthCheckResult($data);
    }

    public function isConnected() {
        return $this -> connection !== null && $this -> connection -> isConnected();
    }

    protected function getConnection() {
        if(! $this -> connection)
            throw new ClientException('Client not connected');

        return $this -> connection;
    }

    private function startConnectTask() {
        $this -> connectTask = new Task(function() {
            return $this -> connectRoutine();
        }) -> run();
    }

    private function connectRoutine() {
        $retryDelay = null;

        while(true) {
            $this -> log -> debug('Trying to connect');

            try {
                $connection = $this -> createConnection();
                $connection -> connect();
                break;
            } catch(Throwable $e) {
                if(! $retryDelay)
                    $retryDelay = 1;
                else if($retryDelay == 1)
                    $retryDelay = 5;
                else if($retryDelay == 5)
                    $retryDelay = 10;

                $this -> log -> error(
                    'Failed to connect, retrying in {retryDelay} seconds',
                    [ 'retryDelay' => $retryDelay ],
                    $e
                );
                delay($retryDelay);
            }
        }

        $this -> connection = $connection;
        $this -> connection -> once('close', function() {
            $this -> onConnectionClose();
        });
        $this -> log -> info('Connected');
        $this -> emit('connect');
    }

    protected function onConnectionClose() {
        $this -> connection = null;
        $this -> emit('close');

        if($this -> disconnectDeferred) {
            $this -> disconnectDeferred -> resolve(null);
            return;
        }

        $this -> log -> warning('Connection lost, reconnecting');
        $this -> startConnectTask();
    }
}
