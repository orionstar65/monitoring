<?php
require_once BASEPATH.'app/controllers/Base.php';

/**
 * Super Admin-only database status, bounded retention and native MySQL backup.
 * Never accepts an arbitrary table name or SQL statement from the browser.
 */
class Databasemanagement extends Base {
    private const CLEANABLE = ['browser_history_logs','key_logs','usb_device_logs'];
    private const BATCH_SIZE = 2000;
    private const ARCHIVE_MAX = 536870912; // compressed 512 MiB
    private const SQL_MAX = 3221225472; // unpacked 3 GiB

    public function __construct() {
        parent::__construct();
        $this->requireSuperAdmin();
    }

    private function requirePost(): void {
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){
            http_response_code(405);
            exit('POST required');
        }
        // CSRF is checked by Backend/html/index.php on every POST.
    }
    private function quoteIdentifier(string $name): string {
        if(!preg_match('/^[a-z][a-z0-9_]*$/D',$name))
            throw new RuntimeException('Invalid identifier');
        return chr(96).$name.chr(96);
    }
    private function availableCleanupTables(): array {
        $rows=$this->db->fetchAll(
            "SELECT DISTINCT t.TABLE_NAME
             FROM information_schema.TABLES t
             JOIN information_schema.COLUMNS c
               ON c.TABLE_SCHEMA=t.TABLE_SCHEMA AND c.TABLE_NAME=t.TABLE_NAME
             WHERE t.TABLE_SCHEMA=DATABASE() AND t.TABLE_TYPE='BASE TABLE'
               AND c.COLUMN_NAME='created_at'
               AND t.TABLE_NAME IN ('browser_history_logs','key_logs','usb_device_logs')"
        );
        $present=array_column($rows,'TABLE_NAME');
        return array_values(array_intersect(self::CLEANABLE,$present));
    }

    public function index(): void {
        $rows=$this->db->fetchAll(
            "SELECT t.TABLE_NAME,t.ENGINE,t.TABLE_ROWS,
                    t.DATA_LENGTH,t.INDEX_LENGTH,t.DATA_FREE,t.UPDATE_TIME,
                    MAX(CASE WHEN c.COLUMN_NAME='created_at' THEN 1 ELSE 0 END) AS has_created_at
             FROM information_schema.TABLES t
             LEFT JOIN information_schema.COLUMNS c
               ON c.TABLE_SCHEMA=t.TABLE_SCHEMA AND c.TABLE_NAME=t.TABLE_NAME
             WHERE t.TABLE_SCHEMA=DATABASE() AND t.TABLE_TYPE='BASE TABLE'
             GROUP BY t.TABLE_NAME,t.ENGINE,t.TABLE_ROWS,
                      t.DATA_LENGTH,t.INDEX_LENGTH,t.DATA_FREE,t.UPDATE_TIME
             ORDER BY (t.DATA_LENGTH+t.INDEX_LENGTH) DESC,t.TABLE_NAME"
        );
        $eligible=$this->availableCleanupTables();
        $totalBytes=0;
        foreach($rows as &$row){
            $row['cleanable']=in_array($row['TABLE_NAME'],$eligible,true);
            $totalBytes+=(int)$row['DATA_LENGTH']+(int)$row['INDEX_LENGTH'];
        }
        unset($row);
        $this->view('databasemanagement/index',[
            'tables'=>$rows,
            'cleanable'=>$eligible,
            'totalBytes'=>$totalBytes,
            'databaseName'=>DB_NAME
        ]);
    }

    /**
     * One DELETE per eligible table, not thousands of browser-driven batches.
     * The grouped storage_statistics adjustment and delete run atomically.
     * Large purges may still take time or hold row locks: keep the created_at
     * index from the retention migration and schedule large runs off-hours.
     */
    public function cleanup_batch(): void {
        $this->requirePost();
        $months=filter_var($_POST['months']??null,FILTER_VALIDATE_INT);
        $index=filter_var($_POST['table_index']??null,FILTER_VALIDATE_INT);
        if(!$months||$months<1||$months>120 || $index===false||$index<0 ||
           ($_POST['confirm']??'')!=='DELETE OLD LOGS'){
            http_response_code(400);
            $this->json(['success'=>false,'error'=>'Invalid retention request or confirmation']);
            return;
        }
        $tables=$this->availableCleanupTables();
        if($index>=count($tables)){
            $this->json(['success'=>true,'done'=>true,'deleted'=>0,'next_table'=>$index,
                         'tables_total'=>count($tables)]);
            return;
        }
        $table=$tables[$index];
        // Only the allowlisted event tables are purged. Screenshot metadata
        // must always be deleted with its physical files by Screenshot cleanup.
        $spec=[
            'browser_history_logs'=>['date'=>'visit_date','type'=>'browser_history',
                'bytes'=>"OCTET_LENGTH(COALESCE(url,''))+OCTET_LENGTH(COALESCE(title,''))"],
            'key_logs'=>['date'=>'key_date','type'=>'key_logs',
                'bytes'=>"OCTET_LENGTH(COALESCE(application,''))+OCTET_LENGTH(COALESCE(key_pressed,''))"],
            'usb_device_logs'=>['date'=>'device_date','type'=>'usb_logs',
                'bytes'=>"OCTET_LENGTH(COALESCE(device_name,''))+OCTET_LENGTH(COALESCE(device_path,''))+OCTET_LENGTH(COALESCE(device_type,''))"]
        ][$table];
        $cutoffRow=$this->db->fetch(
            "SELECT DATE_FORMAT(DATE_SUB(NOW(),INTERVAL :months MONTH), '%Y-%m-%d %H:%i:%s') AS cutoff",
            ['months'=>$months]
        );
        $cutoff=(string)($cutoffRow['cutoff']??'');
        if($cutoff===''){
            http_response_code(500);
            $this->json(['success'=>false,'error'=>'Could not determine retention cutoff']);
            return;
        }
        $tableSQL=$this->quoteIdentifier($table);
        $dateSQL=$this->quoteIdentifier($spec['date']);
        $pdo=$this->db->getConnection();
        try{
            // Retention and its monthly summary must commit or roll back together.
            $pdo->beginTransaction();
            $this->db->query(
                'UPDATE storage_statistics s
                 INNER JOIN (
                     SELECT monitor_ip_id,DATE_FORMAT('.$dateSQL.", '%Y-%m') AS month_year,
                            COUNT(*) AS removed_count,
                            COALESCE(SUM(".$spec['bytes']."),0) AS removed_bytes
                     FROM ".$tableSQL.'
                     WHERE created_at < :cutoff
                     GROUP BY monitor_ip_id,DATE_FORMAT('.$dateSQL.", '%Y-%m')
                 ) removed
                 ON removed.monitor_ip_id=s.monitor_ip_id
                   AND removed.month_year=s.month_year
                 SET s.record_count=GREATEST(0,s.record_count-removed.removed_count),
                     s.total_size=GREATEST(0,s.total_size-removed.removed_bytes),
                     s.last_updated=CURRENT_TIMESTAMP
                 WHERE s.data_type=:data_type",
                ['cutoff'=>$cutoff,'data_type'=>$spec['type']]
            );
            $deleted=$this->db->query(
                'DELETE FROM '.$tableSQL.' WHERE created_at < :cutoff',
                ['cutoff'=>$cutoff]
            )->rowCount();
            $pdo->commit();
        }catch(Throwable $error){
            if($pdo->inTransaction())$pdo->rollBack();
            error_log('DB retention cleanup failure '.$table.': '.$error->getMessage());
            http_response_code(500);
            $this->json(['success'=>false,'error'=>'Could not clean '.$table.'. Check server logs.']);
            return;
        }
        $next=$index+1;
        $this->json([
            'success'=>true,'done'=>$next>=count($tables),
            'deleted'=>$deleted,'table'=>$table,
            'next_table'=>$next,'cutoff'=>$cutoff,'tables_total'=>count($tables)
        ]);
    }

    private function tempPath(string $prefix): string {
        $path=tempnam(sys_get_temp_dir(),$prefix);
        if($path===false)throw new RuntimeException('Temporary storage unavailable');
        @chmod($path,0600);
        return $path;
    }
    private function defaultsFile(): string {
        $file=$this->tempPath('monitor-mysql-');
        // Trusted application configuration only; never interpolated shell input.
        $quote=static fn(string $s):string =>
            '"'.str_replace(['\\','"','\n',"\r"],['\\\\','\\"','\\n',''],$s).'"';
        $settings="[client]\n"
            ."host=".$quote(DB_HOST)."\n"
            ."user=".$quote(DB_USER)."\n"
            ."password=".$quote(DB_PASS)."\n"
            ."default-character-set=utf8mb4\n";
        if(file_put_contents($file,$settings,LOCK_EX)===false)
            throw new RuntimeException('Could not create temporary connection profile');
        return $file;
    }
    private function runMysql(array $command,?string $input,?string $output): void {
        if(!function_exists('proc_open'))
            throw new RuntimeException('MySQL CLI execution is disabled on this server');
        $descriptors=[
            0=>$input===null?['pipe','r']:['file',$input,'r'],
            1=>['file',$output??'/dev/null','w'],
            2=>['pipe','w']
        ];
        $process=@proc_open($command,$descriptors,$pipes);
        if(!is_resource($process))
            throw new RuntimeException('MySQL client executable could not start');
        if($input===null)fclose($pipes[0]);
        // Limit error output and never send raw DB CLI output to the browser.
        $stderr=stream_get_contents($pipes[2],8192);
        fclose($pipes[2]);
        $result=proc_close($process);
        if($result!==0){
            error_log('Database management CLI exited '.$result.': '.substr((string)$stderr,0,250));
            throw new RuntimeException('Database command failed; check MySQL client installation and server logs');
        }
    }
    // A dedicated, stable key keeps backups restorable after database password
    // rotation. Existing installations may fall back to their DB password.
    private function backupSigningKey(): string {
        $configured=getenv('MONITOR_BACKUP_SIGNING_KEY');
        if(is_string($configured) && $configured!==''){
            if(strlen($configured)<32)
                throw new RuntimeException('Backup signing key must be at least 32 bytes');
            return $configured;
        }
        if(DB_PASS==='')throw new RuntimeException('Set MONITOR_BACKUP_SIGNING_KEY to enable signed backups');
        return DB_PASS;
    }
    private function generateBackup(): string {
        set_time_limit(0);
        $credentials=$this->defaultsFile();
        $sql=$this->tempPath('monitor-export-');
        $archive=$this->tempPath('monitor-gzip-');
        try{
            $this->runMysql([
                'mysqldump','--defaults-extra-file='.$credentials,
                '--single-transaction','--quick','--routines','--triggers',
                '--default-character-set=utf8mb4','--add-drop-table',DB_NAME
            ],null,$sql);
            $signing=hash_init('sha256',HASH_HMAC,$this->backupSigningKey());
            hash_update($signing,DB_NAME."\n");
            $signatureStream=fopen($sql,'rb');
            if(!$signatureStream)throw new RuntimeException('Backup signing failed');
            try{
                if(hash_update_stream($signing,$signatureStream)===false)
                    throw new RuntimeException('Backup signing failed');
            }finally{fclose($signatureStream);}
            $signature=hash_final($signing);
            $input=fopen($sql,'rb');
            $gzip=gzopen($archive,'wb6');
            if(!$input||!$gzip)throw new RuntimeException('Backup compression failed');
            if(gzwrite($gzip,'-- MonitorClient DB Backup v2 database='.DB_NAME.' hmac='.$signature."\n")===false)
                throw new RuntimeException('Backup header write failed');
            while(!feof($input)){
                $block=fread($input,1048576);
                if($block===false)throw new RuntimeException('Backup read failure');
                if($block!=='' && gzwrite($gzip,$block)===false)
                    throw new RuntimeException('Backup compression failed');
            }
            fclose($input);
            gzclose($gzip);
            return $archive;
        }catch(Throwable $error){
            @unlink($archive);
            throw $error;
        }finally{
            @unlink($credentials);
            @unlink($sql);
        }
    }

    public function download_backup(): void {
        $this->requirePost();
        try{
            $file=$this->generateBackup();
        }catch(Throwable $error){
            error_log('Database backup failure: '.$error->getMessage());
            http_response_code(503);
            exit('Backup could not be generated. Verify mysqldump and PHP temporary storage.');
        }
        // Bind the restore permission to the exact archive delivered, not
        // merely the fact that this browser generated some backup recently.
        $checksum=hash_file('sha256',$file);
        if(!is_string($checksum)){
            @unlink($file);
            http_response_code(503);
            exit('Backup verification failed. Try downloading again.');
        }
        $_SESSION['monitor_backup_at']=time();
        $_SESSION['monitor_backup_sha256']=$checksum;
        session_write_close();
        // Even if a browser aborts this download, remove the temporary dump.
        register_shutdown_function(static function() use ($file){@unlink($file);});
        $name='monitoring-db-'.gmdate('Ymd-His').'.sql.gz';
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="'.$name.'"');
        header('Content-Length: '.filesize($file));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, private');
        readfile($file);
        @unlink($file);
        exit;
    }

    public function restore_backup(): void {
        $this->requirePost();
        if(($_POST['confirm']??'')!=='RESTORE DATABASE' ||
           ($_POST['acknowledge']??'')!=='yes' ||
           time()-(int)($_SESSION['monitor_backup_at']??0)>7200){
            http_response_code(400);
            exit('Restore requires confirmation and a backup generated in the last two hours.');
        }
        $upload=$_FILES['backup']??null;
        if(!is_array($upload) || ($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK ||
           !is_uploaded_file((string)($upload['tmp_name']??'')) ||
           ($upload['size']??0)<=0 || ($upload['size']??0)>self::ARCHIVE_MAX ||
           !str_ends_with(strtolower((string)($upload['name']??'')),'.sql.gz')){
            http_response_code(400);
            exit('Upload a valid .sql.gz backup (check PHP upload size limits).');
        }
        set_time_limit(0);
        $sql=$this->tempPath('monitor-restore-');
        $credentials=null;
        try{
            $compressed=gzopen($upload['tmp_name'],'rb');
            if(!$compressed)throw new RuntimeException('Invalid gzip archive');
            $output=fopen($sql,'wb');
            if(!$output)throw new RuntimeException('Temporary storage unavailable');
            $size=0;
            $first='';
            while(!gzeof($compressed)){
                $data=gzread($compressed,1048576);
                if($data===false)throw new RuntimeException('Unable to read gzip archive');
                $size+=strlen($data);
                if($size>self::SQL_MAX)throw new RuntimeException('Backup exceeds expanded size limit');
                if(strlen($first)<256)$first.=substr($data,0,256-strlen($first));
                if($data!=='' && fwrite($output,$data)!==strlen($data))
                    throw new RuntimeException('Insufficient restore staging space');
            }
            gzclose($compressed);
            fclose($output);
            $header=explode("\n",$first,2)[0]."\n";
            $v2Prefix='-- MonitorClient DB Backup v2 database='.DB_NAME.' hmac=';
            $v1Header='-- MonitorClient DB Backup v1 database='.DB_NAME."\n";
            if(str_starts_with($header,$v2Prefix) &&
               preg_match('/^'.preg_quote($v2Prefix,'/').'([a-f0-9]{64})\n$/D',$header,$match)){
                // Authenticate older as well as new signed archives. Never
                // run SQL from a renamed or modified gzip file.
                $signature=hash_init('sha256',HASH_HMAC,$this->backupSigningKey());
                hash_update($signature,DB_NAME."\n");
                $sqlInput=fopen($sql,'rb');
                if(!$sqlInput)throw new RuntimeException('Could not validate backup');
                try{
                    if(fgets($sqlInput)!==$header ||
                       hash_update_stream($signature,$sqlInput)===false)
                        throw new RuntimeException('Backup verification failed');
                }finally{fclose($sqlInput);}
                if(!hash_equals($match[1],hash_final($signature)))
                    throw new RuntimeException('Backup authentication failed');
            }elseif($header===$v1Header){
                // Transitional compatibility: legacy unsigned archives may
                // only be restored if byte-identical to this session's backup.
                $checksum=(string)($_SESSION['monitor_backup_sha256']??'');
                $actual=hash_file('sha256',(string)$upload['tmp_name']);
                if(!preg_match('/^[a-f0-9]{64}$/D',$checksum) ||
                   !is_string($actual) || !hash_equals($checksum,$actual))
                    throw new RuntimeException('Unsigned backup does not match the current session download');
            }else{
                throw new RuntimeException('Not an authenticated backup for this monitoring database');
            }
            $credentials=$this->defaultsFile();
            // Warning: SQL restore is not transactional for schema DDL. A
            // partially failed restore requires recovery from the prior backup.
            $this->runMysql([
                'mysql','--defaults-extra-file='.$credentials,
                '--default-character-set=utf8mb4',DB_NAME
            ],$sql,null);
            // Base::redirect() calls exit, which may bypass the finally block.
            // Never leave a SQL dump or a MySQL password file on disk.
            @unlink($sql);
            @unlink($credentials);
            $credentials=null;
            // A restored users table can invalidate this session.
            session_unset();
            session_regenerate_id(true);
            $_SESSION['csrf_token']=bin2hex(random_bytes(32));
            $_SESSION['success_message']='Database restored. Sign in again to reload current account data.';
            $this->redirect('auth/login');
        }catch(Throwable $error){
            error_log('Database restore failure: '.$error->getMessage());
            http_response_code(503);
            exit('Restore failed. The database may be partially restored. Recover from the downloaded backup. Check server logs.');
        }finally{
            @unlink($sql);
            if($credentials!==null)@unlink($credentials);
        }
    }
}
