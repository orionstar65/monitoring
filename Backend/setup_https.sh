#!/usr/bin/env bash
# Enable HTTPS on the existing ACC-Monitor Apache virtual host (Ubuntu/Debian).
# LAN: sudo bash Backend/setup_https.sh --host 192.168.2.241
# Publicly trusted certificate already installed:
#   sudo bash Backend/setup_https.sh --host monitor.example.com --mode existing \
#        --cert /etc/letsencrypt/live/monitor.example.com/fullchain.pem \
#        --key /etc/letsencrypt/live/monitor.example.com/privkey.pem
set -Eeuo pipefail
umask 077

usage() {
cat <<'HELP'
Usage: sudo bash Backend/setup_https.sh [options]

Options:
  --host HOST         Exact LAN IPv4 address or DNS name clients will use
                      (default: detected primary IPv4 address)
  --port PORT         Existing Apache backend port (default: 8924)
  --mode lan          Create/reuse a private CA and issue a server certificate (default)
  --mode existing     Use an existing trusted TLS certificate and key
  --cert FILE         Full-chain PEM certificate for --mode existing
  --key FILE          Private key PEM for --mode existing
  --vhost FILE        Apache vhost file (default: /etc/apache2/sites-available/mybackend.conf)
  -h, --help          Show this help

LAN mode: install the generated PUBLIC CA certificate on each client device.
Never distribute the CA private key. A private CA is not automatically trusted.
HTTPS replaces HTTP on the SAME port. HTTP URLs on that port will stop working.
HELP
}
die(){ echo "ERROR: $*" >&2; exit 1; }
need(){ command -v "$1" >/dev/null 2>&1 || die "Missing $1. Install it first."; }
[[ "$#" -eq 0 || "$1" != "-h" && "$1" != "--help" ]] || { usage; exit 0; }
[[ "$EUID" -eq 0 ]] || die "Run as root (sudo bash ...)."

host=""
port=8924
mode=lan
cert_source=""
key_source=""
vhost=/etc/apache2/sites-available/mybackend.conf
while (($#)); do
  case "$1" in
    --host) (($# >= 2)) || die "--host requires a value"; host="$2"; shift 2;;
    --port) (($# >= 2)) || die "--port requires a value"; port="$2"; shift 2;;
    --mode) (($# >= 2)) || die "--mode requires a value"; mode="$2"; shift 2;;
    --cert) (($# >= 2)) || die "--cert requires a value"; cert_source="$2"; shift 2;;
    --key) (($# >= 2)) || die "--key requires a value"; key_source="$2"; shift 2;;
    --vhost) (($# >= 2)) || die "--vhost requires a value"; vhost="$2"; shift 2;;
    -h|--help) usage; exit 0;;
    *) die "Unknown option: $1";;
  esac
done
[[ "$mode" == lan || "$mode" == existing ]] || die "--mode must be lan or existing"
[[ "$port" =~ ^[0-9]+$ ]] && ((10#$port >= 1 && 10#$port <= 65535)) || die "Invalid --port"
need python3; need openssl; need apache2ctl; need a2enmod; need systemctl
[[ -f "$vhost" ]] || die "Existing Apache vhost not found: $vhost. Configure Apache with Backend/setup_ubuntu_server.sh first."
if grep -q '# BEGIN ACC DUAL HTTP' "$vhost"; then
  die "The site now uses HTTP 8924 + HTTPS 8925. Use setup_dual_protocol.sh; do not run the single-port HTTPS installer."
fi

if [[ -z "$host" ]]; then
  host="$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{
    for(i=1;i<=NF;i++) if($i=="src") {print $(i+1); exit}
  }')"
  [[ -n "$host" ]] || die "Could not detect IPv4. Pass --host 192.168.x.x"
  echo "Detected server host: $host (override with --host if clients use a different address)"
fi
# Validate host and get OpenSSL subjectAltName type: never interpolate unchecked input.
san_type="$(python3 - "$host" <<'PY'
import ipaddress, re, sys
host=sys.argv[1]
try:
    ip=ipaddress.ip_address(host)
    if ip.version != 4: sys.exit(2)
    print("IP")
except ValueError:
    if len(host)>253 or not re.fullmatch(r"[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?",host):
        sys.exit(2)
    if any(not 0<len(label)<=63 or label.startswith("-") or label.endswith("-") for label in host.split(".")):
        sys.exit(2)
    print("DNS")
PY
)" || die "Host must be a valid IPv4 address or DNS name"
if [[ "$mode" == existing ]]; then
  [[ -r "$cert_source" && -r "$key_source" ]] || die "Both --cert and --key must be readable"
else
  [[ -z "$cert_source" && -z "$key_source" ]] || die "--cert/--key are only allowed with --mode existing"
fi

tls_dir=/etc/monitoring/tls
mkdir -p "$tls_dir/private" "$tls_dir/ca" "$tls_dir/backups"
chmod 700 "$tls_dir" "$tls_dir/private" "$tls_dir/backups"
chmod 755 "$tls_dir/ca"
ca_cert="$tls_dir/ca/monitoring-lan-root.crt"
ca_key="$tls_dir/private/monitoring-lan-root.key"
server_cert="$tls_dir/monitoring-server.crt"
server_key="$tls_dir/private/monitoring-server.key"
work="$(mktemp -d "$tls_dir/private/build.XXXXXXXX")"
backup=""
modified=0
finished=0
cleanup(){
  local status=$?
  if (( status != 0 && modified == 1 && finished == 0 )); then
    echo "Restoring the previous Apache vhost after failure..." >&2
    cp -p "$backup" "$vhost" || true
    apache2ctl configtest >&2 || true
    systemctl reload apache2 >&2 || true
  fi
  rm -rf "$work"
}
trap cleanup EXIT

if [[ "$mode" == lan ]]; then
  if [[ -e "$ca_cert" && ! -e "$ca_key" || ! -e "$ca_cert" && -e "$ca_key" ]]; then
    die "CA is incomplete. Preserve the existing CA identity; do not create a new CA blindly."
  fi
  if [[ ! -e "$ca_cert" ]]; then
    echo "Creating private LAN certificate authority (CA)..."
    cat > "$work/ca.cnf" <<'CONF'
[ req ]
distinguished_name = dn
x509_extensions = ca_ext
prompt = no
[ dn ]
CN = ACC Monitoring LAN Root CA
O = ACC Monitoring LAN
[ ca_ext ]
basicConstraints = critical, CA:TRUE, pathlen:0
keyUsage = critical, keyCertSign, cRLSign
subjectKeyIdentifier = hash
CONF
    openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out "$ca_key" >/dev/null 2>&1
    openssl req -new -x509 -sha256 -days 3650 -key "$ca_key" \
      -config "$work/ca.cnf" -out "$ca_cert"
  else
    echo "Reusing existing LAN CA; previously trusted clients do not need another CA."
  fi
  chmod 600 "$ca_key"
  chmod 644 "$ca_cert"
  echo "Issuing TLS certificate for $host..."
  cat > "$work/server.cnf" <<CONF
[ server_ext ]
basicConstraints = critical, CA:FALSE
keyUsage = critical, digitalSignature, keyEncipherment
extendedKeyUsage = serverAuth
subjectAltName = ${san_type}:$host
authorityKeyIdentifier = keyid,issuer
subjectKeyIdentifier = hash
CONF
  openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out "$work/server.key" >/dev/null 2>&1
  openssl req -new -sha256 -key "$work/server.key" \
    -subj "/CN=$host/O=ACC Monitoring" -out "$work/server.csr"
  openssl x509 -req -in "$work/server.csr" -CA "$ca_cert" -CAkey "$ca_key" \
    -CAcreateserial -sha256 -days 365 -extfile "$work/server.cnf" \
    -extensions server_ext -out "$work/server.crt" >/dev/null 2>&1
  install -o root -g root -m 0600 "$work/server.key" "$server_key"
  install -o root -g root -m 0644 "$work/server.crt" "$server_cert"
else
  echo "Installing existing certificate and private key securely..."
  # Both certificate and key must have the same public key; reject mismatches.
  openssl x509 -in "$cert_source" -noout >/dev/null || die "Invalid certificate"
  openssl pkey -in "$key_source" -noout >/dev/null || die "Invalid private key"
  cert_pub="$(openssl x509 -in "$cert_source" -pubkey -noout | openssl pkey -pubin -outform DER | openssl dgst -sha256)"
  key_pub="$(openssl pkey -in "$key_source" -pubout -outform DER | openssl dgst -sha256)"
  [[ "$cert_pub" == "$key_pub" ]] || die "Certificate and private key do not match"
  install -o root -g root -m 0600 "$key_source" "$server_key"
  install -o root -g root -m 0644 "$cert_source" "$server_cert"
fi
openssl x509 -in "$server_cert" -checkend 0 -noout >/dev/null || die "Server certificate is expired"
if [[ "$mode" == lan ]]; then
  openssl verify -CAfile "$ca_cert" "$server_cert" >/dev/null || die "LAN certificate failed CA verification"
fi
if [[ "$san_type" == IP ]]; then
  openssl x509 -in "$server_cert" -noout -checkip "$host" | grep -q 'does match' \
    || die "Certificate does not contain IP SAN: $host"
else
  openssl x509 -in "$server_cert" -noout -checkhost "$host" | grep -q 'does match' \
    || die "Certificate does not contain DNS SAN: $host"
fi
echo "Enabling Apache TLS module..."
a2enmod ssl headers >/dev/null

# Preserve the existing vhost's DocumentRoot, Directory allowlists and logs.
# Only inject the marked TLS block into the existing virtual host.
backup="$tls_dir/backups/mybackend.conf.$(date -u +%Y%m%dT%H%M%SZ).$$"
cp -p "$vhost" "$backup"
python3 - "$vhost" "$port" "$host" "$server_cert" "$server_key" <<'PY'
from pathlib import Path
import re, sys
vhost, port, host, cert, key = sys.argv[1:]
path=Path(vhost)
text=path.read_text()
pattern=re.compile(r'(<VirtualHost\s+[^>]*:' + re.escape(port) + r'\s*>)(.*?)(</VirtualHost>)',re.I|re.S)
matches=list(pattern.finditer(text))
if len(matches)!=1:
    raise SystemExit(f"Expected exactly one Apache VirtualHost for port {port}, found {len(matches)}")
m=matches[0]
body=m.group(2)
begin="# BEGIN MONITORING HTTPS"
end="# END MONITORING HTTPS"
if begin in body:
    body=re.sub(r'\n\s*'+re.escape(begin)+r'.*?'+re.escape(end), '', body, flags=re.S)
elif re.search(r'^\s*(SSLEngine|SSLCertificateFile|SSLCertificateKeyFile)\b',body,re.I|re.M):
    raise SystemExit("Existing unmanaged SSL settings found. Refusing to replace them.")
block=f"""
    {begin}
    ServerName {host}
    SSLEngine on
    SSLProtocol -all +TLSv1.2 +TLSv1.3
    SSLCertificateFile "{cert}"
    SSLCertificateKeyFile "{key}"
    Header always set X-Content-Type-Options "nosniff"
    {end}
"""
text=text[:m.start()]+m.group(1)+block+body+m.group(3)+text[m.end():]
path.write_text(text)
PY
modified=1
echo "Validating Apache configuration..."
apache2ctl configtest
echo "Reloading Apache..."
systemctl reload apache2
finished=1

url="https://$host:$port/html/auth/login"
echo ""
echo "HTTPS enabled: $url"
echo "Apache vhost backed up at: $backup"
if [[ "$mode" == lan ]]; then
  echo "Public CA cert for client devices: $ca_cert"
  echo "NEVER copy/share this PRIVATE CA key: $ca_key"
  echo "Windows/macOS/Ubuntu MUST trust the public CA cert before clients can connect."
  echo "To verify locally: curl --cacert '$ca_cert' -I '$url'"
else
  echo "Using your supplied certificate; renew it with your CA and rerun this script as necessary."
  echo "Verify: curl -I '$url'"
fi
echo "HTTP on port $port is now replaced by HTTPS. Do not use http:// on that port."
echo "If you still get a PHP 500 or database error, verify MONITOR_DB_* settings separately."
