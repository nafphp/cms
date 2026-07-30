Page builder BlockTypes live in `Types/` and are rendered through `NixPHP\CMS\Content\BlockViewRegistry`.

Naming convention:

- `FooBlockType` <-> `id() === 'foo'` <-> `src/views/page/blocks/foo.phtml`.
- Multi-word IDs use lowercase words separated with hyphens.
- Multi-word block view files use underscores to match the existing view naming style.
- Example: `ArticleContentBlockType` <-> `id() === 'article-content'` <-> `src/views/page/blocks/article_content.phtml`.

Use a variant field instead of a new BlockType when the render structure is identical and only styling, tone, or wording changes. Add a new BlockType when the field schema or data semantics are different enough that the builder and renderer need a distinct contract.

Layout model:

- `Stack`, `Section`, `Columns`, and `Grid` are inner-slot layout blocks. They arrange content inside one page-builder slot.
- Custom layout mode is page-level layout. It arranges the slots themselves.
- Both layers intentionally coexist. Users choose custom layout for coarse page structure and use container blocks for complex slot content.

`vendor/bin/nix integrity:check` is the source of truth for drift between BlockTypes, block views, and page template definitions. Run it after adding or renaming a BlockType, block view, or page template.

Layout system locations:

The layout system has three concrete locations, not three competing systems:

- `src/views/page/templates/*.phtml` — HTML-controlled templates with arbitrary
  markup and CSS. The developer's tool when full layout freedom is needed.
- `app/Resources/pages/page_templates.php` — programmatic `Layout`/`Template`
  definitions for the `.phtml` templates above. The `Template` points at the
  physical view and parent; the `Layout` declares the page slots/blocks
  (id, region, accepts, defaultContentType). Required because `.phtml` files do
  not self-declare their slots. Slot ids here must match the `$block('id')`
  calls in the matching `.phtml` — `vendor/bin/nix integrity:check` enforces this.
- `page_layouts` table — property-driven layouts (region/size/position/
  surface/maxHeight/overflow) rendered by the generic
  `src/views/page/templates/custom.phtml`. The user's tool inside the page
  builder, persisted as reusable presets.

The first two together describe one feature: developer-authored templates
with arbitrary HTML. The third is a separate feature: user-built layouts
without writing code. They intentionally coexist because property-driven
layouts cannot reproduce arbitrary HTML structures (e.g. complex CSS grids,
custom breakpoints), and `.phtml` templates cannot be edited by end users.

Deletion of a `page_layouts` row is rejected by
`PageLayoutRepository::delete()` if any page still references it via
`meta.layoutPresetId`. Pages must be re-pointed first; there is no foreign
key constraint, the check is enforced in PHP.
