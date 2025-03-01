<?php

namespace mowzs\lib\task\event;

use mowzs\lib\task\Task;

abstract class TaskEvent
{
    public string|Task $task;
    /**
     * @var mixed|null
     */
    protected mixed $task_output;

    public function __construct(Task|string $task, $task_output = null)
    {
        $this->task = $task;
        $this->task_output = $task_output;
    }

    /**
     * @return false|Task|string
     */
    public function getName(): Task|bool|string
    {
        if (is_string($this->task)) {
            return $this->task;
        }
        return get_class($this->task);
    }

    /**
     *
     * @return mixed|null
     */
    public function getTaskOutput(): mixed
    {
        return $this->task_output;
    }
}
