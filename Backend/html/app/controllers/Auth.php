<?php
require_once BASEPATH . 'app/controllers/Base.php';

class Auth extends Base {
    
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            // var_dump("admin: ", password_hash($password, PASSWORD_DEFAULT));
            if (empty($username) || empty($password)) {
                $error = 'Username and password are required';
            } else {
                $user = $this->db->fetch(
                    "SELECT * FROM users WHERE username = :username OR email = :email",
                    ['username' => $username,'email'=>$username]
                );
                
                $attemptKey=hash('sha256',($_SERVER['REMOTE_ADDR']??'').'|'.strtolower($username));
                $attempts=(int)$this->db->fetch("SELECT COUNT(*) AS hits FROM login_attempts WHERE attempt_key=:k AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)",['k'=>$attemptKey])['hits'];
                if($attempts>=8){http_response_code(429);$error='Too many login attempts; try again later.';}
                elseif ($user && password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    $this->redirect(!empty($user['must_change_password'])
                        ? 'auth/change_password'
                        : ($user['role']==='admin' ? 'dashboard' : 'monitors'));
                } else {
                    $this->db->insert('login_attempts',['attempt_key'=>$attemptKey]);
                    $error = 'Invalid username or password';
                }
            }
        }
        
        $this->view('auth/login', ['error' => $error ?? null]);
    }
    
    public function signup() {
        if($_SERVER['REQUEST_METHOD']==='POST'){
            $name=trim((string)($_POST['name']??''));
            $username=trim((string)($_POST['username']??''));
            $email=trim((string)($_POST['email']??''));
            $password=(string)($_POST['password']??'');
            if(strlen($name)<2||strlen($name)>150
                ||preg_match('/[<>\\x00-\\x1f\\x7f]/',$name)
                ||!preg_match('/^[A-Za-z0-9_.-]{3,50}$/',$username)
                ||($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL))
                ||strlen($email)>254||strlen($password)<12||strlen($password)>1024){
                $error='Enter a name, valid email, username (3–50 characters) and password of at least 12 characters.';
            }else{
                // Enforce the same signup rate limit as the desktop API.
                $key=hash('sha256',($_SERVER['REMOTE_ADDR']??'').'|signup');
                $attempts=(int)$this->db->fetch(
                    'SELECT COUNT(*) AS hits FROM login_attempts WHERE attempt_key=:k AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)',
                    ['k'=>$key])['hits'];
                if($attempts>=10){
                    http_response_code(429);
                    $this->view('auth/signup',['error'=>'Too many registration attempts. Please try later.']);
                    return;
                }
                $this->db->insert('login_attempts',['attempt_key'=>$key]);
                $existing=$this->db->fetch('SELECT id FROM users WHERE username=:username OR email=:email',
                    ['username'=>$username,'email'=>$email]);
                if($existing)$error='Username or email already registered.';
                else try{
                    $this->db->insert('users',['name'=>$name,'username'=>$username,'email'=>$email!==''?$email:null,
                        'password'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'viewer',
                        'created_at'=>date('Y-m-d H:i:s')]);
                    $_SESSION['success_message']='Account created. Please sign in.';
                    $this->redirect('auth/login');
                }catch(PDOException $e){$error='Could not create account.';}
            }
        }
        $this->view('auth/signup',['error'=>$error??null]);
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
            elseif(strlen($new)<12 || strlen($new)>1024)$error='New password must be at least 12 characters.';
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
                $_SESSION['success_message']='Password updated. Desktop clients will need to sign in again.';
                $this->redirect(($_SESSION['role']??'')==='admin'?'dashboard':'monitors');
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
