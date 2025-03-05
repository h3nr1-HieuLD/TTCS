<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Exception;

class BuildQueueConsumer
{
    private $connection;
    private $channel;
    private $buildQueue = 'build_requests';
    private $resultQueue = 'build_results';

    public function __construct()
    {
        $this->connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST', 'localhost'),
            env('RABBITMQ_PORT', 5672),
            env('RABBITMQ_USER', 'guest'),
            env('RABBITMQ_PASSWORD', 'guest')
        );
        $this->channel = $this->connection->channel();
        
        // Declare queues
        $this->channel->queue_declare($this->buildQueue, false, true, false, false);
        $this->channel->queue_declare($this->resultQueue, false, true, false, false);
    }

    public function consume()
    {
        $this->channel->basic_consume(
            $this->buildQueue,
            '',
            false,
            false,
            false,
            false,
            function (AMQPMessage $message) {
                $this->processMessage($message);
            }
        );

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    protected function processMessage(AMQPMessage $message)
    {
        try {
            $data = json_decode($message->getBody(), true);
            
            // Process the build request
            $result = $this->handleBuild($data);
            
            // Send result back to result queue
            $this->publishResult($result);
            
            // Acknowledge the message
            $message->ack();
            
        } catch (Exception $e) {
            // Send error to result queue
            $this->publishResult([
                'status' => 'error',
                'error' => $e->getMessage(),
                'build_id' => $data['build_id'] ?? null
            ]);
            
            // Acknowledge the message even if there's an error
            $message->ack();
        }
    }

    protected function handleBuild(array $data)
    {
        // TODO: Implement actual build logic
        // This will include:
        // 1. Cloning the repository
        // 2. Checking out the specific commit
        // 3. Running build commands
        // 4. Collecting build results
        
        return [
            'build_id' => $data['build_id'],
            'status' => 'success',
            'repository' => $data['repository'],
            'commit' => $data['commit'],
            'timestamp' => time()
        ];
    }

    protected function publishResult(array $result)
    {
        $message = new AMQPMessage(
            json_encode($result),
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
        );
        
        $this->channel->basic_publish($message, '', $this->resultQueue);
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}