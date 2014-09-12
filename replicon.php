<?php
/**
 * Executes process to get tasks from Toggl and create worklogs in Jira
 *
 * @author Joe Constant <jconstant@alpineinc.com>
 * Date: 7/29/14
 * Time: 10:45 PM
 */
require_once 'vendor/autoload.php';

$rundate = null;
$enddate = null;
if ($argc > 1) {
    $rundate = $argv[1];
    $enddate = $rundate;
}
if ($argc > 2) {
    $enddate = $argv[2];
}

$replicon_config = sfYaml::load('replicon.yaml');
$r = new Replicon(
    $replicon_config['username'],
    $replicon_config['password'],
    [
        'companyKey' => $replicon_config['company']
    ]
);
$t = new \Replicon\Timesheet(
    $replicon_config['username'],
    $replicon_config['password'],
    [
        'companyKey' => $replicon_config['company']
    ]
);
//$r->_debug = true;

// Toggl values
$toggl_config = sfYaml::load('toggl.yaml');
$toggl = new Toggl($toggl_config['workspace_id'], $toggl_config['api_token']);
$toggl->user_agent = $toggl_config['user_agent'];

$entries = $toggl->getTimeEntries($rundate, $enddate);
$user = $r->findUseridByLogin($replicon_config['username']);
$timesheet = $r->getTimesheetByUseridDate($user->Id, $rundate);

$taskCache = [];
$loggedTimes = [];
$toBeLogged = [];
foreach ($entries as $entry) {
    $ticket = $entry->description;
    $date = date('Y-m-d', strtotime($entry->start));
    $client = $entry->client;
    $time = $entry->dur;
    $time = $time / 60 / 1000 / 60;
    $taskCode = filter_var($entry->task, FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION);
    if (strpos($taskCode, '-') !== false) {
        $taskCode = str_replace('-', '', $taskCode);
    }
    $taskCode = $entry->project . $taskCode;
    if (!isset($toBeLogged[$taskCode])) {
        $toBeLogged[$taskCode] = [];
    }
    if (!isset($toBeLogged[$taskCode][$date])) {
        $toBeLogged[$taskCode][$date] = [
            'time'   => 0,
            'ticket' => []
        ];
    }
    $toBeLogged[$taskCode][$date]['time'] += $time;
    $toBeLogged[$taskCode][$date]['ticket'][] = $ticket;
    $loggedTimes[] = "{$client} ticket {$ticket} was logged on {$date} for {$time}h";
}
$t->setId($timesheet);
foreach($toBeLogged as $taskid => $entries) {
    $task = $t->createTask($taskid);
    $cells = [];
    $cells[] = $task;
    foreach($entries as $date => $entry) {
        $cell = $t->createCell($date, $entry['time'], implode(',', $entry['ticket']));
        $cells[] = $cell;
    }
    $t->addTimeRow($cells);
}
$t->saveTimesheet();
exit;
asort($loggedTimes);
echo "******** Created Replicon Entries **********\n";
print_r($loggedTimes);
echo "*****************************************\n";
