<?php
require_once BASEPATH . 'app/controllers/Base.php';

class Users extends Base {
    
    public function __construct() {
        parent::__construct();
        $this->requireAdmin();
    }
    
    public function index() {
        $users = $this->db->fetchAll(
            "SELECT u.id,u.user_no,u.name,u.role,u.created_at,u.must_change_password,
                    MAX(m.last_monitor_tic) AS last_activity,
                    COUNT(m.id) AS device_count
             FROM users u LEFT JOIN tbl_monitor_ips m ON m.owner_user_id=u.id
             GROUP BY u.id,u.user_no,u.name,u.role,u.created_at,u.must_change_password
             ORDER BY u.created_at DESC"
        );
        
        $this->view('users/index', ['users' => $users]);
    }
    
    public function create() {
        $error=null;
        if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
            $userNo=trim((string)($_POST['user_no']??''));
            $name=trim((string)($_POST['name']??''));
            $password=(string)($_POST['password']??'');
            $role=(string)($_POST['role']??ROLE_USER);
            if(!in_array($role,['admin','user'],true))$role='user';
            if(!preg_match('/^[1-9][0-9]{0,14}$/D',$userNo)||strlen($name)<2||strlen($name)>150
                ||strlen($password)<6||strlen($password)>1024) {
                $error='Enter a unique numeric User No (1–15 digits), full name, and password of at least 6 characters.';
            } elseif($this->db->fetch('SELECT id FROM users WHERE user_no=:n',['n'=>$userNo])) {
                $error='User No already exists.';
            } else try {
                $this->db->insert('users',[
                    'user_no'=>$userNo,'name'=>$name,
                    'password'=>password_hash($password,PASSWORD_DEFAULT),
                    'role'=>$role,'created_at'=>date('Y-m-d H:i:s')
                ]);
                $_SESSION['success_message']='User created successfully.';
                $this->redirect('users');
            } catch (PDOException $e) {
                if($e->getCode()!=='23000')throw $e;
                $error='User No already exists.';
            }
        }
        $this->view('users/create',['error'=>$error]);
    }

    public function edit($id) {
        $user = $this->db->fetch("SELECT * FROM users WHERE id = :id", ['id' => $id]);
        
        if (!$user) {
            $this->redirect('users');
        }
        
        $monitors = $this->db->fetchAll('SELECT m.* FROM tbl_monitor_ips m WHERE m.owner_user_id=:uid ORDER BY m.id',['uid'=>$id]);
        $accounts = $this->db->fetchAll('SELECT u.id,u.user_no,u.name,COUNT(m.id) AS device_count FROM users u LEFT JOIN tbl_monitor_ips m ON m.owner_user_id=u.id GROUP BY u.id,u.user_no,u.name ORDER BY u.name,u.id');
        require_once BASEPATH . 'app/services/StatusService.php';
        $statusService=StatusService::getInstance();
        foreach($monitors as &$monitor){$monitor['status_info']=$statusService->calculateClientStatus($monitor['last_monitor_tic']??null);}
        unset($monitor);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name=trim((string)($_POST['name']??''));
            $role = $_POST['role'] ?? ROLE_USER;
            if(!in_array($role,['admin','user'],true))$role='user';
            $password = $_POST['password'] ?? '';
            $userNo=trim((string)($_POST['user_no']??''));
            // User No is a unique, administrator-managed numeric identifier.
            $validUserNo=preg_match('/^[1-9][0-9]{0,14}$/D',$userNo)===1;

            
            if (!$validUserNo || strlen($name)<2 || strlen($name)>150) {
                $error = 'Enter a valid User No (1-15 digits) and full name.';
            } else {
                $duplicateNo=$this->db->fetch('SELECT id FROM users WHERE user_no=:no AND id!=:id',['no'=>$userNo,'id'=>$id]);
                if ($duplicateNo) {
                    $error='User No already exists';
                } else {
                    $data = [
                        'user_no'=>$userNo,
                        'name'=>$name,
                        'role' => $role
                    ];
                    
                    if (!empty($password)) {
                        if(strlen($password)<6 || strlen($password)>1024){
                            $this->view('users/edit',['user'=>$user,'error'=>'Password must contain at least 6 characters.','monitors'=>$monitors,'accounts'=>$accounts]);
                            return;
                        }
                        $data['password'] = password_hash($password, PASSWORD_DEFAULT);
                        $data['must_change_password'] = 1;
                    }
                    
                    if($user['role']==='admin' && $role!=='admin'){
                        $admins=(int)$this->db->fetch("SELECT COUNT(*) AS n FROM users WHERE role='admin'")['n'];
                        if($admins<=1){
                            $this->view('users/edit',['user'=>$user,'error'=>'Cannot demote the last administrator.','monitors'=>$monitors,'accounts'=>$accounts]);
                            return;
                        }
                    }
                    try {
                        $result = $this->db->update('users', $data, 'id = :id', ['id' => $id]);
                    } catch (PDOException $e) {
                        if($e->getCode()==='23000') {
                            $error='User No already exists';
                            $this->view('users/edit',['user'=>array_merge($user,['user_no'=>$userNo]),'error'=>$error,'monitors'=>$monitors,'accounts'=>$accounts]);
                            return;
                        }
                        throw $e;
                    }
                    
                    // Check if data actually changed by comparing with original user data
                    $dataChanged = false;
                    foreach ($data as $key => $value) {
                        if ($user[$key] !== $value) {
                            $dataChanged = true;
                            break;
                        }
                    }
                    
                    if ($result > 0 || !$dataChanged) {
                        if(!empty($password) || $role!==$user['role']){
                            $this->db->query(
                                'UPDATE client_sessions SET revoked_at=NOW() WHERE user_id=:id AND revoked_at IS NULL',
                                ['id'=>$id]
                            );
                        }
                        // Success: either rows were updated OR no changes were needed
                        $_SESSION['success_message'] = 'User updated successfully';
                        $this->redirect('users');
                    } else {
                        $error = 'Update failed';
                    }
                }
            }
        }
        
        $this->view('users/edit', ['user' => $user, 'error' => $error ?? null, 'monitors'=>$monitors, 'accounts'=>$accounts]);
    }
    
    /** Administrative reassignment independent of the monitor's client-side lock. */
    public function remap_monitor($id) {
        $this->requireAdmin();
        if (($_SERVER['REQUEST_METHOD']??'')!=='POST') {http_response_code(405);exit('POST required');}
        $monitorId=filter_var($_POST['monitor_id']??null,FILTER_VALIDATE_INT);
        $userId=filter_var($_POST['owner_user_id']??null,FILTER_VALIDATE_INT)?:null;
        if (!$monitorId || !$this->db->fetch('SELECT id FROM users WHERE id=:id',['id'=>$id])){http_response_code(404);exit('User or monitor not found');}
        if($userId && !$this->db->fetch('SELECT id FROM users WHERE id=:id',['id'=>$userId])){http_response_code(400);exit('Invalid target user');}
        $monitor=$this->db->fetch('SELECT id,owner_user_id FROM tbl_monitor_ips WHERE id=:monitor AND owner_user_id=:owner',
            ['monitor'=>$monitorId,'owner'=>$id]);
        if(!$monitor){http_response_code(409);exit('Monitor is no longer assigned to this user');}
        if((int)$monitor['owner_user_id']!==(int)$userId){
            $this->db->update('tbl_monitor_ips',['owner_user_id'=>$userId],'id=:id',['id'=>$monitorId]);
            $this->db->query('UPDATE client_sessions SET revoked_at=NOW() WHERE monitor_id=:id AND revoked_at IS NULL',['id'=>$monitorId]);
        }
        $_SESSION['success_message']=$userId?'Monitor reassigned successfully':'Monitor unlinked successfully';
        $this->redirect('users/edit/'.$id);
    }

    public function reset_password($id) {
        if (($_SERVER['REQUEST_METHOD']??'') !== 'POST') {
            http_response_code(405);exit('POST required');
        }
        $id=filter_var($id,FILTER_VALIDATE_INT);
        if(!$id || (int)$id===(int)$this->user['id']){
            http_response_code(400);exit('Cannot reset this account');
        }
        if(!$this->db->fetch('SELECT id FROM users WHERE id=:id',['id'=>$id])){
            http_response_code(404);exit('User not found');
        }
        // Requested administrative default. Force a change on next web login.
        $this->db->update('users',[
            'password'=>password_hash('1234567890',PASSWORD_DEFAULT),
            'must_change_password'=>1
        ],'id=:id',['id'=>$id]);
        $this->db->query('UPDATE client_sessions SET revoked_at=NOW() WHERE user_id=:id AND revoked_at IS NULL',['id'=>$id]);
        $_SESSION['success_message']='Temporary default password applied. User must change it on next sign-in.';
        $this->redirect(($_POST['return_to']??'')==='edit'?'users/edit/'.$id:'users');
    }

    public function delete($id) {
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);exit('POST required');}
        if ($id == $_SESSION['user_id']) {
            $this->redirect('users');
        }
        
        $target=$this->db->fetch('SELECT role FROM users WHERE id=:id',['id'=>$id]);
        if(!$target){http_response_code(404);exit('User not found');}
        if($target['role']==='admin'){
            $admins=(int)$this->db->fetch("SELECT COUNT(*) AS n FROM users WHERE role='admin'")['n'];
            if($admins<=1){http_response_code(409);exit('Cannot delete the last administrator');}
        }
        $this->db->delete('users', 'id = :id', ['id' => $id]);
        $this->redirect('users');
    }
}
?> 