<?php namespace Constant\Timelog\Service\Toggl;

use Constant\Timelog\Service\BaseService;

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
     * @var int
     */
    protected $workspace_id;

    /**
     * @var string
     */
    public $user_agent = 'TogglToJira';

    /**
     * Initialize class
     */
    public function __construct($workspace, $api_token, $options =[])
    {
        $this->username = $api_token;
        $this->password = 'api_token';
        $this->workspace_id = $workspace;
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
        $this->uri = 'https://toggl.com/reports/api/v2/details?user_agent=' . $this->user_agent . '&since=' . $startdate . '&until=' . $enddate . '&workspace_id=' . $this->workspace_id;

        $data = [];
        $data = json_encode($data);

        $response = $this->processRequest($data, self::GET);
        $this->_handleError($data, $response);
        return $response->data;
    }
}
