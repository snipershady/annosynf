#!/usr/bin/env bash
# Wrapper host del container legacy-runner (TODO.md fase 0). Uso:
#   tools/legacy-runner/run.sh build
#   tools/legacy-runner/run.sh <comando del container> [argomenti]   (vedi entrypoint.sh)
#
# I file di input si passano con percorsi relativi alla root del progetto: la root è montata in sola
# lettura su /in e la directory degli output (LEGACY_RUNNER_OUT, default var/legacy-runner) su /out.
set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
legacy_dir="${ANNOCRIPT_LEGACY_DIR:-/home/shady/public_html/Annocript}"
out_dir="${LEGACY_RUNNER_OUT:-$project_dir/var/legacy-runner}"
image="annosynf/legacy-runner:2.0.1"

if [[ ! -d "$legacy_dir/LIB" ]]; then
    echo "Legacy Annocript not found in $legacy_dir (set ANNOCRIPT_LEGACY_DIR)." >&2
    exit 1
fi

if [[ "${1:-}" == "build" ]]; then
    exec docker buildx build --load --build-context "legacy=$legacy_dir" -t "$image" "$project_dir/tools/legacy-runner"
fi

mkdir -p "$out_dir"
tty_flags=()
[[ -t 0 && -t 1 ]] && tty_flags=(-it)

exec docker run --rm "${tty_flags[@]}" \
    --user "$(id -u):$(id -g)" \
    --network none \
    -v "$legacy_dir:/legacy:ro" \
    -v "$project_dir:/in:ro" \
    -v "$out_dir:/out" \
    -w /in \
    "$image" "$@"
