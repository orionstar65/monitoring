<?php
require_once BASEPATH.'app/controllers/Base.php';
require_once dirname(__DIR__,3).'/config/AppSettings.php';

/**
 * Super Admin operational key/value configuration.
 * Keys are intentionally allowlisted: credentials, release paths, SQL and
 * PHP configuration are NOT editable using this interface.
 */
class Settings extends Base {
    public function __construct() {
        parent::__construct();
        $this->requireSuperAdmin();
    }
    public function index(): void {
        $values=AppSettings::values($this->db->getConnection());
        $stored=[];
        $migrationNeeded=false;
        try{
            $rows=$this->db->fetchAll('SELECT setting_key,setting_value,updated_by,updated_at FROM app_settings');
            foreach($rows as $row)$stored[(string)$row['setting_key']]=$row;
        }catch(Throwable $error){
            $migrationNeeded=true;
            error_log('[Settings] Operational settings migration required: '.$error->getMessage());
        }
        $this->view('settings/index',[
            'catalog'=>AppSettings::catalog(),
            'values'=>$values,
            'stored'=>$stored,
            'migrationNeeded'=>$migrationNeeded,
            'notice'=>$_SESSION['settings_notice']??null
        ]);
        unset($_SESSION['settings_notice']);
    }
    public function save(): void {
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){
            http_response_code(405);exit('POST required');
        }
        // CSRF token already enforced for every POST by html/index.php.
        $key=trim((string)($_POST['key']??''));
        $raw=$_POST['value']??null;
        $definitions=AppSettings::catalog();
        if(!isset($definitions[$key]) || !is_scalar($raw)){
            http_response_code(400);exit('Unknown or invalid setting');
        }
        $normalized=AppSettings::normalize($key,$raw);
        if($normalized===null){
            http_response_code(422);exit('Value outside allowed range or invalid boolean');
        }
        try{
            if($normalized==='' && $definitions[$key][2]==='optional_int'){
                $this->db->query('DELETE FROM app_settings WHERE setting_key=:k',['k'=>$key]);
            }else{
                $this->db->query(
                    'INSERT INTO app_settings (setting_key,setting_value,updated_by) VALUES (:k,:v,:uid)
                     ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),
                       updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP',
                    ['k'=>$key,'v'=>$normalized,'uid'=>(int)$this->user['id']]
                );
            }
            error_log('[Settings] Super Admin '.(int)$this->user['id'].' updated '.$key);
            $_SESSION['settings_notice']=$key.' saved successfully.';
            $this->redirect('settings');
        }catch(Throwable $error){
            error_log('[Settings] Failed to persist '.$key.': '.$error->getMessage());
            http_response_code(503);
            exit('Could not save setting. Apply the application_settings migration first.');
        }
    }
    public function reset(): void {
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){
            http_response_code(405);exit('POST required');
        }
        $key=trim((string)($_POST['key']??''));
        if(!isset(AppSettings::catalog()[$key])){
            http_response_code(400);exit('Unknown setting');
        }
        try {
            $this->db->query('DELETE FROM app_settings WHERE setting_key=:k',['k'=>$key]);
            $_SESSION['settings_notice']=$key.' restored to its default.';
            $this->redirect('settings');
        }catch(Throwable $error){
            http_response_code(503);exit('Could not reset setting.');
        }
    }
}
