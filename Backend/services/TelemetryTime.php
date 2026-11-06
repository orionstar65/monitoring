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

    // Qt's ISODate for LocalTime and older clients may omit the offset and
    // use either T or a space. Treat ambiguous legacy values as UTC; modern
    // clients always provide Z or an explicit offset.
    //
    // Accept RFC 3339 (including fractional seconds), Qt ISODate, and the
    // historic "YYYY-MM-DD HH:MM:SS" payload without guessing PHP's timezone.
    if(!preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?(Z|[+-]\d{2}:?\d{2})?$/D',
        $wireTime,$parts))return null;

    $normalized=$parts[1].'T'.$parts[2];
    $fraction=$parts[3]??'';
    $offset=$parts[4]??'';
    if($fraction!=='')$normalized.='.'.$fraction;
    if($offset!==''){
        // PHP's P parser accepts Z and colonized offsets.
        if($offset!=='Z' && !str_contains($offset,':'))
            $offset=substr($offset,0,3).':'.substr($offset,3);
        $normalized.=$offset;
    }

    $format='!Y-m-d\TH:i:s';
    if($fraction!=='')$format.='.u';
    if($offset!=='')$format.='P';
    $utc=new DateTimeZone('UTC');
    $parsed=DateTimeImmutable::createFromFormat($format,$normalized,$utc);
    $errors=DateTimeImmutable::getLastErrors();
    if(!$parsed||($errors!==false && ($errors['warning_count']>0||$errors['error_count']>0)))
        return null;
    return $parsed->setTimezone($utc);
}

/**
 * Endpoints accept explicit UTC instants start_utc (inclusive) and
 * end_utc (exclusive). Date-only start_date/end_date remain supported for
 * old clients and are interpreted as UTC calendar dates, not server-local.
 * Browsers must convert their local calendar dates to ISO instants first.
 * Returns [startUTC|null, endUTC|null]; throws for invalid ranges.
 */
function monitorUtcBounds(array $query): array {
    $utc=new DateTimeZone('UTC');
    $bounds=[];
    foreach(['start_utc','end_utc'] as $key){
        $value=trim((string)($query[$key]??''));
        if($value===''){$bounds[$key]=null;continue;}
        if(!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{3})?Z$/D',$value))
            throw new InvalidArgumentException('Invalid '.$key.' (expected UTC ISO-8601)');
        $parsed=DateTimeImmutable::createFromFormat(
            str_contains($value,'.')?'!Y-m-d\TH:i:s.v\Z':'!Y-m-d\TH:i:s\Z',$value,$utc);
        $errors=DateTimeImmutable::getLastErrors();
        if(!$parsed || ($errors!==false&&($errors['warning_count']||$errors['error_count'])))
            throw new InvalidArgumentException('Invalid '.$key);
        $bounds[$key]=$parsed;
    }
    if($bounds['start_utc']===null && !empty($query['start_date'])){
        $bounds['start_utc']=monitorUtcLegacyDay((string)$query['start_date'],$utc);
    }
    if($bounds['end_utc']===null && !empty($query['end_date'])){
        $bounds['end_utc']=monitorUtcLegacyDay((string)$query['end_date'],$utc)->modify('+1 day');
    }
    if($bounds['start_utc']!==null && $bounds['end_utc']!==null
       && $bounds['end_utc']<=$bounds['start_utc'])
        throw new InvalidArgumentException('End of range must follow start');
    return [
        $bounds['start_utc']?->format('Y-m-d H:i:s'),
        $bounds['end_utc']?->format('Y-m-d H:i:s')
    ];
}

function monitorUtcLegacyDay(string $day,DateTimeZone $utc): DateTimeImmutable {
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$day))
        throw new InvalidArgumentException('Invalid calendar day');
    $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$day,$utc);
    if(!$parsed || $parsed->format('Y-m-d')!==$day)
        throw new InvalidArgumentException('Invalid calendar day');
    return $parsed;
}
