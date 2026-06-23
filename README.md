# Research Team Manager

A comprehensive WordPress plugin for managing research team members and publications with Google Scholar integration. This plugin provides a complete solution for academic institutions, research groups, and organizations to showcase their team members and track publications.

It runs in **single-team mode** (one lab) or **multiple-teams mode** (a research team page for every lab on campus). See [Multiple Teams (Labs)](#-multiple-teams-labs) below.

## 🚀 Features

### 👥 Team Member Management
- **Custom Post Type**: Dedicated post type for team members with rich metadata
- **Advanced Custom Fields**: Secure fields for personal and professional information
- **Profile Management**: Profile pictures, biographical information, and detailed descriptions
- **Contact Information**: Email, phone, website, and social media links
- **Academic Profiles**: Integration with Google Scholar, LinkedIn, and ResearchGate
- **Member Status**: Track current members vs alumni with detailed status management
- **Position & Role Tracking**: Flexible position titles and member types (Faculty, PhD Student, Postdoc, etc.)
- **Date Tracking**: Join dates, leave dates, and tenure information
- **Custom Sorting**: Advanced sorting options including priority overrides and custom ordering

### 📚 Publications Management  
- **Google Scholar Integration**: Planned feature for automatic sync (currently manual upload only)
- **Publication Database**: Comprehensive tracking with citations and metadata
- **Admin Interface**: User-friendly management of publications
- **Frontend Display**: Shortcodes and blocks for displaying publications
- **Manual Import**: Upload and manage publications through admin interface
- **Citation Tracking**: Monitor citation counts and publication metrics

### 🎨 Frontend Display & Gutenberg Integration
- **Custom Blocks**: Team member field blocks for the Gutenberg editor
- **Query Loop Extensions**: Enhanced sorting options for Query Loop blocks
- **Responsive Layouts**: Team member grid/list layouts that work on all devices
- **Advanced Filtering**: Filter by role, research area, and member status
- **Individual Profiles**: Dedicated team member profile pages
- **Template System**: Customizable templates for archives and single pages
- **Social Media Integration**: Display social profiles and academic networks

### 🛠️ Developer Features
- **REST API**: Full REST API support for custom integrations
- **Custom Taxonomies**: Research areas and team roles
- **Extensible Architecture**: Well-structured OOP codebase
- **WordPress Standards**: Follows WordPress coding standards and best practices
- **Translation Ready**: Full internationalization support

## 🏫 Multiple Teams (Labs)

The plugin can run as a multi-lab directory where **each lab is a "Team" term** with its own page, settings and publications.

### Choosing a mode
Go to **Team Members → Team Settings** and pick:
- **Multiple teams** *(default)* — adds a **Teams** taxonomy, per-lab settings, per-lab pages and per-lab publications.
- **Single team** — hides the Teams UI and uses the global Scholar ID / one shared publications list (original behaviour). Optionally pick a *default team* to scope listings to one lab.

After switching modes the plugin re-flushes permalinks automatically.

> **📖 Full walkthrough:** see [docs/ADMIN-GUIDE.md](docs/ADMIN-GUIDE.md) for the complete admin & front‑end guide (modes, team fields, members, publications, shortcodes, and the block template).

### Adding a lab
1. **Research Labs → Teams → Add New** — name the lab (e.g. *Robotics Lab*). Its page is created automatically at `/research-team/{slug}/`.
2. Fill in the per-lab settings on the term:
   - **Type** (Lab / Centre / Institute / …) and **Research Theme** (discipline grouping used by the directory)
   - **Lead type** — *Individual* (then search & click to add the **PI member(s)**) or *Organization* (a name)
   - **Team Logo**, **Intro**, **Google Scholar ID**, **Contact Email**, **Website + Link label**, **Location**
   - **Group members by** — None / Member status (Current, Alumni…) / Team role
3. When editing a team member, use the prominent **Research Team** box to assign the lab(s) — or click **Add member** from a team to start pre‑assigned. A member can belong to several labs.

### Switching modes (guarded conversion)
**Team Settings** adapts to the current mode: *Single → Multiple* can create a starter team and move existing members into it; *Multiple → Single* lets you pick the **active team** and choose to keep / delete the others (with confirmation). Permalinks re‑flush automatically.

### Lab pages
- **Block themes:** add `templates/taxonomy-rtm_research_team.html` (see the guide) composed of `[rtm_team_header]` + `[rtm_team_roster]` + `[rtm_team_publications]`. Empty sections self‑hide.
- **Classic themes:** the bundled `templates/taxonomy-rtm_research_team.php` renders the full lab page automatically.

### Per-lab publications & Scholar IDs
**Scholar Settings** shows a table of every team with its own Scholar ID. **Publications** lets you pick a team to view/sync/import *its* papers (Sync/Import appear only when a team is selected). In single mode both collapse to one global ID + list.

## 📋 Requirements

1. Upload the plugin files to `/wp-content/plugins/research-team-manager/`
2. Activate the plugin through the WordPress admin
3. Start adding team members through the admin interface
4. Manually upload publications or wait for automatic Google Scholar sync (coming soon)

## Configuration

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## 📦 Installation

### Standard Installation
1. Download the plugin files
2. Upload to your `/wp-content/plugins/research-team-manager/` directory
3. Activate the plugin through the WordPress admin
4. Configure settings under **Team Members > Settings**

### Manual Installation
1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate through **Plugins > Installed Plugins**
3. Configure initial settings

## ⚙️ Configuration

### Initial Setup
1. **Activate the Plugin**: Navigate to Plugins and activate Research Team Manager
2. **Configure Settings**: Go to **Team Members > Settings** to configure basic options
3. **Set Permissions**: Ensure appropriate user roles have access to manage team members

### Publications Management (Current: Manual Upload)
1. Go to **Team Members > Publications** to manually upload publications
2. Add publication details including title, authors, journal, and citation info
3. Link publications to specific team members
4. **Note**: Automatic Google Scholar sync is planned for future release

### Team Member Setup
1. Navigate to **Team Members > Add New**
2. Fill in comprehensive member information:
   - **Basic Info**: Name, position, department, member type
   - **Contact**: Email, phone, website
   - **Academic Profiles**: Google Scholar, LinkedIn, ResearchGate URLs
   - **Descriptions**: Short description (for listings) and long description (for profile pages)
   - **Dates**: Join date, leave date (if applicable)
   - **Settings**: Member status, position, display order, priority override
3. Set featured image as profile picture
4. Assign to research areas and team roles
5. Publish the team member

## 🎨 Usage

### Gutenberg Blocks
The plugin extends Gutenberg with custom blocks and enhancements:

#### Team Member Field Block
Display specific team member information in posts and pages:
- Member name, position, contact info
- Academic profile links
- Customizable field selection

#### Query Loop Extensions
Enhanced sorting options for Query Loop blocks:
- Sort by join date, leave date, custom order
- Priority override functionality
- Alumni-specific sorting

### Custom Sorting Options
- **Start Date**: Sort by when members joined
- **End Date**: Sort by leave dates (useful for alumni)
- **Custom Order**: Manual ordering via Display Order field
- **Date Priority**: Priority members first, then chronological

## 📝 Shortcodes

### Team Members Display
```shortcode
[rtm_team_members limit="10" show_current_only="true" layout="grid" columns="3"]
```

**Parameters:**
- `limit` - Number of members to show (-1 for all)
- `role` - Filter by specific role slug
- `research_area` - Filter by research area slug
- `team` - Filter by a team/lab (slug or term ID). On a team archive it auto-detects the current team.
- `show_current_only` - Show only current members (true/false)
- `layout` - Display layout (grid/list)
- `columns` - Number of columns for grid layout (2-4)
- `show_bio` - Display biography excerpt (true/false)
- `show_contact` - Show contact information (true/false)
- `show_social` - Display social media links (true/false)

### Publications Display
```shortcode
[rtm_publications limit="10" show_citations="true" group_by_year="true"]
```

**Parameters:**
- `limit` - Number of publications to show (-1 for all)
- `team` - Show only a team/lab's publications (slug or term ID); auto-detects on a team archive
- `show_citations` - Display citation counts (true/false)
- `show_abstract` - Show publication abstracts (true/false)
- `group_by_year` - Group publications by year (true/false)
- `author_filter` - Filter by specific author
- `year_filter` - Filter by publication year

### Labs directory
```shortcode
[rtm_teams]
```
A filterable directory of all labs grouped by **Research Theme**, with theme filter chips, search (name / PI / type), a Type badge, the PI/organization, and an "Official site" link per card. Used on the `/labs` page.

### Team Header (lab page)
```shortcode
[rtm_team_header team="robotics-lab"]
```
Outputs a lab's logo, type · theme, name, **PI member(s) linked to their profiles** (or organization), intro, contact and website link from the team's settings. Omit `team` on a team archive to auto-detect the current lab.

### Lab roster & publications sections (self-hiding)
```shortcode
[rtm_team_roster team="robotics-lab"]        — "Lab Team", grouped per the team's setting (e.g. Alumni separated); leads first
[rtm_team_publications team="robotics-lab"]  — "Publications" for the team
```
Both render their own heading and output **nothing when empty**, so a lab with no members/papers shows just the hero. `group_by` on the roster overrides the team's setting (`none` / `rtm_member_status` / `rtm_team_role`).

### Publications Grouped by Year
```shortcode
[sorted_publications team="robotics-lab"]
```
Lists publications grouped by year with year navigation. `team` scopes to one lab (slug or ID); auto-detects on a team archive.

## 🎨 Template Customization

The plugin includes customizable templates that can be overridden in your theme:

### Template Files
1. Copy template files from `plugins/research-team-manager/templates/` to your theme directory
2. Customize as needed:
   - `archive-rtm_team_member.php` - Team members archive page
   - `single-rtm_team_member.php` - Individual member profile page
   - `taxonomy-rtm_research_team.php` - A single lab/team page

### Available Template Tags
- `rtm_get_member_field($field_name)` - Get specific member field
- `rtm_display_member_contact()` - Display contact information
- `rtm_display_member_social()` - Display social media links
- `rtm_display_member_publications()` - Show member's publications

## 🔧 Advanced Features

### Custom Fields Available
- **Basic Information**: Position, member type, member status
- **Contact Details**: Email, phone, website  
- **Academic Profiles**: Google Scholar, LinkedIn, ResearchGate URLs
- **Descriptions**: Short and long descriptions with rich text support
- **Date Tracking**: Start date, end date for tenure tracking
- **Display Settings**: Custom order, priority override for featured positioning

### REST API Endpoints
- `GET /wp-json/rtm/v1/team-members` - Retrieve team members
- `GET /wp-json/rtm/v1/publications` - Retrieve publications
- `POST /wp-json/rtm/v1/sync-scholar` - Trigger Scholar sync

### Hooks & Filters
```php
// Customize member data before save
add_filter('rtm_before_member_save', 'your_custom_function');

// Modify publication display
add_filter('rtm_publication_display', 'your_display_function');

// Custom sorting logic
add_filter('rtm_custom_sort_query', 'your_sort_function');
```

## 📚 Documentation

### Field Mapping
See `FIELD-MAPPING.md` for detailed information about:
- Import field mappings from other systems
- Custom field structure and usage
- Taxonomy relationships

### Sorting Documentation  
See `SORTING-DOCUMENTATION.md` for comprehensive guide on:
- Advanced sorting options
- Query Loop customizations
- Priority and custom ordering

## 🛠️ Development

### Plugin Structure
```
research-team-manager/
├── admin/                     # Admin interface classes
├── assets/                    # CSS, JS, and media files
├── blocks/                    # Gutenberg blocks
├── includes/                  # Core plugin classes
├── public/                    # Frontend functionality
├── templates/                 # Theme template files
├── research-team-manager.php  # Main plugin file
└── README.md                  # This file
```

### Key Classes
- `RTM_Custom_Fields` - Meta fields and data handling
- `RTM_Teams` - Per-lab `research_team` term settings (PI, logo, intro, Scholar ID, contact)
- `RTM_REST_API` - REST fields + Query Loop sorting for team members
- `RTM_Blocks` - Gutenberg block functionality

> Note: `RTM_Post_Types`, `RTM_Publications`, `RTM_Admin` and `RTM_Public` exist in `includes/`/`public/` but are legacy scaffolding and are **not loaded**. The live functionality is in the main `research-team-manager.php` plus `RTM_Custom_Fields`, `RTM_REST_API`, `RTM_Teams` and `RTM_Blocks`.

## 🐛 Troubleshooting

### Common Issues

**Publications not syncing:**
- Verify Google Scholar User ID is correct
- Check WordPress cron is functioning
- Manually trigger sync in admin panel

**Team members not displaying:**
- Ensure members are published (not draft)
- Check template files are correctly placed
- Verify shortcode parameters

**Sorting not working:**
- Confirm custom fields are populated
- Check Query Loop block HTML syntax
- Verify sorting parameter names

### Debug Mode
Enable WordPress debug mode to troubleshoot issues:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 🤝 Contributing

We welcome contributions! Please:

1. Fork the repository
2. Create a feature branch
3. Follow WordPress coding standards
4. Test thoroughly
5. Submit a pull request

### Coding Standards
- Follow WordPress PHP Coding Standards
- Use proper sanitization and validation
- Include inline documentation
- Write clean, readable code

## 📄 License

This plugin is licensed under GPL v2 or later.

## 🆘 Support

For support and questions:
- Check the documentation files included with the plugin
- Review common troubleshooting steps above
- Submit issues with detailed information

## 📈 Changelog

### Version 1.2.0
- **Team classifiers**: per-team **Type** (Lab/Centre/Institute/…) and **Research Theme**; the `/labs` directory now groups by Theme and shows a Type badge.
- **Structured lead**: PI is now real member post(s) chosen via a **search + click-to-add** picker (linked to their profiles), or an **Organization** name; auto-shows "Principal Investigator(s):" vs "Led by:".
- **Roster grouping**: per-team **Group members by** (none / member status / team role) — e.g. Alumni split into their own section; leads sorted first with a PI badge. New status/role terms become sections automatically.
- **Team-aware admin**: Teams column + filter on the member list, a prominent **Research Team** box on the member editor, and **Add member** from each team.
- **Guarded mode conversion**: Team Settings pipeline for single→multiple (starter team + move members) and multiple→single (choose active team; keep / delete others, with confirmation).
- **Per-team Scholar/Publications**: Scholar Settings is a table of all teams' Scholar IDs; Publications scopes sync/import to a selected team.
- **Website link label** (Official site vs More information) + deep `#anchor` support.
- Added [docs/ADMIN-GUIDE.md](docs/ADMIN-GUIDE.md); front-end CSS inherits the active theme's light/dark variables.

### Version 1.1.0
- **Multiple-teams mode**: model each lab as a `research_team` term with its own page at `/research-team/{slug}/`
- Per-lab settings (PI, logo, intro, Scholar ID, contact) via team term meta
- Per-lab publications (a `team_id` column scopes the publications table and the Publications admin screen)
- Single ⇄ multiple mode toggle under **Team Members → Team Settings**
- New `[rtm_team_header]` shortcode + `team=` attribute on the team/publications shortcodes
- "Team" field added to the Team Member Field block
- Generic default member-status terms (Current / Alumni / Visiting)
- **Hardening**: prefixed all post type / taxonomy keys (`rtm_team_member`, `rtm_research_team`, …) to avoid collisions; added `uninstall.php` (drops table + options); shipped & enqueued front-end CSS (`assets/css/rtm-public.css`); added `wp_unslash()` to save handlers and plugin constants (`RTM_VERSION`, `RTM_PLUGIN_URL`, …); removed unused legacy classes and debug logging
- **`[rtm_teams]` directory**: card-based lab directory (per-theme inline SVG icon · title · PI · focus) with theme filter chips, live search, and a **light/dark** toggle (auto via `prefers-color-scheme`, manual override persisted in `localStorage`); CSS is fully tokenised and cache-busted by file mtime

### Version 1.0.0
- Initial release
- Team member management
- Google Scholar integration
- Custom blocks and sorting
- Publications tracking
- REST API support

---

**Research Team Manager** - Streamline your academic team management with powerful WordPress integration.
2. Customize as needed while maintaining the basic structure
3. Available templates:
   - `single-rtm_team_member.php` - Individual member pages
   - `archive-rtm_team_member.php` - Team member archive
   - `taxonomy-rtm_research_team.php` - Single lab/team page

## CSS Customization

The plugin includes comprehensive CSS for styling. To customize:

1. Use your theme's CSS to override plugin styles
2. Target classes prefixed with `.rtm-`
3. Key classes include:
   - `.rtm-team-members` - Team member container
   - `.rtm-team-member` - Individual member card
   - `.rtm-publications-list` - Publications container
   - `.rtm-publication-item` - Individual publication

## Security Features

- All user inputs are sanitized and validated
- Nonce verification for AJAX requests
- Capability checks for admin functions
- SQL injection prevention with prepared statements
- XSS protection with proper output escaping

## Google Scholar Integration (Planned Feature)

**Current Status**: The plugin currently supports manual publication upload only. Automatic Google Scholar integration is planned for a future release.

**Planned Features**:
- Automatic scraping of Google Scholar profiles to import publications
- Publication titles, authors, and journal information sync
- Citation counts with regular updates
- Links to original papers and abstracts
- Respectful scraping with appropriate delays to avoid blocking

**Current Workaround**: Use the manual publication upload feature in the admin interface to add publications until automatic sync is available.

## Database Structure

### Publications Table
The plugin creates a custom table `wp_rtm_publications` with the following structure:

- `id` - Unique publication ID
- `title` - Publication title
- `authors` - Author list
- `journal` - Journal or venue name
- `year` - Publication year
- `citations` - Citation count
- `url` - Link to publication
- `google_scholar_id` - Unique identifier for Scholar sync
- `abstract` - Publication abstract
- `date_created` - Record creation date
- `date_updated` - Last update timestamp

### Team Member Meta Fields
Custom fields stored in WordPress meta tables:

- `_rtm_position` - Job title/position
- `_rtm_department` - Department or institution
- `_rtm_education` - Educational background
- `_rtm_research_interests` - Research interests
- `_rtm_biography` - Full biography
- `_rtm_email` - Email address
- `_rtm_phone` - Phone number
- `_rtm_office` - Office location
- `_rtm_website` - Personal website
- `_rtm_linkedin` - LinkedIn profile
- `_rtm_twitter` - Twitter profile
- `_rtm_google_scholar` - Google Scholar profile
- `_rtm_orcid` - ORCID identifier
- `_rtm_researchgate` - ResearchGate profile
- `_rtm_github` - GitHub profile
- `_rtm_start_date` - Start date with team
- `_rtm_end_date` - End date (for former members)
- `_rtm_is_current` - Current member status
- `_rtm_order` - Display order

## Troubleshooting

### Common Issues

**Publications not displaying:**
- Verify publications have been manually uploaded through admin interface
- Check that publications are properly linked to team members
- Clear any caching plugins

**Team member images not displaying:**
- Check file permissions on uploads directory
- Verify image file formats are supported
- Clear any caching plugins
- Check for theme conflicts

**Shortcodes not working:**
- Ensure plugin is activated
- Check for syntax errors in shortcode attributes
- Verify you have published team members
- Check theme compatibility

### Debug Mode
Add this to wp-config.php for debugging:
```php
define('RTM_DEBUG', true);
```

This will enable detailed logging for troubleshooting issues.

## Support

For support and feature requests, please:
1. Check the documentation above
2. Review technical documentation in the [`docs/`](docs/) folder:
   - [Field Mapping Documentation](docs/FIELD-MAPPING.md) - For data migration
   - [Custom Sorting Documentation](docs/SORTING-DOCUMENTATION.md) - For advanced display options
3. Review the plugin settings
4. Test with default theme to rule out conflicts
5. Contact your developer for custom implementations

## Changelog

### Version 1.0.0
- Initial release
- Team member management system
- Manual publications management
- Frontend display templates
- Responsive design
- Security hardening

### Planned Features (Upcoming Releases)
- **Google Scholar Integration**: Automatic publication sync from Google Scholar profiles
- **Advanced Analytics**: Publication metrics and citation tracking
- **Enhanced Filters**: More sophisticated filtering options
- **Export Features**: CSV/PDF export capabilities

## License

This plugin is licensed under GPL v2 or later.

## Credits

- Built with WordPress best practices
- Uses Google Scholar for publication data
- Responsive CSS framework
- jQuery for interactive features