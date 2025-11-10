# Quick Start Guide - Concurrent Card Checking

## How to Use the New Concurrent Checking Feature

### Step 1: Access the Card Checker
Navigate to `card_checker.php` in your browser.

### Step 2: Configure Concurrency
Look for the **"Concurrency Limit"** input field in the form.
- Set a value between **1-20** (recommended: **10-15**)
- Higher values = faster checking (but may stress the API)
- Lower values = more stable (recommended for slow connections)

### Step 3: Add Your Cards
Paste your cards in the format:
```
4111111111111111|12|2025|123
5500000000000004|01|2026|456
4242424242424242|06|2027|789
```

### Step 4: Add Site URLs
Paste one or more site URLs:
```
https://example.com/checkout
https://shop.example.com/cart
```

### Step 5: (Optional) Add Proxy
If using a proxy, enter it in the format:
```
ip:port:username:password
```
Example:
```
123.45.67.89:8080:proxyuser:proxypass
```

### Step 6: Start Checking
Click the **"Start Check"** button (Play button).

### Step 7: Monitor Progress
Watch the real-time metrics:
- **Total Cards**: Total number of cards in the batch
- **Charged**: Cards that were successfully charged
- **Live**: Cards that are valid/approved
- **Dead**: Cards that were declined
- **Pending**: Cards waiting to be checked
- **Active**: Currently processing cards

**Performance Metrics:**
- **Progress Bar**: Visual completion indicator
- **Cards/sec**: Real-time processing speed
- **Elapsed**: Time since check started
- **ETA**: Estimated time to completion

### Step 8: View Results
Results are automatically categorized into three tabs:
- **Charged Tab**: Successfully charged cards
- **Live Tab**: Approved/valid cards
- **Dead Tab**: Declined/invalid cards

### Step 9: Export Results (Optional)
Each tab has two buttons:
- **Copy All**: Copy all results to clipboard
- **Download**: Download results as a text file

## Tips for Best Performance

### Optimal Concurrency Settings
| Cards to Check | Recommended Concurrency |
|----------------|------------------------|
| 1-10 cards     | 3-5                   |
| 10-50 cards    | 8-12                  |
| 50-100 cards   | 12-15                 |
| 100+ cards     | 15-20                 |

### Performance Examples
With concurrency set to **10**:
- 10 cards: ~6-10 seconds
- 50 cards: ~20-35 seconds
- 100 cards: ~40-70 seconds

With concurrency set to **20**:
- 10 cards: ~3-5 seconds
- 50 cards: ~12-20 seconds
- 100 cards: ~25-40 seconds

*Actual times depend on API response times and network speed*

## Visual Indicators

### Active Checks Counter
Shows a spinning icon (🔄) when checks are running.

### Progress Bar
- **Green gradient bar** fills from left to right
- **Percentage** displayed above the bar
- Updates in real-time as cards are processed

### Color Coding
- **Green** (✅): Charged cards
- **Cyan** (ℹ️): Live/Approved cards
- **Red** (❌): Dead/Declined cards
- **Orange** (⏳): Pending cards

## Troubleshooting

### Checks are slow
- **Reduce concurrency limit** to 5-8
- Check your internet connection
- Verify the API is responding

### Some cards show JS_ERROR
- Normal for network issues
- These cards count as declined
- Credits are still deducted
- Try re-checking failed cards

### Progress stuck at 99%
- Wait a few more seconds
- Last few requests may take longer
- System will auto-complete when done

### Stop button not working
- Button disables immediately on click
- Active requests will complete first
- May take 5-10 seconds to fully stop

## Credit Usage

- **1 credit per card** checked
- Credits deducted **after** successful check
- Failed API calls still deduct credits (to prevent abuse)
- Check your remaining credits in the dashboard

## History

All checks are automatically saved to history:
- Click **"View History"** button
- Load previous card/site combinations
- Review past check results
- Delete old history entries

## Advanced Features

### Batch API (For Developers)
Use the new batch endpoint for custom implementations:

```javascript
fetch('check_card_batch.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    cards: ['4111111111111111|12|2025|123'],
    sites: ['https://example.com'],
    proxy: '123.45.67.89:8080:user:pass',
    concurrent: 15
  })
})
.then(response => response.json())
.then(data => {
  console.log('Results:', data);
});
```

## FAQ

**Q: What's the maximum concurrency?**
A: 20 concurrent checks, but 10-15 is recommended for stability.

**Q: Can I check multiple sites per card?**
A: Yes, the system randomly selects a site from your list for each card.

**Q: Will this use more credits?**
A: No, still 1 credit per card regardless of concurrency.

**Q: What if I have slow internet?**
A: Lower the concurrency to 3-5 for better stability.

**Q: Can I stop a check in progress?**
A: Yes, click the Stop button. Active requests will complete first.

**Q: Are results saved?**
A: Yes, all checks are logged to the database and local history.

**Q: What happens if the API is down?**
A: Individual cards will show errors, but others continue processing.

---

**Need Help?** Contact support or check the documentation.

**Pro Tip**: Start with low concurrency (5) and increase gradually to find your optimal setting!
