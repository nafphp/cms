# NixCMS

NixCMS is a lightweight, extensible CMS built on top of NixPHP.

The first development path is intentionally bare-metal: PHP, Composer, and the PHP built-in server. Docker will be added later as a second ready-to-run setup path.

## Requirements

- PHP 8.3 or newer
- Composer

## Install

```bash
composer install
```

Copy the example environment file when you need local overrides:

```bash
cp .env.example .env.local
```

## Run

```bash
composer serve
```

Open `http://127.0.0.1:8080`.

## Assets

Install frontend dependencies once:

```bash
npm install
```

Build CSS:

```bash
npm run build
```

During UI work:

```bash
npm run dev
```

The current skeleton exposes:

- `/`
- `/admin`
- `/admin/articles`
- `/admin/users`
- `/admin/roles`
- `/mcp` from `nixphp/mcp`, protected by bearer-token auth

## CLI

```bash
bin/nix
bin/nix route:debug
bin/nix db:migrate up
bin/nix mcp:token:create
bin/nix queue:consume
bin/nix schedule:ticker
```

## Structure

- `app/config.php`: application and plugin configuration
- `app/plugins.php`: explicit NixPHP plugin load order
- `app/routes.php`: HTTP routes
- `app/Controllers`: web, admin, and API/action controllers
- `app/Services`: reusable domain workflows
- `app/Repositories`: persistence queries
- `app/Models`: ORM entities
- `app/Migrations`: database migrations
- `app/Mcp/Tools`: MCP tools
- `views`: server-rendered `.phtml` templates
- `app/Resources`: source assets and language files
- `public`: webroot and public assets
- `storage`: runtime files
- `logs`: application logs

## Admin UI

The admin UI is server-rendered and uses public assets directly:

- `public/css/nixcms.css`
- `public/css/notify.css`
- `public/js/admin.js`
- `public/js/lib/notifyJs`
- `views/components/admin/*`

Admin styling should live in self-contained `.phtml` components by default. `public/css/nixcms.css` is generated from `app/Resources/css/nixcms.css` and is reserved for global tokens, base rules, menu state helpers, and small infrastructure that cannot sensibly live in one component. Browser-consumed CSS and JS must be present under `public`.
