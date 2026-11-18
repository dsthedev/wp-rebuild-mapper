# WP Rebuild Mapper

A small utility plugin to map existing custom content structures to use as a reference when rebuilding a WordPress website.

## Content export

Activate the plugin on WordPress with PHP 7.4 or newer, then open **Tools > Rebuild Mapper** and select **Export JSON**. The report is generated on demand for the current site and isn't stored by the plugin. No ACF installation is required.

Report version 1 includes registered post types and counts by status, reading settings, and a sitemap ordered by page ID. Follow each `parent_id` to its parent page; `0` is a root, and a nonzero ID can refer to a page outside the export. Auto-drafts and inherited page records are omitted from the sitemap. A `null` assigned template means WordPress returned no custom assignment. URLs omit fragments and query parameters except the standard `page_id`; they are not preview links and may not resolve for unpublished pages or sites with custom query-based routing.

The export excludes post bodies, authors, and raw metadata. It still includes unpublished page titles and structure, so review it before sharing.

## Markdown export

Select **Export Markdown** for a readable summary of the same report. It includes a text page tree and, for up to 100 pages, a Mermaid diagram that renders in compatible Markdown viewers. Missing parents and parent cycles are labeled; the diagram breaks cyclic links so the remaining hierarchy can be drawn. The Schema and Relationships checkboxes add summaries to Markdown. Use **Export JSON** for the full records.

### Content model breakdown

Select **Include schema**, then **Export Markdown** for plain headings and tables describing the content model. No diagram viewer is needed for this breakdown:

- Registered post types, their public and hierarchy settings, attached taxonomies, and explicit ACF field group references.
- Registered taxonomies, including categories and tags, their attached post types, and term tables with named parents and IDs.
- Every exposed ACF field group, its active state and full placement rules, plus field tables with labels, names, types, keys, nested paths, and configured relationship targets.
- ACF options pages when discovery is available.

Post type and taxonomy group references identify explicit `==` location rules only. They are not an evaluation of where fields appear: additional AND conditions, OR alternatives, exclusions, templates, specific pages, options pages, and custom location rules must be read in the field group catalogue. Inactive groups are labeled. A missing direct reference does not mean an object has no fields. All registered post types are listed, including WordPress and plugin internals; the report does not infer which are custom from their names or claim ACF registered them.

Nested field paths preserve group/repeater containers and flexible-content layouts. Relationship targets are configured permissions, not proof of saved links; no explicit target restriction does not override ACF defaults or runtime filters. Clone expansion and settings absent from discovery are not inferred. Other metadata is not automatically classified as an ACF field.

Also select **Include relationships** for an observed ACF relationship usage table with source post counts for saved values, valid targets, missing targets, and mismatched targets. These counts are not individual links and outcomes can overlap. Empty results, unavailable lookups, and unsupported scan locations are distinguished. The existing usage scan limitations below still apply. JSON discovery and the read-only export behavior are unchanged.

## Optional schema export

Check **Include schema** before exporting to add the `schema` section. It lists registered post type settings, supported feature names, and attached taxonomies, plus registered taxonomies and their terms. Follow each term's `parent_id` within its taxonomy; `0` is a root. Unused terms are included. A taxonomy with no terms has `terms: []`; `terms: null` means WordPress returned an error while listing terms.

`schema.observed_post_meta` lists `post_type`, `meta_key`, and `post_count` for keys stored on registered post types, across all statuses. Duplicate rows for the same key on one post count once. Counts show storage, not whether values are nonempty or still used. No metadata values or individual post IDs are included in this section. An empty list means no keys were found; `null` means the lookup failed.

`schema.acf` lists field group definitions that ACF exposes at export time, including groups loaded from the database, PHP, or Local JSON. Location rules retain ACF's grouping: rules within a group are ANDed, and groups are ORed. Fields include their keys, names, labels, and types, plus nested fields and flexible content layouts where available. Options pages are listed when ACF Pro exposes them; `options_pages: null` means that API is unavailable. `schema.acf` is `null` when the field discovery APIs are unavailable. This section does not include field defaults, saved values, or options page contents.

ACF Relationship and Post Object fields include `target_filters` with configured post types, post statuses, and term filters. An empty list means no explicit filter was found for that setting; ACF defaults and runtime query filters can still apply. ACF Taxonomy fields report their configured taxonomy, or `null` if none is exposed. These settings describe field definitions, not saved selections or usage.

## Optional relationship export

Check **Include relationships** to add `relationships` independently of **Include schema**. `page_parents` lists nonzero parent IDs from the existing page map. `other_post_parents` lists stored parent links for other registered hierarchical post types, across all statuses. A stored parent ID does not guarantee that the parent still exists.

`term_assignment_counts` lists `taxonomy`, `term_id`, `post_type`, and `post_count` for direct assignments between existing terms and posts of types currently attached to the taxonomy. Counts include all post statuses, do not roll up child terms into their parents, and omit combinations with no assignments. Individual post-to-term links are not included. `null` means a lookup failed; an empty list means no records matched.

`acf_post_field_usage` summarizes ACF Relationship, Post Object, and Taxonomy fields backed by post metadata. It requires a matching ACF field-key reference on the source post and uses the first stored value row for each key. Counts are by source post type and exclude revisions. `posts_with_saved_value` includes empty or unrecognized values; the valid, missing, and mismatched counts consider only positive IDs. Each source post counts once per field and outcome and can appear in more than one outcome. Post targets must exist, have a registered type, and match explicit post type/status filters. Term targets must exist in the configured taxonomy. Counts do not evaluate term pick-list filters, runtime overrides, or ACF's Load Terms setting. No individual links or saved values are exported. `null` means ACF is unavailable, or, for a field's `by_post_type`, that its lookup failed.

Usage includes top-level fields and direct subfields of top-level repeaters. Subfield records include `repeater_key` and `repeater_name`. Only rows within the parent's stored count qualify, and both the parent and the subfield must have matching ACF references. Deeper nesting, flexible layouts, clones, options pages, and term metadata aren't scanned.

## Smoke test

1. On a disposable site, create a published parent page and a draft child with a custom template. Set the front and posts pages under **Settings > Reading**.
2. Export twice from an administrator account. Check that the JSON matches when the site hasn't changed and contains the expected counts, reading settings, child `parent_id`, template, and status.
3. Check that a user without `manage_options` cannot see the Tools screen or download a report with a direct POST. Check that an administrator's POST with a missing or invalid nonce also returns no report.
4. Export with **Include schema** selected. Check that an empty registered taxonomy appears with an empty term list, and that a known child term points to its parent. Export with the checkbox cleared and verify the report lists only `content` and has no `schema` key.
5. In `schema.observed_post_meta`, spot-check a known custom field against the number of posts that have it. Multiple rows for that key on a single post should not increase the count.
6. With ACF active, compare a known field group's location rules, nested fields, and a relationship field's `target_filters` against its ACF settings. With ACF inactive on a disposable site, check that `schema.acf` is `null` and the rest of the schema still exports.
7. Export with **Include relationships** selected and **Include schema** cleared. Check a known page parent and a known term's direct assignment count for one post type. Export with **Include relationships** cleared and verify the `relationships` section is absent.
8. Compare a top-level ACF relationship field with an empty selection and a known valid selection. The empty selection should not increase `posts_with_valid`, and multiple valid targets on one post should increase it by only one.
9. Save multiple repeater rows with valid links in the same subfield on one post. Confirm that post contributes only once to the subfield's `posts_with_valid`, and check that any stale metadata beyond the saved row count does not contribute.
10. Export Markdown with schema and relationships selected. Compare a known post type's attached taxonomies and a taxonomy's named term parents against WordPress. Compare an ACF group's full AND/OR rules, active state, nested field paths, and relationship targets against ACF. A template-only group must remain in the catalogue without being automatically assigned to every post type; unrestricted targets must not become guessed links. Check that pipes, newlines, HTML, and Markdown characters in labels cannot split table rows or insert markup. With ACF unavailable, the post type and taxonomy tables must still render with an explicit ACF-unavailable message. With relationships selected alone, usage tables must still render independently of schema.