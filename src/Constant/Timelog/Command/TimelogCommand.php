<?php namespace Constant\Timelog\Command;

use Constant\Timelog\Service\Jira\JiraService;
use Constant\Timelog\Service\Replicon\RepliconService;
use Constant\Timelog\Service\Replicon\Timesheet;
use Constant\Timelog\Service\Toggl\TimeEntry;
use Constant\Timelog\Service\Toggl\TogglService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Helper\ProgressBar;

class TimelogCommand extends Command
{
    /**
     * @var array
     */
    protected $loggedTimes = [];

    /**
     * @var array
     */
    protected $notLoggedTimes = [];

    /**
     * @var array
     */
    protected $_jiraInstances;

    /**
     * @var ProgressBar
     */
    private $progress;

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $rundate = $input->getArgument('start_date');
        $enddate = $input->getArgument('end_date');
        if (!isset($enddate)) {
            $enddate = $rundate;
        }
        $jira = false;
        $replicon = false;
        if ($input->getOption('all')) {
            $jira = true;
            $replicon = true;
        }
        if ($input->getOption('jira')) {
            $jira = true;
        }
        if ($input->getOption('replicon')) {
            $replicon = true;
        }

        // Toggl values
        $toggl_config = Yaml::parse('toggl.yaml');
        $toggl = new TogglService($output, $toggl_config['workspace_id'], $toggl_config['api_token']);
        $toggl->user_agent = $toggl_config['user_agent'];

        $entries = $toggl->getTimeEntries($rundate, $enddate);

        if (OutputInterface::VERBOSITY_NORMAL >= $output->getVerbosity()) {
            // create a new progress bar
            $this->progress = new ProgressBar($output, count($entries));
        }

        if ($jira) {
            $this->startProgress();
            $this->_processJira($entries, $output);
        }

        if ($replicon) {
            $this->startProgress();
            $this->processReplicon($rundate, $entries, $output);
        }
    }

    /**
     * @param array $entries
     * @param OutputInterface $output
     */
    protected function _processJira($entries, OutputInterface $output)
    {
        // Jira values
        $jiraConfig = Yaml::parse('jira.yaml');

        $clients = $jiraConfig['Clients'];

        $this->_initiailzeJiraInstances($output, $jiraConfig);
        foreach ($entries as $entry) {
            $entry = $this->_processJiraTimeEntry($clients, $entry);
            $this->advanceProgress();
        }
        asort($this->loggedTimes);
        asort($this->notLoggedTimes);
        $this->clearProgress();
        $output->writeln('<info>Created Jira Worklogs...</info>');
        $loggedTable = new Table($output);
        $loggedTable->setHeaders(['Client', 'Ticket', 'Date', 'Duration'])
            ->setRows($this->loggedTimes)
            ->render();
        $output->writeln('<info>Jira Worklogs Not Created...</info>');
        $notLoggedTable = new Table($output);
        $notLoggedTable->setHeaders(['Client', 'Ticket', 'Date', 'Duration'])
            ->setRows($this->notLoggedTimes)
            ->render();
    }

    /**
     * @param OutputInterface $output
     * @param $jiraConfig
     *
     * @return array
     */
    protected function _initiailzeJiraInstances(OutputInterface $output, $jiraConfig)
    {
        $this->_jiraInstances = [];

        foreach ($jiraConfig['Sites'] as $key => $site) {
            $this->_jiraInstances[$key] = new JiraService($output, $site['user'], $site['pass'], ['site' => $site['url']]);
        }

        return $this->_jiraInstances;
    }

    /**
     * @param $clients
     * @param TimeEntry $entry
     * @return \Constant\Timelog\Service\Toggl\TimeEntry
     */
    protected function _processJiraTimeEntry($clients, $entry)
    {
        $description = $entry->getDescription();
        $date = $entry->getEntryDate();
        $time = $entry->getDuration();
        $client = $entry->getClient();
        $ticket = $entry->getTicket();
        if (!isset($clients[$client])) {
            $this->notLoggedTimes[] = [
                'client' => $client,
                'ticket' => $ticket,
                'date' => $date,
                'duration' => "{$time}h",
            ];
            return $entry;
        }
        if (!$entry->isLogged()) {
            $clientSite = $clients[$client]['site'];
            $this->getJiraInstance($clientSite)->setWorklog($date, $ticket, $time, $description);
            $entry->addTag('Jira'); // Mark as logged so we don't log it again in the future
            $entry = $entry->save();
        }
        $this->loggedTimes[] = [
            'client' => $client,
            'ticket' => $ticket,
            'date' => $date,
            'duration' => "{$time}h",
        ];
        return $entry;
    }

    /**
     * @param $clientSite
     *
     * @return JiraService|null
     */
    private function getJiraInstance($clientSite)
    {
        if (isset($this->_jiraInstances[$clientSite])) {
            return $this->_jiraInstances[$clientSite];
        }
        return null;
    }

    protected function configure()
    {
        $this
            ->setName('process')
            ->setDescription('Converts time log entries from Toggl to Jira and/or Replicon')
            ->addArgument(
                'start_date',
                InputArgument::REQUIRED,
                'The starting date.'
            )
            ->addArgument(
                'end_date',
                InputArgument::OPTIONAL,
                'The ending date.'
            )
            ->addOption(
                'jira',
                'j',
                InputOption::VALUE_NONE,
                'If set, Jira Worklogs will be created'
            )
            ->addOption(
                'replicon',
                'r',
                InputOption::VALUE_NONE,
                'If set, Replicon will be updated'
            )
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'If set, both Replicon and Jira will be updated'
            );

        /*
        // green text
        $output->writeln('<info>foo</info>');

        // yellow text
        $output->writeln('<comment>foo</comment>');

        // black text on a cyan background
        $output->writeln('<question>foo</question>');

        // white text on a red background
        $output->writeln('<error>foo</error>');
        */
    }

    /**
     * @param $rundate
     * @param $entries
     * @param OutputInterface $output
     */
    protected function processReplicon($rundate, $entries, OutputInterface $output)
    {
        $replicon_config = Yaml::parse('replicon.yaml');
        $r = new RepliconService(
            $output,
            $replicon_config['username'],
            $replicon_config['password'],
            [
                'companyKey' => $replicon_config['company']
            ]
        );
        $t = new Timesheet(
            $output,
            $replicon_config['username'],
            $replicon_config['password'],
            [
                'companyKey' => $replicon_config['company']
            ]
        );

        $user = $r->findUseridByLogin($replicon_config['username']);
        $timesheet = $r->getTimesheetByUseridDate($user->Id, $rundate);

        $taskCache = [];
        $loggedTimes = [];
        $toBeLogged = [];
        foreach ($entries as $entry) {
            $taskid = $entry->getTask();
            $timeoff = 0;
            if (empty($taskid)) {
                $taskid = $entry->getTags()[0];
                $timeoff = 1;
            }
            if (!isset($toBeLogged[$taskid])) {
                $toBeLogged[$taskid] = [];
            }
            if (!isset($toBeLogged[$taskid][$entry->getEntryDate()])) {
                $toBeLogged[$taskid][$entry->getEntryDate()] = [
                    'time' => 0,
                    'ticket' => []
                ];
            }
            $toBeLogged[$taskid][$entry->getEntryDate()]['time'] += $entry->getDuration();
            $ticket = $entry->getTicket();
            if (empty($ticket)) {
                $ticket = $entry->getDescription();
            }
            $toBeLogged[$taskid][$entry->getEntryDate()]['ticket'][] = $ticket;
            $toBeLogged[$taskid][$entry->getEntryDate()]['tags'][] = $entry->getTags();
            $toBeLogged[$taskid][$entry->getEntryDate()]['timeoff'] = $timeoff;

            $loggedTimes[] = [
                'Client' => $entry->getClient(),
                'Description' => $entry->getDescription(),
                'Date' => $entry->getEntryDate(),
                'Duration' => $entry->getDuration() . 'h'
            ];
        }
        $t->setId($timesheet);
        foreach ($toBeLogged as $taskid => $entries) {
            $task = $this->_getTask($t, $taskid, $entries);
            $cells = [];
            $cells[] = $task;
            foreach ($entries as $date => $entry) {
                $cell = $t->createCell($date, $entry['time'], implode(',', $entry['ticket']));
                $cells[] = $cell;
            }
            $t->addTimeRow($cells);
            $this->advanceProgress();
        }
        $t->saveTimesheet();
        asort($loggedTimes);
        $this->clearProgress();
        $output->writeln('<info>Created Replicon Entries...</info>');
        $loggedTable = new Table($output);
        $loggedTable->setHeaders(['Client', 'Description', 'Date', 'Duration'])
            ->setRows($loggedTimes)
            ->render();
    }

    private function _getTask(Timesheet $t, $taskid, $entries)
    {
        $entry = array_shift($entries);
        if ($entry['timeoff'] == 1) {
            return $this->_createActivity($t, $taskid);
        }
        return $t->createTask($taskid);
    }

    private function _createActivity(Timesheet $t, $type)
    {
        switch($type){
            case 'Vacation':
                return $t->createActivity(Timesheet::ACTIVITY_VACATION);
            case 'Sick':
                return $t->createActivity(Timesheet::ACTIVITY_SICK);
            case 'Holiday':
                return $t->createActivity(Timesheet::ACTIVITY_HOLIDAY);
            default:
                throw new \Exception('Invalid time-off type: ' . $type);
        }
    }

    private function startProgress()
    {
        if ($this->progress) {
            $this->progress->start();
        }
    }
    private function finishProgress()
    {
        if ($this->progress) {
            $this->progress->finish();
        }
    }
    private function advanceProgress()
    {
        if ($this->progress) {
            $this->progress->advance();
        }
    }
    private function clearProgress()
    {
        if ($this->progress) {
            $this->progress->clear();
        }
    }
}