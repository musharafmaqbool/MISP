<?php

declare(strict_types=1);

App::uses('AppShell', 'Console/Command');
App::uses('Worker', 'Tools/BackgroundJobs');

class SchedulerWorkerShell extends AppShell
{
    public $uses = ['Task', 'Feed', 'Server', 'Job', 'User'];

    /** @var Worker */
    private $worker;

    public function main()
    {
        $pid = getmypid();
        if ($pid === false) {
            throw new RuntimeException("Could not get current process ID");
        }

        $this->worker = new Worker(
            [
                'pid' => $pid,
                'queue' => 'scheduler',
                'user' => ProcessTool::whoami(),
            ]
        );

        CakeLog::info("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - starting task scheduler...");

        while (true) {
            $now = time();

            try {
                $tasks = $this->Task->find('all', [
                    'conditions' => [
                        'next_execution_time <=' => $now
                    ]
                ]);
            } catch (Exception $e) {
                CakeLog::error("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - failed to fetch tasks: " . $e->getMessage());
                sleep(10);
                continue;
            }

            foreach ($tasks as $task) {
                $task = $task['Task'];
                try {
                    $this->processTask($task);
                } catch (Exception $e) {
                    CakeLog::error("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - failed to process task ID {$task['id']}: " . $e->getMessage());
                }
            }

            sleep(10);
        }
    }

    private function processTask(array $task)
    {
        CakeLog::info("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - processing task: {$task['type']}");

        [$model,  $action, $params] = explode(':', $task['type']);

        if ($task['process_id']) {

            $job = $this->Job->read(null, $task['process_id']);

            if ($job['Job']['status'] === Job::STATUS_RUNNING) {
                CakeLog::info("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - job is already running for this task: {$task['process_id']}");
                return;
            }
        }

        $this->setNextExecutionTime($task);

        if ($model == 'Server') {
            $this->runServerTask($task, $action, $params);
        } elseif ($model == 'Feed') {
            if ($action === 'fetch') {
                $this->runFeedFetchTask($task, $action, $params);
            } elseif ($action === 'cache') {
                $this->runFeedCacheTask($task, $action, $params);
            } else {
                CakeLog::error("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - unknown action for Feed: {$action}");
                return;
            }
        } else {
            CakeLog::error("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - unknown model: {$model}");
            return;
        }
    }

    private function setNextExecutionTime(array $task)
    {
        $previous = (int)$task['next_execution_time'];
        $interval = (int)$task['timer'];
        $now = time();

        $missed = max(1, ceil(($now - $previous) / $interval));
        $next = $previous + $missed * $interval;

        $task['next_execution_time'] = $next;

        try {
            $this->Task->id = $task['id'];
            $this->Task->saveField('next_execution_time', $task['next_execution_time']);
        } catch (Exception $e) {
            CakeLog::error("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - failed to save next execution time for Task ID: {$task['id']}. Error: " . $e->getMessage());
            return;
        }
    }

    private function runServerTask($task, $action, $params)
    {
        if (!in_array($action, ['pull', 'push'], true)) {
            CakeLog::error("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - unknown action: {$action}");
        }

        [$userId, $serverId, $technique] = explode(',', $params);

        if (!is_numeric($userId)) {
            CakeLog::error("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - invalid parameters: expected numeric userId.");
        }

        if (!is_numeric($serverId) || $serverId === 'all') {
            CakeLog::error("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - invalid parameters: expected numeric serverId or `all`.");
        }

        if (!in_array($technique, ['full', 'incremental', 'pull_relevant_clusters'], true)) {
            CakeLog::error("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - invalid parameters: expected technique to be 'pull' or 'push'.");
        }

        $user = $this->User->getAuthUser($userId);
        if (empty($user)) {
            CakeLog::error("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - user ID do not match an existing user.");
        }

        $jobId = $this->Job->createJob($user, Job::WORKER_DEFAULT, $action, "Server: $serverId",  ucfirst($action . 'ing.'));
        $this->getBackgroundJobsTool()->enqueue(
            BackgroundJobsTool::DEFAULT_QUEUE,
            BackgroundJobsTool::CMD_SERVER,
            [
                $action,
                $user['id'],
                $serverId,
                $technique,
                $jobId,
            ],
            true,
            $jobId
        );

        $this->Task->id = $task['id'];
        $this->Task->saveField('process_id', $jobId);

        CakeLog::info("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - enqueued {$action} for Server ID: {$serverId} with Task ID: {$task['id']}");
    }

    private function runFeedFetchTask($task, $action, $params)
    {
        [$userId, $feedId] = explode(',', $params);

        if (!is_numeric($userId)) {
            CakeLog::error("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - invalid parameters: expected numeric userId");
        }

        if (!is_numeric($feedId) || $feedId === 'all') {
            CakeLog::error("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - invalid parameters: expected numeric feedId or `all`.");
        }

        $user = $this->User->getAuthUser($userId);
        if (empty($user)) {
            CakeLog::error("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - user ID do not match an existing user.");
        }

        $jobId = $this->Job->createJob(
            $user,
            Job::WORKER_DEFAULT,
            'fetch_feeds',
            'Feed: ' . $feedId,
            __('Starting fetch from Feed.')
        );

        $this->Feed->getBackgroundJobsTool()->enqueue(
            BackgroundJobsTool::DEFAULT_QUEUE,
            BackgroundJobsTool::CMD_SERVER,
            [
                'fetchFeed',
                $user['id'],
                $feedId,
                $jobId
            ],
            true,
            $jobId
        );

        $this->Task->id = $task['id'];
        $this->Task->saveField('process_id', $jobId);

        CakeLog::info("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - enqueued {$action} for Feed ID: {$feedId} with Task ID: {$task['id']}");
    }

    private function runFeedCacheTask($task, $action, $params)
    {
        [$userId, $scope] = explode(',', $params);

        if (!is_numeric($userId)) {
            CakeLog::error("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - invalid parameters: expected numeric userId");
        }

        if (!in_array($scope, ['freetext', 'csv', 'misp', 'all'], true)) {
            CakeLog::error("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - invalid parameters: expected scope to be 'freetext', 'csv', 'misp' or 'all'.");
        }

        $user = $this->User->getAuthUser($userId);
        if (empty($user)) {
            CakeLog::error('User ID do not match an existing user.');
        }

        $jobId = $this->Job->createJob(
            $user,
            Job::WORKER_DEFAULT,
            'cache_feeds',
            $scope,
            __('Starting feed caching.')
        );

        $this->Feed->getBackgroundJobsTool()->enqueue(
            BackgroundJobsTool::DEFAULT_QUEUE,
            BackgroundJobsTool::CMD_SERVER,
            [
                'cacheFeed',
                $user['id'],
                $scope,
                $jobId
            ],
            true,
            $jobId
        );

        $this->Task->id = $task['id'];
        $this->Task->saveField('process_id', $jobId);

        CakeLog::info("[WORKER PID: {$this->worker->pid()}][{$this->worker->queue()}] - enqueued feed cache with scope {$scope} with Task ID: {$task['id']}");
    }
}
