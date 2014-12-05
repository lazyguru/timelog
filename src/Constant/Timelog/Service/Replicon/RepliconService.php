<?php namespace Constant\Timelog\Service\Replicon;

use Constant\Timelog\Service\BaseService;
use Symfony\Component\Console\Output\OutputInterface;

class RepliconService extends BaseService
{
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

        $this->_headers['X-Replicon-Security-Context'] = 'User';
    }

    public function getTimesheetByUseridDate($userid, $date)
    {
        if (empty($date)) {
            $date = date('Y-m-d', time() - 86400);
        }
        $date = explode('-', $date);
        $data = [
            'Action'     => 'Query',
            'QueryType'  => 'EntryTimesheetByUserDate',
            'DomainType' => 'Replicon.Suite.Domain.EntryTimesheet',
            'Args'       => [
                [
                    '__type'   => 'Replicon.Domain.User',
                    'Identity' => (string)$userid
                ],
                [
                    "__type" => "Date",
                    "Year"   => $date[0],
                    "Month"  => $date[1],
                    "Day"    => $date[2]
                ]
            ]
        ];
        $this->output->writeln('<debug>' . print_r($data, true) . '</debug>');
        $data = json_encode($data);

        $response = $this->processRequest($data);
        $this->_handleError($data, $response);
        return $response->Value[0]->Identity;
    }

    public function getTaskByCode($code)
    {
        $data = array(
            'Action'     => 'Query',
            'QueryType'  => 'TaskByCode',
            'DomainType' => 'Replicon.Project.Domain.Task',
            'Args'       => array(
                $code
            )
        );
        $this->output->writeln('<debug>' . print_r($data, true) . '</debug>');
        $data = json_encode($data);

        $response = $this->processRequest($data);
        $this->_handleError($data, $response);
        return $response->Value[0]->Properties;
    }

    public function findUseridByLogin($username)
    {
        $data = array(
            'Action'     => 'Query',
            'QueryType'  => 'UserByLoginName',
            'DomainType' => 'Replicon.Domain.User',
            'Args'       => array(
                $username
            )
        );
        $this->output->writeln('<debug>' . print_r($data, true) . '</debug>');
        $data = json_encode($data);

        $response = $this->processRequest($data);
        $this->_handleError($data, $response);
        return $response->Value[0]->Properties;
    }

    public function addTimeEntry($timesheet, $date, $code, $duration, $comment)
    {
        $date = explode('-', $date);
        $data = [
            "Action"     => "Edit",
            "Type"       => "Replicon.Suite.Domain.EntryTimesheet",
            "Identity"   => (string)$timesheet,
            "Operations" => [
                [
                    "__operation" => "CollectionAdd",
                    "Collection"  => "TimeEntries",
                    "Operations"  => [
                        [
                            "__operation"           => "SetProperties",
                            "CalculationModeObject" => [
                                "Type"       => "Replicon.TimeSheet.Domain.CalculationModeObject",
                                "Identity"   => "CalculateInOutTime",
                                "Properties" => [
                                    "Name" => "CalculationModeObject_CalculateInOutTime"
                                ]
                            ],
                            "EntryDate"             => [
                                "__type" => "Date",
                                "Year"   => $date[0],
                                "Month"  => $date[1],
                                "Day"    => $date[2]
                            ],
                            "Duration"              => [
                                "__type" => "Timespan",
                                "Hours"  => $duration
                            ],
                            "Comments"              => $comment,
                            "Task"                  => [
                                "Identity" => (string)$code
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->output->writeln('<debug>' . print_r($data, true) . '</debug>');
        $data = json_encode($data);

        $response = $this->processRequest($data);
        $this->_handleError($data, $response);
    }

}