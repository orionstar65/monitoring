<?php
require_once BASEPATH . 'app/controllers/Base.php';

class Users extends Base {
    
    public function __construct() {
        parent::__construct();
        $this->requireAdmin();
    }
    
    public function index() {
        $users = $this->db->fetchAll(
            "SELECT u.id,u.name,u.username,u.email,u.role,u.created_at,u.must_change_password,
                    MAX(m.last_monitor_tic) AS last_activity
             FROM users u LEFT JOIN tbl_monitor_ips m ON m.owner_user_id=u.id
             GROUP BY u.id,u.name,u.username,u.email,u.role,u.created_at,u.must_change_password
             ORDER BY u.created_at DESC"
        );
        
        $this->view('users/index', ['users' => $users]);
    }
    
    public function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $name=trim((string)($_POST['name']??$username));
            $password = $_POST['password'] ?? '';
            $email = $_POST['email'] ?? '';
            $role = $_POST['role'] ?? ROLE_USER;
            if(!in_array($role,['admin','user','viewer'],true))$role='viewer';
            
            if (empty($username) || strlen($password)<12 || strlen($name)<2
                || ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL))) {
                $error = 'All fields are required';
            } else {
                // Check if username already exists
                $existing = $this->db->fetch(
                    "SELECT id FROM users WHERE username = :username",
                    ['username' => $username]
                );
                
                if ($existing) {
                    $error = 'Username already exists';
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
            $username = $_POST['username'] ?? '';
            $name=trim((string)($_POST['name']??$username));
            $email = $_POST['email'] ?? '';
            $role = $_POST['role'] ?? ROLE_USER;
            if(!in_array($role,['admin','user','viewer'],true))$role='viewer';
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || strlen($name)<2 || ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL))) {
                $error = 'Username and email are required';
            } else {
                // Check if username already exists for another user
                $existing = $this->db->fetch(
                    "SELECT id FROM users WHERE username = :username AND id != :id",
                    ['username' => $username, 'id' => $id]
                );
                
                if ($existing) {
                    $error = 'Username already exists';
                } else {
                    $data = [
                        'name'=>$name,
                        'username' => $username,
                        'email' => $email!==''?$email:null,
                        'role' => $role
                    ];
                    
                    if (!empty($password)) {
                        if(strlen($password)<12){
                            $this->view('users/edit',['user'=>$user,'error'=>'Password must contain at least 12 characters.']);
                            return;
                        }
                        $data['password'] = password_hash($password, PASSWORD_DEFAULT);
                        $data['must_change_password'] = 1;
                    }
                    
                    $result = $this->db->update('users', $data, 'id = :id', ['id' => $id]);
                    
                    // Check if data actually changed by comparing with original user data
                    $dataChanged = false;
                    foreach ($data as $key => $value) {
                        if ($user[$key] !== $value) {
                            $dataChanged = true;
                            break;
                        }
                    }
                    
                    if ($result > 0 || !$dataChanged) {
                        if(!empty($password))$this->db->query(
                            'UPDATE client_sessions SET revoked_at=NOW() WHERE user_id=:id AND revoked_at IS NULL',
                            ['id'=>$id]
                        );
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
        
        $this->db->delete('users', 'id = :id', ['id' => $id]);
        $this->redirect('users');
    }
}
?> 