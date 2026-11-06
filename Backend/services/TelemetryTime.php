<?php
/**
 * Telemetry transport time contract:
 *   - Clients send an ISO-8601 timestamp with a UTC offset.
 *   - Persist DATETIME values and media directory names in UTC.
 *   - Older clients' timezone-less timestamps are interpreted as UTC.
 *
 * Display and filtering use browser-local calendar days converted to UTC.
 * Do not change date_default_timezone_set(): unrelated legacy APIs rely
 * on their existing date handling.
 */
function monitorCaptureTimeUTC(string $wireTime): ?DateTimeImmutable {
    $wireTime=trim($wireTime);
    if($wireTime==='')return null;
    $utc=new DateTimeZone('UTC');

    // Explicit offset, including the Z form accepted by PHP's P specifier.
    if(preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D',$wireTime)){
        $normalized=str_replace(' ','T',$wireTime);
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:sP',$normalized,$utc);
        $errors=DateTimeImmutable::getLastErrors();
        if(!$parsed || ($errors!==false && ($errors['warning_count']>0 || $errors['error_count']>0)))return null;
        return $parsed->setTimezone($utc);
    }

    // Legacy v1.3/v1.5 uploaders sent a bare datetime. Treat as UTC, not
    // the server's configurable PHP default timezone.
    if(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$wireTime)){
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$wireTime,$utc);
        return $parsed && $parsed->format('Y-m-d H:i:s')===$wireTime ? $parsed:null;
    }
    return null;
}
