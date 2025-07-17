<?php

App::uses('AppController', 'Controller');

/**
 * @property Task $Task
 * @property Server $Server
 * @property Feed $Feed
 * @property Workflow $Workflow
 */

class TasksController extends AppController
{
    public $components = [
        'CRUD',
        'RequestHandler'
    ];

    public $paginate = [
        'limit' => 60,
        'recursive' => -1,
        'order' => [
            'Task.id' => 'ASC',
        ]
    ];

    public function index()
    {
        if (!$this->_isSiteAdmin()) {
            throw new MethodNotAllowedException('You are not authorised to do that.');
        }

        $this->CRUD->index([
            'contain' => ['User.id', 'User.email'],
        ]);
        if ($this->IndexFilter->isRest()) {
            return $this->restResponsePayload;
        }
    }

    public function toggleEnabled($id)
    {
        if (!$this->_isSiteAdmin()) {
            throw new MethodNotAllowedException('You are not authorised to do that.');
        }

        $task = $this->Task->find('first', array(
            'recursive' => -1,
            'conditions' => array('Task.id' => $id)
        ));
        if (empty($task)) {
            return $this->RestResponse->saveFailResponse('Task', 'toggleEnabled', $id, 'Invalid Task', $this->response->type());
        }
        if ($this->request->is('post')) {
            $task['Task']['enabled'] = !$task['Task']['enabled'];
            $result = $this->Task->save($task);
            if ($result) {
                return $this->RestResponse->saveSuccessResponse('Task', 'toggleEnabled', $id, $this->response->type());
            } else {
                return $this->RestResponse->saveFailResponse('Task', 'toggleEnabled', $id, $this->validationError, $this->response->type());
            }
        }

        $this->set('enabled', !$task['Task']['enabled']);
        $this->set('id', $id);
        $this->autoRender = false;
        $this->layout = false;
        $this->render('ajax/toggle_enabled');
    }

    public function add()
    {
        if (!$this->_isSiteAdmin()) {
            throw new MethodNotAllowedException('You are not authorised to do that.');
        }

        $this->set('dropdownData', $this->getDropdownData());

        if ($this->request->is('post')) {

            $this->request->data['Task']['message'] = '';
            $this->request->data = $this->massageFormInput($this->request->data);

            $this->CRUD->add();
            if ($this->restResponsePayload) {
                return $this->restResponsePayload;
            }
        }

        $this->set('menuData', [
            'menuList' => 'admin',
            'menuItem' => 'tasks',
        ]);
    }

    public function edit($id)
    {
        if (!$this->_isSiteAdmin()) {
            throw new MethodNotAllowedException('You are not authorised to do that.');
        }

        $this->set('dropdownData', $this->getDropdownData());

        if ($this->request->is('put')) {
            $this->request->data = $this->massageFormInput($this->request->data);
            $this->CRUD->edit($id);
            if ($this->restResponsePayload) {
                return $this->restResponsePayload;
            }
        } else {
            $this->CRUD->edit($id, [
                'afterFind' => function (array $task) {
                    if (isset($task['Task']['params'])) {
                        $params = explode(',', $task['Task']['params']);
                        if ($task['Task']['type'] === 'Server') {
                            $task['Task']['server_id'] = $params[0];
                            $task['Task']['server_technique'] = $params[1];
                        } elseif ($task['Task']['type'] === 'Feed') {
                            if($task['Task']['action'] === 'fetch') {
                                $task['Task']['feed_id'] = $params[0];
                            } elseif ($task['Task']['action'] === 'cache') {
                                if (count($params) < 2) {
                                    $this->Flash->error(__('Invalid feed parameters.'));
                                    return;
                                }
                                $task['Task']['feed_id'] = $params[0];
                                $task['Task']['feed_scope'] = $params[1];
                            }
                        }
                    }

                    if (isset($task['Task']['next_execution_time'])) {
                        $task['Task']['next_execution_date'] = date('Y-m-d', $task['Task']['next_execution_time']);
                        $task['Task']['next_execution_time'] = date('H:i:s', $task['Task']['next_execution_time']);
                    } else {
                        $task['Task']['next_execution_date'] = '';
                        $task['Task']['next_execution_time'] = '';
                    }
                    return $task;
                },
                'fields' => ['type', 'action', 'params', 'timer', 'next_execution_time', 'enabled'],
                'contain' => ['User.id']
            ]);

            if ($this->restResponsePayload) {
                return $this->restResponsePayload;
            }

            $this->set('edit', true);
            $this->set('menuData', [
                'menuList' => 'admin',
                'menuItem' => 'tasks',
            ]);
            $this->render('add');
        }
    }

    public function delete($id)
    {
        if (!$this->_isSiteAdmin()) {
            throw new MethodNotAllowedException('You are not authorised to do that.');
        }
        $this->CRUD->delete($id);
        if ($this->IndexFilter->isRest()) {
            return $this->restResponsePayload;
        }
    }

    private function getDropdownData()
    {
        $this->Server = ClassRegistry::init('Server');
        $this->Feed = ClassRegistry::init('Feed');
        $this->Workflow = ClassRegistry::init('Workflow');

        $workflows = $this->Workflow->find('all', [
            'order' => ['Workflow.name' => 'ASC'],
        ]);

        // Filter enabled workflows to only include those that have ad-hoc triggers
        $dropdownWorkflows = [];
        foreach ($workflows as $workflow) {
            if ($workflow['Workflow']['enabled']) {
                foreach ($workflow['Workflow']['listening_triggers'] as $listeningTrigger) {
                    if ($listeningTrigger['is_adhoc']) {
                        $dropdownWorkflows[$workflow['Workflow']['id']] = $workflow['Workflow']['name'];
                        break;
                    }
                }
            }
        }

        $dropdownData = [
            'users' => $this->User->find('list', [
                'fields' => ['User.id', 'User.email'],
                'conditions' => ['User.disabled' => 0],
                'order' => ['User.email' => 'ASC']
            ]),
            'servers' =>  ['all' => __('All Servers')] + $this->Server->find('list', [
                'fields' => ['Server.id', 'Server.name'],
                'order' => ['Server.name' => 'ASC']
            ]),
            'feeds' => ['all' => __('All Feeds')] + $this->Feed->find('list', [
                'fields' => ['Feed.id', 'Feed.name'],
                'order' => ['Feed.name' => 'ASC']
            ]),
            'workflows' => $dropdownWorkflows,
        ];

        return $dropdownData;
    }

    private function massageFormInput(array $data)
    {
        if ($data['Task']['type'] === 'Server') {
            $data['Task']['action'] = $data['Task']['server_action'];
            $data['Task']['params'] = implode(
                ',',
                [
                    $data['Task']['server_id'],
                    $data['Task']['server_technique']
                ]
            );
        } elseif ($data['Task']['type'] === 'Feed') {
            $data['Task']['action'] = $data['Task']['feed_action'];

            if ($data['Task']['feed_action'] === 'fetch') {
                if (!isset($data['Task']['feed_id']) || empty($data['Task']['feed_id'])) {
                    $this->Flash->error(__('Please select a feed.'));
                    return;
                }
                $data['Task']['params'] = $data['Task']['feed_id'];
            } elseif ($data['Task']['feed_action'] === 'cache') {
                if (!isset($data['Task']['feed_scope']) || empty($data['Task']['feed_scope'])) {
                    $this->Flash->error(__('Please select a feed scope.'));
                    return;
                }
                $data['Task']['params'] = implode(
                    ',',
                    [
                        $data['Task']['feed_id'],
                        $data['Task']['feed_scope'],
                    ]
                );
            } else {
                $this->Flash->error(__('Invalid action for Feed'));
                return;
            }
        } elseif ($data['Task']['type'] === 'Workflow') {
            $data['Task']['action'] = 'execute';

            if (!isset($data['Task']['workflow']) || empty($data['Task']['workflow'])) {
                $this->Flash->error(__('Please select a workflow.'));
                return;
            }
        } else {
            $this->Flash->error(__('Invalid type'));
            return;
        }

        $data['Task']['timer'] = $data['Task']['time_multiplier'] * $data['Task']['time_unit'];

        if ($data['Task']['timer'] < 60) {
            $this->Flash->error(__('Invalid timer value, must be at least 60 seconds or 1 minute.'));
            return;
        }

        if ($data['Task']['next_execution_date']) {
            $time = $data['Task']['next_execution_time'] == "" ? '00:00:00' : $data['Task']['next_execution_time'];
            $data['Task']['next_execution_time'] = strtotime($data['Task']['next_execution_date'] . ' ' . $time);
        } else {
            $data['Task']['next_execution_time'] = time() - 1;
        }

        return $data;
    }
}
