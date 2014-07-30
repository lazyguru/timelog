<?php

/**
 * Class TogglToJira
 *
 * @author Joe Constant <joe@joeconstant.com>
 * @link http://joeconstant.com/
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
    protected function getTimeEntries($rundate = null)
    {
        if (empty($rundate)) {
            $rundate = date('Y-m-d', time() - 86400);
        }
        $report_url = 'https://toggl.com/reports/api/v2/details?user_agent='.$this->user_agent.'&since=' . $rundate . '&until=' . $rundate;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_USERPWD, $this->api_token . ':api_token');
        curl_setopt($ch, CURLOPT_URL, $report_url . '&workspace_id=' . $this->workspace_id);
        $result = curl_exec($ch);
        $result = json_decode($result);
        return $result->data;
    }

    /**
     *  Main entry point
     *
     * @param string $rundate
     */
    public function run($rundate = null)
    {
        $entries = $this->getTimeEntries($rundate);
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
        echo "******** Created Jira Worklogs **********\n";
        print_r($this->loggedTimes);
        echo "******* No Jira Worklogs Created ********\n";
        print_r($this->notLoggedTimes);
        echo "*****************************************\n";
    }

    /**
     * Create worklog entry in Jira
     *
     * @param array $site
     * @param string $date
     * @param string $ticket
     * @param mixed $timeSpent
     * @param string $comment
     * @return mixed
     */
    protected function setWorklog($site, $date, $ticket, $timeSpent, $comment = '')
    {
        $auth = base64_encode("{$site['user']}:{$site['pass']}");

        if (is_int($timeSpent)) {
            $timeSpent .= 'h';
        }

        $json = json_encode(array(
            'timeSpent' => $timeSpent,
            'started'   => "{$date}T00:00:00.000-0600",
            'comment'   => $comment
        ));

        $url = "{$site['url']}/rest/api/2/issue/{$ticket}/worklog";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            "Authorization: Basic $auth"
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_POST, true);
        $result=curl_exec ($ch);

        curl_close($ch);

        return $result;
    }

}
