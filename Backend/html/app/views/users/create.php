<?php
$title='Create User - '.APP_NAME;
$active_page='users';
$page_title='Create User';
ob_start();
$userFormMode='create';
include __DIR__.'/_account_form.php';
$content=ob_get_clean();
include BASEPATH.'app/views/layouts/main.php';
?>
