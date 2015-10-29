<?php namespace Constant\Timelog\Service\Toggl;

use Constant\Service\BaseService;
use Psr\Log\LoggerInterface;

/**
 * Class Toggl
 *
 * @author  Joe Constant <joe@joeconstant.com>
 * @link    http://joeconstant.com/
 * @license MIT
 */
class TogglService extends BaseService
{
    /**
     * @var string
     */
    public $user_agent = 'Timelog';
    /**
     * @var int
     */
    protected $workspace_id;

    /**
     * Initialize class
     * @param LoggerInterface $log
     * @param $workspace
     * @param $api_token
     * @param array $options
     */
    public function __construct(LoggerInterface $log, $workspace, $api_token, $options = [])
    {
        $this->output = $log;
        $this->username = $api_token;
        $this->password = 'api_token';
        $this->workspace_id = $workspace;
    }

    /**
     * @param TimeEntry $entry
     * @return TimeEntry
     */
    public function saveTimeEntry(TimeEntry $entry)
    {
        $startTime = strtotime($entry->getEntryDate());
        $endTime = $startTime + $entry->getDurationTime();
        $this->uri = 'https://www.toggl.com/api/v8/time_entries/' . $entry->getId();
        $data = [
            'time_entry' => [
                'description' => $entry->getDescription(),
                'start' => substr(strftime('%Y-%m-%dT%H:%M:%S%z', $startTime), 0, 22) . ':00',
                'stop' => substr(strftime('%Y-%m-%dT%H:%M:%S%z', $endTime), 0, 22) . ':00',
                'billable' => $entry->isBillable(),
                'wid' => $this->workspace_id,
                'duration' => $entry->getDurationTime(),
                'tags' => $entry->getTags(),
                'id' => $entry->getId(),
                'created_with' => $this->user_agent
            ]
        ];

        $this->output->debug(print_r($data, true));
        $data = json_encode($data);

        $response = $this->processRequest($data, self::PUT);
        $this->_handleError($data, $response);
        return $entry;
    }

    /**
     * Get tasks from Toggl for $rundate.  Will set $rundate to yesterday if not supplied
     *
     * @param string $startdate
     * @param string $enddate
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
        $this->uri = 'https://toggl.com/reports/api/v2/details?user_agent=' . $this->user_agent . '&since=' . $startdate . '&until=' . $enddate . '&workspace_id=' . $this->workspace_id;

        $data = [];
        $data = json_encode($data);

        $response = $this->processRequest($data, self::GET);
        $this->_handleError($data, $response);
        $toggl_entries = $this->processTogglResponse($response);
        $timeEntries = [];
        foreach ($toggl_entries as $entry) {
            $te = new TimeEntry(
                $this,
                $entry->id,
                $entry->client,
                $entry->project,
                $entry->description
            );
            $te->setEntryDate(date('Y-m-d', strtotime($entry->start)));
            $te->setDurationTime($entry->dur);
            $te->setTask($entry->task);
            $te->setDescription($entry->description);
            $te->processTags($entry->tags);
            $te->setBillable($entry->is_billable);
            $timeEntries[] = $te;
        }
        return $timeEntries;
    }

    protected function processTogglResponse($response)
    {
        // We only need to do something if results are paginated
        if ($response->total_count <= 50) {
            return $response->data;
        }

        $reqdata = [];
        $reqdata = json_encode($reqdata);
        $pages = ceil($response->total_count / 50);
        $curr_page = 1;
        $data = $response->data;

        $orig_uri = $this->uri;

        while ($curr_page < $pages) {
            $curr_page++;
            $this->uri .= '&page=' . $curr_page;
            $response = $this->processRequest($reqdata, self::GET);
            $this->_handleError($reqdata, $response);
            foreach ($response->data as $entry) {
                $data[] = $entry;
            }
            $this->uri = $orig_uri;
        }

        return $data;
    }
}
