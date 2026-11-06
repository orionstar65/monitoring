<?php
require_once BASEPATH . 'app/controllers/Base.php';

class Auth extends Base {
    private function loginDestination(array $user): string {
        if(!empty($user['must_change_password']))return 'auth/change_password';
        switch((string)($user['role']??'')){
            case ROLE_SUPER_ADMIN:return 'dashboard';
            case ROLE_SCOPED_ADMIN:return 'view';
            default:return 'monitors';
        }
    }
    /**
     * Fail clearly when PHP is deployed ahead of the three-role schema.
     * A legacy 'admin' must not silently inherit a delegated-admin scope.
     */
    private function loginSetupError(array $user): ?string {
        $column=$this->db->fetch(
            "SELECT COLUMN_TYPE AS role_type FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role'"
        );
        if(!str_contains(strtolower((string)($column['role_type']??'')),"'super-admin'"))
            return 'Server setup incomplete: the Super Admin role migration must be applied before logging in.';
        if(($user['role']??'')===ROLE_SCOPED_ADMIN){
            $table=$this->db->fetch(
                "SELECT TABLE_NAME FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_user_assignments'"
            );
            if(!$table)return 'Server setup incomplete: the Admin assignments table is missing.';
        }
        return null;
    }

    
    public function login() {
        if(($_SERVER['REQUEST_METHOD']??'GET')==='GET' && !empty($_SESSION['user_id']) && $this->user){
            $setup=$this->loginSetupError($this->user);
            if($setup===null)$this->redirect($this->loginDestination($this->user));
            http_response_code(503);
            $this->view('auth/login',['error'=>$setup]);return;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $identifier = trim((string)($_POST['identifier'] ?? ''));
            $password = $_POST['password'] ?? '';
            if (!preg_match('/^[1-9][0-9]{0,14}$/D',$identifier) || $password==='') {
                $error = 'Enter your User No and password.';
            } else {
                $user=$this->db->fetch(
                    'SELECT * FROM users WHERE user_no=:user_no LIMIT 1',
                    ['user_no'=>$identifier]
                );

                $attemptKey=hash('sha256',($_SERVER['REMOTE_ADDR']??'').'|'.strtolower($identifier));
                $attempts=(int)$this->db->fetch("SELECT COUNT(*) AS hits FROM login_attempts WHERE attempt_key=:k AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)",['k'=>$attemptKey])['hits'];
                if($attempts>=8){http_response_code(429);$error='Too many login attempts; try again later.';}
                elseif ($user && password_verify($password, $user['password'])) {
                    $setup=$this->loginSetupError($user);
                    if($setup!==null){
                        http_response_code(503);
                        $error=$setup;
                    }else{
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_no'] = (string)$user['user_no'];
                        $_SESSION['full_name'] = $user['name'];
                        $_SESSION['role'] = $user['role'];
                        $this->redirect($this->loginDestination($user));
                    }
                } else {
                    $this->db->insert('login_attempts',['attempt_key'=>$attemptKey]);
                    $error = 'Invalid User No or password.';
                }
            }
        }
        
        $this->view('auth/login', ['error' => $error ?? null]);
    }
    
    // Accounts are provisioned by administrators using a manually assigned User No.
    public function signup() {
        http_response_code(403);
        $this->redirect('auth/login');
    }

    public function change_password() {
        if(empty($_SESSION['user_id']))$this->redirect('auth/login');
        $user=$this->db->fetch('SELECT password,must_change_password FROM users WHERE id=:id',
            ['id'=>$_SESSION['user_id']]);
        if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
            $old=(string)($_POST['current_password']??'');
            $new=(string)($_POST['new_password']??'');
            $confirm=(string)($_POST['confirm_password']??'');
            if(!password_verify($old,$user['password']))$error='Current password is incorrect.';
            elseif(strlen($new)<6 || strlen($new)>1024)$error='New password must be at least 6 characters.';
            elseif(!hash_equals($new,$confirm))$error='New passwords do not match.';
            elseif(password_verify($new,$user['password']))$error='Choose a different password.';
            else {
                $this->db->update('users',[
                    'password'=>password_hash($new,PASSWORD_DEFAULT),
                    'must_change_password'=>0
                ],'id=:id',['id'=>$_SESSION['user_id']]);
                $this->db->query('UPDATE client_sessions SET revoked_at=NOW() WHERE user_id=:id AND revoked_at IS NULL',
                    ['id'=>$_SESSION['user_id']]);
                session_regenerate_id(true);
                $_SESSION['success_message']='Password updated successfully.';
                $this->redirect(($_SESSION['role']??'')===ROLE_SUPER_ADMIN?'dashboard':(($_SESSION['role']??'')===ROLE_SCOPED_ADMIN?'view':'monitors'));
            }
        }
        $this->view('auth/change_password',[
            'error'=>$error??null,'required'=>!empty($user['must_change_password'])
        ]);
    }
    public function logout() {
        session_destroy();
        $this->redirect('auth/login');
    }
    
    public function register() {
        $this->requireAdmin();
        $this->redirect('users/create');
    }
}
?>
