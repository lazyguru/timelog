<?php namespace Constant;

use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Log\LogSubscriber;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

abstract class Service {

    public    $_debug   = false;
    protected $_headers = [];
    protected $uri      = '';
    protected $username = '';
    protected $password = '';

    public abstract function __construct($username, $password, $options = []);

    public function _handleError($data, $response)
    {
        if ($this->_debug) {
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

    protected function processRequest($data)
    {
        $this->_headers['Content-Type'] = 'application/json';
        try {
            $client = new \GuzzleHttp\Client([
                'defaults' => [
                    'proxy' => 'localhost:8888'
                ]
            ]);

            // create a log channel
            $log = new Logger(__CLASS__);
            $log->pushHandler(new StreamHandler(__CLASS__ . '.log', Logger::DEBUG));

            $subscriber = new LogSubscriber($log);
            $client->getEmitter()->attach($subscriber);

            $response = $client->post($this->uri, [
                'auth'    => [$this->username, $this->password],
                'headers' => $this->_headers,
                'body'    => $data,
                'verify'  => false
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