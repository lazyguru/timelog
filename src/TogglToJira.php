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
     * @var int
     */
    protected $workspace_id;

    /**
     * @var string
     */
    protected $user_agent;

    /**
     * @var string
     */
    protected $api_token;

    /**
     * @var array
     */
    protected $jira;

    /**
     * Initialize class
     */
    public function __construct()
    {
        // Toggl values
        $toggl = sfYaml::load('toggl.yaml');
        $this->workspace_id = $toggl['workspace_id'];
        $this->user_agent = $toggl['user_agent'];
        $this->api_token = $toggl['api_token'];

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
     * Get tasks from Toggl for $rundate.  Will set $rundate to yesterday if not supplied
     *
     * @param string $rundate
     * @return array
     */
    protected function getTimeEntries($rundate = null, $enddate = null)
    {
        if (empty($rundate)) {
            $rundate = date('Y-m-d', time() - 86400);
        }
        if (empty($enddate)) {
            $enddate = $rundate;
        }
        $report_url = 'https://toggl.com/reports/api/v2/details?user_agent=' . $this->user_agent . '&since=' . $rundate . '&until=' . $enddate . '&workspace_id=' . $this->workspace_id;

        try {
            $client = new GuzzleHttp\Client();
            $request = $client->createRequest('GET', $report_url, [
                'auth' => [$this->api_token, 'api_token']
            ]);
            $request->setHeader('Content-Type', 'application/json');
            $response = $client->send($request);
            return json_decode($response->getBody())->data;
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            $raw_response = explode("\n", $e->getResponse());
            throw new Exception(end($raw_response));
        }
    }

    /**
     *  Main entry point
     *
     * @param string $rundate
     */
    public function run($rundate = null, $enddate = null)
    {
        $entries = $this->getTimeEntries($rundate, $enddate);
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
