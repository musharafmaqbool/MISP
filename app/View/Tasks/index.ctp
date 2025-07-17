<?php
echo sprintf('<div%s>', empty($ajax) ? ' class="index"' : '');

if (!$schedulerEnabled){
    echo '<div class="alert alert-danger">';
    echo __('The task scheduler is not enabled. To enable it please add the missing `scheduler` program configuration to your supervisor configuration file.');
    echo '<br>';
    echo __('You can find the configuration file in %s.', '<code>build/supervisor/50-workers.conf</code>');
    echo '<br>';
    echo __('For more information, please refer to the %s.', '<a href="https://github.com/MISP/MISP/wiki/Supervisor-Task-Scheduler-Guide-(2.5)">MISP documentation</a>');
    echo '</div>';
}
$fields = [
    [
        'name' => '#',
        'sort' => 'Task.id',
        'data_path' => 'Task.id',
    ],
    [
        'name' => __('Type'),
        'sort' => 'Task.type',
        'data_path' => 'Task.type',
    ],
    [
        'name' => __('Action'),
        'sort' => 'Task.action',
        'data_path' => 'Task.action',
    ],
        [
        'name' => __('Parameters'),
        'sort' => 'Task.params',
        'data_path' => 'Task.params',
    ],
    [
        'name' => __('User'),
        'element' => 'links',
        'url' => $baseurl . '/users/view',
        'url_params_data_paths' => ['User.id'],
        'sort' => 'User.email   ',
        'data_path' => 'User.email',
    ],
    [
        'name' => __('Timer (s)'),
        'sort' => 'Task.timer',
        'data_path' => 'Task.timer',
        'element' => 'custom',
        'function' => function (array $row) {
            $seconds = $row['Task']['timer'];

            if ($seconds <= 0) return "invalid interval";

            $units = [
                86400 => 'day',
                3600  => 'hour',
                60    => 'minute',
                1     => 'second'
            ];

            $parts = [];

            foreach ($units as $unitSeconds => $unitName) {
                if ($seconds >= $unitSeconds) {
                    $value = intdiv($seconds, $unitSeconds);
                    $seconds %= $unitSeconds;
                    $parts[] = "$value $unitName" . ($value > 1 ? 's' : '');
                }
            }

            return h('every ' . implode(' ', $parts));
        }
    ],
    [
        'name' => __('Next execution'),
        'sort' => 'Task.next_execution_time',
        'element' => 'datetime',
        'data_path' => 'Task.next_execution_time',
    ],
    [
        'name' => __('Description'),
        'sort' => 'Task.description',
        'data_path' => 'Task.description',
    ],
    [
        'name' => __('Message'),
        'sort' => 'Task.message',
        'data_path' => 'Task.message',
    ],
    [
        'name' => __('Enabled'),
        'element' => 'toggle',
        'url' => $baseurl . '/tasks/toggleEnabled',
        'url_params_data_paths' => array(
            'Task.id'
        ),
        'sort' => 'required',
        'class' => 'short',
        'data_path' => 'Task.enabled',
        'disabled' => !$isSiteAdmin,
    ],
];
echo $this->element('genericElements/IndexTable/index_table', [
    'data' => [
        'data' => $data,
        'top_bar' => [
            'pull' => 'right',
            'children' => [
                [
                    'type' => 'simple',
                    'children' => [
                        'data' => [
                            'type' => 'simple',
                            'fa-icon' => 'plus',
                            'text' => __('Add scheduled task'),
                            'class' => 'btn-primary modal-open',
                            'url' => "$baseurl/tasks/add",
                        ]
                    ]
                ]
            ]
        ],
        'fields' => $fields,
        'title' => empty($ajax) ? __('Scheduled Tasks Index') : false,
        'description' => empty($ajax) ? __('Here you can schedule pre-defined tasks that will be executed every X seconds.') : false,
        'pull' => 'right',
        'actions' => [
            [
                'url' => $baseurl . '/tasks/edit',
                'url_params_data_paths' => array(
                    'Task.id'
                ),
                'icon' => 'edit',
                'title' => 'Edit task',
            ],
            [
                'class' => 'modal-open',
                'url' => "$baseurl/tasks/delete",
                'url_params_data_paths' => ['Task.id'],
                'icon' => 'trash',
                'title' => __('Delete task'),
            ]
        ]
    ]
]);
echo '</div>';
if (empty($ajax)) {
    echo $this->element('/genericElements/SideMenu/side_menu', array('menuList' => 'admin', 'menuItem' => 'tasks'));
}
