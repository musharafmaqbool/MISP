<?php
App::uses('AppModel', 'Model');

class Task extends AppModel
{
    public $recursive = -1;

    public $actsAs = array(
        'Containable',
    );

    public $belongsTo = [
        'User'
    ];
}
