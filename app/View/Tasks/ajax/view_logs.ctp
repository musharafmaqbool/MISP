<?php
$html = '';

$html .= '<h5><i class="fas fa-cogs"></i> ' . __('Job Details') . '</h5>';
$html .= '<table class="table table-striped table-bordered">';
$html .= '<tr><th>' . __('ID') . '</th><td>' . h($job['Job']['id']) . '</td></tr>';
$html .= '<tr><th>' . __('Type') . '</th><td>' . h($job['Job']['job_type']) . '</td></tr>';
$html .= '<tr><th>' . __('Input') . '</th><td>' . h($job['Job']['job_input']) . '</td></tr>';
$html .= '<tr><th>' . __('Worker') . '</th><td>' . h($job['Job']['worker']) . '</td></tr>';
$html .= '<tr><th>' . __('Org') . '</th><td>' . h($job['Org']['name']) . '</td></tr>';
$html .= '<tr><th>' . __('Status') . '</th><td>' . h($job['Job']['status']) . '</td></tr>';
$html .= '<tr><th>' . __('Progress') . '</th><td>' . h($job['Job']['progress']) . '%</td></tr>';
$html .= '<tr><th>' . __('Retries') . '</th><td>' . h($job['Job']['retries']) . '</td></tr>';
$html .= '<tr><th>' . __('Message') . '</th><td>' . h($job['Job']['message']) . '</td></tr>';
$html .= '<tr><th>' . __('Process ID') . '</th><td>' . h($job['Job']['process_id']) . '</td></tr>';
$html .= '<tr><th>' . __('Created') . '</th><td>' . h($job['Job']['date_created']) . '</td></tr>';
$html .= '<tr><th>' . __('Modified') . '</th><td>' . h($job['Job']['date_modified']) . '</td></tr>';
$html .= '</table>';

if (!empty($logs['error']) || !empty($logs['backtrace'])) {
    $html .= '<hr>';
    $html .= '<h5><i class="fas fa-bug"></i> ' . __('Error Log') . '</h5>';

    if (!empty($logs['error'])) {
        $html .= '<div class="alert alert-danger"><strong>' . __('Error:') . '</strong> ' . h($logs['error']) . '</div>';
    }

    if (!empty($logs['backtrace'])) {
        $html .= '<h5>' . __('Backtrace') . '</h5>';
        $html .= '<pre class="bg-light p-2" style="max-height:300px; overflow:auto;">' . h(implode("\n", $logs['backtrace'])) . '</pre>';
    }
}

$modalData = [
    'data' => [
        'title' => __('View Task #%s Logs', h($task['Task']['id'])),
        'content' => [
            [
                'html' => $html
            ],
        ]
    ],
    'type' => 'xl',
];
echo $this->element('genericElements/infoModal', $modalData);
