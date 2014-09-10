<?php

use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Log\LogSubscriber;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Class Toggl
 *
 * @author  Joe Constant <joe@joeconstant.com>
 * @link    http://joeconstant.com/
 * @license MIT
 */
class Toggl
{
    /**
     * @var int
     */
    protected $workspace_id;

    /**
     * @var string
     */
    public $user_agent = 'TogglToJira';

    /**
     * @var string
     */
    protected $api_token;

    /**
     * Initialize class
     */
    public function __construct($workspace, $api_token)
    {
        $this->workspace_id = $workspace;
        $this->api_token = $api_token;
    }

    /**
     * Get tasks from Toggl for $rundate.  Will set $rundate to yesterday if not supplied
     *
     * @param string $startdate
     * @return array
     * @throws Exception
     */
    public function getTimeEntries($startdate = null, $enddate = null)
    {
        if (empty($startdate)) {
            $startdate = date('Y-m-d', time() - 86400);
        }
        if (empty($enddate)) {
            $enddate = $startdate;
        }
        $report_url = 'https://toggl.com/reports/api/v2/details?user_agent=' . $this->user_agent . '&since=' . $startdate . '&until=' . $enddate . '&workspace_id=' . $this->workspace_id;

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
}
