# Field Mapping Documentation

This document describes how fields from the old WordPress site are mapped to the new Research Team Manager plugin fields during the import process.

## Field Mapping Table (UPDATED)

| Old Site Field (ACF) | New Plugin Field | Field Type | Notes |
|---------------------|------------------|------------|-------|
| `email` | `_rtm_email` | Email | Contact email address |
| `phonenumber` | `_rtm_phone` | Phone | Contact phone number |
| `website` | `_rtm_website` | URL | Personal website URL |
| `long_description` | `_rtm_long_description` | Rich Text | Detailed description for profile page |
| `short_description` | `_rtm_short_description` | Rich Text | Brief description for listing page |
| `linkedin_url` | `_rtm_linkedin_url` | URL | LinkedIn profile URL |
| `google_scholar_url` | `_rtm_google_scholar_url` | URL | Google Scholar profile URL |
| `researchgate_url` | `_rtm_researchgate_url` | URL | ResearchGate profile URL |
| `profile_picture` | Featured Image | Image | Profile photo as WordPress featured image |
| `member_status` (taxonomy) | `_rtm_member_status` | Select | "Current Member" or "Alumni Member" |
| `member_status` (taxonomy) | `_rtm_position` | Text | Auto-populated based on taxonomy (Faculty, PhD Student, etc.) |
| `member_status` (taxonomy) | `_rtm_member_type` | Text | **NEW**: Graduate Student, Undergraduate Student, Visiting Scholar, Honorary Member, Faculty, Postdoc |
| `post_title` | `post_title` | Text | Member name (unchanged) |
| `post_content` | `post_content` | Rich Text | Main content (unchanged) |

## New Fields Available in Research Team Manager

The new plugin has additional fields that weren't in the old system:

| New Plugin Field | Field Type | Description |
|-----------------|------------|-------------|
| `_rtm_position` | Text | Position/Title (e.g., Principal Investigator, PhD Student) |
| `_rtm_department` | Text | Department/Institution |
| `_rtm_education` | Textarea | Education/Degrees information |
| `_rtm_research_interests` | Textarea | Research interests and areas |
| `_rtm_start_date` | Date | Start date with the team |
| `_rtm_end_date` | Date | End date (for alumni members) |
| `_rtm_office` | Text | Office location |
| `_rtm_twitter` | URL | Twitter/X profile URL |
| `_rtm_orcid` | URL | ORCID identifier URL |
| `_rtm_github` | URL | GitHub profile URL |
| `_rtm_order` | Number | Display order (lower numbers appear first) |

## Automatic Field Population

The import script automatically handles these mappings:

### Member Status Logic (Updated for Your Site)
The old site's `member_status` taxonomy is analyzed to determine if a member is current:

**Your Specific Taxonomy Terms:**
- `matter_alumni_member` ("MATTER's Alumni") → **Alumni Member**
- `team_member` ("MATTER's Team Members") → **Current Member** 
- `honorary_member` ("Visiting/ Honorary member") → **Current Member**

**General Rules:**
- Terms containing "alumni" → Alumni Member
- Terms containing "team", "visiting", "honorary" → Current Member
- This sets the `_rtm_is_current` field accordingly

### Member Type Mapping Logic (NEW)
The `_rtm_member_type` field is automatically populated based on your specific taxonomy:

**Your Specific Mappings:**
| Your Taxonomy Term | Member Status | Position | Member Type |
|--------------------|---------------|----------|-------------|
| "MATTER's Alumni" | Alumni Member | Alumni Member | Graduate Student |
| "MATTER's Team Members" | Current Member | Team Member | Graduate Student |
| "Visiting/ Honorary member" | Current Member | Visiting/Honorary Member | Visiting Scholar |

**General Logic for Other Sites:**
| Taxonomy Keywords | Member Type | Position |
|-------------------|-------------|----------|
| `faculty` | Faculty | Faculty |
| `phd`, `graduate` | Graduate Student | PhD Student |
| `postdoc` | Postdoc | Postdoctoral Researcher |
| `master` | Graduate Student | Master's Student |
| `undergraduate`, `undergrad` | Undergraduate Student | Undergraduate Student |
| `visiting` | Visiting Scholar | Visiting Scholar |
| `honorary` | Honorary Member | Honorary Member |
| `research` | Graduate Student | Research Assistant |

**Default:** If no keywords match, assumes "Graduate Student"

### Display Order
- Uses the original post ID from the old site as the display order
- This helps maintain the relative ordering of team members
- Can be manually adjusted after import if needed

### Image Handling
- `profile_picture` from ACF becomes the WordPress featured image
- Image URLs are preserved in the import data
- Actual image files need to be transferred separately using the export-team-images.php script

## Manual Data Entry After Import

After running the import, you may want to manually populate these new fields:

1. **Position/Title** (`_rtm_position`)
   - Add specific job titles or roles
   - Examples: "Principal Investigator", "PhD Student", "Postdoctoral Researcher"

2. **Department** (`_rtm_department`)
   - Add department or institution information
   - Examples: "Computer Science", "University of Example"

3. **Education** (`_rtm_education`)
   - Add degree information if not already in biography
   - Format: "PhD Computer Science, University of Example, 2020"

4. **Research Interests** (`_rtm_research_interests`)
   - Extract from biography or add new content
   - Focus on specific research areas and keywords

5. **Start/End Dates** (`_rtm_start_date`, `_rtm_end_date`)
   - Important for timeline tracking
   - Leave end date blank for current members

6. **Office Location** (`_rtm_office`)
   - Physical office or lab location
   - Examples: "Room 123, Science Building"

7. **Additional Social Links**
   - Twitter/X (`_rtm_twitter`)
   - ORCID (`_rtm_orcid`)
   - GitHub (`_rtm_github`)

## Data Migration Best Practices

1. **Backup First**: Always backup your database before running the import
2. **Test Import**: Run the import on a staging site first
3. **Review Data**: Check imported data for accuracy and completeness
4. **Image Transfer**: Use the export-team-images.php script to properly transfer images
5. **Manual Review**: Review each team member profile after import
6. **Update New Fields**: Add information to the new fields that weren't available in the old system

## Troubleshooting

### Common Issues:
1. **Missing Images**: Images show as broken links
   - Solution: Use export-team-images.php to transfer image files
2. **Incorrect Current Status**: Members marked as current/alumni incorrectly
   - Solution: Update the status logic in import script or manually correct
3. **Formatting Issues**: Rich text content appears broken
   - Solution: Check HTML formatting in biography field

### Field Validation:
- Email addresses are validated during import
- URLs are sanitized and validated
- HTML content in biography is filtered for security

## Extended Field Mapping

For sites with custom fields beyond the standard ACF setup, you can extend the field mapping by modifying the `$field_mappings` array in the import script:

```php
$field_mappings = array(
    // Standard mappings
    'email' => '_rtm_email',
    'phonenumber' => '_rtm_phone',
    // Add your custom mappings here
    'custom_field_name' => '_rtm_custom_target',
);
```

This allows for flexible adaptation to different source site configurations.