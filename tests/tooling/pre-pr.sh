#!/usr/bin/env bash
# `make pre-pr` on a fresh clone (DiluxOne/.github#479): it runs every check
# of the pull request and the merge queue, starts and stops what it needs,
# fails when one check fails, and passes twice in a row from the same state.
#
#   bash tests/tooling/pre-pr.sh            the whole test (about three runs)
#   bash tests/tooling/pre-pr.sh dry        only what `make -n pre-pr` names
#
# It clones this checkout (what is committed, nothing of the working tree)
# into a temporary directory, with the remote branches, so the clone's
# BASE_REF (origin/main) is this one's. Needs what `make pre-pr` needs:
# Docker, Node.js, GNU make, git and python3.
set -euo pipefail

here=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
mode=${1:-all}
fails=0

ok() { echo "ok   $1"; }
ko() { echo "FAIL $1"; fails=$((fails + 1)); }

# The ports of the environments pre-pr starts: its own network (8902/8903)
# and the Plugin Check one (8900/8901).
left_running() {
	docker ps --format '{{.Names}} {{.Ports}}' | grep -E ':(8900|8901|8902|8903)->' || true
}

none_left() { # <when>
	local left
	left=$(left_running)
	if [ -z "$left" ]; then ok "no pre-pr or Plugin Check container left running $1"; else ko "containers left running $1: $left"; fi
}

clone=$(mktemp -d "${TMPDIR:-/tmp}/wpmus-pre-pr.XXXXXX")
cleanup() {
	# The environments are the clone's: never leave them behind, whatever failed.
	if [ -f "$clone/Makefile" ]; then make -C "$clone" --no-print-directory pre-pr-down plugin-check-down >/dev/null 2>&1 || true; fi
	# Root-owned files a container wrote cannot always be removed by the user.
	rm -rf "$clone" 2>/dev/null || docker run --rm -v "$(dirname "$clone"):/t" alpine rm -rf "/t/$(basename "$clone")" || true
}
trap cleanup EXIT

git clone -q --no-hardlinks "$here" "$clone"
git -C "$clone" fetch -q "$here" '+refs/remotes/origin/*:refs/remotes/origin/*'

# 1. What `make -n pre-pr` names: every item of the kind's `pre-pr` list
# (kinds/<kind>/pack.yml in DiluxOne/.github, `pre-pr.py covers`), each by
# the commands that check it. An item of the list this test does not know
# fails, so a new one in the pack is not passed over.
dry=$(make -C "$clone" --no-print-directory -n pre-pr)
make -C "$clone" --no-print-directory central >/dev/null
covers=$(python3 "$clone/build/.dx-central/scripts/pre-pr.py" covers "$clone")
[ -n "$covers" ] && ok "the kind's pre-pr list is read" || ko "the kind's pre-pr list is empty"

needs() { # <item>: the patterns `make -n pre-pr` must show for it, one per line
	case "$1" in
		lint*) printf '%s\n' 'make[^;]* lint\b' 'design-lint' ;;
		static\ analysis*) printf '%s\n' 'make[^;]* stan\b' 'make[^;]* psalm\b' ;;
		unit\ tests*) printf '%s\n' 'make[^;]* test-unit\b' 'make[^;]* test-unit-min\b' ;;
		translations*) printf '%s\n' 'make[^;]* i18n-form\b' 'make[^;]* i18n-check\b' 'make[^;]* i18n-pot\b' 'make[^;]* i18n-mo\b' ;;
		Plugin\ Check*) printf '%s\n' 'make[^;]* plugin-check\b' ;;
		integration\ tests*) printf '%s\n' 'make[^;]* pre-pr-env\b' 'phpunit -c phpunit-integration\.xml' ;;
		every\ E2E\ suite*) printf '%s\n' 'CI=1 [^;]*npx playwright test;' 'CI=1 [^;]*test-e2e-single' ;;
		the\ review\ rules*) printf '%s\n' 'make[^;]* review-rules\b' ;;
		*) return 1 ;;
	esac
}

while IFS= read -r item; do
	[ -n "$item" ] || continue
	if ! patterns=$(needs "$item"); then ko "the pack's item '$item' is one this test does not know: map it in needs()"; continue; fi
	missing=
	while IFS= read -r pattern; do
		grep -qE -- "$pattern" <<<"$dry" || missing="$missing /$pattern/"
	done <<<"$patterns"
	[ -z "$missing" ] && ok "make -n pre-pr names: $item" || ko "make -n pre-pr does not name, for '$item':$missing"
done <<<"$covers"

# What the pull request runs beside the pack's list: syntax on every PHP,
# readme.txt and versions, the changelog fragments, the version markers, the
# docs job.
for pattern in 'make[^;]* syntax\b' 'make[^;]* readme-check\b' 'make[^;]* changelog-check\b' 'make[^;]* markers-check\b' 'make[^;]* docs-check\b' 'trap stop EXIT'; do
	grep -qE -- "$pattern" <<<"$dry" && ok "make -n pre-pr names /$pattern/" || ko "make -n pre-pr does not name /$pattern/"
done

# A step PRE_PR_FAIL does not know is refused, not ignored.
if make -C "$clone" --no-print-directory pre-pr PRE_PR_FAIL=no-such-step >/dev/null 2>&1; then ko "PRE_PR_FAIL=no-such-step passed"; else ok "PRE_PR_FAIL with an unknown step fails"; fi

if [ "$mode" = dry ]; then
	echo; [ "$fails" -eq 0 ] && echo "all dry checks passed" || echo "$fails failed"
	exit $(( fails > 0 ))
fi

# 2. One check forced to fail, once both environments are up: pre-pr exits
# non-zero and leaves nothing running.
log=$(mktemp)
if make -C "$clone" --no-print-directory pre-pr PRE_PR_FAIL=integration >"$log" 2>&1; then
	ko "make pre-pr PRE_PR_FAIL=integration passed"; tail -40 "$log"
else
	grep -q 'failed on purpose (PRE_PR_FAIL=integration)' "$log" && ok "make pre-pr fails when one check fails (integration)" \
		|| { ko "make pre-pr failed before the forced step"; tail -60 "$log"; }
fi
none_left "after the failed run"

# 3. The whole run on the fresh clone, then again right after in the same
# clone: the trap left it as the run found it (C2).
for run in first second; do
	if make -C "$clone" --no-print-directory pre-pr >"$log" 2>&1; then ok "make pre-pr passes ($run run)"; else ko "make pre-pr failed ($run run)"; tail -80 "$log"; fi
	# A suite that exits 0 having run nothing is no pass: each says what ran.
	section() { sed -n "/^▶ $1\$/,/^▶ /p" "$log"; }
	section integration | grep -qE '^OK \([0-9]+ tests' && ok "the integration suite ran ($run run)" || ko "the integration suite reported no tests ($run run)"
	section e2e | grep -qE '^ +[0-9]+ passed' && ok "the E2E network suite ran ($run run)" || ko "the E2E network suite reported nothing passed ($run run)"
	section e2e-single | grep -qE '^ +[0-9]+ passed' && ok "the single-site check ran ($run run)" || ko "the single-site check reported nothing passed ($run run)"
	none_left "after the $run run"
done
rm -f "$log"

echo
[ "$fails" -eq 0 ] && echo "all tests passed" || echo "$fails failed"
exit $(( fails > 0 ))
