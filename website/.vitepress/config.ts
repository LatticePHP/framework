import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'LatticePHP',
  description: 'A modular PHP framework with NestJS-style architecture and native durable workflow orchestration',

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/logo.svg' }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/installation' },
      { text: 'API', link: '/api/' },
      { text: 'Examples', link: '/examples/crm' },
      {
        text: 'Ecosystem',
        items: [
          { text: 'Starter Kits', link: '/guide/starters' },
          { text: 'CRM Example', link: '/examples/crm' },
          { text: 'GitHub', link: 'https://github.com/latticephp/lattice' },
        ]
      }
    ],

    sidebar: {
      '/guide/': [
        {
          text: 'Prologue',
          items: [
            { text: 'Installation', link: '/guide/installation' },
            { text: 'Configuration', link: '/guide/configuration' },
            { text: 'Directory Structure', link: '/guide/directory-structure' },
            { text: 'Starter Kits', link: '/guide/starters' },
            { text: 'Deployment', link: '/guide/deployment' },
          ]
        },
        {
          text: 'Getting Started',
          items: [
            { text: 'Your First API', link: '/guide/getting-started' },
            { text: 'Why LatticePHP', link: '/guide/why-latticephp' },
          ]
        },
        {
          text: 'Architecture',
          items: [
            { text: 'Architecture Overview', link: '/guide/architecture' },
            { text: 'Modules', link: '/guide/modules' },
            { text: 'Service Container', link: '/guide/container' },
            { text: 'Pipeline', link: '/guide/pipeline' },
          ]
        },
        {
          text: 'The Basics',
          items: [
            { text: 'HTTP & Routing', link: '/guide/http-api' },
            { text: 'Requests', link: '/guide/requests' },
            { text: 'Validation', link: '/guide/validation' },
            { text: 'Database', link: '/guide/database' },
            { text: 'Error Handling', link: '/guide/error-handling' },
            { text: 'CLI Commands', link: '/guide/cli' },
          ]
        },
        {
          text: 'Digging Deeper',
          items: [
            { text: 'Events & Listeners', link: '/guide/events' },
            { text: 'Queues & Jobs', link: '/guide/queues' },
            { text: 'Cache', link: '/guide/cache' },
            { text: 'Mail', link: '/guide/mail' },
            { text: 'Notifications', link: '/guide/notifications' },
            { text: 'File Storage', link: '/guide/filesystem' },
            { text: 'Task Scheduling', link: '/guide/scheduling' },
            { text: 'HTTP Client', link: '/guide/http-client' },
          ]
        },
        {
          text: 'Security',
          items: [
            { text: 'Authentication', link: '/guide/auth' },
            { text: 'Authorization', link: '/guide/authorization' },
            { text: 'OAuth2 Server', link: '/guide/oauth' },
            { text: 'Social Auth', link: '/guide/social-auth' },
            { text: 'API Keys & PATs', link: '/guide/api-keys' },
            { text: 'Security Best Practices', link: '/guide/security' },
          ]
        },
        {
          text: 'Enterprise',
          items: [
            { text: 'Workflows', link: '/guide/workflows' },
            { text: 'Microservices', link: '/guide/microservices' },
            { text: 'GraphQL', link: '/guide/graphql' },
            { text: 'gRPC', link: '/guide/grpc' },
            { text: 'WebSockets', link: '/guide/websockets' },
            { text: 'CQRS', link: '/guide/cqrs' },
            { text: 'Circuit Breaker', link: '/guide/circuit-breaker' },
            { text: 'Feature Flags', link: '/guide/feature-flags' },
          ]
        },
        {
          text: 'AI Integration',
          items: [
            { text: 'MCP Server', link: '/guide/mcp' },
            { text: 'Catalyst', link: '/guide/catalyst' },
          ]
        },
        {
          text: 'Observability',
          items: [
            { text: 'Logging & Tracing', link: '/guide/observability' },
            { text: 'OpenAPI Generation', link: '/guide/openapi' },
          ]
        },
        {
          text: 'Operations',
          items: [
            { text: 'Runtime', link: '/guide/runtime' },
            { text: 'Testing', link: '/guide/testing' },
            { text: 'Package Authoring', link: '/guide/package-authoring' },
          ]
        },
        {
          text: 'Migration',
          items: [
            { text: 'From Laravel', link: '/guide/migration-from-laravel' },
          ]
        }
      ],
      '/api/': [
        {
          text: 'API Reference',
          items: [
            { text: 'Overview', link: '/api/' },
          ]
        }
      ],
      '/examples/': [
        {
          text: 'Examples',
          items: [
            { text: 'CRM Application', link: '/examples/crm' },
          ]
        }
      ]
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/latticephp/lattice' }
    ],

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2026 LatticePHP'
    },

    search: {
      provider: 'local'
    },

    editLink: {
      pattern: 'https://github.com/latticephp/lattice/edit/main/website/:path'
    },
  },

  markdown: {
    theme: {
      light: 'github-light',
      dark: 'github-dark'
    },
    lineNumbers: true
  }
})
