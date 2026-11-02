<?php
require_once BASEPATH . 'app/controllers/Base.php';

class Users extends Base {
    
    public function __construct() {
        parent::__construct();
        $this->requireAdmin();
    }
    
    public function index() {
        $users = $this->db->fetchAll(
            "SELECT u.id,u.user_no,u.name,u.username,u.email,u.role,u.created_at,u.must_change_password,
                    MAX(m.last_monitor_tic) AS last_activity
             FROM users u LEFT JOIN tbl_monitor_ips m ON m.owner_user_id=u.id
             GROUP BY u.id,u.user_no,u.name,u.username,u.email,u.role,u.created_at,u.must_change_password
             ORDER BY u.created_at DESC"
        );
        
        $this->view('users/index', ['users' => $users]);
    }
    
    public function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim((string)($_POST['username'] ?? ''));
            $name=trim((string)($_POST['name']??$username));
            $password = $_POST['password'] ?? '';
            $email = trim((string)($_POST['email'] ?? ''));
            $role = $_POST['role'] ?? ROLE_USER;
            if(!in_array($role,['admin','user','viewer'],true))$role='viewer';
            
            if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/',$username)
                || strlen($password)<12 || strlen($password)>1024
                || strlen($name)<2 || strlen($name)>150
                || ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL))) {
                $error = 'Enter a valid name, username, optional email and password (12+ characters).';
            } else {
                // Check if username already exists
                $existing = $this->db->fetch(
                    "SELECT id FROM users WHERE username = :username",
                    ['username' => $username]
                );
                
                if ($existing || ($email!=='' && $this->db->fetch(
                        'SELECT id FROM users WHERE email=:email',['email'=>$email]))) {
                    $error = 'Username or email already exists';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    $userId = $this->db->insert('users', [
                        'username' => $username,
                        'name' => $name,
                        'password' => $hashedPassword,
                        'email' => $email!==''?$email:null,
                        'role' => $role,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    if ($userId) {
                        $this->db->update('users',['user_no'=>(int)$userId+100000],'id=:id',['id'=>$userId]);
                        $this->redirect('users');
                    } else {
                        $error = 'Failed to create user';
                    }
                }
            }
        }
        
        $this->view('users/create', ['error' => $error ?? null]);
    }
    
    public function edit($id) {
        $user = $this->db->fetch("SELECT * FROM users WHERE id = :id", ['id' => $id]);
        
        if (!$user) {
            $this->redirect('users');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim((string)($_POST['username'] ?? ''));
            $name=trim((string)($_POST['name']??$username));
            $email = trim((string)($_POST['email'] ?? ''));
            $role = $_POST['role'] ?? ROLE_USER;
            if(!in_array($role,['admin','user','viewer'],true))$role='viewer';
            $password = $_POST['password'] ?? '';
            $userNo=trim((string)($_POST['user_no']??''));
            // User No is a unique, administrator-managed numeric identifier.
            $validUserNo=preg_match('/^[1-9][0-9]{0,14}$/D',$userNo)===1;

            
            if (!$validUserNo || !preg_match('/^[A-Za-z0-9_.-]{3,50}$/',$username)
                || strlen($name)<2 || strlen($name)>150
                || ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL))) {
                $error = 'Enter a valid User No (1-15 digits), name, username and optional email.';
            } else {
                // Check if username already exists for another user
                $existing = $this->db->fetch(
                    "SELECT id FROM users WHERE username = :username AND id != :id",
                    ['username' => $username, 'id' => $id]
                );
                
                $duplicateNo=$this->db->fetch('SELECT id FROM users WHERE user_no=:no AND id!=:id',['no'=>$userNo,'id'=>$id]);
                if ($existing || $duplicateNo || ($email!=='' && $this->db->fetch(
                        'SELECT id FROM users WHERE email=:email AND id!=:id',
                        ['email'=>$email,'id'=>$id]))) {
                    $error = 'User No, username or email already exists';
                } else {
                    $data = [
                        'user_no'=>$userNo,
                        'name'=>$name,
                        'username' => $username,
                        'email' => $email!==''?$email:null,
                        'role' => $role
                    ];
                    
                    if (!empty($password)) {
                        if(strlen($password)<12 || strlen($password)>1024){
                            $this->view('users/edit',['user'=>$user,'error'=>'Password must contain at least 12 characters.']);
                            return;
                        }
                        $data['password'] = password_hash($password, PASSWORD_DEFAULT);
                        $data['must_change_password'] = 1;
                    }
                    
                    if($user['role']==='admin' && $role!=='admin'){
                        $admins=(int)$this->db->fetch("SELECT COUNT(*) AS n FROM users WHERE role='admin'")['n'];
                        if($admins<=1){
                            $this->view('users/edit',['user'=>$user,'error'=>'Cannot demote the last administrator.']);
                            return;
                        }
                    }
                    try {
                        $result = $this->db->update('users', $data, 'id = :id', ['id' => $id]);
                    } catch (PDOException $e) {
                        if($e->getCode()==='23000') {
                            $error='User No, username or email already exists';
                            $this->view('users/edit',['user'=>array_merge($user,['user_no'=>$userNo]),'error'=>$error]);
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
                        if(!empty($password) || $role!==$user['role'] || $username!==$user['username']){
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
        
        $this->view('users/edit', ['user' => $user, 'error' => $error ?? null]);
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
        $this->redirect('users');
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