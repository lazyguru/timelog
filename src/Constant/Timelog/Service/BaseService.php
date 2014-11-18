<?php namespace Constant\Timelog\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Log\LogSubscriber;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

abstract class BaseService {

    const POST = 'post';
    const GET  = 'get';

    public    $_debug   = false;
    protected $_headers = [];
    protected $uri      = '';
    protected $username = '';
    protected $password = '';

    public abstract function __construct($username, $password, $options = []);

    public function _handleError($data, $response)
    {
        if ($this->_debug) {
            echo 'Class: ' . get_class($this) . "\n";
            echo "Request:\n";
            echo "**********\n";
            print_r($data);
            echo "\n**********\n";
            echo "Response:\n";
            echo "**********\n";
            print_r($response);
            echo "\n**********\n";
        }
    }

    protected function processRequest($data, $method = self::POST)
    {
        $this->_headers['Content-Type'] = 'application/json';
        try {
            $client = new \GuzzleHttp\Client();

            // create a log channel
            $log = new Logger(get_class($this));
            $log->pushHandler(new StreamHandler(get_class($this) . '.log', Logger::DEBUG));

            $subscriber = new LogSubscriber($log);
            $client->getEmitter()->attach($subscriber);

            $response = $client->$method($this->uri, [
                'auth'    => [$this->username, $this->password],
                'headers' => $this->_headers,
                'body'    => $data
            ]);

            return json_decode($response->getBody());
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            echo $e->getRequest();
            if ($e->hasResponse()) {
                echo $e->getResponse();
            }
        }
    }
} 
