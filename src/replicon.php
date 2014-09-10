<?php

use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Log\LogSubscriber;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Replicon
{
    private $companyKey = '';
    private $uri        = '';
    private $username   = '';
    private $password   = '';
    public  $_debug     = false;

    protected $_errorno;

    protected $_error;

    protected $_status;

    public function __construct($companyKey, $username, $password, $version = '8.29.66')
    {
        $this->companyKey = $companyKey;
        $this->uri = "https://na1.replicon.com/{$companyKey}/RemoteApi/RemoteApi.ashx/{$version}/";
        $this->username = $username;
        $this->password = $password;
    }

    private function submitPost($data)
    {

        try {
            $client = new GuzzleHttp\Client();

            // create a log channel
            $log = new Logger('Replicon');
            $log->pushHandler(new StreamHandler('replicon.log', Logger::DEBUG));

            $subscriber = new LogSubscriber($log);
            $client->getEmitter()->attach($subscriber);

            $response = $client->post($this->uri, [
                'auth'    => [$this->username, $this->password],
                'headers' => [
                    'X-Replicon-Security-Context' => 'User',
                    'Content-Type'                => 'application/json'
                ],
                'body'    => $data
            ]);

            return json_decode($response->getBody());
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            echo $e->getRequest();
            if ($e->hasResponse()) {
                echo $e->getResponse();
            }
        }
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
        $data = json_encode($data);

        $response = $this->submitPost($data);
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
        $data = json_encode($data);

        $response = $this->submitPost($data);
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
        $data = json_encode($data);

        $response = $this->submitPost($data);
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

        $data = json_encode($data);

        $response = $this->submitPost($data);
        $this->_handleError($data, $response);
        //return $response->Value[0]->Properties;
    }

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

}