# Block Type

Shows how to register page-builder block metadata through `cms()->blockTypes()`.
Use this when a plugin should add an editable block to the admin builder.
The example registers `example_quote` with `replace: true`.
The view follows `page.blocks.<id_with_underscores>`; override `view()` for another name.
An explicit `cms()->blocks()->add()` mapping always wins.
