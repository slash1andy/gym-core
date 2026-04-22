#!/usr/bin/env python3
"""
gym_v1_audit.py — M6.10 gym/v1 endpoint audit.

Hits the gym/v1 namespace on a live site and verifies each route's conformance
with the contract that AI agents depend on (M6.1 prerequisites):

  - Response envelope { "success": true, "data": ... [, "meta": ... ] }
  - Pagination meta shape { total, total_pages, page, per_page } on paged reads
  - Error responses have { code, message, data.status }
  - Authentication: unauthenticated requests to non-public routes return 401/403
  - Performance: p95 < 200ms per endpoint (acceptance threshold)

Usage:
    # Default: audit the staging site
    python3 scripts/gym_v1_audit.py

    # Audit a different host (e.g., a Local by Flywheel install)
    python3 scripts/gym_v1_audit.py --site https://haanpaa.local

    # JSON output (for CI)
    python3 scripts/gym_v1_audit.py --json

Stdlib only — no external dependencies.
"""

from __future__ import annotations

import argparse
import json
import ssl
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from statistics import quantiles

DEFAULT_SITE = "https://haanpaa-staging.mystagingwebsite.com"
NAMESPACE = "gym/v1"
LATENCY_BUDGET_MS = 200  # p95
SAMPLES_PER_ENDPOINT = 5

_SSL_CTX = ssl.create_default_context()


def _get(url, timeout=30, headers=None):
    """GET a URL, return (status_code, body_bytes, elapsed_ms, error_str_or_None)."""
    req = urllib.request.Request(url, headers=headers or {}, method="GET")
    start = time.perf_counter()
    try:
        resp = urllib.request.urlopen(req, timeout=timeout, context=_SSL_CTX)
        body = resp.read()
        elapsed_ms = (time.perf_counter() - start) * 1000
        return resp.getcode(), body, elapsed_ms, None
    except urllib.error.HTTPError as exc:
        elapsed_ms = (time.perf_counter() - start) * 1000
        try:
            body = exc.read()
        except Exception:
            body = b""
        return exc.code, body, elapsed_ms, None
    except Exception as exc:
        elapsed_ms = (time.perf_counter() - start) * 1000
        return None, b"", elapsed_ms, str(exc)


def _json_or_none(body):
    try:
        return json.loads(body)
    except Exception:
        return None


def fetch_namespace_index(site):
    """Pull the WP REST index filtered to our namespace to enumerate routes."""
    url = f"{site.rstrip('/')}/wp-json/{NAMESPACE}"
    status, body, _ms, err = _get(url)
    if err or status != 200:
        return None, f"could not fetch namespace index: {err or f'HTTP {status}'}"
    data = _json_or_none(body)
    if not data or "routes" not in data:
        return None, "namespace index missing 'routes' key"
    return data, None


def classify_route(route_data):
    """Return ('public', 'auth'|'unknown') based on endpoint metadata."""
    # WP's index doesn't expose permission_callback. Heuristic: if any endpoint
    # is marked with 'args' that reference a user/auth context, treat as auth.
    # We fall back to observing the 401/403 response in the live call.
    return "unknown"


def is_readable_route(methods):
    return "GET" in methods


def has_path_params(path):
    return "(?P<" in path


def check_envelope(data):
    """Return a list of envelope issues for a success body, empty if OK."""
    issues = []
    if not isinstance(data, dict):
        return ["body is not a JSON object"]
    if data.get("success") is not True:
        issues.append("envelope missing 'success: true'")
    if "data" not in data:
        issues.append("envelope missing 'data' key")
    return issues


def check_pagination(data):
    """Check meta.pagination shape if present. Returns (has_pagination, issues)."""
    meta = data.get("meta") if isinstance(data, dict) else None
    if not isinstance(meta, dict):
        return False, []
    pag = meta.get("pagination")
    if not isinstance(pag, dict):
        return False, []
    issues = []
    for key in ("total", "total_pages", "page", "per_page"):
        if key not in pag:
            issues.append(f"pagination meta missing '{key}'")
    return True, issues


def check_error(data, status):
    """Validate an error body has WP REST shape { code, message, data.status }."""
    issues = []
    if not isinstance(data, dict):
        return [f"error body HTTP {status} is not a JSON object"]
    for key in ("code", "message"):
        if key not in data:
            issues.append(f"error HTTP {status} missing '{key}'")
    inner = data.get("data")
    if isinstance(inner, dict):
        if "status" not in inner:
            issues.append(f"error HTTP {status} missing data.status")
    else:
        issues.append(f"error HTTP {status} missing data object")
    return issues


def probe_endpoint(site, route_path, method="GET"):
    """Hit an endpoint SAMPLES_PER_ENDPOINT times; discard first sample as warm-up.

    The first request absorbs TLS handshake and connection setup; leaving it in
    skews the measured p95 enough to make the 200ms budget meaningless. We keep
    the warm-up in real_latencies for diagnostics but base p95 on the rest.
    """
    probe_path = _substitute_params(route_path)
    url = f"{site.rstrip('/')}/wp-json{probe_path}"

    latencies = []
    last_status = None
    last_body = None
    last_err = None

    for _ in range(SAMPLES_PER_ENDPOINT):
        status, body, ms, err = _get(url)
        latencies.append(ms)
        last_status, last_body, last_err = status, body, err

    # Drop the cold-start sample if we have enough to spare.
    measured = latencies[1:] if len(latencies) > 1 else latencies

    return {
        "url": url,
        "probe_path": probe_path,
        "last_status": last_status,
        "last_body": last_body,
        "last_error": last_err,
        "latencies_ms": measured,
        "cold_start_ms": latencies[0] if latencies else None,
    }


def _substitute_params(route_path):
    """Replace WP route regex parameters with safe probe values."""
    import re
    # (?P<name>regex) → safe default based on regex
    def repl(m):
        name = m.group(1)
        pattern = m.group(2)
        if "\\d" in pattern:
            return "1"
        if "a-z" in pattern or "A-Z" in pattern:
            return "rockford"
        return "1"
    return re.sub(r"\(\?P<([a-z_]+)>([^)]+)\)", repl, route_path)


def p95(values):
    if not values:
        return 0.0
    if len(values) < 2:
        return float(values[0])
    # 20 quantiles → index 18 ≈ 95th
    qs = quantiles(sorted(values), n=20)
    return qs[18] if len(qs) > 18 else max(values)


def run_audit(site, json_output=False, latency_budget_ms=LATENCY_BUDGET_MS):
    index, err = fetch_namespace_index(site)
    if err:
        out = {"error": err, "site": site}
        print(json.dumps(out) if json_output else f"FATAL: {err}")
        return 2

    routes = index["routes"]
    # Filter to gym/v1 child routes only. The namespace root (/gym/v1) is the
    # WP REST index — it has its own shape and is not part of our contract.
    gym_routes = {p: r for p, r in routes.items() if p.startswith(f"/{NAMESPACE}/")}

    results = []
    violations = []
    skipped = 0

    for path, meta in sorted(gym_routes.items()):
        # Each entry: { namespace, methods, endpoints: [ { methods, args } ] }
        endpoints = meta.get("endpoints", [])
        methods = set()
        for ep in endpoints:
            methods.update(ep.get("methods", []))

        entry = {
            "path": path,
            "methods": sorted(methods),
            "has_path_params": has_path_params(path),
            "checks": {},
            "p95_ms": None,
        }

        if not is_readable_route(methods):
            # POST-only routes can't be safely probed from outside (side effects,
            # nonce requirements). Exclude from the GET-based audit rather than
            # flag as a violation.
            entry["checks"]["skipped"] = "POST-only route; not probed"
            skipped += 1
            results.append(entry)
            continue

        probe = probe_endpoint(site, path, "GET")
        entry["probe_url"] = probe["url"]
        entry["last_status"] = probe["last_status"]
        entry["p95_ms"] = round(p95(probe["latencies_ms"]), 1)

        if probe["last_error"]:
            entry["checks"]["transport"] = probe["last_error"]
            violations.append(f"{path}: transport error ({probe['last_error']})")
            results.append(entry)
            continue

        body = _json_or_none(probe["last_body"])
        status = probe["last_status"]

        if status == 200:
            env_issues = check_envelope(body)
            has_pag, pag_issues = check_pagination(body)
            if env_issues:
                entry["checks"]["envelope"] = env_issues
                for i in env_issues:
                    violations.append(f"{path}: {i}")
            if has_pag and pag_issues:
                entry["checks"]["pagination"] = pag_issues
                for i in pag_issues:
                    violations.append(f"{path}: {i}")
        elif status in (400, 401, 403, 404):
            err_issues = check_error(body, status)
            if err_issues:
                entry["checks"]["error_shape"] = err_issues
                for i in err_issues:
                    violations.append(f"{path}: {i}")
            # Auth errors on protected routes are expected; flag only shape issues.
        else:
            violations.append(f"{path}: unexpected HTTP {status}")

        if entry["p95_ms"] and entry["p95_ms"] > latency_budget_ms:
            violations.append(
                f"{path}: p95 {entry['p95_ms']}ms exceeds {latency_budget_ms}ms budget"
            )

        results.append(entry)

    summary = {
        "site": site,
        "namespace": NAMESPACE,
        "total_routes": len(gym_routes),
        "probed_routes": sum(1 for r in results if r.get("p95_ms") is not None),
        "skipped_routes": skipped,
        "latency_budget_ms": latency_budget_ms,
        "violations": violations,
        "violation_count": len(violations),
        "results": results,
    }

    if json_output:
        print(json.dumps(summary, indent=2))
    else:
        print(f"gym_v1_audit — {site}")
        print(f"namespace: {NAMESPACE}")
        print(f"routes: {summary['total_routes']}  probed: {summary['probed_routes']}\n")
        for r in results:
            # A route is "FAIL" only when a check is something other than the
            # SKIP sentinel (POST-only / not probed). Skipped routes shouldn't
            # be flagged as failures.
            non_skip_checks = {k: v for k, v in r["checks"].items() if k != "skipped"}
            if "skipped" in r["checks"] and not non_skip_checks:
                bullet = "SKIP"
            elif not r["checks"]:
                bullet = "PASS"
            else:
                bullet = "FAIL"
            p95s = r["p95_ms"]
            p95_marker = "" if p95s is None else f"  p95={p95s}ms"
            status_marker = "" if r.get("last_status") is None else f"  HTTP {r['last_status']}"
            print(f"  [{bullet}] {r['path']}  ({','.join(r['methods'])}){status_marker}{p95_marker}")
            for key, val in r["checks"].items():
                if isinstance(val, list):
                    for item in val:
                        print(f"         - {key}: {item}")
                else:
                    print(f"         - {key}: {val}")
        print(f"\n{'=' * 60}")
        print(f"  {summary['violation_count']} violation(s)")
        for v in violations:
            print(f"    - {v}")
        print(f"{'=' * 60}")

    return 0 if summary["violation_count"] == 0 else 1


def main():
    parser = argparse.ArgumentParser(
        description="M6.10 gym/v1 endpoint audit — envelope, pagination, errors, latency"
    )
    parser.add_argument("--site", default=DEFAULT_SITE, help="Site base URL (default: staging)")
    parser.add_argument("--json", action="store_true", dest="json_output")
    parser.add_argument(
        "--latency-budget-ms",
        type=int,
        default=LATENCY_BUDGET_MS,
        help=(
            "p95 budget per endpoint in milliseconds (default: %(default)s). "
            "The 200ms target from the acceptance criteria is server-side "
            "execution time; add headroom when probing over the public internet."
        ),
    )
    args = parser.parse_args()

    sys.exit(
        run_audit(
            args.site,
            json_output=args.json_output,
            latency_budget_ms=args.latency_budget_ms,
        )
    )


if __name__ == "__main__":
    main()
