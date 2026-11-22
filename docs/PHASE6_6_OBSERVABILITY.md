# Phase 6.6 — Observability and benchmark baseline

**Status: local-only utility and plan, not production performance certification.**

## Safe baseline measurement

Run only against your own disposable, isolated Laravel development instance listening on loopback:

```bash
python3 -m unittest discover -s tests/ci -p 'test_local_benchmark.py' -v
python3 scripts/local_benchmark.py --url http://127.0.0.1:8080/up --count 10 --timeout 2
```

This script restricts requests to literal `127.0.0.1` on a nonprivileged port and the fixed `GET /up` path, rejects credentials, query strings, redirects and other hosts, caps samples at 50, sends sequential requests only, and bounds each request timeout. It does not log in, mutate data, send mail, run load against staging/production, or probe real task APIs. Its latency percentiles reflect **local liveness endpoint HTTP observations only**, not task API performance, throughput, user experience or production SLO compliance.

These benchmark tests use synthetic mocks and make no network requests. Benchmark executions have not been performed against any running application in this phase.

## Observability inventory (requires separate infrastructure work)

- Capture sanitized HTTP request rate, 4xx/5xx, and route-group latency (no user identifiers, query tokens, invitation tokens, task titles or email addresses).
- Capture database health, pool saturation, slow-query frequency and storage; restrict credential access and never expose slow-query parameters in telemetry.
- Monitor Redis memory, evictions, queue oldest-job age, failed jobs, retry rates, worker restarts and scheduler heartbeat.
- Track TLS expiry, backup age, offsite encrypted backup integrity and successful isolated restore evidence.
- Record a reproducible benchmark environment: CPU, memory, Docker and PHP versions, MySQL dataset size (synthetic), test duration, sample size, P50/P95/P99, error counts, measurement timestamp and CI commit.
- Establish actual thresholds only after staged measurement, and assign alert owner and incident response procedures before any production deployment.

## Release gates

- [ ] PR CI green and safety checks reviewed
- [ ] Generate and review authentic `package-lock.json`; remove `npm install` fallback after `npm ci` migration
- [ ] Complete dependency audits, including separately open PR #23
- [ ] Run broader API benchmarks only on explicit approved nonproduction infrastructure using disposable test data
- [ ] Run isolated backup restore drill and document measured RPO/RTO
- [ ] Review access controls, environment protection and branch rulesets
- [ ] Obtain explicit approval for production deployment and `main` merge

No public monitoring endpoint, production test traffic, deployment, real email sending or database reset is authorized by this phase.
