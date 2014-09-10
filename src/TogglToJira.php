<?php

use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Log\LogSubscriber;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Class TogglToJira
 *
 * @author  Joe Constant <joe@joeconstant.com>
 * @link    http://joeconstant.com/
 * @license MIT
 */
class TogglToJira
{
    /**
     * @var array
     */
    protected $loggedTimes = array();

    /**
     * @var array
     */
    protected $notLoggedTimes = array();

    /**
     * @var array
     */
    protected $jira;

    /**
     * @var Toggl
     */
    protected $_toggl;

    /**
     * Initialize class
     */
    public function __construct()
    {
        // Toggl values
        $toggl_config = sfYaml::load('toggl.yaml');
        $this->_toggl = new Toggl($toggl_config['workspace_id'], $toggl_config['api_token']);
        $this->_toggl->user_agent = $toggl_config['user_agent'];

        // Jira values
        $this->jira = sfYaml::load('jira.yaml');
    }

    /**
     * Retrieve list of clients
     *
     * @return array
     */
    protected function getClients()
    {
        return $this->jira['Clients'];
    }

    /**
     * Retrieve list of sites
     *
     * @return array
     */
    protected function getSites()
    {
        return $this->jira['Sites'];
    }

    /**
     *  Main entry point
     *
     * @param string $rundate
     */
    public function run($rundate = null, $enddate = null)
    {
        $entries = $this->_toggl->getTimeEntries($rundate, $enddate);
        $clients = $this->getClients();
        $sites = $this->getSites();
        foreach ($entries as $entry) {
            $ticket = $entry->description;
            $date = date('Y-m-d', strtotime($entry->start));
            $client = $entry->client;
            $time = $entry->dur;
            $time = $time / 60 / 1000 / 60;
            if (isset($clients[$client])) {
                $clientSite = $clients[$client]['site'];
                $site = $sites[$clientSite];
                $this->setWorklog($site, $date, $ticket, $time);
                $this->loggedTimes[] = "{$client} ticket {$ticket} was logged on {$date} for {$time}h";
            } else {
                $this->notLoggedTimes[] = "{$client} task {$ticket} was NOT logged on {$date} for {$time}h";
            }
        }
        $this->displayReport();
    }

    /**
     * Outputs the time entries split out by whether Jira worklogs were created or not
     */
    function displayReport()
    {
        asort($this->loggedTimes);
        asort($this->notLoggedTimes);
        echo "******** Created Jira Worklogs **********\n";
        print_r($this->loggedTimes);
        echo "******* No Jira Worklogs Created ********\n";
        print_r($this->notLoggedTimes);
        echo "*****************************************\n";
    }

    /**
     * Create worklog entry in Jira
     *
     * @param array  $site
     * @param string $date
     * @param string $ticket
     * @param mixed  $timeSpent
     * @param string $comment
     * @throws Exception
     * @return mixed
     */
    protected function setWorklog($site, $date, $ticket, $timeSpent, $comment = '')
    {
        $url = "{$site['url']}/rest/api/2/issue/{$ticket}/worklog";

        if (is_numeric($timeSpent)) {
            $timeSpent .= 'h';
        }

        try {
            $client = new GuzzleHttp\Client();

            // create a log channel
            $log = new Logger('TogglToJira');
            $log->pushHandler(new StreamHandler('guzzle.log', Logger::DEBUG));

            $subscriber = new LogSubscriber($log);
            $client->getEmitter()->attach($subscriber);

            $response = $client->post($url, [
                'auth' => [$site['user'], $site['pass']],
                'json' => [
                    'timeSpent' => $timeSpent,
                    'started' => "{$date}T00:00:00.000-0600",
                    'comment' => $comment
                ]
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
