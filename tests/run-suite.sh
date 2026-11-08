#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORK="$(mktemp -d)"
chmod 0700 "$WORK"
cleanup() {
  rm -rf "$WORK"
}
trap cleanup EXIT

PHPUNIT_PHAR="$WORK/phpunit-4.8.36.phar"
PHPUNIT_ASC="$WORK/phpunit-4.8.36.phar.asc"
EXPECTED_SHA="ab8bc3017a64cf75a2112f5e089a1d5b0c4cc2116556d60c1af5e3f584e85c5e"
EXPECTED_FPR="D8406D0D82947747293778314AA394086372C20A"

curl -fsSL -o "$PHPUNIT_PHAR" https://phar.phpunit.de/phpunit-4.8.36.phar
curl -fsSL -o "$PHPUNIT_ASC" https://phar.phpunit.de/phpunit-4.8.36.phar.asc

ACTUAL_SHA="$(sha256sum "$PHPUNIT_PHAR" | awk '{print $1}')"
if [ "$ACTUAL_SHA" != "$EXPECTED_SHA" ]; then
  echo "PHPUnit phar SHA-256 mismatch" >&2
  exit 1
fi

GNUPGHOME="$(mktemp -d)"
chmod 0700 "$GNUPGHOME"
export GNUPGHOME
gpg --batch --keyserver hkps://keyserver.ubuntu.com --recv-keys "$EXPECTED_FPR"
VERIFY_OUT="$(gpg --batch --status-fd 1 --verify "$PHPUNIT_ASC" "$PHPUNIT_PHAR" 2>&1)"
echo "$VERIFY_OUT" | grep -q "VALIDSIG $EXPECTED_FPR"
rm -rf "$GNUPGHOME"
unset GNUPGHOME

DOCKER_BIN="${DOCKER_BIN:-docker}"
if [ "${USE_SUDO_DOCKER:-0}" = "1" ]; then
  if command -v sudo >/dev/null 2>&1; then
    DOCKER_BIN="sudo docker"
  fi
fi
if ! command -v docker >/dev/null 2>&1; then
  echo "docker not found in PATH (set USE_SUDO_DOCKER=1 only when needed)" >&2
  exit 1
fi

PHP_IMAGE="statssearch-php56"
MARIADB_IMAGE="mariadb@sha256:78f6411897a1971b6b55b975cda6ff6fea1e405d10f500451662cfc08699d709"
NETWORK="statssearch-net"
MARIADB_NAME="statssearch-mariadb"

if ! $DOCKER_BIN image inspect "$PHP_IMAGE" >/dev/null 2>&1; then
  $DOCKER_BIN build --platform linux/amd64 -t "$PHP_IMAGE" -f "$ROOT/tests/docker/php56.Dockerfile" "$ROOT/tests/docker"
fi

DB_HOST="mariadb"
DB_PORT="3306"
PHP_NETWORK_ARGS=(--network "$NETWORK")
PROBE_PHP='mysqli_report(MYSQLI_REPORT_OFF); $m = mysqli_init(); if (!$m) { exit(1); } mysqli_options($m, MYSQLI_OPT_CONNECT_TIMEOUT, 3); $ok = @mysqli_real_connect($m, getenv("STATSSEARCH_DB_HOST"), getenv("STATSSEARCH_DB_USER"), getenv("STATSSEARCH_DB_PASSWORD"), getenv("STATSSEARCH_DB_NAME"), (int) getenv("STATSSEARCH_DB_PORT")); if (!$ok) { exit(1); } exit(0);'

wait_mariadb_ready() {
  local index
  for index in $(seq 1 30); do
    if $DOCKER_BIN exec "$MARIADB_NAME" mariadb-admin ping -ustatssearch -pstatssearch --silent >/dev/null 2>&1; then
      return 0
    fi
    sleep 1
  done
  return 1
}

if ! $DOCKER_BIN network inspect "$NETWORK" >/dev/null 2>&1; then
  $DOCKER_BIN network create "$NETWORK"
fi

$DOCKER_BIN rm -f "$MARIADB_NAME" >/dev/null 2>&1 || true
$DOCKER_BIN run -d --name "$MARIADB_NAME" --hostname mariadb --network-alias mariadb --network "$NETWORK" \
  --platform linux/amd64 \
  -e MARIADB_DATABASE=statssearch \
  -e MARIADB_USER=statssearch \
  -e MARIADB_PASSWORD=statssearch \
  -e MARIADB_ROOT_PASSWORD=statssearch-root \
  "$MARIADB_IMAGE"

if ! wait_mariadb_ready; then
  echo "MariaDB did not become ready within 30 seconds" >&2
  exit 1
fi

probe_ok=0
if $DOCKER_BIN run --rm --network "$NETWORK" --platform linux/amd64 \
  -e STATSSEARCH_DB_HOST=mariadb \
  -e STATSSEARCH_DB_PORT=3306 \
  -e STATSSEARCH_DB_NAME=statssearch \
  -e STATSSEARCH_DB_USER=statssearch \
  -e STATSSEARCH_DB_PASSWORD=statssearch \
  "$PHP_IMAGE" \
  php -d error_reporting=-1 -r "$PROBE_PHP" >/dev/null 2>&1; then
  probe_ok=1
fi

if [ "$probe_ok" -ne 1 ]; then
  HOST_DB_PORT="${STATSSEARCH_HOST_DB_PORT:-3307}"
  $DOCKER_BIN rm -f "$MARIADB_NAME" >/dev/null 2>&1 || true
  $DOCKER_BIN run -d --name "$MARIADB_NAME" \
    --platform linux/amd64 \
    -p "127.0.0.1:${HOST_DB_PORT}:3306" \
    -e MARIADB_DATABASE=statssearch \
    -e MARIADB_USER=statssearch \
    -e MARIADB_PASSWORD=statssearch \
    -e MARIADB_ROOT_PASSWORD=statssearch-root \
    "$MARIADB_IMAGE"

  if ! wait_mariadb_ready; then
    echo "MariaDB host fallback did not become ready within 30 seconds" >&2
    exit 1
  fi

  DB_HOST="127.0.0.1"
  DB_PORT="$HOST_DB_PORT"
  PHP_NETWORK_ARGS=(--network host)
fi

verify_ok=0
for _ in $(seq 1 30); do
  if $DOCKER_BIN run --rm "${PHP_NETWORK_ARGS[@]}" --platform linux/amd64 \
    -e STATSSEARCH_DB_HOST="$DB_HOST" \
    -e STATSSEARCH_DB_PORT="$DB_PORT" \
    -e STATSSEARCH_DB_NAME=statssearch \
    -e STATSSEARCH_DB_USER=statssearch \
    -e STATSSEARCH_DB_PASSWORD=statssearch \
    "$PHP_IMAGE" \
    php -d error_reporting=-1 -r "$PROBE_PHP" >/dev/null 2>&1; then
    verify_ok=1
    break
  fi
  sleep 1
done
if [ "$verify_ok" -ne 1 ]; then
  echo "Database connectivity verification failed for ${DB_HOST}:${DB_PORT}" >&2
  exit 1
fi

$DOCKER_BIN run --rm "${PHP_NETWORK_ARGS[@]}" --platform linux/amd64 \
  -v "$ROOT":/src:ro \
  -v "$WORK":/work \
  -e STATSSEARCH_DB_HOST="$DB_HOST" \
  -e STATSSEARCH_DB_PORT="$DB_PORT" \
  -e STATSSEARCH_DB_NAME=statssearch \
  -e STATSSEARCH_DB_USER=statssearch \
  -e STATSSEARCH_DB_PASSWORD=statssearch \
  -e STATSSEARCH_PHPUNIT=/work/phpunit-4.8.36.phar \
  -e STATSSEARCH_WORK_DIR=/work \
  "$PHP_IMAGE" \
  php -d error_reporting=-1 /src/tests/run-suite.php
