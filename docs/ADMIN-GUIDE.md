# Research Team Manager — Admin & Usage Guide

This guide covers the plugin as of **v1.2.0**, including the multiple‑teams (one
page per lab) workflow.

---

## 1. Modes: single vs multiple teams

The plugin runs in one of two modes (set under **Team Members / Research Labs →
Team Settings**):

| Mode | What it does |
|---|---|
| **Single team** | One implicit team. The Teams taxonomy UI is hidden; publications + Scholar ID are global. Behaves like a classic "our team" plugin. |
| **Multiple teams** | A lab directory: each lab is a term in the **Teams** (`rtm_research_team`) taxonomy with its own page, settings, members and publications. The top‑level admin menu is labelled **Research Labs**. |

### Switching modes (guarded conversion pipeline)

The **Team Settings** page adapts to the current mode:

- **Single → Multiple:** optionally **create a starter team** and **move all
  existing members into it**, so nothing is left unassigned.
- **Multiple → Single:** choose the **Active team** (the one team single mode
  scopes to), then decide what happens to the others:
  - **Keep them (hidden)** — non‑destructive, reversible.
  - **Delete the other teams** — members are kept (just unassigned).
  - **Delete the other teams AND their members** — members not in the active
    team are permanently deleted.
  Destructive options require a confirmation checkbox (and a JS confirm), and the
  server refuses to delete without an active team selected.

---

## 2. Teams (labs)

Each lab is a term under **Research Labs → Teams**. Its archive lives at
`/research-team/{slug}/`. Per‑team settings (term meta) on the Add/Edit Team screen:

| Field | Meta key | Notes |
|---|---|---|
| **Type** | `rtm_team_type` | Kind of unit: Lab, Research Group, Centre, Institute, Hub, Network, Working Group, Field Station, Facility, Program, Other. |
| **Research Theme** | `rtm_team_theme` | Discipline grouping (slug). Drives the directory's sections + filter chips. |
| **Lead type** | `rtm_team_lead_type` | `person` (one/more members) or `org`. |
| **Principal Investigator(s)** | `rtm_team_lead_ids` | When lead type = person: a **searchable click‑to‑add** picker of member posts. Selecting a member also adds them to the team and flags them as PI. |
| **Organization name** | `rtm_team_pi` | When lead type = org (e.g., "Statistics Canada partnership"). |
| **Team Logo** | `rtm_team_logo` | Media attachment ID. |
| **Intro** | `rtm_team_intro` | Rich text shown in the page hero. |
| **Google Scholar ID** | `rtm_team_scholar_id` | Scopes this team's publications. Also editable in bulk on Scholar Settings. |
| **Contact Email** | `rtm_team_contact_email` | |
| **Website / info link** | `rtm_team_website` | May be a deep link with an `#anchor`. |
| **Link label** | `rtm_team_link_type` | `website` → "Official lab website"; `info` → "More information". |
| **Location** | `rtm_team_location` | |
| **Group members by** | `rtm_team_roster_group` | `none`, `rtm_member_status` (default), or `rtm_team_role`. Splits the roster into sections (e.g., Current vs **Alumni**). New status/role terms become sections automatically. |

> The taxonomy stays **hierarchical** so members are assigned via checkboxes. The
> Parent field is unused for grouping — grouping is driven by **Research Theme**.

---

## 3. Members

Team members are `rtm_team_member` posts (**Research Labs → All Team Members**).

- The list has a **Teams** column and an **All teams** filter (multiple mode).
- The editor shows a prominent **Research Team** box (checkboxes) so a member is
  always added *to a team*.
- From any team row you can click **Add member** to open the editor with that
  team pre‑selected (`?rtm_team={id}`).
- A member flagged as lead (`_rtm_is_lead`) shows a **Principal Investigator**
  badge and sorts first in the roster. A person can lead/belong to several labs
  (one member post, many teams).

---

## 4. Publications & Google Scholar (multiple mode)

- **Scholar Settings** → an overview **table of every team** with its own
  editable Scholar ID, live publication count, Profile/Sync links, and a fallback
  ID for teams without one.
- **Publications** → pick a team to view its publications; **Sync / Import** only
  appear once a specific team is selected (each uses that team's Scholar ID). With
  "All teams" selected you get a hint to pick one.

In single mode both pages collapse to the classic global Scholar ID + one list.

---

## 5. Front end

### The labs directory — `[rtm_teams]`
A filterable, themed directory grouped by **Research Theme**, with theme filter
chips, a search box (name / PI / type), and a Type badge + PI + "Official site"
link per card. Each card links to the lab's page.

### A lab page
On a **block theme**, the lab archive is rendered by a block template
(`taxonomy-rtm_research_team.html`, see §6) composed from three shortcodes; on a
**classic theme** the bundled `templates/taxonomy-rtm_research_team.php` is used.

### Shortcodes

| Shortcode | Purpose | Key attributes |
|---|---|---|
| `[rtm_teams]` | Labs directory (cards grouped by theme). | `show_pi` |
| `[rtm_team_header]` | Lab hero: logo, type · theme, title, PI/Org (linked), intro, contact, website. | `team` |
| `[rtm_team_roster]` | "Lab Team" — members, grouped per the team's setting; leads first; **hidden if empty**. | `team`, `heading`, `group_by` |
| `[rtm_team_publications]` | "Publications" for the team; **hidden if empty**. | `team`, `heading` |
| `[sorted_publications]` | Publications grouped by year. | `team` |
| `[rtm_team_members]` | Simple member grid. | `team`, `role`, `research_area`, `limit` |

`team` accepts a slug or ID; omitted, it auto‑detects the current lab archive.

### Styling / dark mode
Front‑end CSS (`assets/css/rtm-public.css`) inherits the active theme's variables
(`--bg-card`, `--text-primary`, `--spark-orange`, …), so it follows the theme's
light/dark mode automatically. Override the accent with `--rtm-accent`.

---

## 6. Block template for lab pages (block themes)

Block themes define taxonomy archives via a theme template. Add
`templates/taxonomy-rtm_research_team.html` to the active theme:

```html
<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->
<!-- wp:group {"tagName":"main","layout":{"type":"constrained","contentSize":"1100px"},"style":{"spacing":{"padding":{"top":"8rem","bottom":"4rem","left":"1.5rem","right":"1.5rem"}}}} -->
<main class="wp-block-group" style="padding:8rem 1.5rem 4rem">
  <!-- wp:shortcode -->[rtm_team_header]<!-- /wp:shortcode -->
  <!-- wp:shortcode -->[rtm_team_roster]<!-- /wp:shortcode -->
  <!-- wp:shortcode -->[rtm_team_publications]<!-- /wp:shortcode -->
</main>
<!-- /wp:group -->
<!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->
```

The roster/publications sections self‑hide when empty, so a lab with only a hero
shows just the hero. A sticky footer is applied on these pages so short pages
still pin the footer to the bottom.

---

## 7. Data model summary

- `rtm_team_member` (CPT) — people. Meta: `_rtm_position`, `_rtm_is_lead`, plus
  the legacy contact/description fields.
- `rtm_research_team` (taxonomy, hierarchical) — the labs. Term meta as in §2.
- `rtm_research_area`, `rtm_team_role`, `rtm_member_status` (taxonomies on members).
- `wp_rtm_publications` (custom table) — publications, with a `team_id` column.
- Options: `rtm_team_mode`, `rtm_default_team`, `rtm_google_scholar_user_id`
  (all removed on uninstall, which also drops the publications table; member
  posts and terms are intentionally preserved).
