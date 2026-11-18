# WP Rebuild Mapper

A small utility plugin to map existing custom content structures to use as a reference when rebuilding a WordPress website.

## Content export

Activate the plugin on WordPress with PHP 7.4 or newer, then open **Tools > Rebuild Mapper** and select **Export JSON**. The report is generated on demand for the current site and isn't stored by the plugin. No ACF installation is required.

Report version 1 includes registered post types and counts by status, reading settings, and a sitemap ordered by page ID. Follow each `parent_id` to its parent page; `0` is a root, and a nonzero ID can refer to a page outside the export. Auto-drafts and inherited page records are omitted from the sitemap. A `null` assigned template means WordPress returned no custom assignment. URLs omit fragments and query parameters except the standard `page_id`; they are not preview links and may not resolve for unpublished pages or sites with custom query-based routing.

The export excludes post bodies, authors, and raw metadata. It still includes unpublished page titles and structure, so review it before sharing.

## Smoke test

1. On a disposable site, create a published parent page and a draft child with a custom template. Set the front and posts pages under **Settings > Reading**.
2. Export twice from an administrator account. Check that the JSON matches when the site hasn't changed and contains the expected counts, reading settings, child `parent_id`, template, and status.
3. Check that a user without `manage_options` cannot see the Tools screen or download a report with a direct POST. Check that an administrator's POST with a missing or invalid nonce also returns no report.