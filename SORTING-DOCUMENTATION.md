# Research Team Manager - Custom Sorting Documentation

## Overview
This plugin provides advanced sorting options for team member Query Loop blocks, allowing you to sort by join dates, leave dates, custom order, and priority overrides.

## Available Sorting Options

### 1. **Default WordPress Sorting**
- `orderBy: "date"` - Sort by publish date
- `orderBy: "title"` - Sort by member name
- `orderBy: "rand"` - Random order

### 2. **Custom Sorting Options**

#### Start Date Sorting (`orderBy: "start_date"`)
Sorts team members by when they joined the team.
- Members WITH start dates appear first (sorted by date)
- Members WITHOUT start dates appear after (sorted by publish date)
- Use `order: "desc"` for newest members first
- Use `order: "asc"` for oldest members first

#### End Date Sorting (`orderBy: "end_date"` or `orderBy: "left_date"`)
Sorts team members by when they left the team.
- Members WITH end dates appear first (sorted by date)
- Members WITHOUT end dates appear after (sorted by publish date)
- Useful for Alumni sections

#### Custom Order Sorting (`orderBy: "custom_order"`)
Sorts by the Display Order field.
- Lower numbers appear first
- Allows complete manual control over ordering

#### Date Priority Sorting (`orderBy: "date_priority"`)
Advanced sorting with priority overrides.
- Members with "Priority Override" checked appear FIRST (sorted by Display Order)
- Non-prioritized members appear AFTER (sorted by start date)
- Perfect for featuring PIs or team leads while maintaining chronological order for others

## How to Use in Query Loop Blocks

### Method 1: Edit Block as HTML
1. Select your Query Loop block
2. Click the three dots menu (⋮) → "Edit as HTML"
3. Find `"orderBy":"date"` in the code
4. Replace with your desired sorting option (e.g., `"orderBy":"start_date"`)
5. Switch back to visual editor

### Method 2: Direct Code Editing
Edit your template/page HTML directly:

```html
<!-- Current Members sorted by join date (newest first) -->
<!-- wp:query {"queryId":18,"query":{"perPage":69,"pages":0,"offset":0,"postType":"team_member","order":"desc","orderBy":"start_date","taxQuery":{"member_status":[6]}},"className":"rtm-team-member-loop"} -->

<!-- Alumni sorted by when they left (most recent first) -->
<!-- wp:query {"queryId":19,"query":{"perPage":20,"pages":0,"offset":0,"postType":"team_member","order":"desc","orderBy":"end_date","taxQuery":{"member_status":[7,8]}},"className":"rtm-team-member-loop"} -->

<!-- PIs with priority sorting -->
<!-- wp:query {"queryId":20,"query":{"perPage":10,"pages":0,"offset":0,"postType":"team_member","order":"desc","orderBy":"date_priority","taxQuery":{"member_status":[11]}},"className":"rtm-team-member-loop"} -->

<!-- Manual custom order -->
<!-- wp:query {"queryId":21,"query":{"perPage":50,"pages":0,"offset":0,"postType":"team_member","order":"asc","orderBy":"custom_order"},"className":"rtm-team-member-loop"} -->
```

## Admin Fields

### Team Member Dates & Priority Meta Box
Located in the team member edit screen, this meta box contains:

1. **Start Date**: Date when the member joined the team
2. **End Date**: Date when the member left (leave blank for current members)
3. **Display Order**: Number for manual sorting (lower = higher priority)
4. **Priority Override**: Checkbox to prioritize member in date-based sorting

### How Priority Override Works
When using `orderBy: "date_priority"`:
1. Members with Priority Override ✓ checked appear first
2. These prioritized members are sorted by Display Order
3. Non-prioritized members appear after, sorted by start date

Example scenario:
- PI 1 (Priority ✓, Order: 1)
- PI 2 (Priority ✓, Order: 2)
- Postdoc (Priority ✓, Order: 3)
- PhD Student A (No priority, joined 2024)
- PhD Student B (No priority, joined 2023)

Result order: PI 1, PI 2, Postdoc, PhD Student A, PhD Student B

## Technical Implementation

### Files Involved
- `/includes/class-rtm-rest-api.php` - Main sorting logic
- `/includes/class-rtm-custom-fields.php` - Admin meta box fields
- `/assets/block-editor-extensions.js` - Block editor enhancements

### Meta Keys Used
- `_rtm_start_date` - Join date
- `_rtm_end_date` - Leave date
- `_rtm_order` - Display order number
- `_rtm_date_priority` - Priority override flag

### How It Works
1. The plugin hooks into `pre_get_posts` to modify queries
2. For date sorting, it uses LEFT JOIN to include members without dates
3. Custom SQL ORDER BY clauses ensure proper sorting:
   - Members with dates are sorted by those dates
   - Members without dates fall back to publish date
   - Priority override creates a two-tier sorting system

### Key Features
- **No Filtering**: All members are always included, regardless of whether they have dates
- **Fallback Sorting**: Members without dates use publish date as fallback
- **Query-Specific**: Each query is handled independently, allowing multiple Query Loops on the same page
- **Backwards Compatible**: Regular WordPress sorting still works normally

## Examples by Use Case

### Research Group Page
```html
<!-- Principal Investigators (with priority) -->
<!-- wp:query {"query":{"postType":"team_member","orderBy":"date_priority","taxQuery":{"member_status":[11]}}} -->

<!-- Current PhD Students (by join date) -->
<!-- wp:query {"query":{"postType":"team_member","orderBy":"start_date","order":"desc","taxQuery":{"member_status":[6]}}} -->

<!-- Alumni (by departure date) -->
<!-- wp:query {"query":{"postType":"team_member","orderBy":"end_date","order":"desc","taxQuery":{"member_status":[7]}}} -->
```

### Team Directory
```html
<!-- All members in custom order -->
<!-- wp:query {"query":{"postType":"team_member","orderBy":"custom_order","order":"asc"}} -->
```

### Recent Additions
```html
<!-- Newest 5 members -->
<!-- wp:query {"query":{"postType":"team_member","orderBy":"start_date","order":"desc","perPage":5}} -->
```

## Troubleshooting

### Members Not Showing
- Check that you're not using `meta_key` directly (this filters out members without that meta)
- Ensure the Member Status taxonomy is set correctly
- Verify the Query Loop has the correct `postType: "team_member"`

### Sorting Not Working
- Confirm you've edited the `orderBy` value inside the `query` object
- Check that you're using the exact sorting key (e.g., "start_date" not "startdate")
- Clear any caching plugins after making changes

### Priority Override Not Working
1. Ensure the checkbox is saved on the team member
2. Use `orderBy: "date_priority"` (not "start_date")
3. Set Display Order numbers for prioritized members

## REST API Endpoints

The sorting options also work with REST API queries:

```javascript
// Get team members sorted by join date
/wp-json/wp/v2/team_member?orderby=start_date&order=desc

// Get alumni sorted by departure
/wp-json/wp/v2/team_member?orderby=end_date&order=desc&member_status=7,8

// Get members with priority sorting
/wp-json/wp/v2/team_member?orderby=date_priority
```

## Version History
- v1.0.0 - Initial implementation of custom sorting
- Added start_date and end_date sorting
- Added priority override system
- Added custom_order sorting
- Ensured all members are included (no filtering)

---

For more information or support, see the main plugin documentation.