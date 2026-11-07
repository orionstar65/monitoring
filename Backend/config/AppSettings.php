<?php
declare(strict_types=1);

/**
 * Safe, typed allowlist of operational settings. Secrets, database credentials,
 * release storage roots and arbitrary PHP/SQL paths are intentionally excluded.
 * Existing sites retain these defaults until migration has been applied.
 */
final class AppSettings {
    public const TABLE='app_settings';

    /** Key => [section, label, type, default, minimum, maximum]. */
    public static function catalog(): array {
        $c=[
            'interval.screenshot.seconds'=>['Client intervals','Screenshot capture','int',60,5,3600],
            'interval.heartbeat.seconds'=>['Client intervals','Heartbeat upload','int',300,10,3600],
            'interval.browser.seconds'=>['Client intervals','Browser history upload','int',300,30,86400],
            'interval.keyboard.seconds'=>['Client intervals','Keyboard log upload','int',300,5,3600],
            'interval.usb.seconds'=>['Client intervals','USB events upload','int',300,10,86400],
            'interval.config_refresh.seconds'=>['Client intervals','Configuration refresh','int',300,30,86400],
            'feature.screenshot.enabled'=>['Client features','Screenshots','bool',true,null,null],
            'feature.browser_history.enabled'=>['Client features','Browser history','bool',true,null,null],
            'feature.keyboard.enabled'=>['Client features','Keyboard logging','bool',true,null,null],
            'feature.usb.enabled'=>['Client features','USB event logging','bool',true,null,null],
            'feature.debug_logging.enabled'=>['Client features','Client debug logging','bool',false,null,null],
            'feature.auto_update.enabled'=>['Client features','Automatic updates allowed','bool',false,null,null],
            'server.max_retry_attempts'=>['Client network','Maximum retry attempts','int',3,1,20],
            'server.connection_timeout.seconds'=>['Client network','Connection timeout','int',10,1,120],
            'server.request_timeout.seconds'=>['Client network','Request timeout','int',30,2,300],
            'server.chunk_size'=>['Client network','Log upload batch size','int',1000,50,10000],
            'server.compression.enabled'=>['Client network','Use upload compression','bool',true,null,null],
            'logging.api_requests.enabled'=>['Backend','API request metadata logging','bool',true,null,null],
            'retention.default_months'=>['Backend','Default database cleanup period (months)','int',3,1,120]
        ];
        // Optional OS-specific interval overrides; empty rows mean global.
        foreach(['ubuntu'=>'Ubuntu','windows'=>'Windows','macos'=>'macOS'] as $os=>$label){
            foreach([
                'screenshot'=>['Screenshot capture',5,3600],
                'heartbeat'=>['Heartbeat upload',10,3600],
                'browser'=>['Browser history upload',30,86400],
                'keyboard'=>['Keyboard log upload',5,3600],
                'usb'=>['USB events upload',10,86400],
                'config_refresh'=>['Configuration refresh',30,86400]
            ] as $kind=>$info){
                $c["platform.$os.interval.$kind.seconds"]=["$label overrides",$info[0],'optional_int',null,$info[1],$info[2]];
            }
        }
        return $c;
    }

    public static function normalize(string $key,mixed $input): ?string {
        $entry=self::catalog()[$key]??null;
        if(!$entry || !is_scalar($input))return null;
        $s=trim((string)$input);
        if($entry[2]==='optional_int' && $s==='')return '';
        if($entry[2]==='bool') {
            if($s==='1' || $s==='0')return $s;
            return null;
        }
        if(!preg_match('/^[0-9]{1,8}$/D',$s))return null;
        $n=(int)$s;
        return $n>=$entry[4] && $n<=$entry[5] ? (string)$n : null;
    }

    /** One DB read per request; unavailable/unmigrated DB falls back safely. */
    public static function values(PDO|mysqli|null $db=null): array {
        $definitions=self::catalog();
        $values=[];
        foreach($definitions as $key=>$entry)$values[$key]=$entry[3];
        if(!$db)return $values;
        try{
            if($db instanceof PDO){
                $rows=$db->query('SELECT setting_key,setting_value FROM '.self::TABLE)->fetchAll(PDO::FETCH_ASSOC);
            }else{
                $res=$db->query('SELECT setting_key,setting_value FROM '.self::TABLE);
                if(!$res)return $values;
                $rows=$res->fetch_all(MYSQLI_ASSOC);
                $res->free();
            }
            foreach($rows as $row){
                $key=(string)$row['setting_key'];
                if(!isset($definitions[$key]))continue;
                $raw=self::normalize($key,$row['setting_value']);
                if($raw===null || $raw==='')continue;
                $values[$key]=$definitions[$key][2]==='bool' ? $raw==='1' : (int)$raw;
            }
        }catch(Throwable $e){
            // A new migration must not bring down telemetry or the portal.
            error_log('[AppSettings] Database settings unavailable; using defaults: '.$e->getMessage());
        }
        return $values;
    }
    public static function value(PDO|mysqli|null $db,string $key): mixed {
        $values=self::values($db);
        return $values[$key]??null;
    }
}
