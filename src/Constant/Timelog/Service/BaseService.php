<?php namespace Constant\Timelog\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Subscriber\Log\LogSubscriber;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseService
{

    const POST = 'post';
    const GET = 'get';
    const PUT = 'put';

    protected $_headers = [];
    protected $uri = '';
    protected $username = '';
    protected $password = '';

    /**
     * @var OutputInterface $output
     */
    protected $output;

    /**
     * @param OutputInterface $output
     * @param $username
     * @param $password
     * @param array $options
     */
    public abstract function __construct(OutputInterface $output, $username, $password, $options = []);

    public function _handleError($data, $response)
    {
        if (OutputInterface::VERBOSITY_DEBUG <= $this->output->getVerbosity()) {
            $this->output->writeln('<debug>' . 'Class: ' . get_class($this) . '</debug>');
            $this->output->writeln('<debug>' . 'Request: ' . '</debug>');
            $this->output->writeln('<debug>' . '**********' . '</debug>');
            $this->output->writeln('<debug>' . print_r($data, true) . '</debug>');
            $this->output->writeln('<debug>' . '**********' . '</debug>');
            $this->output->writeln('<debug>' . 'Response: ' . '</debug>');
            $this->output->writeln('<debug>' . '**********' . '</debug>');
            $this->output->writeln('<debug>' . print_r($response, true) . '</debug>');
            $this->output->writeln('<debug>' . '**********' . '</debug>');
        }
    }

    protected function processRequest($data, $method = self::POST)
    {
        $this->_headers['Content-Type'] = 'application/json';
        try {
            $client = new Client();

            // create a log channel
            $log = new Logger(get_class($this));
            $log->pushHandler(new StreamHandler('timelog.log', Logger::DEBUG));

            $subscriber = new LogSubscriber($log);
            $client->getEmitter()->attach($subscriber);

            $response = $client->$method($this->uri, [
                'auth' => [$this->username, $this->password],
                'headers' => $this->_headers,
                'body' => $data
            ]);

            return json_decode($response->getBody());
        } catch (BadResponseException $e) {
            $this->output->writeln('<error>' . $e->getRequest() . '</error>');
            if ($e->hasResponse()) {
                $this->output->writeln('<error>' . $e->getResponse() . '</error>');
            }
        }
    }
} 
