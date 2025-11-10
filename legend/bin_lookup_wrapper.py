#!/usr/bin/env python3
"""
Simple wrapper for BIN lookup to return JSON for PHP integration
"""

import sys
import json
from bin_lookup import get_card_info_from_cc

def main():
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'CC number required'}))
        sys.exit(1)
    
    cc_number = sys.argv[1]
    
    try:
        card_info = get_card_info_from_cc(cc_number)
        print(json.dumps(card_info))
    except Exception as e:
        print(json.dumps({'error': str(e)}))
        sys.exit(1)

if __name__ == '__main__':
    main()
