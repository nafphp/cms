Page builder BlockTypes live in `Types/` and are rendered through `NixCMS\Content\BlockViewRegistry`.

Naming convention:

- `FooBlockType` <-> `id() === 'foo'` <-> `views/page/blocks/foo.phtml`.
- Multi-word IDs use lowercase words separated with hyphens.
- Multi-word block view files use underscores to match the existing view naming style.
- Example: `ArticleContentBlockType` <-> `id() === 'article-content'` <-> `views/page/blocks/article_content.phtml`.

Use a variant field instead of a new BlockType when the render structure is identical and only styling, tone, or wording changes. Add a new BlockType when the field schema or data semantics are different enough that the builder and renderer need a distinct contract.

Layout model:

- `Stack`, `Section`, `Columns`, and `Grid` are inner-slot layout blocks. They arrange content inside one page-builder slot.
- Custom layout mode is page-level layout. It arranges the slots themselves.
- Both layers intentionally coexist. Users choose custom layout for coarse page structure and use container blocks for complex slot content.

`php bin/nix integrity:check` is the source of truth for drift between BlockTypes, block views, and page template definitions. Run it after adding or renaming a BlockType, block view, or page template.
