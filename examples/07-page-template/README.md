# Page Template

Shows how a plugin adds a page-builder template through `cms()->pageTemplates()`.
A template has two parts: the `.phtml` view with `$block('slot')` calls, and a `Layout` that declares the same slot ids for the builder.
Plugins register templates in their bootstrap; a host overrides or adds them in `src/Resources/pages/page_templates.php`, which always wins by id.
Use `cms()->pageTemplates()->remove('id')` to hide a template a host does not want to offer.
The example loads `/css/example-split.css` with `->addStylesheet()`.
NAF has no plugin asset publishing: copy `public/css/example-split.css` into the host's `public/css/`.
Host/plugin Tailwind classes are not compiled into the CMS stylesheet; ship your own CSS.
