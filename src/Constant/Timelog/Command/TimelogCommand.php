<?php namespace Constant\Timelog\Command;

use Constant\Timelog\Service\Replicon\Timesheet;
use sfYaml;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Constant\Timelog\Service\Jira\JiraService;
use Constant\Timelog\Service\Toggl\TogglService;
use Constant\Timelog\Service\Replicon\RepliconService;

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
    protected $logger;
    protected $_jiraInstances;

    protected function configure()
    {
        $this
            ->setName('timelog:process')
            ->setDescription('Process Toggl Time Entries')
            ->addArgument(
                'start_date',
                InputArgument::REQUIRED,
                'What is the starting date?'
            )
            ->addArgument(
                'end_date',
                InputArgument::OPTIONAL,
                'What is the ending date?'
            )
            ->addOption(
                'jira',
                null,
                InputOption::VALUE_NONE,
                'If set, Jira Worklogs will be created'
            )
            ->addOption(
                'replicon',
                null,
                InputOption::VALUE_NONE,
                'If set, Replicon will be updated'
            )
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                'If set, both Replicon and Jira will be updated'
            )
        ;

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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger = new ConsoleLogger($output);

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
        $toggl_config = sfYaml::load('toggl.yaml');
        $toggl = new TogglService($toggl_config['workspace_id'], $toggl_config['api_token']);
        $toggl->user_agent = $toggl_config['user_agent'];

        $entries = $toggl->getTimeEntries($rundate, $enddate);

        if ($jira) {
            $this->_processJira($entries);
        }

        if ($replicon) {
            $replicon_config = sfYaml::load('replicon.yaml');
            $r = new RepliconService(
                $replicon_config['username'],
                $replicon_config['password'],
                [
                    'companyKey' => $replicon_config['company']
                ]
            );
            $t = new Timesheet(
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
            asort($loggedTimes);
            $this->logger->debug("******** Created Replicon Entries **********");
            $this->logger->debug(print_r($loggedTimes, true));
            $this->logger->debug("*****************************************");
        }
    }

    /**
     * @param $jiraConfig
     *
     * @return array
     */
    protected function _initiailzeJiraInstances($jiraConfig)
    {
        $this->_jiraInstances = [];

        foreach($jiraConfig['Sites'] as $key => $site) {
            $this->_jiraInstances[$key] = new JiraService($site['user'], $site['pass'], ['site' => $site['url']]);
        }

        return $this->_jiraInstances;
    }

    /**
     * @param $entry
     */
    protected function _processJiraTimeEntry($clients, $entry)
    {
        $ticket = $entry->description;
        $date   = date('Y-m-d', strtotime($entry->start));
        $time   = $entry->dur;
        $time   = $time / 60 / 1000 / 60;
        $client = $entry->client;
        if (!isset($clients[$client])) {
            $this->notLoggedTimes[] = "{$client} task {$ticket} was NOT logged on {$date} for {$time}h";
            return;
        }
        $clientSite = $clients[$client]['site'];
        $this->getJiraInstance($clientSite)->setWorklog($date, $ticket, $time);
        $this->loggedTimes[] = "{$client} ticket {$ticket} was logged on {$date} for {$time}h";
    }

    /**
     * @param $entries
     */
    protected function _processJira($entries)
    {
        // Jira values
        $jiraConfig = sfYaml::load('jira.yaml');

        $clients = $jiraConfig['Clients'];

        $this->_initiailzeJiraInstances($jiraConfig);
        foreach ($entries as $entry) {
            $this->_processJiraTimeEntry($clients, $entry);
        }
        asort($this->loggedTimes);
        asort($this->notLoggedTimes);
        $this->logger->info("******** Created Jira Worklogs **********");
        $this->logger->info(print_r($this->loggedTimes, true));
        $this->logger->info("******* No Jira Worklogs Created ********");
        $this->logger->info(print_r($this->notLoggedTimes, true));
        $this->logger->info("*****************************************");
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
}