# MCP Tool

Shows the small class pattern used by NixCMS MCP tools.
Use this when a plugin should expose one controlled executable capability.
The example registers `cms_example_ping` and protects it with `system:read`.
Copy the class into `app/Mcp/Tools` or a plugin namespace, then register it.
