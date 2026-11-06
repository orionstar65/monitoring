<?php
require_once BASEPATH . 'app/controllers/Base.php';

class Users extends Base {
    
    public function __construct() {
        parent::__construct();
        $this->requireManager();
    }
    
    public function index() {
        $scope=$this->isScopedAdmin()
            ? 'WHERE u.role=:target_role AND u.id IN (
                SELECT a.managed_user_id FROM admin_user_assignments a
                WHERE a.admin_user_id=:manager_id)'
            : '';
        $params=$this->isScopedAdmin()
            ? ['target_role'=>ROLE_USER,'manager_id'=>(int)$this->user['id']] : [];
        $users = $this->db->fetchAll(
            "SELECT u.id,u.user_no,u.name,u.role,u.created_at,u.must_change_password,
                    MAX(m.last_monitor_tic) AS last_activity,
                    COUNT(m.id) AS device_count
             FROM users u LEFT JOIN tbl_monitor_ips m ON m.owner_user_id=u.id
             {$scope}
             GROUP BY u.id,u.user_no,u.name,u.role,u.created_at,u.must_change_password
             ORDER BY CAST(u.user_no AS UNSIGNED),u.name,u.id", $params
        );
        
        $this->view('users/index', ['users' => $users]);
    }
    
    public function create() {
        $this->requireSuperAdmin();
        $error=null;
        if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
            $userNo=trim((string)($_POST['user_no']??''));
            $name=trim((string)($_POST['name']??''));
            $password=(string)($_POST['password']??'');
            $role=(string)($_POST['role']??ROLE_USER);
            if(!in_array($role,[ROLE_SUPER_ADMIN,ROLE_SCOPED_ADMIN,ROLE_USER],true))$role=ROLE_USER;
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
        $id=(int)$id;
        if($this->isScopedAdmin())$this->requireUserAccess($id);
        $user = $this->db->fetch("SELECT * FROM users WHERE id = :id", ['id' => $id]);
        
        if (!$user) {
            $this->redirect('users');
        }
        
        $monitors = $this->db->fetchAll("SELECT m.* FROM tbl_monitor_ips m WHERE m.owner_user_id=:uid ORDER BY COALESCE(m.type,''),COALESCE(m.platform,''),INET_ATON(COALESCE(NULLIF(m.last_seen_ip,''),m.ip_address)),COALESCE(NULLIF(m.last_seen_ip,''),m.ip_address),m.id",['uid'=>$id]);
        $accounts = $this->isScopedAdmin()? [] :
            $this->db->fetchAll('SELECT u.id,u.user_no,u.name,COUNT(m.id) AS device_count FROM users u LEFT JOIN tbl_monitor_ips m ON m.owner_user_id=u.id GROUP BY u.id,u.user_no,u.name ORDER BY CAST(u.user_no AS UNSIGNED),u.name,u.id');
        require_once BASEPATH . 'app/services/StatusService.php';
        $statusService=StatusService::getInstance();
        foreach($monitors as &$monitor){$monitor['status_info']=$statusService->calculateClientStatus($monitor['last_monitor_tic']??null);}
        unset($monitor);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name=trim((string)($_POST['name']??''));
            $role=$this->isAdmin()?($_POST['role']??$user['role']):$user['role'];
            if(!in_array($role,[ROLE_SUPER_ADMIN,ROLE_SCOPED_ADMIN,ROLE_USER],true)){
                http_response_code(400);exit('Unknown role');
            }
            if($this->isScopedAdmin()&&($user['role']!==ROLE_USER
                || (isset($_POST['role'])&&$_POST['role']!==ROLE_USER))){
                http_response_code(403);exit('Role changes require Super Admin');
            }
            $password = (string)($_POST['password'] ?? '');
            if($this->isScopedAdmin() && $password!==''){
                http_response_code(403);exit('Password changes require Super Admin');
            }
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
                    
                    if($user['role']===ROLE_SUPER_ADMIN && $role!==ROLE_SUPER_ADMIN){
                        $admins=(int)$this->db->fetch("SELECT COUNT(*) AS n FROM users WHERE role=:role",['role'=>ROLE_SUPER_ADMIN])['n'];
                        if($admins<=1){
                            $this->view('users/edit',['user'=>$user,'error'=>'Cannot demote the last Super Admin.','monitors'=>$monitors,'accounts'=>$accounts]);
                            return;
                        }
                    }
                    $connection=$this->db->getConnection();
                    try {
                        $connection->beginTransaction();
                        $result=$this->db->update('users',$data,'id = :id',['id'=>$id]);
                        if($role!==$user['role']){
                            // Prevent prior assignments from silently reactivating
                            // after a promotion/demotion or assigned-user role change.
                            $this->db->query(
                                'DELETE FROM admin_user_assignments
                                 WHERE admin_user_id=:admin OR managed_user_id=:managed',
                                ['admin'=>$id,'managed'=>$id]
                            );
                        }
                        if($password!=='' || $role!==$user['role']){
                            $this->db->query(
                                'UPDATE client_sessions SET revoked_at=NOW()
                                 WHERE user_id=:id AND revoked_at IS NULL',['id'=>$id]
                            );
                        }
                        $connection->commit();
                    } catch (PDOException $e) {
                        if($connection->inTransaction())$connection->rollBack();
                        if($e->getCode()==='23000'){
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
    
    /** Super Admin-only assignment of regular user accounts to a delegated Admin. */
    public function assign_users($adminId) {
        $this->requireSuperAdmin();
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit('POST required');}
        $adminId=(int)$adminId;
        $target=$this->db->fetch('SELECT id,role FROM users WHERE id=:id',['id'=>$adminId]);
        if(!$target||$target['role']!==ROLE_SCOPED_ADMIN){http_response_code(400);exit('Target must be a delegated Admin');}
        $requested=$_POST['managed_user_ids']??[];
        if(!is_array($requested)||count($requested)>5000){http_response_code(400);exit('Invalid selection');}
        $ids=[];
        foreach($requested as $value){
            $n=filter_var($value,FILTER_VALIDATE_INT);
            if(!$n||$n<1){http_response_code(400);exit('Invalid user ID');}
            $ids[$n]=$n;
        }
        if($ids){
            $placeholders=implode(',',array_fill(0,count($ids),'?'));
            $matches=$this->db->fetchAll(
                "SELECT id FROM users WHERE role=? AND id IN ({$placeholders})",
                array_merge([ROLE_USER],array_values($ids))
            );
            if(count($matches)!==count($ids)){http_response_code(400);exit('Only regular users may be assigned');}
        }
        $connection=$this->db->getConnection();
        $connection->beginTransaction();
        try{
            $this->db->query('DELETE FROM admin_user_assignments WHERE admin_user_id=:admin',['admin'=>$adminId]);
            foreach($ids as $id)$this->db->query(
                'INSERT INTO admin_user_assignments (admin_user_id,managed_user_id) VALUES (:admin,:managed)',
                ['admin'=>$adminId,'managed'=>$id]
            );
            $connection->commit();
        }catch(Throwable $error){
            if($connection->inTransaction())$connection->rollBack();
            throw $error;
        }
        $_SESSION['success_message']='Assigned users updated.';
        $this->redirect('users/edit/'.$adminId);
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
        $this->requireSuperAdmin();
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
        $this->requireSuperAdmin();
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);exit('POST required');}
        if ($id == $_SESSION['user_id']) {
            $this->redirect('users');
        }
        
        $target=$this->db->fetch('SELECT role FROM users WHERE id=:id',['id'=>$id]);
        if(!$target){http_response_code(404);exit('User not found');}
        if($target['role']===ROLE_SUPER_ADMIN){
            $admins=(int)$this->db->fetch("SELECT COUNT(*) AS n FROM users WHERE role=:role",['role'=>ROLE_SUPER_ADMIN])['n'];
            if($admins<=1){http_response_code(409);exit('Cannot delete the last Super Admin');}
        }
        $this->db->delete('users', 'id = :id', ['id' => $id]);
        $this->redirect('users');
    }
}
?> 