<?php


namespace Drupal\thruway\Logger;


use Psr\Log\AbstractLogger;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use React\EventLoop\LoopInterface;

class ConsoleLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = array())
    {
        echo "{$level}: {$message}";
    }

}