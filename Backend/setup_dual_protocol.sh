#!/usr/bin/env bash
# HTTP on 8924 (restricted LAN); HTTPS on 8925. Does not touch the database.
set -Eeuo pipefail
umask 077
fail(){ echo "ERROR: $*" >&2;exit 1; }
[[ "$EUID" == 0 ]] || fail "Run using sudo"
host=""
subnet="192.168.2.0/24"
while (($#));do
 case "$1" in
 --host) [[ $# -ge 2 ]] || fail "Missing host";host="$2";shift 2;;
 --lan-cidr) [[ $# -ge 2 ]] || fail "Missing CIDR";subnet="$2";shift 2;;
 -h|--help) echo "Usage: sudo bash Backend/setup_dual_protocol.sh [--host SERVER_IP] [--lan-cidr 192.168.2.0/24]";exit 0;;
 *) fail "Unexpected argument: $1";;
 esac
done
for cmd in python3 openssl apache2ctl a2enmod systemctl;do command -v "$cmd" >/dev/null || fail "Missing $cmd";done
site=/etc/apache2/sites-available/mybackend.conf
ports=/etc/apache2/ports.conf
[[ -f "$site" && -f "$ports" ]] || fail "Backend Apache vhost not found"
if [[ -z "$host" ]];then host="$(hostname -I | awk '{print $1}')";fi
[[ -n "$host" ]] || fail "Specify --host"
python3 - "$host" "$subnet" <<'PY'
import ipaddress,re,sys
try:ipaddress.IPv4Network(sys.argv[2],strict=False)
except ValueError:raise SystemExit("Invalid LAN CIDR")
try:ipaddress.IPv4Address(sys.argv[1])
except ValueError:
 if len(sys.argv[1])>253 or not re.fullmatch("[A-Za-z0-9][A-Za-z0-9.-]*",sys.argv[1]):
  raise SystemExit("Invalid host")
PY
cert=/etc/monitoring/tls/monitoring-server.crt
key=/etc/monitoring/tls/private/monitoring-server.key
ca=/etc/monitoring/tls/ca/monitoring-lan-root.crt
if [[ ! -r "$cert" || ! -r "$key" || ! -r "$ca" ]];then
 echo "Creating a private LAN CA and HTTPS certificate..."
 bash "$(dirname "$0")/setup_https.sh" --host "$host" --port 8924
fi
if [[ "$host" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]];then
 openssl x509 -in "$cert" -noout -checkip "$host" | grep -q 'does match' || fail "Certificate does not match $host"
else
 openssl x509 -in "$cert" -noout -checkhost "$host" | grep -q 'does match' || fail "Certificate does not match $host"
fi
openssl x509 -in "$cert" -noout -checkend 86400 >/dev/null || fail "TLS certificate expired"
openssl verify -CAfile "$ca" "$cert" >/dev/null || fail "TLS certificate does not verify"
a2enmod ssl headers env rewrite >/dev/null
backup=/etc/monitoring/tls/backups
mkdir -p "$backup"
stamp="$(date -u +%Y%m%dT%H%M%S)-$$"
cp -p "$site" "$backup/site-$stamp"
cp -p "$ports" "$backup/ports-$stamp"
restore(){
 cp -p "$backup/site-$stamp" "$site" || true
 cp -p "$backup/ports-$stamp" "$ports" || true
 apache2ctl configtest >&2 || true
 systemctl reload apache2 >&2 || true
}
trap restore ERR
python3 - "$site" "$host" "$subnet" "$cert" "$key" <<'PY'
from pathlib import Path
import re,sys
path=Path(sys.argv[1]);host,subnet,cert,key=sys.argv[2:]
text=path.read_text()
pattern=re.compile(r'<VirtualHost\s+[^>]*:(8924|8925)\s*>(.*?)</VirtualHost>',re.I|re.S)
matches=list(pattern.finditer(text))
http=[m for m in matches if m.group(1)=="8924"]
secure=[m for m in matches if m.group(1)=="8925"]
if len(http)!=1 or len(secure)>1:raise SystemExit("Expected one vhost on 8924")
if secure and "# BEGIN ACC DUAL HTTPS" not in secure[0].group(2):
 raise SystemExit("Unmanaged port 8925 vhost; refusing to overwrite")
body=http[0].group(2)
for begin,end in [
 ("# BEGIN MONITORING HTTPS","# END MONITORING HTTPS"),
 ("# BEGIN ACC DUAL HTTP","# END ACC DUAL HTTP")]:
 body=re.sub(r'\n\s*'+re.escape(begin)+r'.*?'+re.escape(end),"",body,flags=re.S)
if re.search(r'^\s*(SSLEngine|SSLCertificateFile|SSLCertificateKeyFile)\b',body,re.I|re.M):
 raise SystemExit("Unmanaged SSL directives on port 8924")
include="IncludeOptional /etc/monitoring/apache-db.conf"
if include not in body:body+="\n    "+include+"\n"
hblock=f"""
    # BEGIN ACC DUAL HTTP
    SetEnv MONITOR_ALLOW_HTTP 1
    <Location />
      <RequireAny>
        Require ip 127.0.0.1
        Require ip 192.168.1.0/24
        Require ip 192.168.2.0/24
        Require ip {subnet}
      </RequireAny>
    </Location>
    # END ACC DUAL HTTP
"""
sblock=f"""
    # BEGIN ACC DUAL HTTPS
    ServerName {host}
    SSLEngine on
    SSLProtocol -all +TLSv1.2 +TLSv1.3
    SSLCertificateFile "{cert}"
    SSLCertificateKeyFile "{key}"
    # END ACC DUAL HTTPS
"""
replacement=f"<VirtualHost *:8924>{hblock}{body}\n</VirtualHost>\n<VirtualHost *:8925>{sblock}{body}\n</VirtualHost>"
for m in sorted([http[0]]+secure,key=lambda m:m.start(),reverse=True):
 text=text[:m.start()]+(replacement if m==http[0] else "")+text[m.end():]
path.write_text(text)
PY
if ! grep -Eq '^[[:space:]]*Listen[[:space:]]+8925([[:space:]]|$)' "$ports";then
 echo 'Listen 8925' >> "$ports"
fi
if command -v ufw >/dev/null && ufw status | grep -q 'Status: active';then
 ufw allow from "$subnet" to any port 8924 proto tcp >/dev/null
 ufw allow from "$subnet" to any port 8925 proto tcp >/dev/null
fi
apache2ctl configtest
systemctl reload apache2
trap - ERR
echo "HTTP:  http://$host:8924/html/ (LAN only)"
echo "HTTPS: https://$host:8925/html/"
echo "Trust the public LAN CA on clients: $ca"
echo "If login says Database service unavailable: sudo bash Backend/setup_database_access.sh"
echo "WARNING: HTTP transmits credentials and monitoring data unencrypted."
