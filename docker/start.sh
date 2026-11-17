#!/bin/sh
set -eu
PORT="${PORT:-80}"
case "$PORT" in *[!0-9]*|'') echo 'PORT must be numeric' >&2; exit 1;; esac
if [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then echo 'PORT out of range' >&2; exit 1; fi
export PORT
printf 'Listen %s\n' "$PORT" > /etc/apache2/ports.conf

# Log enabled MPM files before correcting the standard Debian module selection.
# These paths contain module names only; do not log environment variables.
echo 'Apache startup: MPM module files before selection:' >&2
for module in /etc/apache2/mods-enabled/mpm_*.load; do
    [ ! -e "$module" ] || ls -l "$module" >&2
done
a2dismod -f mpm_event mpm_worker
a2enmod mpm_prefork

# Test exactly the configuration and environment that apache2-foreground will use.
# On failure, preserve Apache's diagnostic and show where MPM directives appear.
if ! modules="$(apache2-foreground -t -D DUMP_MODULES 2>&1)"; then
    printf 'Apache startup configuration test failed:\n%s\n' "$modules" >&2
    echo 'Files containing MPM LoadModule directives:' >&2
    grep -ilE '^[[:space:]]*LoadModule[[:space:]]+mpm_[[:alnum:]_]+_module' \
        /etc/apache2/apache2.conf /etc/apache2/mods-enabled/*.load \
        /etc/apache2/conf-enabled/*.conf /etc/apache2/sites-enabled/*.conf 1>&2 2>/dev/null || :
    exit 1
fi
printf '%s\n' "$modules" | awk '/mpm_.*_module/ {print "Apache startup: " $0; count++; if ($1 != "mpm_prefork_module") bad=1} END {if (count != 1 || bad) {print "Apache startup: expected exactly one prefork MPM" > "/dev/stderr"; exit 1}}'
exec apache2-foreground
