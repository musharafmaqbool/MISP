<?php
    echo $this->Form->create('Task', array(
        'id' => 'RequiredCheckboxForm' . h($id),
        'label' => false,
        'style' => 'display:none;',
        'url' => $baseurl . '/tasks/toggleEnabled/' . $id
    ));
    echo $this->Form->checkbox('required', array(
        'checked' => $required,
        'label' => false,
        'disabled' => !$isSiteAdmin,
        'class' => 'required-toggle'
    ));
    echo $this->Form->end();
?>
