# Page With Permission

Shows how to register an admin page through `cms()->pages()`.
Use this when a plugin or app module needs a server-rendered screen.
The page requires `articles.view`, so it is hidden behind normal auth.
Copy `page.php` into a bootstrap file or another explicit registration point.
