#!/usr/bin/env python3
"""
Test Script for Stripe Auth Checker
Validates all components and provides diagnostic information
"""

import sys
import json
from datetime import datetime

def test_imports():
    """Test if all required modules can be imported"""
    print("=" * 60)
    print("TESTING IMPORTS")
    print("=" * 60)
    
    try:
        import requests
        print(f"✅ requests module installed (version {requests.__version__})")
    except ImportError as e:
        print(f"❌ requests module missing: {e}")
        print("   Install: pip3 install requests")
        return False
    
    try:
        from stripe_auth_checker import StripeAuthChecker, parse_cc_string, validate_luhn, auth
        print("✅ stripe_auth_checker module imported successfully")
    except ImportError as e:
        print(f"❌ stripe_auth_checker import failed: {e}")
        return False
    
    try:
        from bin_lookup import get_card_info_from_cc, format_card_info_for_response
        print("✅ bin_lookup module imported successfully")
    except ImportError as e:
        print(f"❌ bin_lookup import failed: {e}")
        return False
    
    print("\n✅ All imports successful!\n")
    return True


def test_cc_parsing():
    """Test CC string parsing functionality"""
    print("=" * 60)
    print("TESTING CC PARSING")
    print("=" * 60)
    
    from stripe_auth_checker import parse_cc_string
    
    test_cases = [
        ("4111111111111111|12|2025|123", "Standard pipe format"),
        ("4111111111111111|12|25|123", "2-digit year format"),
        ("4111111111111111 12 2025 123", "Space-separated format"),
        ("4111111111111111122025123", "Digits only (25 total)"),
    ]
    
    all_passed = True
    for cc_string, description in test_cases:
        try:
            cc, mm, yyyy, cvv = parse_cc_string(cc_string)
            print(f"✅ {description}")
            print(f"   Input: {cc_string}")
            print(f"   Output: CC={cc[:4]}...{cc[-4:]}, MM={mm}, YYYY={yyyy}, CVV={'*' * len(cvv)}")
        except Exception as e:
            print(f"❌ {description}")
            print(f"   Input: {cc_string}")
            print(f"   Error: {e}")
            all_passed = False
    
    print(f"\n{'✅' if all_passed else '❌'} CC Parsing tests {'passed' if all_passed else 'failed'}!\n")
    return all_passed


def test_luhn_validation():
    """Test Luhn algorithm validation"""
    print("=" * 60)
    print("TESTING LUHN VALIDATION")
    print("=" * 60)
    
    from stripe_auth_checker import validate_luhn
    
    test_cases = [
        ("4111111111111111", True, "Valid Visa test card"),
        ("5555555555554444", True, "Valid Mastercard test card"),
        ("1234567890123456", False, "Invalid card number"),
    ]
    
    all_passed = True
    for cc, expected, description in test_cases:
        result = validate_luhn(cc)
        status = "✅" if result == expected else "❌"
        print(f"{status} {description}: {cc[:4]}...{cc[-4:]} - {'Valid' if result else 'Invalid'}")
        if result != expected:
            all_passed = False
    
    print(f"\n{'✅' if all_passed else '❌'} Luhn validation tests {'passed' if all_passed else 'failed'}!\n")
    return all_passed


def test_bin_lookup():
    """Test BIN lookup functionality"""
    print("=" * 60)
    print("TESTING BIN LOOKUP")
    print("=" * 60)
    
    try:
        from bin_lookup import get_card_info_from_cc, format_card_info_for_response
        
        test_cc = "4111111111111111|12|2025|123"
        print(f"Testing with card: {test_cc}")
        
        card_info = get_card_info_from_cc(test_cc)
        print(f"\n Card Info:")
        print(f"  Type: {card_info.get('type', 'Unknown')}")
        print(f"  Bank: {card_info.get('bank', 'Unknown')}")
        print(f"  Country: {card_info.get('country', 'Unknown')}")
        
        formatted = format_card_info_for_response(card_info)
        print(f"\n Formatted output:{formatted}")
        
        print("\n✅ BIN lookup working!\n")
        return True
    except Exception as e:
        print(f"❌ BIN lookup failed: {e}\n")
        return False


def test_stripe_checker_init():
    """Test StripeAuthChecker initialization"""
    print("=" * 60)
    print("TESTING STRIPE CHECKER INITIALIZATION")
    print("=" * 60)
    
    try:
        from stripe_auth_checker import StripeAuthChecker
        
        # Test without proxy
        print("Testing without proxy...")
        checker1 = StripeAuthChecker("example.com")
        print(f"✅ Domain: {checker1.domain}")
        print(f"✅ Session initialized")
        
        # Test with proxy
        print("\nTesting with proxy...")
        checker2 = StripeAuthChecker("example.com", "192.168.1.1:8080:user:pass")
        print(f"✅ Proxy configured")
        
        print("\n✅ Stripe checker initialization working!\n")
        return True
    except Exception as e:
        print(f"❌ Initialization failed: {e}\n")
        return False


def test_full_checker_dry_run():
    """Test the full checker in dry run mode (no actual requests)"""
    print("=" * 60)
    print("TESTING FULL CHECKER (DRY RUN)")
    print("=" * 60)
    
    try:
        from stripe_auth_checker import parse_cc_string, validate_luhn, is_stripe_rejected_card, validate_expiry
        
        test_cc = "4111111111111111|12|2025|123"
        print(f"Test card: {test_cc}")
        
        # Parse
        cc, mm, yyyy, cvv = parse_cc_string(test_cc)
        print(f"✅ Parsed: CC={cc[:4]}...{cc[-4:]}, MM={mm}, YYYY={yyyy}, CVV={'*' * len(cvv)}")
        
        # Validate Luhn
        if validate_luhn(cc):
            print(f"✅ Luhn validation passed")
        else:
            print(f"❌ Luhn validation failed")
            return False
        
        # Check if Stripe rejects
        if is_stripe_rejected_card(cc):
            print(f"⚠️  This card pattern is rejected by Stripe")
        else:
            print(f"✅ Card pattern accepted by Stripe")
        
        # Validate expiry
        is_valid, error = validate_expiry(mm, yyyy)
        if is_valid:
            print(f"✅ Expiry validation passed")
        else:
            print(f"❌ Expiry validation failed: {error}")
            return False
        
        print("\n✅ All validation checks passed!\n")
        return True
    except Exception as e:
        print(f"❌ Dry run failed: {e}\n")
        import traceback
        traceback.print_exc()
        return False


def main():
    """Run all tests"""
    print("\n" + "=" * 60)
    print("STRIPE AUTH CHECKER - DIAGNOSTIC TEST SUITE")
    print("=" * 60)
    print(f"Test started at: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("=" * 60 + "\n")
    
    results = {
        'imports': test_imports(),
        'cc_parsing': False,
        'luhn': False,
        'bin_lookup': False,
        'initialization': False,
        'dry_run': False
    }
    
    # Only run other tests if imports succeeded
    if results['imports']:
        results['cc_parsing'] = test_cc_parsing()
        results['luhn'] = test_luhn_validation()
        results['bin_lookup'] = test_bin_lookup()
        results['initialization'] = test_stripe_checker_init()
        results['dry_run'] = test_full_checker_dry_run()
    
    # Summary
    print("=" * 60)
    print("TEST SUMMARY")
    print("=" * 60)
    
    for test_name, passed in results.items():
        status = "✅ PASS" if passed else "❌ FAIL"
        print(f"{status}: {test_name.replace('_', ' ').title()}")
    
    all_passed = all(results.values())
    
    print("=" * 60)
    if all_passed:
        print("✅ ALL TESTS PASSED - Stripe Auth Checker is functional!")
        print("\nYou can now use it with:")
        print("  python3 stripe_auth_checker.py <domain> <cc|mm|yyyy|cvv> [proxy]")
        print("\nExample:")
        print("  python3 stripe_auth_checker.py example.com 4111111111111111|12|2025|123")
    else:
        print("❌ SOME TESTS FAILED - Review errors above")
        failed_tests = [name for name, passed in results.items() if not passed]
        print(f"\nFailed tests: {', '.join(failed_tests)}")
    print("=" * 60)
    
    return all_passed


if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)
