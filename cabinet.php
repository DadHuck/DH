<?php
define( 'DATALIFEENGINE', true );
define( 'ROOT_DIR', substr( dirname(  __FILE__ ), 0, -12 ) );
define( 'ENGINE_DIR', ROOT_DIR . '/engine' );

require_once ENGINE_DIR . '/classes/mysql.php';
require_once ENGINE_DIR . '/data/dbconfig.php';
require_once ENGINE_DIR . '/modules/functions.php';
dle_session();
require_once ENGINE_DIR . '/modules/sitelogin.php';

require_once ENGINE_DIR . '/modules/cabinet/config.php';
require_once ENGINE_DIR . '/modules/cabinet/function.php';
require_once ENGINE_DIR . '/modules/cabinet/modules/Rcon.php';

if (isset($_POST['user_hash']) && isset($_POST['action'])) {
    $resultAction = $db->safesql($_POST['action']);
    if ($_POST['action'] == 'reload_balance') {
        echo $member_id['money'];
    }
}