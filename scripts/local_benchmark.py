#!/usr/bin/env python3
"""Bounded sequential local-only HTTP liveness benchmark, not a production load test."""
import argparse
import http.client
import json
import math
import sys
import time
from urllib.parse import urlsplit

def validate_target(url):
    try:
        p = urlsplit(url)
        port = p.port
    except ValueError as exc:
        raise ValueError("Invalid URL or port") from exc
    if (p.scheme != "http" or p.hostname != "127.0.0.1" or
        p.username is not None or p.password is not None or
        p.path != "/up" or p.query or p.fragment or
        not port or not 1024 <= port <= 65535):
        raise ValueError("Only http://127.0.0.1:<nonprivileged-port>/up is permitted")
    return port

def percentile(values, fraction):
    if not values:
        raise ValueError("No samples")
    i = (len(values) - 1) * fraction
    low, high = math.floor(i), math.ceil(i)
    return values[low] + (values[high] - values[low]) * (i - low)

def probe(port, timeout):
    connection = http.client.HTTPConnection("127.0.0.1", port, timeout=timeout)
    started = time.perf_counter()
    try:
        connection.request("GET", "/up", headers={"Accept": "text/html"})
        response = connection.getresponse()
        response.read(4096)
        return response.status, (time.perf_counter() - started) * 1000
    finally:
        connection.close()

def benchmark(url, count=10, timeout=2.0):
    port = validate_target(url)
    if not isinstance(count, int) or not 1 <= count <= 50:
        raise ValueError("count must be between 1 and 50")
    if not 0.1 <= timeout <= 5.0:
        raise ValueError("timeout must be between 0.1 and 5.0 seconds")
    samples = []
    failures = 0
    for _ in range(count):
        try:
            status, ms = probe(port, timeout)
            if 200 <= status < 300:
                samples.append(ms)
            else:
                failures += 1
        except (OSError, http.client.HTTPException, TimeoutError):
            failures += 1
    samples.sort()
    return {
        "endpoint": "/up", "requests": count,
        "successes": len(samples), "failures": failures,
        "p50_ms": round(percentile(samples, 0.50), 2) if samples else None,
        "p95_ms": round(percentile(samples, 0.95), 2) if samples else None,
        "p99_ms": round(percentile(samples, 0.99), 2) if samples else None,
        "note": "Local liveness samples only; not application throughput or production SLA.",
    }

def main():
    parser = argparse.ArgumentParser(description="Low-impact loopback-only TaskForge /up latency probe")
    parser.add_argument("--url", default="http://127.0.0.1:8080/up")
    parser.add_argument("--count", type=int, default=10)
    parser.add_argument("--timeout", type=float, default=2.0)
    args = parser.parse_args()
    try:
        report = benchmark(args.url, args.count, args.timeout)
    except ValueError as exc:
        parser.error(str(exc))
    print(json.dumps(report, indent=2))
    return 0 if report["failures"] == 0 else 1

if __name__ == "__main__":
    sys.exit(main())
