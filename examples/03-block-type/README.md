# Block Type

Shows how to register page-builder block metadata through `cms()->blockTypes()`.
Use this when a plugin should add an editable block to the admin builder.
The example registers `example_quote` with `replace: true`.
Block rendering still belongs in `cms()->blocks()` view mappings.
