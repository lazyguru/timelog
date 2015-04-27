<?php namespace Constant\Timelog\Service\Replicon;

use Constant\Timelog\Service\BaseService;
use Symfony\Component\Console\Output\OutputInterface;

class Timesheet extends BaseService
{

    private $id;

    const ACTIVITY_NONE = 0;
    const ACTIVITY_TRAINING = 4;
    const ACTIVITY_VACATION = 6;
    const ACTIVITY_SICK = 7;
    const ACTIVITY_HOLIDAY = 8;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    protected $_timeRows = [];

    /**
     * Initialize class
     * @param OutputInterface $output
     * @param $username
     * @param $password
     * @param array $options
     */
    public function __construct(OutputInterface $output, $username, $password, $options = [])
    {
        $this->output = $output;
        $companyKey = $options['companyKey'];
        $version = isset($options['version']) ? $options['version'] : '8.29.66';
        $this->uri = "https://na1.replicon.com/{$companyKey}/RemoteApi/RemoteApi.ashx/{$version}/";
        $this->username = $username;
        $this->password = $password;

        $this->_headers['Content-Type'] = 'application/json';
        $this->_headers['X-Replicon-Security-Context'] = 'User';
        $this->_timeRows[] = [
            "__operation" => "CollectionClear",
            "Collection" => "TimeRows"
        ];
    }

    public function saveTimesheet()
    {
        $data = [
            "Action" => "Edit",
            "Type" => "Replicon.TimeSheet.Domain.Timesheet",
            "Identity" => (string)$this->getId(),
            "Operations" => $this->_timeRows
        ];
        if (OutputInterface::VERBOSITY_DEBUG <= $this->output->getVerbosity()) {
            $this->output->writeln('<debug>' . print_r($data, true) . '</debug>');
        }
        $data = json_encode($data);
        $response = $this->processRequest($data);
        if ('Exception' == $response->Status) {
            $this->output->writeln('<error>' . $response->Message . '</error>');
        }
        $this->_handleError($data, $response);
    }

    public function createCell($date, $duration, $comment)
    {
        $date = explode('-', $date);
        return [
            "__operation" => "CollectionAdd",
            "Collection" => "Cells",
            "Operations" => [
                [
                    "__operation" => "SetProperties",
                    "CalculationModeObject" => [
                        "__type" => "Replicon.TimeSheet.Domain.CalculationModeObject",
                        "Identity" => "CalculateInOutTime"
                    ]
                ],
                [
                    "__operation" => "SetProperties",
                    "EntryDate" => [
                        "__type" => "Date",
                        "Year" => $date[0],
                        "Month" => $date[1],
                        "Day" => $date[2]
                    ],
                    "Duration" => [
                        "__type" => "Timespan",
                        "Hours" => $duration
                    ],
                    "Comments" => $comment
                ]
            ]
        ];
    }

    public function addTimeRow($cells = [])
    {
        $this->_timeRows[] = [
            "__operation" => "CollectionAdd",
            "Collection" => "TimeRows",
            "Operations" => $cells
        ];
    }

    public function createTask($taskCode)
    {
        $data = [
            'Action' => 'Query',
            'QueryType' => 'TaskByCode',
            'DomainType' => 'Replicon.Project.Domain.Task',
            'Args' => [
                $taskCode
            ]
        ];
        if (OutputInterface::VERBOSITY_DEBUG <= $this->output->getVerbosity()) {
            $this->output->writeln('<debug>' . print_r($data, true) . '</debug>');
        }
        $data = json_encode($data);

        $response = $this->processRequest($data);
        $this->_handleError($data, $response);
        $task = $response->Value[0]->Properties;

        return [
            "__operation" => "SetProperties",
            "Task" => [
                "__type" => "Replicon.Project.Domain.Task",
                "Identity" => (string)$task->Id
            ]
        ];
    }

    public function createActivity($type = ACTIVITY_NONE)
    {
        if ($type == self::ACTIVITY_NONE) {
            return [];
        }

        return [
            "__operation" => "SetProperties",
            "Activity" => [
                "__type" => "Replicon.Domain.Activity",
                "Identity" => (string)$type
            ]
        ];
    }
}