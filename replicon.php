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
$r = new Replicon($replicon_config['company'], $replicon_config['username'], $replicon_config['password']);
$r->_debug = true;

// Toggl values
$toggl_config = sfYaml::load('toggl.yaml');
$toggl = new Toggl($toggl_config['workspace_id'], $toggl_config['api_token']);
$toggl->user_agent = $toggl_config['user_agent'];

$entries = $toggl->getTimeEntries($rundate, $enddate);
$user = $r->findUseridByLogin($replicon_config['username']);
$timesheet = $r->getTimesheetByUseridDate($user->Id, $rundate);

$taskCache = [];
$loggedTimes = [];
foreach ($entries as $entry) {
    $ticket = $entry->description;
    $date = date('Y-m-d', strtotime($entry->start));
    $client = $entry->client;
    $time = $entry->dur;
    $time = $time / 60 / 1000 / 60;

    $taskCode = $entry->project . filter_var($entry->task, FILTER_SANITIZE_NUMBER_FLOAT);
    if (!isset($taskCache[$taskCode])) {
        $taskCache[$taskCode] = $r->getTaskByCode($taskCode);
    }
    $task = $taskCache[$taskCode];
    $r->addTimeEntry($timesheet, $date, $task->Id, $time, (string)abs(filter_var($ticket, FILTER_SANITIZE_NUMBER_INT)));
    $loggedTimes[] = "{$client} ticket {$ticket} was logged on {$date} for {$time}h";
}

asort($loggedTimes);
echo "******** Created Replicon Entries **********\n";
print_r($loggedTimes);
echo "*****************************************\n";
