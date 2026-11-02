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
                    
                    $this->redirect($user['role']==='viewer' ? 'monitors' : 'dashboard');
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
            if(strlen($name)<2||strlen($name)>150||!preg_match('/^[A-Za-z0-9_.-]{3,50}$/',$username)
                ||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254||strlen($password)<12){
                $error='Enter a name, valid email, username (3–50 characters) and password of at least 12 characters.';
            }else{
                $existing=$this->db->fetch('SELECT id FROM users WHERE username=:username OR email=:email',
                    ['username'=>$username,'email'=>$email]);
                if($existing)$error='Username or email already registered.';
                else try{
                    $this->db->insert('users',['name'=>$name,'username'=>$username,'email'=>$email,
                        'password'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'viewer',
                        'created_at'=>date('Y-m-d H:i:s')]);
                    $_SESSION['success_message']='Account created. Please sign in.';
                    $this->redirect('auth/login');
                }catch(PDOException $e){$error='Could not create account.';}
            }
        }
        $this->view('auth/signup',['error'=>$error??null]);
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
