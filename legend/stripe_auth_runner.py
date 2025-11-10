#!/usr/bin/env python3
"""
Stripe Auth Runner
Lightweight wrapper around stripe_auth_checker.auth that prints JSON only.
This is designed for web integrations that need machine-readable output
without the verbose logging produced by the CLI workflow.
"""

import json
import sys
from typing import Any, Dict, Optional


def build_error(message: str, status: str = "ERROR") -> Dict[str, Any]:
    """
    Build a standard error payload so callers always receive valid JSON.
    """
    return {
        "success": False,
        "status": status,
        "message": message,
        "account_email": None,
        "pm_id": None,
        "raw_response": "",
        "raw_response_json": None,
        "status_code": 0,
    }


def run_auth(domain: str, cc_string: str, proxy: Optional[str]) -> Dict[str, Any]:
    """
    Execute the auth flow and normalise the result.
    """
    try:
        from stripe_auth_checker import auth
    except Exception as exc:  # pragma: no cover - import-time failure
        return build_error(f"Failed to import stripe_auth_checker: {exc}")

    try:
        result = auth(domain, cc_string, proxy)
    except Exception as exc:
        return build_error(f"Exception: {exc}")

    if not isinstance(result, dict):  # Safety: ensure dict response
        return build_error("Auth function returned unexpected response type.")

    # Ensure essential keys exist
    result.setdefault("success", False)
    result.setdefault("status", "UNKNOWN")
    result.setdefault("message", "")
    result.setdefault("account_email", None)
    result.setdefault("pm_id", None)
    result.setdefault("raw_response", "")
    result.setdefault("raw_response_json", None)
    result.setdefault("status_code", 0)

    # Attach domain for convenience
    result.setdefault("domain", domain)
    return result


def main(argv: Optional[list] = None) -> int:
    args = list(argv or sys.argv[1:])

    if len(args) < 2:
        error_payload = build_error(
            "Usage: stripe_auth_runner.py <domain> <cc_string> [proxy]",
            status="USAGE_ERROR",
        )
        print(json.dumps(error_payload, ensure_ascii=False))
        return 1

    domain = args[0].strip()
    cc_string = args[1].strip()
    proxy = args[2].strip() if len(args) > 2 and args[2] else None

    payload = run_auth(domain, cc_string, proxy)
    print(json.dumps(payload, ensure_ascii=False))
    # Always exit 0 so declines are not treated as execution failures
    return 0


if __name__ == "__main__":
    sys.exit(main())
