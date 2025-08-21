# Research Team Manager

A comprehensive WordPress plugin for managing research team members and publications with Google Scholar integration.

## Features

### Team Member Management
- Custom post type for team members with rich metadata
- Secure custom fields for personal and professional information
- Profile pictures and biographical information
- Contact information and social media links
- Research areas and team roles taxonomy
- Current/former member status tracking

### Publications Management
- Automatic sync with Google Scholar profiles
- Publication database with citations tracking
- Admin interface for managing publications
- Shortcodes for displaying publications on frontend
- Configurable sync frequency and settings

### Frontend Display
- Responsive team member grid/list layouts
- Filtering by role and research area
- Individual team member profile pages
- Publications listing with search capabilities
- Social media integration

## Installation

1. Upload the plugin files to `/wp-content/plugins/research-team-manager/`
2. Activate the plugin through the WordPress admin
3. Configure Google Scholar settings under Team Members > Scholar Settings
4. Start adding team members and syncing publications

## Configuration

### Google Scholar Setup
1. Go to **Team Members > Scholar Settings**
2. Enter your Google Scholar User ID (from your profile URL)
3. Configure automatic sync preferences
4. Test the connection

### Adding Team Members
1. Go to **Team Members > Add New**
2. Fill in member information including:
   - Name, position, and department
   - Contact details and social links
   - Research interests and biography
   - Profile picture
3. Assign research areas and team roles
4. Publish the team member

## Shortcodes

### Team Members Display
```
[rtm_team_members limit="10" show_current_only="true" layout="grid" columns="3"]
```

**Parameters:**
- `limit` - Number of members to show (-1 for all)
- `role` - Filter by specific role slug
- `research_area` - Filter by research area slug
- `show_current_only` - Show only current members (true/false)
- `layout` - Display layout (grid/list)
- `columns` - Number of columns for grid layout (2-4)
- `show_bio` - Display biography excerpt (true/false)
- `show_contact` - Show contact information (true/false)
- `show_social` - Display social media links (true/false)

### Publications Display
```
[rtm_publications limit="10" show_citations="true" group_by_year="true"]
```

**Parameters:**
- `limit` - Number of publications to show
- `show_citations` - Display citation counts (true/false)
- `show_abstract` - Show publication abstracts (true/false)
- `group_by_year` - Group publications by year (true/false)

## Template Customization

The plugin includes default templates that can be overridden in your theme:

1. Copy template files from `plugins/research-team-manager/templates/` to your theme directory
2. Customize as needed while maintaining the basic structure
3. Available templates:
   - `single-team_member.php` - Individual member pages
   - `archive-team_member.php` - Team member archive

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

## Google Scholar Integration

The plugin scrapes Google Scholar profiles to automatically import publications. This includes:

- Publication titles and authors
- Journal information and publication year
- Citation counts (updated on sync)
- Links to original papers
- Abstract information where available

**Note:** Google Scholar scraping is done respectfully with appropriate delays and user-agent strings to avoid being blocked.

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

**Google Scholar sync not working:**
- Verify the User ID is correct
- Check if your site can make external HTTP requests
- Ensure the Scholar profile is public
- Try manual sync in admin

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
2. Review the plugin settings
3. Test with default theme to rule out conflicts
4. Contact your developer for custom implementations

## Changelog

### Version 1.0.0
- Initial release
- Team member management system
- Google Scholar integration
- Publications management
- Frontend display templates
- Responsive design
- Security hardening

## License

This plugin is licensed under GPL v2 or later.

## Credits

- Built with WordPress best practices
- Uses Google Scholar for publication data
- Responsive CSS framework
- jQuery for interactive features