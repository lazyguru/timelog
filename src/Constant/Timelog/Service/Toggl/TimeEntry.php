<?php namespace Constant\Timelog\Service\Toggl;

class TimeEntry
{
    protected $id = 0;
    protected $ticket;
    protected $description;
    protected $entry_date;
    protected $client;
    protected $project;
    protected $tags = [];
    protected $task;
    protected $logged = false;
    protected $billable = false;
    protected $duration = 0;

    public function __construct(TogglService $service, $id, $client, $project, $description)
    {
        $this->service = $service;
        $this->id = $id;
        $this->client = $client;
        $this->project = $project;
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getTask()
    {
        return $this->task;
    }

    /**
     * @param string $task
     */
    public function setTask($task)
    {
        $taskCode = filter_var($task, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        if (strpos($taskCode, '-') !== false) {
            $taskCode = str_replace('-', '', $taskCode);
        }
        $taskCode = $this->project . $taskCode;
        $this->task = $taskCode;
    }

    /**
     * @return string
     */
    public function getTicket()
    {
        return $this->ticket;
    }

    /**
     * @param string $ticket
     */
    public function setTicket($ticket)
    {
        $this->ticket = $ticket;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param string $client
     */
    public function setClient($client)
    {
        $this->client = $client;
    }

    /**
     * @return string
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * @param string $project
     */
    public function setProject($project)
    {
        $this->project = $project;
    }

    /**
     * @return array
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @param array $tags
     */
    public function setTags($tags)
    {
        $this->tags = $tags;
    }

    /**
     * @param int $dur
     */
    public function setDurationTime($dur)
    {
        $this->duration = $dur / 60 / 1000 / 60;
    }

    /**
     * @return int
     */
    public function getDurationTime()
    {
        return $this->duration * 60 * 60;
    }

    /**
     * @return boolean
     */
    public function isLogged()
    {
        return $this->logged;
    }

    /**
     * @param boolean $logged
     */
    public function setLogged($logged)
    {
        $this->logged = $logged;
    }

    /**
     * @return boolean
     */
    public function isBillable()
    {
        return $this->billable;
    }

    /**
     * @param boolean $billable
     */
    public function setBillable($billable)
    {
        $this->billable = $billable;
    }

    /**
     * @return int
     */
    public function getDuration()
    {
        return $this->duration;
    }

    /**
     * @param int $duration
     */
    public function setDuration($duration)
    {
        $this->duration = $duration;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $tag
     */
    public function addTag($tag)
    {
        $this->tags[] = $tag;
        $this->processTags();
    }

    public function processTags($tags = null)
    {
        if (empty($tags)) {
            $tags = $this->tags;
        }
        foreach ($tags as $tag) {
            $this->tags[] = $tag;
            if (preg_match('/[A-Z]+\-[\d]+/', $tag)) {
                $this->setTicket($tag);
                continue;
            }
            if ($tag == 'Jira') {
                $this->setLogged(true);
            }
        }
        $this->tags = array_unique($this->tags);
    }

    /**
     * @return TimeEntry
     */
    public function save()
    {
        return $this->service->saveTimeEntry($this);
    }

    /**
     * @return mixed
     */
    public function getEntryDate()
    {
        return $this->entry_date;
    }

    /**
     * @param mixed $entry_date
     */
    public function setEntryDate($entry_date)
    {
        $this->entry_date = $entry_date;
    }
}