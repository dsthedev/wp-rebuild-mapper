# GitHub Copilot Instructions — WordPress Rebuild Mapper

## Purpose

Build a small, open-source, read-only WordPress developer plugin that produces a structured reference for rebuilding an existing WordPress site. It should answer: what content exists, how is it organized, what custom schemas and relationships exist, and what needs review before a rebuild?

The plugin is a **discovery and documentation tool**, not a migration or backup tool. Favor accuracy, a narrow scope, native WordPress APIs, and straightforward maintainable code.

## MVP modules

### 1. Content map

- Inventory registered post types with counts by status.
- Generate a hierarchical page sitemap with ID, title, slug, URL, status, parent ID, and assigned template.
- Record the configured front page and posts page.
- Summarize posts and custom post types without exporting full post bodies.
- Include basic category/taxonomy associations when useful to explain content organization.

### 2. Schema map

- Inventory registered post types: identifiers, labels, public visibility, supported features, rewrite settings, and attached taxonomies.
- Inventory taxonomies: identifiers, post type associations, hierarchy settings, terms, and parent/child term structure.
- Inventory observed metadata keys and aggregate usage counts by post type.
- When ACF is active, inventory field groups, location rules, field keys/names/types, nested fields, repeaters, flexible content layouts, and discoverable options pages.
- Include definitions regardless of whether they originate in the database, PHP registration, or ACF Local JSON, where discoverable through supported APIs.
- Distinguish **registered definitions** from **observed stored data**. Do not assume a field is unused merely because no values were observed.

### 3. Relationship map

- Record page/post parent relationships and post-to-taxonomy associations.
- Document ACF Post Object, Relationship, and Taxonomy field definitions and their relevant target settings.
- Report aggregate relationship usage where reliably detectable.
- Distinguish confirmed relationships from inferred relationships; do not treat arbitrary numeric metadata as a post ID.

## Rebuild planning metadata

Support optional developer-assigned dispositions: `rebuild`, `migrate`, `review`, `exclude`.

Keep these decisions separate from factual discovery results. Do not automatically classify objects. For the MVP, a documented optional section in the export is sufficient; **do not build a planning UI** without approval.

## Exports

- **JSON:** Primary machine-readable report, with a versioned schema and stable identifiers.
- **Markdown:** Human-readable technical specification generated from the same discovery model.
- Suggested top-level sections: `report_version`, `site`, `content`, `schema`, `relationships`, `diagnostics`, `rebuild_plan`.
- Include minimal environment context (WordPress/PHP/ACF versions), but exclude secrets and unnecessary identifying details.
- Make results deterministic where practical (stable ordering and predictable keys).
- Never export full post bodies, private user data, credentials, tokens, form submissions, or raw sensitive metadata values.

## Admin UX

Add a minimal **Tools → Rebuild Mapper** screen with checkboxes for Content, Schema, and Relationships, plus **Export JSON** and **Export Markdown** actions. Use native WordPress admin UI. No frontend output, dashboard, charts, custom tables, or persistent report storage.

## Technical guidance

- Follow WordPress coding standards and supported APIs.
- ACF integration must be optional: the plugin works without ACF installed.
- Prefer `get_post_types()`, `get_taxonomies()`, `get_terms()`, `get_posts()` / `WP_Query`, metadata APIs, and documented ACF APIs.
- Use read-only SQL only when APIs cannot efficiently provide aggregate data; respect the configured table prefix and prepare variable SQL inputs.
- Keep discovery separate from report formatting/export.
- Favor small functions and classes over a complex plugin framework or unnecessary OOP layers.
- For larger sites, use batching/aggregate queries rather than loading all records into memory.
- Account for schema elements registered by PHP at runtime; database-only inspection is insufficient.
- Avoid claiming perfect knowledge of dynamic PHP usage or all possible relationships. Document limitations clearly.

## Security

- Strictly read-only: never change posts, terms, options, ACF definitions, or database schema.
- Restrict admin screen and downloads to authorized administrators using capability checks and nonces.
- Sanitize inputs, escape admin output, and prevent unauthenticated/public export endpoints.
- No telemetry, external services, or automatic uploads.
- Reports may still contain site structure and URLs: warn that exports should be reviewed before public sharing.

## Lightweight diagnostics (after core mapping)

Surface evidence-based observations such as missing assigned templates, metadata keys with no corresponding ACF definition, empty taxonomies, and field definitions without observed values. Label ambiguous findings as **possible**, not confirmed errors. No automated fixes.

## Explicit non-goals

Do not add content import/migration, database backups, schema conversion, ACF editing, full media exports, visual sitemap builders, AI integration, automated rebuild estimates, background synchronization, or project-management features without explicit approval.

## Incremental development sequence

1. **Foundation + content inventory:** plugin scaffold, minimal Tools page, registered post type inventory, hierarchical page sitemap, JSON export.
2. **Schema discovery:** taxonomies, terms, metadata usage, optional ACF field definitions.
3. **Relationship mapping:** hierarchy, taxonomy associations, ACF relationship definitions and reliable usage summaries.
4. **Reporting refinement:** shared report model, Markdown export, limited diagnostics, optional planning metadata.

Do not start later phases until the developer approves them.

## Mandatory human feedback and approval loop

**Never implement a requested change immediately.** For each task:

1. Inspect relevant code and briefly state the proposed approach.
2. Name the files to create or modify.
3. Mention material tradeoffs or assumptions, if any.
4. **Stop and wait for the developer's exact `ok`.**
5. Only then implement the approved scope.
6. Summarize changes and any verification needed; stop again.

Rules:

- General discussion is **not** approval; `ok` approves only the current proposal.
- Do not create files, edit code, install dependencies, run migrations, or expand scope before approval.
- If implementation requires a material departure from the approved plan, stop and request another `ok`.
- Do not automatically proceed to the next task or phase.
- Keep responses concise, technical, and focused on **one manageable step at a time**. Avoid unsolicited tutorials or large task lists.

## Success criteria

A developer can activate the plugin on a legacy WordPress site, export a concise JSON or Markdown report, and use it as a credible reference for the site's content hierarchy, registered custom schema, ACF structures, relationships, and rebuild dependencies—without modifying the source site.
