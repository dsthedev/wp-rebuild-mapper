# WP Rebuild Mapper

A small utility plugin to map existing custom content structures to use as a reference when rebuilding a WordPress website.

## Content export

Activate the plugin on WordPress with PHP 7.4 or newer, then open **Tools > Rebuild Mapper** and select **Export JSON**. The report is generated on demand for the current site and isn't stored by the plugin. No ACF installation is required.

Report version 1 includes registered post types and counts by status, reading settings, and a sitemap ordered by page ID. Follow each `parent_id` to its parent page; `0` is a root, and a nonzero ID can refer to a page outside the export. Auto-drafts and inherited page records are omitted from the sitemap. A `null` assigned template means WordPress returned no custom assignment. URLs omit fragments and query parameters except the standard `page_id`; they are not preview links and may not resolve for unpublished pages or sites with custom query-based routing.

The export excludes post bodies, authors, and raw metadata. It still includes unpublished page titles and structure, so review it before sharing.

## Optional schema export

Check **Include schema** before exporting to add the `schema` section. It lists registered post type settings, supported feature names, and attached taxonomies, plus registered taxonomies and their terms. Follow each term's `parent_id` within its taxonomy; `0` is a root. Unused terms are included. A taxonomy with no terms has `terms: []`; `terms: null` means WordPress returned an error while listing terms.

`schema.observed_post_meta` lists `post_type`, `meta_key`, and `post_count` for keys stored on registered post types, across all statuses. Duplicate rows for the same key on one post count once. Counts show storage, not whether values are nonempty or still used. No metadata values or individual post IDs are included in this section. An empty list means no keys were found; `null` means the lookup failed.

`schema.acf` lists field group definitions that ACF exposes at export time, including groups loaded from the database, PHP, or Local JSON. Location rules retain ACF's grouping: rules within a group are ANDed, and groups are ORed. Fields include their keys, names, labels, and types, plus nested fields and flexible content layouts where available. Options pages are listed when ACF Pro exposes them; `options_pages: null` means that API is unavailable. `schema.acf` is `null` when the field discovery APIs are unavailable. This section does not include field defaults, saved values, or options page contents.

ACF Relationship and Post Object fields include `target_filters` with configured post types, post statuses, and term filters. An empty list means no explicit filter was found for that setting; ACF defaults and runtime query filters can still apply. ACF Taxonomy fields report their configured taxonomy, or `null` if none is exposed. These settings describe field definitions, not saved selections or usage.

## Optional relationship export

Check **Include relationships** to add `relationships` independently of **Include schema**. `page_parents` lists nonzero parent IDs from the existing page map. `other_post_parents` lists stored parent links for other registered hierarchical post types, across all statuses. A stored parent ID does not guarantee that the parent still exists.

`term_assignment_counts` lists `taxonomy`, `term_id`, `post_type`, and `post_count` for direct assignments between existing terms and posts of types currently attached to the taxonomy. Counts include all post statuses, do not roll up child terms into their parents, and omit combinations with no assignments. Individual post-to-term links are not included. `null` means a lookup failed; an empty list means no records matched.

`acf_post_field_usage` summarizes top-level ACF Relationship, Post Object, and Taxonomy fields backed by post metadata. It requires a matching ACF field-key reference on the source post and uses the first stored value row for each key. Counts are by source post type and exclude revisions. `posts_with_saved_value` includes empty or unrecognized values; the valid, missing, and mismatched counts consider only positive IDs. Each source post counts once per outcome and can appear in more than one outcome. Post targets must exist, have a registered type, and match explicit post type/status filters. Term targets must exist in the configured taxonomy. Counts do not evaluate term pick-list filters, runtime overrides, or ACF's Load Terms setting. Nested fields, options pages, and term metadata aren't scanned. No individual links or saved values are exported. `null` means ACF is unavailable, or, for a field's `by_post_type`, that its lookup failed.

## Smoke test

1. On a disposable site, create a published parent page and a draft child with a custom template. Set the front and posts pages under **Settings > Reading**.
2. Export twice from an administrator account. Check that the JSON matches when the site hasn't changed and contains the expected counts, reading settings, child `parent_id`, template, and status.
3. Check that a user without `manage_options` cannot see the Tools screen or download a report with a direct POST. Check that an administrator's POST with a missing or invalid nonce also returns no report.
4. Export with **Include schema** selected. Check that an empty registered taxonomy appears with an empty term list, and that a known child term points to its parent. Export with the checkbox cleared and verify the report lists only `content` and has no `schema` key.
5. In `schema.observed_post_meta`, spot-check a known custom field against the number of posts that have it. Multiple rows for that key on a single post should not increase the count.
6. With ACF active, compare a known field group's location rules, nested fields, and a relationship field's `target_filters` against its ACF settings. With ACF inactive on a disposable site, check that `schema.acf` is `null` and the rest of the schema still exports.
7. Export with **Include relationships** selected and **Include schema** cleared. Check a known page parent and a known term's direct assignment count for one post type. Export with **Include relationships** cleared and verify the `relationships` section is absent.
8. Compare a top-level ACF relationship field with an empty selection and a known valid selection. The empty selection should not increase `posts_with_valid`, and multiple valid targets on one post should increase it by only one.